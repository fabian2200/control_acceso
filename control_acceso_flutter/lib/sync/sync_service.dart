import 'package:connectivity_plus/connectivity_plus.dart';

import '../config.dart';
import '../data/db.dart';
import '../data/foto.dart';
import 'api_client.dart';

class SyncResult {
  const SyncResult({
    required this.ok,
    required this.online,
    this.error,
    this.pendientes = 0,
  });

  final bool ok;
  final bool online;
  final String? error;
  final int pendientes;
}

class AccesoSync {
  AccesoSync({AccesoApi? api, AccesoDb? db})
      : _api = api ?? AccesoApi(),
        _db = db ?? AccesoDb.instance;

  final AccesoApi _api;
  final AccesoDb _db;

  static const catalogKeys = <String, String>{
    'cargos': 'cargos',
    'departamentos': 'departamentos',
    'empresas': 'empresas',
    'empleados': 'empleados',
    'users': 'users',
    'permisos': 'permisos',
    'admin_acceso': 'admin_acceso',
    'acceso_terminales': 'acceso_terminales',
    'acceso_horarios': 'acceso_horarios',
    'acceso_horario_items': 'acceso_horario_items',
    'acceso_empleado_horarios': 'acceso_empleado_horarios',
  };

  Future<bool> hayInternet() async {
    final result = await Connectivity().checkConnectivity();
    if (result.contains(ConnectivityResult.none) || result.isEmpty) {
      return false;
    }
    return _api.health();
  }

  Future<int> pendientes() async {
    final db = await _db.database;
    final r = await db.rawQuery(
      'SELECT COUNT(*) c FROM acceso_registros WHERE sincronizado = 0',
    );
    final o = await db.rawQuery(
      'SELECT COUNT(*) c FROM acceso_salidas_ocasionales WHERE sincronizado = 0',
    );
    final n = await db.rawQuery(
      'SELECT COUNT(*) c FROM acceso_novedades WHERE sincronizado = 0',
    );
    return (r.first['c'] as int? ?? 0) + (o.first['c'] as int? ?? 0) + (n.first['c'] as int? ?? 0);
  }

  Future<SyncResult> ejecutar() async {
    final online = await hayInternet();
    if (!online) {
      return SyncResult(ok: false, online: false, error: 'sin_red', pendientes: await pendientes());
    }

    try {
      await _pullCatalogo();
      await _pushMarcas();
      await _pushNovedades();
      await _pullNovedades();
      await recortarMarcasLocales();
      await recortarNovedadesMesAnterior();
      return SyncResult(ok: true, online: true, pendientes: await pendientes());
    } catch (e) {
      return SyncResult(
        ok: false,
        online: true,
        error: e.toString(),
        pendientes: await pendientes(),
      );
    }
  }

  Future<void> _pullCatalogo() async {
    final data = await _api.catalogo();
    for (final entry in catalogKeys.entries) {
      final rows = (data[entry.key] as List?) ?? const [];
      await _db.replaceCatalog(
        entry.value,
        rows.whereType<Map>().map((e) => _sqliteRow(Map<String, dynamic>.from(e))).toList(),
      );
    }
    await _db.upsert('acceso_sync_checkpoints', {
      'tabla': 'catalogo',
      'pulled_at': DateTime.now().toIso8601String(),
      'cursor': data['server_time']?.toString(),
      'updated_at': DateTime.now().toIso8601String(),
    });
  }

  Future<void> _pushMarcas() async {
    final db = await _db.database;
    final terminalId = await _db.terminalId(AppConfig.terminalCodigo);
    if (terminalId != null) {
      await db.update('acceso_registros', {'terminal_id': terminalId}, where: 'terminal_id IS NULL');
      await db.update(
        'acceso_salidas_ocasionales',
        {'terminal_id': terminalId},
        where: 'terminal_id IS NULL',
      );
    }

    final ocasionales = await db.query('acceso_salidas_ocasionales', where: 'sincronizado = 0', orderBy: 'id');
    final registros = await db.query('acceso_registros', where: 'sincronizado = 0', orderBy: 'id');
    if (ocasionales.isEmpty && registros.isEmpty) return;

    final payload = {
      'ocasionales': ocasionales.map(_payloadOcasional).toList(),
      'registros': [
        for (final row in registros) await _payloadRegistro(row),
      ],
    };
    final ack = await _api.marcas(payload);

    for (final item in (ack['ocasionales'] as List?) ?? const []) {
      if (item is! Map || item['ok'] != true) continue;
      final clave = Map<String, dynamic>.from(item['clave'] as Map? ?? {});
      await db.update(
        'acceso_salidas_ocasionales',
        {'sincronizado': 1},
        where: 'empleado_id = ? AND salida_en = ? AND sincronizado = 0',
        whereArgs: [clave['empleado_id'], clave['salida_en']],
      );
    }

    for (final item in (ack['registros'] as List?) ?? const []) {
      if (item is! Map || item['ok'] != true) continue;
      final clave = Map<String, dynamic>.from(item['clave'] as Map? ?? {});
      await db.update(
        'acceso_registros',
        {'sincronizado': 1},
        where: 'empleado_id = ? AND tipo = ? AND fecha = ? AND registrado_en = ? AND sincronizado = 0',
        whereArgs: [
          clave['empleado_id'],
          clave['tipo'],
          clave['fecha'],
          clave['registrado_en'],
        ],
      );
    }
  }

  Future<void> recortarMarcasLocales() async {
    final db = await _db.database;
    final hoy = _hoy();
    await db.delete(
      'acceso_registros',
      where: 'sincronizado = 1 AND fecha < ?',
      whereArgs: [hoy],
    );
    await db.delete(
      'acceso_salidas_ocasionales',
      where: "sincronizado = 1 AND estado != 'abierta' AND date(salida_en) < ?",
      whereArgs: [hoy],
    );
  }

  Future<void> _pushNovedades() async {
    final db = await _db.database;
    final terminalId = await _db.terminalId(AppConfig.terminalCodigo);
    if (terminalId != null) {
      await db.update('acceso_novedades', {'terminal_id': terminalId}, where: 'terminal_id IS NULL');
    }
    final rows = await db.query('acceso_novedades', where: 'sincronizado = 0', orderBy: 'id');
    if (rows.isEmpty) return;

    final ack = await _api.pushNovedades({
      'novedades': rows.map(_payloadNovedad).toList(),
    });

    for (final item in (ack['novedades'] as List?) ?? const []) {
      if (item is! Map || item['ok'] != true) continue;
      final uuid = '${item['uuid'] ?? ''}';
      if (uuid.isEmpty) continue;
      await db.update(
        'acceso_novedades',
        {'sincronizado': 1},
        where: 'uuid = ? AND sincronizado = 0',
        whereArgs: [uuid],
      );
    }
  }

  Future<void> _pullNovedades() async {
    final since = await _db.setting('novedades_since');
    final data = await _api.pullNovedades(since: since);
    final db = await _db.database;
    final stamp = DateTime.now().toIso8601String();

    for (final item in (data['novedades'] as List?) ?? const []) {
      if (item is! Map) continue;
      final map = Map<String, dynamic>.from(item);
      final uuid = '${map['uuid'] ?? ''}';
      if (uuid.isEmpty) continue;
      final existentes = await db.query('acceso_novedades', where: 'uuid = ?', whereArgs: [uuid], limit: 1);
      final aprobada = map['aprobada'];
      int? aprobadaInt;
      if (aprobada is bool) {
        aprobadaInt = aprobada ? 1 : 0;
      } else if (aprobada is num) {
        aprobadaInt = aprobada.toInt();
      } else if (aprobada != null) {
        aprobadaInt = int.tryParse('$aprobada');
      }

      if (existentes.isEmpty) {
        final conflicto = await db.query(
          'acceso_novedades',
          where: 'empleado_id = ? AND fecha = ? AND jornada = ?',
          whereArgs: [
            map['empleado_id'],
            map['fecha']?.toString().substring(0, 10),
            map['jornada'],
          ],
          limit: 1,
        );
        if (conflicto.isNotEmpty) {
          await db.update(
            'acceso_novedades',
            {
              'uuid': uuid,
              'aprobada': aprobadaInt,
              'sincronizado': 1,
              'updated_at': stamp,
            },
            where: 'id = ?',
            whereArgs: [conflicto.first['id']],
          );
        } else {
          await db.insert('acceso_novedades', {
            'uuid': uuid,
            'empleado_id': map['empleado_id'],
            'fecha': map['fecha']?.toString().substring(0, 10),
            'jornada': map['jornada'],
            'hora_inicio_jornada': map['hora_inicio_jornada'],
            'hora_fin_jornada': map['hora_fin_jornada'],
            'motivo': map['motivo'] ?? 'Novedad',
            'quien_autoriza': map['quien_autoriza'],
            'aprobada': aprobadaInt,
            'sincronizado': 1,
            'created_at': stamp,
            'updated_at': stamp,
          });
        }
      } else {
        await db.update(
          'acceso_novedades',
          {
            'aprobada': aprobadaInt,
            'updated_at': stamp,
          },
          where: 'uuid = ?',
          whereArgs: [uuid],
        );
      }
    }

    final serverTime = data['server_time']?.toString();
    if (serverTime != null && serverTime.isNotEmpty) {
      await _db.setSetting('novedades_since', serverTime);
    }
  }

  Future<void> recortarNovedadesMesAnterior() async {
    final now = DateTime.now();
    final inicioMes =
        '${now.year.toString().padLeft(4, '0')}-${now.month.toString().padLeft(2, '0')}-01';
    final db = await _db.database;
    await db.delete('acceso_novedades', where: 'fecha < ?', whereArgs: [inicioMes]);
  }

  Map<String, dynamic> _payloadNovedad(Map<String, Object?> row) {
    return {
      'uuid': row['uuid'],
      'empleado_id': row['empleado_id'],
      'fecha': row['fecha'],
      'jornada': row['jornada'],
      'hora_inicio_jornada': row['hora_inicio_jornada'],
      'hora_fin_jornada': row['hora_fin_jornada'],
      'motivo': row['motivo'],
      'quien_autoriza': row['quien_autoriza'],
    };
  }

  Map<String, dynamic> _payloadOcasional(Map<String, Object?> row) {
    return {
      'empleado_id': row['empleado_id'],
      'id_horario': row['id_horario'],
      'motivo_texto': row['motivo_texto'],
      'autorizado_por': row['autorizado_por'],
      'permiso_id': row['permiso_id'],
      'salida_en': row['salida_en'],
      'hora_regreso_esperada': row['hora_regreso_esperada'],
      'regreso_en': row['regreso_en'],
      'minutos_tarde': row['minutos_tarde'],
      'foto_salida': FotoKiosko.payload(row['foto_salida']),
      'foto_regreso': FotoKiosko.payload(row['foto_regreso']),
      'estado': row['estado'],
      'revisada_rrhh': row['revisada_rrhh'] == 1,
    };
  }

  Future<Map<String, dynamic>> _payloadRegistro(Map<String, Object?> row) async {
    Map<String, dynamic>? ocasional;
    final ocId = row['salida_ocasional_id'];
    if (ocId != null) {
      final oc = await _db.find('acceso_salidas_ocasionales', ocId);
      if (oc != null) {
        ocasional = {
          'empleado_id': oc['empleado_id'],
          'salida_en': oc['salida_en'],
        };
      }
    }
    return {
      'empleado_id': row['empleado_id'],
      'id_horario': row['id_horario'],
      'tipo': row['tipo'],
      'fecha': row['fecha'],
      'hora': row['hora'],
      'registrado_en': row['registrado_en'],
      'foto': FotoKiosko.payload(row['foto']),
      'hora_esperada': row['hora_esperada'],
      'llego_tarde': row['llego_tarde'],
      'llego_temprano': row['llego_temprano'],
      'salio_temprano': row['salio_temprano'],
      'salio_tarde': row['salio_tarde'],
      'salida_ocasional': ocasional,
    };
  }

  Map<String, Object?> _sqliteRow(Map<String, dynamic> raw) {
    final out = <String, Object?>{};
    raw.forEach((key, value) {
      if (key == 'api_token') return;
      if (value is bool) {
        out[key] = value ? 1 : 0;
      } else if (value is double && value == value.roundToDouble()) {
        out[key] = value.toInt();
      } else if (value is num || value is String || value == null) {
        out[key] = value;
      } else {
        out[key] = value.toString();
      }
    });
    return out;
  }

  String _hoy() {
    final n = DateTime.now();
    return '${n.year.toString().padLeft(4, '0')}-${n.month.toString().padLeft(2, '0')}-${n.day.toString().padLeft(2, '0')}';
  }
}
