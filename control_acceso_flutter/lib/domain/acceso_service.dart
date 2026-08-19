import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../config.dart';
import '../data/db.dart';
import 'hora_fmt.dart';
import 'models.dart';

class AccesoService {
  AccesoService({AccesoDb? db}) : _db = db ?? AccesoDb.instance;

  final AccesoDb _db;
  static const _horasAntes = 2;
  static const _horasDespuesEntrada = 1;
  static const _minutosAntesJornada2 = 30;
  static const _minutosGraciaPermiso = 5;

  Future<Identificado?> identificar(String cedula) async {
    cedula = cedula.replaceAll(RegExp(r'\D'), '');
    if (cedula.length < 5) return null;

    final rows = await _db.query(
      'empleados',
      where: "identificacion = ? AND (estado IS NULL OR LOWER(estado) = 'activo')",
      whereArgs: [cedula],
      limit: 1,
    );
    if (rows.isEmpty) return null;

    final emp = rows.first;
    final id = emp['id'] as int;
    final cargoId = emp['cargo'];
    String cargo = 'Empleado';
    if (cargoId != null) {
      final c = await _db.find('cargos', cargoId);
      cargo = (c?['nombre'] as String?)?.trim().isNotEmpty == true
          ? c!['nombre'] as String
          : cargo;
    }
    final users = await _db.query('users', where: 'empleado = ?', whereArgs: [id], limit: 1);
    final nombres = (emp['nombres'] as String? ?? '').trim();
    final apellidos = (emp['apellidos'] as String? ?? '').trim();
    final nombre = '$nombres $apellidos'.trim();
    final primero = nombres.isEmpty ? 'empleado' : _title(nombres.split(' ').first);

    return Identificado(
      id: id,
      userId: users.isEmpty ? null : users.first['id'] as int?,
      nombre: nombre.isEmpty ? 'Empleado' : nombre,
      primero: primero,
      cargo: cargo,
      identificacion: emp['identificacion']?.toString() ?? cedula,
      foto: emp['foto'] as String?,
    );
  }

  Future<OpenExit?> salidaAbierta(int empleadoId) async {
    final rows = await _db.query(
      'acceso_salidas_ocasionales',
      where: "empleado_id = ? AND estado = 'abierta'",
      whereArgs: [empleadoId],
      orderBy: 'salida_en DESC',
      limit: 1,
    );
    if (rows.isEmpty) return null;
    final row = rows.first;
    final salida = _parseDt(row['salida_en']);
    if (salida == null) return null;
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final day = DateTime(salida.year, salida.month, salida.day);
    return OpenExit(
      time: HoraFmt.of(salida),
      date: DateFormat("d MMM", 'es').format(salida),
      today: day == today,
      reason: row['motivo_texto'] as String?,
      back: HoraFmt.from(row['hora_regreso_esperada']),
    );
  }

  Future<String> sugerirTipo(int empleadoId) async {
    final ultima = await _ultimaMarca(empleadoId, DateTime.now(), const ['entrada', 'salida']);
    if (ultima == null || ultima['tipo'] == 'salida') return 'entrada';
    return 'salida';
  }

  Future<List<BotonJornada>> botonesJornada(int empleadoId) async {
    final now = DateTime.now();
    final item = await _itemHorarioHoy(empleadoId, now);

    if (item == null || _esDescanso(item)) {
      return [
        const BotonJornada(
          tipo: 'entrada',
          label: 'Entrada',
          sub: 'Inicio de jornada',
          clase: 'action-in',
          enabled: true,
        ),
        const BotonJornada(
          tipo: 'salida',
          label: 'Salida',
          sub: 'Fin de jornada',
          clase: 'action-out',
          enabled: true,
        ),
        await _botonOcasional(empleadoId, item, now),
      ];
    }

    final graciaPermiso = await _limiteGraciaPermiso(empleadoId, now);
    final jornadaActual = _jornadaEntradaActual(item, now);
    final botones = <BotonJornada>[];
    for (final slot in _definicionSlots()) {
      final hora = _hora(item, slot['campo'] as String);
      if (hora == null) continue;
      final estado = await _estadoSlot(
        empleadoId,
        item,
        slot,
        now,
        graciaPermiso,
        jornadaActual,
      );
      final motivo = estado['motivo'] as String?;
      final enabled = estado['enabled'] as bool;
      final porPermiso = estado['porPermiso'] == true;
      final horaAm = HoraFmt.from(hora);
      final ventana = porPermiso && graciaPermiso != null
          ? 'Permiso hasta ${HoraFmt.of(graciaPermiso)}'
          : _textoVentana(item, slot, hora, now);
      botones.add(BotonJornada(
        tipo: slot['tipo'] as String,
        campo: slot['campo'] as String,
        label: slot['label'] as String,
        sub: enabled
            ? (ventana == null ? horaAm : '$horaAm  ·  $ventana')
            : (motivo == 'Ya registrada' ? 'Ya registrada' : horaAm),
        nota: (!enabled && motivo != null && motivo != 'Ya registrada' && motivo != hora)
            ? motivo
            : null,
        hora: hora,
        clase: slot['clase'] as String,
        enabled: enabled,
      ));
    }
    botones.add(await _botonOcasional(empleadoId, item, now));
    return botones;
  }

  Future<bool> slotHabilitado(int empleadoId, String tipo, String? campo) async {
    if (tipo == 'regreso') return true;
    for (final boton in await botonesJornada(empleadoId)) {
      if (boton.tipo == tipo && boton.campo == campo) return boton.enabled;
    }
    return false;
  }

  Future<List<PermisoHoy>> permisosHoy(int userId) async {
    final hoy = _fecha(DateTime.now());
    final rows = await _db.query(
      'permisos',
      where: '''
        empleado = ?
        AND LOWER(estado) = 'aprobado'
        AND (estado_reg IS NULL OR UPPER(estado_reg) = 'ACTIVO')
        AND fecha_cancelacion IS NULL
        AND substr(fecha_inicio, 1, 10) <= ?
        AND substr(fecha_fin, 1, 10) >= ?
      ''',
      whereArgs: [userId, hoy, hoy],
      orderBy: 'hora_inicio',
    );
    return rows.map((row) {
      final motivo = (row['motivo'] as String?)?.trim();
      return PermisoHoy(
        id: row['id'] as int,
        motivo: (motivo == null || motivo.isEmpty) ? 'Permiso' : motivo,
        horaInicio: _fmtHora(row['hora_inicio']),
        horaFin: _fmtHora(row['hora_fin']),
        horaFinDigitos: _horaFinDigitos(row['hora_fin']),
        rango: _rangoFechas(row['fecha_inicio']?.toString(), row['fecha_fin']?.toString()),
      );
    }).toList();
  }

  Future<Confirmacion> registrar({
    required int empleadoId,
    required String tipo,
    int? userId,
    String? campo,
    int? permisoId,
    String? motivoTexto,
    String? mandadoPor,
    String? horaRegreso,
    String? foto,
  }) {
    return switch (tipo) {
      'entrada' => _registrarEntrada(empleadoId, campo, foto),
      'salida' => _registrarSalida(empleadoId, campo, foto),
      'salida_ocasional' => _registrarOcasional(
          empleadoId,
          userId: userId,
          permisoId: permisoId,
          motivoTexto: motivoTexto,
          mandadoPor: mandadoPor,
          horaRegreso: horaRegreso,
          foto: foto,
        ),
      'regreso' => cerrarOcasional(empleadoId, foto: foto),
      _ => throw ArgumentError('Tipo de registro no válido.'),
    };
  }

  Future<Confirmacion> _registrarEntrada(int empleadoId, String? campo, String? foto) async {
    final now = DateTime.now();
    final p = await _calcularPuntualidad(empleadoId, 'entrada', now, campo);
    await _crearRegistro(empleadoId, 'entrada', now, p, foto: foto);
    return Confirmacion(
      title: 'Entrada registrada',
      time: HoraFmt.of(now),
      color: (p['llego_tarde'] as int) > 0 ? ColorData.amber : ColorData.green,
      pillText: _pillTexto('entrada', p),
      pillBg: _pillBg('entrada', p),
      pillFg: _pillFg('entrada', p),
      meta: p['hora_esperada'] != null ? 'Esperada ${HoraFmt.from(p['hora_esperada'])}' : null,
      acciones: [_etiquetaAccion('entrada', campo)],
    );
  }

  Future<Confirmacion> _registrarSalida(int empleadoId, String? campo, String? foto) async {
    final now = DateTime.now();
    final p = await _calcularPuntualidad(empleadoId, 'salida', now, campo);
    await _crearRegistro(empleadoId, 'salida', now, p, foto: foto);
    return Confirmacion(
      title: 'Salida registrada',
      time: HoraFmt.of(now),
      color: (p['salio_tarde'] as int) > 0 ? ColorData.amber : ColorData.blue,
      pillText: _pillTexto('salida', p),
      pillBg: _pillBg('salida', p),
      pillFg: _pillFg('salida', p),
      meta: p['hora_esperada'] != null ? 'Esperada ${HoraFmt.from(p['hora_esperada'])}' : 'Fin de jornada',
      acciones: [_etiquetaAccion('salida', campo)],
    );
  }

  Future<Confirmacion> _registrarOcasional(
    int empleadoId, {
    int? userId,
    int? permisoId,
    String? motivoTexto,
    String? mandadoPor,
    String? horaRegreso,
    String? foto,
  }) async {
    final now = DateTime.now();
    Map<String, Object?>? permiso;
    if (permisoId != null && userId != null) {
      final rows = await _db.query(
        'permisos',
        where: 'id = ? AND empleado = ?',
        whereArgs: [permisoId, userId],
        limit: 1,
      );
      if (rows.isNotEmpty) permiso = rows.first;
    }
    var motivo = motivoTexto ?? 'Diligencia empresarial';
    if (permiso != null) {
      final m = (permiso['motivo'] as String?)?.trim();
      motivo = (m == null || m.isEmpty) ? 'Permiso' : m;
    }
    final hora = _normalizarHora(permiso != null ? _horaFinDigitos(permiso['hora_fin']) : horaRegreso);
    final terminalId = await _db.terminalId(AppConfig.terminalCodigo);
    final idHorario = await _idHorario(empleadoId);
    final stamp = _stamp(now);
    final caso = await _clasificarOcasional(empleadoId, now, hora);
    final item = await _itemHorarioHoy(empleadoId, now);
    if (await _ocasionalBloqueadaPorSalida(empleadoId, item, now)) {
      throw StateError('La jornada ya tiene salida');
    }
    final quedaAbierta = _quedaOcasionalAbierta(caso);

    DateTime? regresoEn;
    var estado = 'abierta';
    if (!quedaAbierta) {
      estado = 'cerrada';
      var cierre = _carbonHora(now, hora);
      if (cierre != null && cierre.isBefore(now)) {
        cierre = cierre.add(const Duration(days: 1));
      }
      regresoEn = cierre;
    }

    final ocasionalId = await _db.insert('acceso_salidas_ocasionales', {
      'empleado_id': empleadoId,
      'id_horario': idHorario,
      'terminal_id': terminalId,
      'motivo_texto': motivo,
      'autorizado_por': permiso != null ? null : mandadoPor,
      'permiso_id': permiso?['id'],
      'salida_en': stamp,
      'hora_regreso_esperada': hora,
      'regreso_en': regresoEn != null ? _stamp(regresoEn) : null,
      'foto_salida': foto,
      'estado': estado,
      'revisada_rrhh': 0,
      'sincronizado': 0,
      'created_at': stamp,
      'updated_at': stamp,
    });

    final acciones = <String>[
      quedaAbierta ? 'Salida ocasional abierta' : 'Salida ocasional cerrada',
    ];
    if (permiso == null && (mandadoPor ?? '').isNotEmpty) {
      acciones.add('Autorizado por $mandadoPor');
    }
    if ([3, 4, 5].contains(caso)) {
      final campoSalida = caso == 5 ? _campoUltimaSalida(item) : 'salida_jornada_1';
      final horaRegistro = ([4, 5].contains(caso) ? regresoEn : null) ?? now;
      final insertada = await _insertarRegistroSlot(
        empleadoId,
        'salida',
        now,
        campoSalida,
        ocasionalId: ocasionalId,
        foto: foto,
        registradoEn: horaRegistro,
      );
      if (insertada) acciones.add(_etiquetaAccion('salida', campoSalida));
    }

    final pill = motivo.length > 42 ? '${motivo.substring(0, 40).trim()}…' : motivo;
    final horaAm = HoraFmt.from(hora);
    return Confirmacion(
      title: caso == 5 ? 'Salida de jornada registrada' : 'Salida ocasional registrada',
      time: HoraFmt.of(now),
      color: ColorData.amber,
      pillText: pill,
      pillBg: const Color(0xFFFFFBEB),
      pillFg: const Color(0xFFB45309),
      meta: caso == 5
          ? 'Cierre del día · regreso esperado $horaAm'
          : quedaAbierta
              ? 'Regreso esperado $horaAm'
              : 'Cerrada · regreso esperado $horaAm',
      acciones: acciones,
      casoOcasional: caso,
    );
  }

  Future<Confirmacion> cerrarOcasional(int empleadoId, {String? foto}) async {
    final now = DateTime.now();
    final rows = await _db.query(
      'acceso_salidas_ocasionales',
      where: "empleado_id = ? AND estado = 'abierta'",
      whereArgs: [empleadoId],
      orderBy: 'salida_en DESC',
      limit: 1,
    );
    if (rows.isEmpty) {
      return Confirmacion(
        title: 'Salida ocasional cerrada',
        time: HoraFmt.of(now),
        color: ColorData.green,
        pillText: 'Salida cerrada',
        pillBg: const Color(0xFFECFDF3),
        pillFg: const Color(0xFF15803D),
        acciones: const ['Salida ocasional cerrada'],
      );
    }

    final abierta = rows.first;
    final salidaEn = _parseDt(abierta['salida_en']) ?? now;
    final esperado = _hhmm(abierta['hora_regreso_esperada']);
    final caso = await _clasificarOcasional(empleadoId, salidaEn, esperado);
    late DateTime horaCierre;
    var minutosTarde = 0;
    if (caso == 5) {
      final esperadoElDia = _carbonHora(salidaEn, esperado);
      final diaCierre = (esperadoElDia != null && esperadoElDia.isBefore(salidaEn))
          ? salidaEn.add(const Duration(days: 1))
          : salidaEn;
      horaCierre = _carbonHora(diaCierre, esperado) ?? diaCierre;
    } else {
      horaCierre = now;
      final p = _calcularPuntualidadHora(esperado.isEmpty ? null : esperado, now, 'entrada');
      minutosTarde = p['llego_tarde'] as int;
    }

    final terminalId = await _db.terminalId(AppConfig.terminalCodigo);
    final ocasionalId = abierta['id'] as int;
    await _db.update(
      'acceso_salidas_ocasionales',
      {
        'regreso_en': _stamp(horaCierre),
        'foto_regreso': foto,
        'minutos_tarde': minutosTarde,
        'estado': 'cerrada',
        'terminal_id': abierta['terminal_id'] ?? terminalId,
        'sincronizado': 0,
        'updated_at': _stamp(now),
      },
      where: 'id = ?',
      whereArgs: [ocasionalId],
    );

    final itemHoy = await _itemHorarioHoy(empleadoId, now);
    final tieneJ2 = _tieneJornada2(itemHoy);
    final entradaJ2 = (tieneJ2 && itemHoy != null)
        ? _carbonHora(now, _hora(itemHoy, 'entrada_jornada_2'))
        : null;
    final j2YaInicio = entradaJ2 != null && !now.isBefore(entradaJ2);
    var preguntarEntradaJ2 = false;
    final acciones = <String>['Salida ocasional cerrada'];
    if (caso == 3 && tieneJ2) {
      if (j2YaInicio) {
        final insertada = await _insertarRegistroSlot(
          empleadoId,
          'entrada',
          now,
          'entrada_jornada_2',
          ocasionalId: ocasionalId,
          foto: foto,
        );
        if (insertada) acciones.add(_etiquetaAccion('entrada', 'entrada_jornada_2'));
      } else {
        preguntarEntradaJ2 = true;
      }
    }

    final motivo = abierta['motivo_texto'] as String? ?? '';
    final meta = [
      'Salió ${HoraFmt.of(salidaEn)}',
      if (esperado.isNotEmpty) 'esperado ${HoraFmt.from(esperado)}',
      if (motivo.isNotEmpty) motivo,
    ].join(' · ');

    return Confirmacion(
      title: 'Salida ocasional cerrada',
      time: HoraFmt.of(horaCierre),
      color: minutosTarde > 0 ? ColorData.amber : ColorData.green,
      pillText: minutosTarde > 0 ? 'Tarde · $minutosTarde min' : 'Salida cerrada',
      pillBg: minutosTarde > 0 ? const Color(0xFFFFFBEB) : const Color(0xFFECFDF3),
      pillFg: minutosTarde > 0 ? const Color(0xFFB45309) : const Color(0xFF15803D),
      meta: meta,
      acciones: acciones,
      casoOcasional: caso,
      preguntarEntradaJ2: preguntarEntradaJ2,
    );
  }

  Future<BotonJornada> _botonOcasional(
    int empleadoId,
    Map<String, Object?>? item,
    DateTime now,
  ) async {
    if (await _ocasionalBloqueadaPorSalida(empleadoId, item, now)) {
      final jornada = _jornadaEnCurso(item, now);
      return BotonJornada(
        tipo: 'salida_ocasional',
        label: 'Salida ocasional',
        sub: 'La jornada $jornada ya tiene salida',
        clase: 'action-occ',
        enabled: false,
      );
    }
    return const BotonJornada(
      tipo: 'salida_ocasional',
      label: 'Salida ocasional',
      sub: 'Se cierra al volver',
      clase: 'action-occ',
      enabled: true,
    );
  }

  List<Map<String, String>> _definicionSlots() => const [
        {
          'campo': 'entrada_jornada_1',
          'tipo': 'entrada',
          'jornada': '1',
          'label': 'Entrada jornada 1',
          'clase': 'action-in',
        },
        {
          'campo': 'salida_jornada_1',
          'tipo': 'salida',
          'jornada': '1',
          'label': 'Salida jornada 1',
          'clase': 'action-out',
        },
        {
          'campo': 'entrada_jornada_2',
          'tipo': 'entrada',
          'jornada': '2',
          'label': 'Entrada jornada 2',
          'clase': 'action-in',
        },
        {
          'campo': 'salida_jornada_2',
          'tipo': 'salida',
          'jornada': '2',
          'label': 'Salida jornada 2',
          'clase': 'action-out',
        },
      ];

  Future<Map<String, Object?>> _estadoSlot(
    int empleadoId,
    Map<String, Object?> item,
    Map<String, String> slot,
    DateTime now,
    DateTime? graciaPermiso,
    String? jornadaActual,
  ) async {
    final hora = _hora(item, slot['campo']!);
    if (await _yaRegistrado(empleadoId, slot['tipo']!, hora, now)) {
      return {'enabled': false, 'motivo': 'Ya registrada'};
    }
    final centro = _carbonHora(now, hora);
    if (centro != null) {
      final desde = centro.subtract(const Duration(hours: _horasAntes));
      if (now.isBefore(desde)) {
        final salidaPorEntrada = slot['tipo'] == 'salida' &&
            slot['jornada'] == _jornadaEnCurso(item, now) &&
            await _tieneEntradaDeJornada(empleadoId, item, slot['jornada']!, now);
        if (!salidaPorEntrada) {
          return {
            'enabled': false,
            'motivo': 'Puedes marcar desde las ${HoraFmt.of(desde)}',
          };
        }
      }
      if (slot['tipo'] == 'entrada') {
        final hasta = centro.add(const Duration(hours: _horasDespuesEntrada));
        final limite = DateTime(hasta.year, hasta.month, hasta.day, hasta.hour, hasta.minute, 59);
        if (now.isAfter(limite)) {
          if (_entradaHabilitadaPorPermiso(slot, jornadaActual, now, graciaPermiso)) {
            return {'enabled': true, 'motivo': null, 'porPermiso': true};
          }
          return {'enabled': false, 'motivo': 'Fuera de horario'};
        }
      } else {
        final corte = _horaCierreSalida(item, slot, now);
        if (corte != null && !now.isBefore(corte)) {
          return {'enabled': false, 'motivo': 'Fuera de horario'};
        }
      }
    }
    return {'enabled': true, 'motivo': null};
  }

  bool _entradaHabilitadaPorPermiso(
    Map<String, String> slot,
    String? jornadaActual,
    DateTime now,
    DateTime? graciaPermiso,
  ) {
    if (slot['tipo'] != 'entrada' || jornadaActual == null) return false;
    if (slot['jornada'] != jornadaActual) return false;
    return graciaPermiso != null && !now.isAfter(graciaPermiso);
  }

  /// Jornada de trabajo en curso (1 hasta que inicia J2). No usa el hueco de entrada.
  String _jornadaEnCurso(Map<String, Object?>? item, DateTime now) {
    if (item == null || !_tieneJornada2(item)) return '1';
    final entradaJ2 = _carbonHora(now, _hora(item, 'entrada_jornada_2'));
    if (entradaJ2 != null && !now.isBefore(entradaJ2)) return '2';
    return '1';
  }

  Future<bool> _tieneEntradaDeJornada(
    int empleadoId,
    Map<String, Object?> item,
    String jornada,
    DateTime now,
  ) async {
    final campo = jornada == '2' ? 'entrada_jornada_2' : 'entrada_jornada_1';
    final hora = _hora(item, campo);
    if (hora == null) return false;
    return _yaRegistrado(empleadoId, 'entrada', hora, now);
  }

  Future<bool> _ocasionalBloqueadaPorSalida(
    int empleadoId,
    Map<String, Object?>? item,
    DateTime now,
  ) async {
    if (item == null || _esDescanso(item)) return false;
    final jornada = _jornadaEnCurso(item, now);
    final campo = jornada == '2' ? 'salida_jornada_2' : 'salida_jornada_1';
    return _tieneSalidaCampo(empleadoId, item, campo, now);
  }

  Future<bool> _tieneSalidaCampo(
    int empleadoId,
    Map<String, Object?> item,
    String campo,
    DateTime now,
  ) async {
    final hora = _hora(item, campo);
    if (hora == null) return false;
    if (await _yaRegistrado(empleadoId, 'salida', hora, now)) return true;
    return _yaRegistrado(empleadoId, 'salida', hora, now.add(const Duration(days: 1)));
  }

  /// Entrada de la jornada en curso. En el hueco J1–J2 no hay entrada que reabrir.
  String? _jornadaEntradaActual(Map<String, Object?> item, DateTime now) {
    final salidaJ1 = _carbonHora(now, _hora(item, 'salida_jornada_1'));
    final entradaJ2 = _carbonHora(now, _hora(item, 'entrada_jornada_2'));
    if (_tieneJornada2(item) && entradaJ2 != null && !now.isBefore(entradaJ2)) {
      return '2';
    }
    final finJ1 = salidaJ1 ?? entradaJ2;
    if (_tieneJornada2(item) && finJ1 != null && !now.isBefore(finJ1)) {
      return null;
    }
    if (_hora(item, 'entrada_jornada_1') != null) return '1';
    if (_tieneJornada2(item)) return '2';
    return '1';
  }

  String? _textoVentana(Map<String, Object?> item, Map<String, String> slot, String? hora, DateTime now) {
    final centro = _carbonHora(now, hora);
    if (centro == null) return null;
    final desde = centro.subtract(const Duration(hours: _horasAntes));
    if (slot['tipo'] == 'entrada') {
      final hasta = centro.add(const Duration(hours: _horasDespuesEntrada));
      return '${HoraFmt.of(desde)} – ${HoraFmt.of(hasta)}';
    }
    final corte = _horaCierreSalida(item, slot, now);
    if (corte != null) {
      return '${HoraFmt.of(desde)} – ${HoraFmt.of(corte)}';
    }
    return 'Desde las ${HoraFmt.of(desde)}';
  }

  DateTime? _horaCierreSalida(Map<String, Object?> item, Map<String, String> slot, DateTime now) {
    if (slot['tipo'] != 'salida' || slot['jornada'] != '1') return null;
    final entradaJ2 = _carbonHora(now, _hora(item, 'entrada_jornada_2'));
    if (entradaJ2 == null) return null;
    return entradaJ2.subtract(const Duration(minutes: _minutosAntesJornada2));
  }

  Future<DateTime?> _limiteGraciaPermiso(int empleadoId, DateTime now) async {
    final permisos = await _permisosParaGracia(empleadoId);
    DateTime? limite;
    for (final p in permisos) {
      if (p.horaFin == '--:--') continue;
      final fin = _carbonHora(now, _normalizarHora(p.horaFinDigitos));
      if (fin == null) continue;
      final gracia = fin.add(const Duration(minutes: _minutosGraciaPermiso));
      if (now.isAfter(gracia)) continue;
      final inicio = p.horaInicio == '--:--' ? null : _carbonHora(now, p.horaInicio);
      if (inicio != null && now.isBefore(inicio)) continue;
      if (limite == null || gracia.isAfter(limite)) limite = gracia;
    }
    return limite;
  }

  Future<List<PermisoHoy>> _permisosParaGracia(int empleadoId) async {
    final users = await _db.query('users', where: 'empleado = ?', whereArgs: [empleadoId], limit: 1);
    final ids = <int>{empleadoId};
    if (users.isNotEmpty) ids.add(users.first['id'] as int);
    final porId = <int, PermisoHoy>{};
    for (final id in ids) {
      for (final p in await permisosHoy(id)) {
        porId[p.id] = p;
      }
    }
    return porId.values.toList();
  }

  Future<bool> _yaRegistrado(int empleadoId, String tipo, String? horaEsperada, DateTime now) async {
    if (horaEsperada == null || horaEsperada.isEmpty) return false;
    final esperada = horaEsperada.length == 5 ? '$horaEsperada:00' : horaEsperada;
    final rows = await _db.query(
      'acceso_registros',
      where: 'empleado_id = ? AND fecha = ? AND tipo = ? AND hora_esperada = ?',
      whereArgs: [empleadoId, _fecha(now), tipo, esperada],
      limit: 1,
    );
    if (rows.isNotEmpty) return true;
    final alt = await _db.query(
      'acceso_registros',
      where: 'empleado_id = ? AND fecha = ? AND tipo = ? AND substr(hora_esperada, 1, 5) = ?',
      whereArgs: [empleadoId, _fecha(now), tipo, esperada.substring(0, 5)],
      limit: 1,
    );
    return alt.isNotEmpty;
  }

  Future<Map<String, Object?>> _puntualidadSlot(
    int empleadoId,
    String tipo,
    DateTime now,
    String campo,
  ) async {
    final item = await _itemHorarioHoy(empleadoId, now);
    if (item == null || _hora(item, campo) == null) return _puntualidadVacia();
    return _calcularPuntualidadHora(_hora(item, campo), now, tipo, 0);
  }

  bool _tieneJornada2(Map<String, Object?>? item) {
    if (item == null) return false;
    return _hora(item, 'entrada_jornada_2') != null || _hora(item, 'salida_jornada_2') != null;
  }

  /// Abierta solo en casos 1, 2 y 3 (se cierra al volver). Casos 4 y 5: cerrada al marcar.
  bool _quedaOcasionalAbierta(int caso) {
    return caso == 1 || caso == 2 || caso == 3;
  }

  String _campoUltimaSalida(Map<String, Object?>? item) {
    return _tieneJornada2(item) ? 'salida_jornada_2' : 'salida_jornada_1';
  }

  Future<bool> _insertarRegistroSlot(
    int empleadoId,
    String tipo,
    DateTime now,
    String campo, {
    int? ocasionalId,
    String? foto,
    DateTime? registradoEn,
  }) async {
    final p = await _puntualidadSlot(empleadoId, tipo, now, campo);
    final esperada = p['hora_esperada'] != null ? _hhmm(p['hora_esperada']) : null;
    if (await _yaRegistrado(empleadoId, tipo, esperada, now)) return false;
    await _crearRegistro(
      empleadoId,
      tipo,
      registradoEn ?? now,
      p,
      ocasionalId: ocasionalId,
      foto: foto,
    );
    return true;
  }

  Future<int> _clasificarOcasional(int empleadoId, DateTime salidaEn, String horaRegreso) async {
    final item = await _itemHorarioHoy(empleadoId, salidaEn);
    final esperado = _carbonHora(salidaEn, horaRegreso);
    if (esperado != null && esperado.isBefore(salidaEn)) return 5;
    if (item == null || _esDescanso(item)) return 1;

    final tieneJ2 = _tieneJornada2(item);
    final salidaJ1 = _carbonHora(salidaEn, _hora(item, 'salida_jornada_1'));
    final entradaJ2 = tieneJ2 ? _carbonHora(salidaEn, _hora(item, 'entrada_jornada_2')) : null;
    final salidaJ2 = tieneJ2 ? _carbonHora(salidaEn, _hora(item, 'salida_jornada_2')) : null;
    final ultimaSalida = salidaJ2 ?? salidaJ1;

    if (ultimaSalida != null && !salidaEn.isBefore(ultimaSalida)) return 5;
    if (ultimaSalida != null && esperado != null && !esperado.isBefore(ultimaSalida)) return 5;
    if (tieneJ2 && entradaJ2 != null && !salidaEn.isBefore(entradaJ2)) return 2;
    if (salidaJ1 != null && esperado != null && esperado.isBefore(salidaJ1)) return 1;
    if (tieneJ2 && entradaJ2 != null && esperado != null && !esperado.isBefore(entradaJ2)) return 3;
    if (tieneJ2 && esperado != null && salidaJ1 != null && !esperado.isBefore(salidaJ1)) return 4;
    return 1;
  }

  Future<Map<String, Object?>> _calcularPuntualidad(
    int empleadoId,
    String tipo,
    DateTime now,
    String? campo,
  ) async {
    final item = await _itemHorarioHoy(empleadoId, now);
    campo ??= await _campoHorario(empleadoId, item, tipo, now);
    if (item == null || campo == null) return _puntualidadVacia();
    return _calcularPuntualidadHora(_hora(item, campo), now, tipo, _gabela(item, campo) ?? 0);
  }

  Map<String, Object?> _calcularPuntualidadHora(
    String? horaEsperada,
    DateTime now,
    String tipo, [
    int gabela = 0,
  ]) {
    final resultado = _puntualidadVacia();
    final hora = _carbonHora(now, horaEsperada);
    if (hora == null) return resultado;
    resultado['hora_esperada'] = DateFormat('HH:mm:ss').format(hora);
    final limite = hora.add(Duration(minutes: gabela));
    if (now.isAfter(limite)) {
      final minutos = now.difference(hora).inMinutes;
      if (tipo == 'entrada') {
        resultado['llego_tarde'] = minutos;
      } else {
        resultado['salio_tarde'] = minutos;
      }
    } else if (now.isBefore(hora)) {
      final minutos = hora.difference(now).inMinutes;
      if (tipo == 'entrada') {
        resultado['llego_temprano'] = minutos;
      } else {
        resultado['salio_temprano'] = minutos;
      }
    }
    return resultado;
  }

  Map<String, Object?> _puntualidadVacia() => {
        'hora_esperada': null,
        'llego_tarde': 0,
        'llego_temprano': 0,
        'salio_temprano': 0,
        'salio_tarde': 0,
      };

  Future<void> _crearRegistro(
    int empleadoId,
    String tipo,
    DateTime now,
    Map<String, Object?> p, {
    int? ocasionalId,
    String? foto,
  }) async {
    final fecha = _fecha(now);
    final hora = DateFormat('HH:mm:ss').format(now);
    var where = 'empleado_id = ? AND tipo = ? AND fecha = ?';
    final args = <Object?>[empleadoId, tipo, fecha];
    if (p['hora_esperada'] != null) {
      where += ' AND hora_esperada = ?';
      args.add(p['hora_esperada']);
    } else {
      where += ' AND hora = ?';
      args.add(hora);
    }
    final ya = await _db.query('acceso_registros', where: where, whereArgs: args, limit: 1);
    if (ya.isNotEmpty) {
      if (ya.first['id_horario'] == null) {
        await _db.update(
          'acceso_registros',
          {'id_horario': await _idHorario(empleadoId)},
          where: 'id = ?',
          whereArgs: [ya.first['id']],
        );
      }
      return;
    }
    final stamp = _stamp(now);
    await _db.insert('acceso_registros', {
      'empleado_id': empleadoId,
      'id_horario': await _idHorario(empleadoId),
      'terminal_id': await _db.terminalId(AppConfig.terminalCodigo),
      'salida_ocasional_id': ocasionalId,
      'tipo': tipo,
      'fecha': fecha,
      'hora': hora,
      'registrado_en': stamp,
      'foto': foto,
      'hora_esperada': p['hora_esperada'],
      'llego_tarde': p['llego_tarde'],
      'llego_temprano': p['llego_temprano'],
      'salio_temprano': p['salio_temprano'],
      'salio_tarde': p['salio_tarde'],
      'sincronizado': 0,
      'created_at': stamp,
      'updated_at': stamp,
    });
  }

  Future<int?> _idHorario(int empleadoId) async {
    final rows = await _db.query(
      'acceso_empleado_horarios',
      where: 'empleado_id = ?',
      whereArgs: [empleadoId],
      limit: 1,
    );
    return rows.isEmpty ? null : rows.first['horario_id'] as int?;
  }

  Future<Map<String, Object?>?> _itemHorarioHoy(int empleadoId, DateTime now) async {
    final asig = await _db.query(
      'acceso_empleado_horarios',
      where: 'empleado_id = ?',
      whereArgs: [empleadoId],
      limit: 1,
    );
    if (asig.isEmpty) return null;
    final horario = await _db.find('acceso_horarios', asig.first['horario_id'] as Object);
    if (horario == null || (horario['activo'] as int? ?? 1) != 1) return null;
    final items = await _db.query(
      'acceso_horario_items',
      where: 'horario_id = ? AND dia_semana = ?',
      whereArgs: [horario['id'], now.weekday],
      limit: 1,
    );
    return items.isEmpty ? null : items.first;
  }

  Future<String?> _campoHorario(
    int empleadoId,
    Map<String, Object?>? item,
    String tipo,
    DateTime now,
  ) async {
    if (item == null) return null;
    final rows = await _db.query(
      'acceso_registros',
      where: 'empleado_id = ? AND fecha = ? AND tipo = ?',
      whereArgs: [empleadoId, _fecha(now), tipo],
    );
    final conteo = rows.length;
    if (tipo == 'entrada') {
      return conteo == 0
          ? _primerCampo(item, const ['entrada_jornada_1', 'entrada_jornada_2'])
          : _primerCampo(item, const ['entrada_jornada_2', 'entrada_jornada_1']);
    }
    return conteo == 0
        ? _primerCampo(item, const ['salida_jornada_1', 'salida_jornada_2'])
        : _primerCampo(item, const ['salida_jornada_2', 'salida_jornada_1']);
  }

  String? _primerCampo(Map<String, Object?> item, List<String> campos) {
    for (final campo in campos) {
      if (_hora(item, campo) != null) return campo;
    }
    return null;
  }

  Future<Map<String, Object?>?> _ultimaMarca(int empleadoId, DateTime now, List<String> tipos) async {
    final marks = List.filled(tipos.length, '?').join(',');
    final rows = await _db.query(
      'acceso_registros',
      where: 'empleado_id = ? AND fecha = ? AND tipo IN ($marks)',
      whereArgs: [empleadoId, _fecha(now), ...tipos],
      orderBy: 'registrado_en DESC',
      limit: 1,
    );
    return rows.isEmpty ? null : rows.first;
  }

  String? _hora(Map<String, Object?> item, String campo) {
    final v = item[campo];
    if (v == null) return null;
    final s = v.toString().trim();
    if (s.isEmpty || s == 'null') return null;
    return s.length >= 5 ? s.substring(0, 5) : s;
  }

  bool _esDescanso(Map<String, Object?> item) =>
      _hora(item, 'entrada_jornada_1') == null &&
      _hora(item, 'salida_jornada_1') == null &&
      _hora(item, 'entrada_jornada_2') == null &&
      _hora(item, 'salida_jornada_2') == null;

  int? _gabela(Map<String, Object?> item, String campo) {
    final v = item['gabela_$campo'];
    if (v == null) return null;
    if (v is int) return v;
    return int.tryParse(v.toString());
  }

  DateTime? _carbonHora(DateTime now, String? hora) {
    if (hora == null || hora.trim().isEmpty) return null;
    final hhmm = hora.length >= 5 ? hora.substring(0, 5) : hora;
    final parts = hhmm.split(':');
    if (parts.length < 2) return null;
    final h = int.tryParse(parts[0]);
    final m = int.tryParse(parts[1]);
    if (h == null || m == null) return null;
    return DateTime(now.year, now.month, now.day, h, m);
  }

  DateTime? _parseDt(Object? v) {
    if (v == null) return null;
    return DateTime.tryParse(v.toString().replaceFirst(' ', 'T'));
  }

  String _fecha(DateTime n) => DateFormat('yyyy-MM-dd').format(n);
  String _stamp(DateTime n) => DateFormat('yyyy-MM-dd HH:mm:ss').format(n);

  String _hhmm(Object? v) {
    if (v == null) return '';
    final s = v.toString();
    return s.length >= 5 ? s.substring(0, 5) : s;
  }

  String _normalizarHora(String? hora) {
    var digits = (hora ?? '').replaceAll(RegExp(r'\D'), '');
    if (digits.length > 4) digits = digits.substring(0, 4);
    digits = digits.padRight(4, '0');
    final h = int.parse(digits.substring(0, 2)).clamp(0, 23);
    final i = int.parse(digits.substring(2, 4)).clamp(0, 59);
    return '${h.toString().padLeft(2, '0')}:${i.toString().padLeft(2, '0')}';
  }

  String _horaFinDigitos(Object? hora) {
    var digits = (hora ?? '').toString().replaceAll(RegExp(r'\D'), '');
    if (digits.length <= 2) {
      digits = '${digits.padLeft(2, '0')}00';
    } else if (digits.length == 3) {
      digits = '0$digits';
    }
    return digits.padRight(4, '0').substring(0, 4);
  }

  String _fmtHora(Object? hora) {
    final digits = _horaFinDigitos(hora);
    if ((hora ?? '').toString().replaceAll(RegExp(r'\D'), '').isEmpty) return '--:--';
    return '${digits.substring(0, 2)}:${digits.substring(2, 4)}';
  }

  String _rangoFechas(String? inicioRaw, String? finRaw) {
    String fmt(String? v) {
      final d = _parseDt(v);
      if (d == null) return '';
      return DateFormat('dd/MM').format(d);
    }

    final inicio = fmt(inicioRaw);
    final fin = fmt(finRaw);
    if (inicio.isEmpty || inicio == fin) return inicio;
    return '$inicio – $fin';
  }

  String _title(String s) {
    if (s.isEmpty) return s;
    return s[0].toUpperCase() + s.substring(1).toLowerCase();
  }

  String _etiquetaAccion(String tipo, String? campo) {
    return switch (campo) {
      'entrada_jornada_1' => 'Entrada jornada 1',
      'salida_jornada_1' => 'Salida jornada 1',
      'entrada_jornada_2' => 'Entrada jornada 2',
      'salida_jornada_2' => 'Salida jornada 2',
      _ => tipo == 'entrada' ? 'Entrada' : 'Salida',
    };
  }

  String _pillTexto(String tipo, Map<String, Object?> p) {
    final tarde = tipo == 'entrada' ? p['llego_tarde'] as int : p['salio_tarde'] as int;
    final temprano = tipo == 'entrada' ? p['llego_temprano'] as int : p['salio_temprano'] as int;
    if (tarde > 0) return 'Tarde · $tarde min';
    if (temprano > 0) return 'Temprano · $temprano min';
    return 'A tiempo';
  }

  Color _pillBg(String tipo, Map<String, Object?> p) {
    final tarde = tipo == 'entrada' ? p['llego_tarde'] as int : p['salio_tarde'] as int;
    final temprano = tipo == 'entrada' ? p['llego_temprano'] as int : p['salio_temprano'] as int;
    if (tarde > 0) return const Color(0xFFFFFBEB);
    if (temprano > 0) return const Color(0xFFEFF6FF);
    return const Color(0xFFECFDF3);
  }

  Color _pillFg(String tipo, Map<String, Object?> p) {
    final tarde = tipo == 'entrada' ? p['llego_tarde'] as int : p['salio_tarde'] as int;
    final temprano = tipo == 'entrada' ? p['llego_temprano'] as int : p['salio_temprano'] as int;
    if (tarde > 0) return const Color(0xFFB45309);
    if (temprano > 0) return const Color(0xFF1D4ED8);
    return const Color(0xFF15803D);
  }
}

class ColorData {
  static const green = Color(0xFF16A34A);
  static const amber = Color(0xFFD97706);
  static const blue = Color(0xFF2563EB);
}
