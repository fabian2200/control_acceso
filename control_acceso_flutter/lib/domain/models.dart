import 'package:flutter/material.dart';

import 'hora_fmt.dart';

class Identificado {
  const Identificado({
    required this.id,
    required this.nombre,
    required this.primero,
    required this.cargo,
    required this.identificacion,
    this.userId,
    this.foto,
  });

  final int id;
  final int? userId;
  final String nombre;
  final String primero;
  final String cargo;
  final String identificacion;
  final String? foto;
}

class AdminEmpleado {
  const AdminEmpleado({
    required this.id,
    required this.nombre,
    required this.identificacion,
    this.cargo,
  });

  final int id;
  final String nombre;
  final String identificacion;
  final String? cargo;
}

class LogItem {
  const LogItem({
    required this.cuando,
    required this.tipo,
    required this.titulo,
    required this.detalle,
    this.alerta = false,
  });

  final DateTime cuando;
  final String tipo;
  final String titulo;
  final String detalle;
  final bool alerta;
}

class OpenExit {
  const OpenExit({
    required this.time,
    required this.date,
    required this.today,
    required this.back,
    this.reason,
  });

  final String time;
  final String date;
  final bool today;
  final String back;
  final String? reason;
}

class BotonJornada {
  const BotonJornada({
    required this.tipo,
    required this.label,
    required this.sub,
    required this.clase,
    required this.enabled,
    this.campo,
    this.nota,
    this.hora,
  });

  final String tipo;
  final String? campo;
  final String label;
  final String sub;
  final String? nota;
  final String? hora;
  final String clase;
  final bool enabled;
}

class Confirmacion {
  const Confirmacion({
    required this.title,
    required this.time,
    required this.color,
    required this.pillText,
    required this.pillBg,
    required this.pillFg,
    this.meta,
    this.acciones = const [],
    this.casoOcasional,
    this.preguntarEntradaJ2 = false,
  });

  final String title;
  final String time;
  final Color color;
  final String pillText;
  final Color pillBg;
  final Color pillFg;
  final String? meta;
  final List<String> acciones;
  final int? casoOcasional;
  final bool preguntarEntradaJ2;
}

class PermisoHoy {
  const PermisoHoy({
    required this.id,
    required this.motivo,
    required this.horaInicio,
    required this.horaFin,
    required this.horaFinDigitos,
    required this.rango,
  });

  final int id;
  final String motivo;
  final String horaInicio;
  final String horaFin;
  final String horaFinDigitos;
  final String rango;
}

class SyncUi {
  const SyncUi({
    this.online = false,
    this.pendientes = 0,
    this.syncing = false,
    this.error,
  });

  final bool online;
  final int pendientes;
  final bool syncing;
  final String? error;

  String get etiquetaRed => online ? 'En línea' : 'Sin NUBE';

  String get etiquetaSync {
    if (syncing) return 'Sincronizando…';
    if (error != null && error != 'sin_red') return 'Error de sync';
    if (pendientes > 0) return '$pendientes pendiente${pendientes == 1 ? '' : 's'}';
    if (online) return 'NUBE al día';
    return 'Local';
  }
}

class NovedadContexto {
  const NovedadContexto({
    required this.empleadoId,
    required this.fecha,
    required this.jornada,
    this.horaInicio,
    this.horaFin,
  });

  final int empleadoId;
  final String fecha;
  final int jornada;
  final String? horaInicio;
  final String? horaFin;

  String get rangoLabel {
    final a = horaInicio == null || horaInicio!.isEmpty ? '—' : HoraFmt.from(horaInicio);
    final b = horaFin == null || horaFin!.isEmpty ? '—' : HoraFmt.from(horaFin);
    return '$a – $b';
  }
}

class NovedadMotivos {
  static const diligencia = 'Diligencia Empresarial';
  static const otro = 'Otro';
  static const todos = <String>[
    'Situación familiar',
    'Problema de transporte',
    'Cita médica',
    'Trámite personal',
    diligencia,
    otro,
  ];
}

