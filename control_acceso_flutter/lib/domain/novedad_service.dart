import 'dart:math';

import 'package:intl/intl.dart';

import '../config.dart';
import '../data/db.dart';
import 'acceso_service.dart';
import 'models.dart';

class NovedadService {
  NovedadService({AccesoService? acceso, AccesoDb? db})
      : _acceso = acceso ?? AccesoService(),
        _db = db ?? AccesoDb.instance;

  final AccesoService _acceso;
  final AccesoDb _db;

  Future<NovedadContexto> resolverContexto(int empleadoId, [DateTime? ahora]) async {
    final now = ahora ?? DateTime.now();
    final item = await _acceso.itemHorarioHoy(empleadoId, now);
    if (item == null) {
      throw StateError('Empleado sin horario asignado.');
    }
    if (_acceso.esDescansoItem(item)) {
      throw StateError('Hoy es día de descanso.');
    }

    final inicioDia = _primeraEntrada(item, now);
    final finDia = _ultimaSalida(item, now);
    if (inicioDia == null || finDia == null) {
      throw StateError('Horario incompleto para registrar novedad.');
    }
    if (now.isBefore(inicioDia) || now.isAfter(finDia)) {
      throw StateError('Fuera del horario de jornada.');
    }

    final jornada = int.parse(_acceso.jornadaEnCurso(item, now));
    final prefijo = jornada == 2 ? '2' : '1';
    final horaInicio = _acceso.horaCampo(item, 'entrada_jornada_$prefijo');
    final horaFin = _acceso.horaCampo(item, 'salida_jornada_$prefijo');
    final fecha = DateFormat('yyyy-MM-dd').format(now);

    if (await existeNovedad(empleadoId, fecha, jornada)) {
      throw StateError('Ya hay novedad en esta jornada.');
    }

    return NovedadContexto(
      empleadoId: empleadoId,
      fecha: fecha,
      jornada: jornada,
      horaInicio: horaInicio,
      horaFin: horaFin,
    );
  }

  Future<bool> existeNovedad(int empleadoId, String fecha, int jornada) async {
    final rows = await _db.query(
      'acceso_novedades',
      where: 'empleado_id = ? AND fecha = ? AND jornada = ?',
      whereArgs: [empleadoId, fecha, jornada],
      limit: 1,
    );
    return rows.isNotEmpty;
  }

  /// Pendiente (`aprobada` null) o aprobada: habilita entrada fuera de ventana.
  Future<bool> existeNovedadHabilitante(int empleadoId, String fecha, int jornada) async {
    final rows = await _db.query(
      'acceso_novedades',
      where: 'empleado_id = ? AND fecha = ? AND jornada = ? AND (aprobada IS NULL OR aprobada = 1)',
      whereArgs: [empleadoId, fecha, jornada],
      limit: 1,
    );
    return rows.isNotEmpty;
  }

  Future<String> registrar({
    required NovedadContexto contexto,
    required String motivo,
    String? quienAutoriza,
  }) async {
    final motivoTrim = motivo.trim();
    if (motivoTrim.isEmpty) {
      throw StateError('Selecciona un motivo.');
    }
    final diligenica = motivoTrim.toLowerCase() == NovedadMotivos.diligencia.toLowerCase();
    final autoriza = (quienAutoriza ?? '').trim();
    if (diligenica && autoriza.isEmpty) {
      throw StateError('Indica quién autoriza la diligencia.');
    }
    if (await existeNovedad(contexto.empleadoId, contexto.fecha, contexto.jornada)) {
      throw StateError('Ya hay novedad en esta jornada.');
    }

    final uuid = _uuidV4();
    final stamp = DateFormat('yyyy-MM-dd HH:mm:ss').format(DateTime.now());
    final terminalId = await _db.terminalId(AppConfig.terminalCodigo);

    await _db.insert('acceso_novedades', {
      'uuid': uuid,
      'empleado_id': contexto.empleadoId,
      'terminal_id': terminalId,
      'fecha': contexto.fecha,
      'jornada': contexto.jornada,
      'hora_inicio_jornada': contexto.horaInicio,
      'hora_fin_jornada': contexto.horaFin,
      'motivo': motivoTrim,
      'quien_autoriza': diligenica ? autoriza : null,
      'aprobada': null,
      'sincronizado': 0,
      'created_at': stamp,
      'updated_at': stamp,
    });
    return uuid;
  }

  DateTime? _primeraEntrada(Map<String, Object?> item, DateTime now) {
    final e1 = _acceso.carbonHora(now, _acceso.horaCampo(item, 'entrada_jornada_1'));
    if (e1 != null) return e1;
    return _acceso.carbonHora(now, _acceso.horaCampo(item, 'entrada_jornada_2'));
  }

  DateTime? _ultimaSalida(Map<String, Object?> item, DateTime now) {
    final s2 = _acceso.carbonHora(now, _acceso.horaCampo(item, 'salida_jornada_2'));
    if (s2 != null) return s2;
    return _acceso.carbonHora(now, _acceso.horaCampo(item, 'salida_jornada_1'));
  }

  String _uuidV4() {
    final r = Random.secure();
    final bytes = List<int>.generate(16, (_) => r.nextInt(256));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    String hex(int b) => b.toRadixString(16).padLeft(2, '0');
    final h = bytes.map(hex).join();
    return '${h.substring(0, 8)}-${h.substring(8, 12)}-${h.substring(12, 16)}-${h.substring(16, 20)}-${h.substring(20)}';
  }
}
