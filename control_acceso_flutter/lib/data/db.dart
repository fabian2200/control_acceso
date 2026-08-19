import 'package:intl/intl.dart';
import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

import '../domain/models.dart';
import 'schema.dart';

class AccesoDb {
  AccesoDb._();
  static final AccesoDb instance = AccesoDb._();

  Database? _db;

  Future<Database> get database async {
    if (_db != null) return _db!;
    final dir = await getDatabasesPath();
    final path = p.join(dir, 'acceso_control.db');
    _db = await openDatabase(
      path,
      version: 1,
      onCreate: (db, _) async {
        for (final sql in AccesoSchema.tables) {
          await db.execute(sql);
        }
        for (final sql in AccesoSchema.indexes) {
          await db.execute(sql);
        }
      },
    );
    return _db!;
  }

  Map<String, Object?> coerce(String table, Map<String, Object?> row) {
    final allowed = AccesoSchema.columns[table];
    if (allowed == null) return row;
    final out = <String, Object?>{};
    for (final col in allowed) {
      if (!row.containsKey(col)) continue;
      var value = row[col];
      if (value is bool) value = value ? 1 : 0;
      if (value is double && value == value.roundToDouble()) value = value.toInt();
      out[col] = value;
    }
    if (table == 'users') {
      out['name'] ??= '';
      out['email'] ??= 'user-${out['id']}@local';
      out['password'] ??= '';
    }
    if (table == 'acceso_horarios') {
      out['nombre'] ??= 'Horario';
    }
    if (table == 'acceso_terminales') {
      out['codigo'] ??= '';
      out['nombre'] ??= out['codigo'];
    }
    if (table == 'admin_acceso') {
      out['usuario'] ??= '';
      out['password'] ??= '';
    }
    return out;
  }

  Future<int?> terminalId(String codigo) async {
    final rows = await query(
      'acceso_terminales',
      where: 'codigo = ?',
      whereArgs: [codigo],
      limit: 1,
    );
    if (rows.isEmpty) return null;
    return rows.first['id'] as int?;
  }

  Future<void> upsert(String table, Map<String, Object?> row) async {
    row = coerce(table, row);
    if (row['id'] == null) return;
    final db = await database;
    final cols = row.keys.toList();
    final placeholders = List.filled(cols.length, '?').join(',');
    final updates = cols
        .where((c) => c != 'id')
        .map((c) => '$c=excluded.$c')
        .join(',');
    final sql = updates.isEmpty
        ? 'INSERT OR IGNORE INTO $table (${cols.join(',')}) VALUES ($placeholders)'
        : 'INSERT INTO $table (${cols.join(',')}) VALUES ($placeholders) ON CONFLICT(id) DO UPDATE SET $updates';
    await db.execute(sql, row.values.toList());
  }

  Future<void> replaceCatalog(String table, List<Map<String, Object?>> rows) async {
    final db = await database;
    await db.transaction((txn) async {
      final keep = <Object>[];
      for (var row in rows) {
        row = coerce(table, row);
        if (row['id'] == null) continue;
        keep.add(row['id'] as Object);
        final cols = row.keys.toList();
        final placeholders = List.filled(cols.length, '?').join(',');
        final updates = cols
            .where((c) => c != 'id')
            .map((c) => '$c=excluded.$c')
            .join(',');
        await txn.execute(
          'INSERT INTO $table (${cols.join(',')}) VALUES ($placeholders) ON CONFLICT(id) DO UPDATE SET $updates',
          row.values.toList(),
        );
      }
      if (keep.isEmpty) {
        await txn.delete(table);
        return;
      }
      final marks = List.filled(keep.length, '?').join(',');
      await txn.rawDelete('DELETE FROM $table WHERE id NOT IN ($marks)', keep);
    });
  }

  Future<int> insert(String table, Map<String, Object?> row) async {
    final db = await database;
    return db.insert(table, row);
  }

  Future<int> update(
    String table,
    Map<String, Object?> values, {
    required String where,
    required List<Object?> whereArgs,
  }) async {
    final db = await database;
    return db.update(table, values, where: where, whereArgs: whereArgs);
  }

  Future<List<Map<String, Object?>>> query(
    String table, {
    String? where,
    List<Object?>? whereArgs,
    String? orderBy,
    int? limit,
  }) async {
    final db = await database;
    return db.query(
      table,
      where: where,
      whereArgs: whereArgs,
      orderBy: orderBy,
      limit: limit,
    );
  }

  Future<Map<String, Object?>?> find(String table, Object id) async {
    final rows = await query(table, where: 'id = ?', whereArgs: [id], limit: 1);
    return rows.isEmpty ? null : rows.first;
  }

  Future<String?> setting(String key) async {
    final rows = await query(
      'acceso_sync_checkpoints',
      where: 'tabla = ?',
      whereArgs: [key],
      limit: 1,
    );
    final value = rows.isEmpty ? null : rows.first['cursor'] as String?;
    if (value == null || value.trim().isEmpty) return null;
    return value;
  }

  Future<void> setSetting(String key, String value) async {
    final db = await database;
    await db.insert(
      'acceso_sync_checkpoints',
      {
        'tabla': key,
        'cursor': value,
        'updated_at': DateTime.now().toIso8601String(),
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }

  Future<List<AdminEmpleado>> empleadosAdmin() async {
    final rows = await database.then(
      (db) => db.rawQuery('''
        SELECT e.id, e.nombres, e.apellidos, e.identificacion, c.nombre AS cargo
        FROM empleados e
        LEFT JOIN cargos c ON c.id = e.cargo
        WHERE e.estado IS NULL OR LOWER(e.estado) = 'activo'
        ORDER BY e.nombres, e.apellidos
      '''),
    );
    return [
      for (final row in rows)
        AdminEmpleado(
          id: row['id'] as int,
          nombre: '${row['nombres'] ?? ''} ${row['apellidos'] ?? ''}'.trim(),
          identificacion: '${row['identificacion'] ?? ''}',
          cargo: row['cargo'] as String?,
        ),
    ];
  }

  Future<List<LogItem>> logsMesLocal(int empleadoId, DateTime inicio, DateTime fin) async {
    final from = _ymd(inicio);
    final to = _ymd(fin);
    final registros = await query(
      'acceso_registros',
      where: 'empleado_id = ? AND fecha >= ? AND fecha <= ?',
      whereArgs: [empleadoId, from, to],
      orderBy: 'registrado_en',
    );
    final ocasionales = await query(
      'acceso_salidas_ocasionales',
      where: 'empleado_id = ? AND date(salida_en) >= ? AND date(salida_en) <= ?',
      whereArgs: [empleadoId, from, to],
      orderBy: 'salida_en',
    );
    return [
      ...registros.map(_logRegistro),
      ...ocasionales.expand(_logOcasional),
    ]..sort((a, b) => a.cuando.compareTo(b.cuando));
  }

  LogItem _logRegistro(Map<String, Object?> row) {
    final tipo = '${row['tipo']}';
    final cuando = DateTime.tryParse('${row['registrado_en']}'.replaceFirst(' ', 'T')) ?? DateTime.now();
    final tarde = (row['llego_tarde'] as int? ?? 0) + (row['salio_tarde'] as int? ?? 0);
    final esperada = row['hora_esperada'] == null ? null : '${row['hora_esperada']}'.substring(0, 5);
    return LogItem(
      cuando: cuando,
      tipo: tipo,
      titulo: _tituloTipo(tipo),
      detalle: [
        (row['hora'] as String?)?.substring(0, 5) ?? '',
        if (esperada != null) 'esperada $esperada',
        if (tarde > 0) 'tarde $tarde min',
      ].where((s) => s.isNotEmpty).join(' · '),
      alerta: tarde > 0,
    );
  }

  Iterable<LogItem> _logOcasional(Map<String, Object?> row) sync* {
    final salida = DateTime.tryParse('${row['salida_en']}'.replaceFirst(' ', 'T'));
    if (salida != null) {
      yield LogItem(
        cuando: salida,
        tipo: 'salida_ocasional',
        titulo: 'Salida ocasional',
        detalle: [
          DateFormat('HH:mm').format(salida),
          if ((row['motivo_texto'] as String?)?.isNotEmpty == true) '${row['motivo_texto']}',
          if ((row['hora_regreso_esperada'] as String?)?.isNotEmpty == true)
            'regreso ${_hhmm(row['hora_regreso_esperada'])}',
        ].join(' · '),
      );
    }
    final regreso = DateTime.tryParse('${row['regreso_en']}'.replaceFirst(' ', 'T'));
    if (regreso != null) {
      final tarde = row['minutos_tarde'] as int? ?? 0;
      yield LogItem(
        cuando: regreso,
        tipo: 'regreso',
        titulo: 'Regreso',
        detalle: [
          DateFormat('HH:mm').format(regreso),
          if (tarde > 0) 'tarde $tarde min',
        ].join(' · '),
        alerta: tarde > 0,
      );
    }
  }

  String _hhmm(Object? v) {
    final s = '${v ?? ''}';
    return s.length >= 5 ? s.substring(0, 5) : s;
  }

  String _ymd(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  String _tituloTipo(String tipo) {
    return switch (tipo) {
      'entrada' => 'Entrada',
      'salida' => 'Salida',
      'salida_ocasional' => 'Salida ocasional',
      'regreso' => 'Regreso',
      _ => tipo,
    };
  }
}
