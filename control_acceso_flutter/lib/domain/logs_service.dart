import '../data/db.dart';
import '../sync/api_client.dart';
import 'hora_fmt.dart';
import 'models.dart';

class LogsService {
  LogsService({AccesoApi? api, AccesoDb? db})
      : _api = api ?? AccesoApi(),
        _db = db ?? AccesoDb.instance;

  final AccesoApi _api;
  final AccesoDb _db;

  Future<({List<LogItem> items, bool desdeNube})> rango(
    int empleadoId, {
    required DateTime desde,
    required DateTime hasta,
  }) async {
    var inicio = DateTime(desde.year, desde.month, desde.day);
    var fin = DateTime(hasta.year, hasta.month, hasta.day);
    if (inicio.isAfter(fin)) {
      final tmp = inicio;
      inicio = fin;
      fin = tmp;
    }
    try {
      final items = <LogItem>[];
      var cursor = DateTime(inicio.year, inicio.month, 1);
      final ultimo = DateTime(fin.year, fin.month, 1);
      while (!cursor.isAfter(ultimo)) {
        final data = await _api.logsMes(empleadoId: empleadoId, anio: cursor.year, mes: cursor.month);
        items.addAll(_desdeApi(data));
        cursor = DateTime(cursor.year, cursor.month + 1, 1);
      }
      return (items: _filtrar(items, inicio, fin), desdeNube: true);
    } catch (_) {
      return (items: await _db.logsMesLocal(empleadoId, inicio, fin), desdeNube: false);
    }
  }

  List<LogItem> _filtrar(List<LogItem> items, DateTime inicio, DateTime fin) {
    final from = DateTime(inicio.year, inicio.month, inicio.day);
    final to = DateTime(fin.year, fin.month, fin.day);
    final filtrados = items.where((item) {
      final dia = DateTime(item.cuando.year, item.cuando.month, item.cuando.day);
      return !dia.isBefore(from) && !dia.isAfter(to);
    }).toList();
    filtrados.sort((a, b) => b.cuando.compareTo(a.cuando));
    return filtrados;
  }

  List<LogItem> _desdeApi(Map<String, dynamic> data) {
    final items = <LogItem>[];
    for (final row in (data['registros'] as List?) ?? const []) {
      if (row is! Map) continue;
      final map = Map<String, dynamic>.from(row);
      final tipo = '${map['tipo']}';
      final cuando = _dt(map['registrado_en']) ?? DateTime.now();
      final tarde = _int(map['llego_tarde']) + _int(map['salio_tarde']);
      final esperada = HoraFmt.from(map['hora_esperada']);
      items.add(LogItem(
        cuando: cuando,
        tipo: tipo,
        titulo: _titulo(tipo),
        detalle: [
          HoraFmt.from(map['hora']),
          if (esperada.isNotEmpty) 'esperada $esperada',
          if (tarde > 0) 'tarde $tarde min',
        ].where((s) => s.isNotEmpty).join(' · '),
        alerta: tarde > 0,
        foto: _foto(map['foto']),
      ));
    }
    for (final row in (data['ocasionales'] as List?) ?? const []) {
      if (row is! Map) continue;
      final map = Map<String, dynamic>.from(row);
      final salida = _dt(map['salida_en']);
      if (salida != null) {
        items.add(LogItem(
          cuando: salida,
          tipo: 'salida_ocasional',
          titulo: 'Salida ocasional',
          detalle: [
            HoraFmt.of(salida),
            if ('${map['motivo_texto'] ?? ''}'.isNotEmpty) '${map['motivo_texto']}',
            if ('${map['autorizado_por'] ?? ''}'.isNotEmpty) 'autorizado por ${map['autorizado_por']}',
            if (HoraFmt.from(map['hora_regreso_esperada']).isNotEmpty) 'regreso ${HoraFmt.from(map['hora_regreso_esperada'])}',
          ].join(' · '),
          foto: _foto(map['foto_salida']),
        ));
      }
      final regreso = _dt(map['regreso_en']);
      if (regreso != null) {
        final tarde = _int(map['minutos_tarde']);
        items.add(LogItem(
          cuando: regreso,
          tipo: 'regreso',
          titulo: 'Regreso',
          detalle: [
            HoraFmt.of(regreso),
            if (tarde > 0) 'tarde $tarde min',
          ].join(' · '),
          alerta: tarde > 0,
          foto: _foto(map['foto_regreso']),
        ));
      }
    }
    items.sort((a, b) => b.cuando.compareTo(a.cuando));
    return items;
  }

  DateTime? _dt(Object? v) {
    if (v == null) return null;
    final dt = DateTime.tryParse(v.toString().replaceFirst(' ', 'T'));
    if (dt == null) return null;
    return HoraFmt.wall(dt);
  }

  int _int(Object? v) {
    if (v is int) return v;
    return int.tryParse('${v ?? 0}') ?? 0;
  }

  String? _foto(Object? v) {
    final value = v?.toString().trim() ?? '';
    return value.isEmpty ? null : value;
  }

  String _titulo(String tipo) {
    return switch (tipo) {
      'entrada' => 'Entrada',
      'salida' => 'Salida',
      'salida_ocasional' => 'Salida ocasional',
      'regreso' => 'Regreso',
      _ => tipo,
    };
  }
}
