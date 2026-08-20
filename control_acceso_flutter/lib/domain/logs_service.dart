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

  Future<({List<LogItem> items, bool desdeNube})> mes(int empleadoId) async {
    final now = DateTime.now();
    final inicio = DateTime(now.year, now.month, 1);
    final fin = DateTime(now.year, now.month + 1, 0);
    try {
      final data = await _api.logsMes(empleadoId: empleadoId, anio: now.year, mes: now.month);
      return (items: _desdeApi(data), desdeNube: true);
    } catch (_) {
      return (items: await _db.logsMesLocal(empleadoId, inicio, fin), desdeNube: false);
    }
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
        ));
      }
    }
    items.sort((a, b) => a.cuando.compareTo(b.cuando));
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
