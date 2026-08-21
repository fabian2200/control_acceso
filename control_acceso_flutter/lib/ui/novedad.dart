import 'package:flutter/material.dart';

import '../domain/models.dart';
import 'kiosk.dart';
import 'theme.dart';
import 'widgets.dart';

class NovedadCedulaScreen extends StatefulWidget {
  const NovedadCedulaScreen({super.key, required this.controller});
  final KioskController controller;

  @override
  State<NovedadCedulaScreen> createState() => _NovedadCedulaScreenState();
}

class _NovedadCedulaScreenState extends State<NovedadCedulaScreen> {
  String cedula = '';

  @override
  Widget build(BuildContext context) {
    final empty = cedula.isEmpty;
    final c = widget.controller;
    return Padding(
      padding: const EdgeInsets.fromLTRB(48, 28, 48, 28),
      child: Column(
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Eyebrow('Novedades', fontSize: 24),
                    Text(
                      'Registrar novedad',
                      style: TextStyle(fontSize: 36, fontWeight: FontWeight.w600, color: KioskColors.ink),
                    ),
                  ],
                ),
              ),
              _VolverNovedad(onTap: c.cancelarNovedad),
            ],
          ),
          const SizedBox(height: 18),
          Expanded(
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Container(
                        height: 86,
                        alignment: Alignment.centerLeft,
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF0FDF4),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: KioskColors.green, width: 1.5),
                        ),
                        child: Text(
                          empty ? 'Número de identificación' : cedula,
                          style: TextStyle(
                            fontSize: empty ? 24 : 34,
                            fontWeight: empty ? FontWeight.w400 : FontWeight.w600,
                            color: empty ? KioskColors.faint : KioskColors.ink,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    SizedBox(
                      height: 86,
                      width: 106,
                      child: Material(
                        color: KioskColors.amarillo.withValues(alpha: 0.2),
                        elevation: 0,
                        shadowColor: const Color(0x220F172A),
                        borderRadius: BorderRadius.circular(12),
                        child: InkWell(
                          onTap: () {
                            if (cedula.isEmpty) return;
                            setState(() {
                              cedula = cedula.substring(0, cedula.length - 1);
                            });
                          },
                          borderRadius: BorderRadius.circular(12),
                          child: DecoratedBox(
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: KioskColors.amarillo),
                            ),
                            child: const Center(
                              child: Icon(Icons.backspace_outlined, color: KioskColors.amarillo, size: 28),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 24),
                Expanded(
                  child: Center(
                    child: KioskKeypad(
                      compact: true,
                      onAnyKey: c.limpiarError,
                      onDigit: (d) {
                        if (cedula.length >= 12) return;
                        setState(() => cedula += d);
                      },
                      onBack: () {
                        if (cedula.isEmpty) return;
                        setState(() => cedula = cedula.substring(0, cedula.length - 1));
                      },
                      onClear: () => setState(() => cedula = ''),
                      onOk: () async {
                        final msg = await c.identificarNovedad(cedula);
                        if (!context.mounted || msg == null) return;
                        await showKioskDialog(
                          context,
                          title: 'No se puede continuar',
                          message: msg,
                          icon: Icons.event_busy_outlined,
                        );
                      },
                      okEnabled: cedula.length >= 5 && !c.busy,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Paso 1: solo motivo.
class NovedadFormScreen extends StatelessWidget {
  const NovedadFormScreen({super.key, required this.controller});
  final KioskController controller;

  static const _motivos = <(String, IconData, String)>[
    ('Situación familiar', Icons.family_restroom_outlined, 'Asuntos del hogar o familia'),
    ('Problema de transporte', Icons.directions_bus_outlined, 'Retraso o falla de transporte'),
    ('Cita médica', Icons.medical_services_outlined, 'Atención o cita de salud'),
    ('Trámite personal', Icons.assignment_outlined, 'Gestión personal'),
    ('Diligencia Empresarial', Icons.business_center_outlined, 'Requiere quién autoriza'),
    ('Otro', Icons.more_horiz, 'Otro motivo'),
  ];

  @override
  Widget build(BuildContext context) {
    final emp = controller.empleado;
    final ctx = controller.novedadContexto;
    if (emp == null || ctx == null) {
      return const Center(child: Text('Sin datos de novedad'));
    }
    final motivo = controller.novedadMotivo;
    final esDiligencia = motivo == NovedadMotivos.diligencia;
    final puedeContinuar = motivo != null && motivo.isNotEmpty && !controller.busy;

    return Padding(
      padding: const EdgeInsets.fromLTRB(48, 24, 48, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('Novedades · Motivo'),
          const SizedBox(height: 8),
          const Text(
            'Selecciona el motivo',
            style: TextStyle(fontSize: 34, fontWeight: FontWeight.w600, color: KioskColors.ink),
          ),
          const SizedBox(height: 14),
          _EmpleadoResumen(empleado: emp, contexto: ctx),
          const SizedBox(height: 48),
          Expanded(
            child: LayoutBuilder(
              builder: (context, constraints) {
                const cols = 3;
                const gap = 14.0;
                final cardWidth = (constraints.maxWidth - gap * (cols - 1)) / cols;
                return SingleChildScrollView(
                  child: Wrap(
                    spacing: gap,
                    runSpacing: gap,
                    children: [
                      for (final m in _motivos)
                        SizedBox(
                          width: cardWidth,
                          child: _NovedadOptionCard(
                            icon: m.$2,
                            title: m.$1,
                            subtitle: m.$3,
                            selected: motivo == m.$1,
                            onTap: () => controller.elegirMotivoNovedad(m.$1),
                          ),
                        ),
                    ],
                  ),
                );
              },
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              _VolverNovedad(onTap: controller.volverNovedadCedula),
              const Spacer(),
              ElevatedButton.icon(
                iconAlignment: IconAlignment.end,
                label: Text(esDiligencia ? 'Siguiente' : 'Registrar novedad', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: Colors.white)),
                icon: esDiligencia ? const Icon(Icons.arrow_forward_outlined, color: Colors.white, size: 28) : const Icon(Icons.save_alt_outlined, color: Colors.white, size: 28),
                onPressed: !puedeContinuar
                    ? null
                    : () async {
                        final msg = await controller.continuarNovedadMotivo();
                        if (!context.mounted || msg == null) return;
                        await showKioskDialog(
                          context,
                          title: 'No se pudo registrar',
                          message: msg,
                          icon: Icons.error_outline,
                        );
                      },
                style: ElevatedButton.styleFrom(
                  backgroundColor: puedeContinuar ? KioskColors.azul : KioskColors.muted,
                  disabledBackgroundColor: KioskColors.muted,
                  padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 18),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Paso 2: quién autoriza (solo diligencia).
class NovedadQuienScreen extends StatelessWidget {
  const NovedadQuienScreen({super.key, required this.controller});
  final KioskController controller;

  static const _opciones = <(String, IconData, String)>[
    ('Jefe inmediato', Icons.badge_outlined, 'Tu jefe directo'),
    ('Jefe de recursos Humanos', Icons.groups_outlined, 'Autorización de RRHH'),
    ('Gerencia', Icons.apartment_outlined, 'Autorización de gerencia'),
    ('Otro', Icons.person_search_outlined, 'Buscar empleado'),
  ];

  @override
  Widget build(BuildContext context) {
    final emp = controller.empleado;
    final ctx = controller.novedadContexto;
    final quien = controller.novedadQuien;
    final puedeGuardar = (quien ?? '').isNotEmpty && !controller.busy;

    return Padding(
      padding: const EdgeInsets.fromLTRB(48, 24, 48, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('Novedades · Autorización'),
          const SizedBox(height: 8),
          const Text(
            'Quién autoriza',
            style: TextStyle(fontSize: 34, fontWeight: FontWeight.w600, color: KioskColors.ink),
          ),
          const SizedBox(height: 8),
          Text(
            'Motivo: ${controller.novedadMotivo ?? NovedadMotivos.diligencia}',
            style: const TextStyle(fontSize: 18, color: KioskColors.muted),
          ),
          if (emp != null && ctx != null) ...[
            const SizedBox(height: 14),
            _EmpleadoResumen(empleado: emp, contexto: ctx),
          ],
          const SizedBox(height: 18),
          Expanded(
            child: LayoutBuilder(
              builder: (context, constraints) {
                const cols = 2;
                const gap = 14.0;
                final cardWidth = (constraints.maxWidth - gap * (cols - 1)) / cols;
                return SingleChildScrollView(
                  child: Wrap(
                    spacing: gap,
                    runSpacing: gap,
                    children: [
                      for (final op in _opciones)
                        SizedBox(
                          width: cardWidth,
                          child: _NovedadOptionCard(
                            icon: op.$2,
                            title: op.$1,
                            subtitle: op.$1 == 'Otro' && controller.novedadQuienDesdeLista && (quien ?? '').isNotEmpty
                                ? quien!
                                : op.$3,
                            selected: quien == op.$1 ||
                                (op.$1 == 'Otro' && controller.novedadQuienDesdeLista && (quien ?? '').isNotEmpty),
                            onTap: () => controller.elegirAutorizaNovedad(op.$1),
                          ),
                        ),
                    ],
                  ),
                );
              },
            ),
          ),
          if ((quien ?? '').isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Text(
                'Autoriza: $quien',
                style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600, color: KioskColors.green),
              ),
            ),
          const SizedBox(height: 10),
          Row(
            children: [
              _VolverNovedad(onTap: controller.volverNovedadForm),
              const Spacer(),
              ElevatedButton.icon(
                iconAlignment: IconAlignment.end,
                label: const Text('Registrar novedad', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: Colors.white)),
                icon: const Icon(Icons.save_alt_outlined, color: Colors.white, size: 28),
                onPressed: !puedeGuardar
                    ? null
                    : () async {
                        final msg = await controller.guardarNovedad();
                        if (!context.mounted || msg == null) return;
                        await showKioskDialog(
                          context,
                          title: 'No se pudo registrar',
                          message: msg,
                          icon: Icons.error_outline,
                        );
                      },
                style: ElevatedButton.styleFrom(
                  backgroundColor: puedeGuardar ? KioskColors.azul : KioskColors.muted,
                  disabledBackgroundColor: KioskColors.muted,
                  padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 18),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class NovedadAutorizaEmpleadoScreen extends StatefulWidget {
  const NovedadAutorizaEmpleadoScreen({super.key, required this.controller});
  final KioskController controller;

  @override
  State<NovedadAutorizaEmpleadoScreen> createState() => _NovedadAutorizaEmpleadoScreenState();
}

class _NovedadAutorizaEmpleadoScreenState extends State<NovedadAutorizaEmpleadoScreen> {
  String filtro = '';

  @override
  Widget build(BuildContext context) {
    final c = widget.controller;
    final empleados = c.empleadosAdmin.where((e) {
      if (filtro.isEmpty) return true;
      final q = filtro.toLowerCase();
      return e.nombre.toLowerCase().contains(q) || e.identificacion.contains(q);
    }).toList();

    return Padding(
      padding: const EdgeInsets.fromLTRB(48, 28, 48, 28),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('Novedades'),
          const SizedBox(height: 8),
          const Text('Buscar quién autoriza', style: TextStyle(fontSize: 34, fontWeight: FontWeight.w600, color: KioskColors.ink)),
          const SizedBox(height: 14),
          TextField(
            onChanged: (v) => setState(() => filtro = v),
            style: const TextStyle(fontSize: 20),
            decoration: InputDecoration(
              hintText: 'Buscar por nombre o cédula',
              prefixIcon: const Icon(Icons.search, color: KioskColors.green),
              filled: true,
              fillColor: const Color(0xFFF8FAFC),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: KioskColors.line, width: 1.5),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: KioskColors.green, width: 2),
              ),
            ),
          ),
          const SizedBox(height: 14),
          Expanded(
            child: ListView.separated(
              itemCount: empleados.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (_, i) {
                final e = empleados[i];
                return Material(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(16),
                    onTap: () => c.elegirAutorizaEmpleadoNovedad(e),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: KioskColors.line, width: 1.5),
                      ),
                      child: Row(
                        children: [
                          Container(
                            width: 48,
                            height: 48,
                            decoration: BoxDecoration(
                              color: const Color(0xFFECFDF3),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: const Icon(Icons.person_outline, color: KioskColors.green),
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(e.nombre, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w600, color: KioskColors.ink)),
                                Text(e.identificacion, style: const TextStyle(fontSize: 16, color: KioskColors.muted)),
                              ],
                            ),
                          ),
                          const Icon(Icons.chevron_right, color: KioskColors.faint),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
          const SizedBox(height: 12),
          _VolverNovedad(onTap: c.volverNovedadQuien),
        ],
      ),
    );
  }
}

class _EmpleadoResumen extends StatelessWidget {
  const _EmpleadoResumen({required this.empleado, required this.contexto});
  final Identificado empleado;
  final NovedadContexto contexto;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFF0FDF4), Color(0xFFF8FAFC)],
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
        ),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFBBF7D0), width: 1.5),
      ),
      child: Row(
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFBBF7D0)),
            ),
            child: const Icon(Icons.badge_outlined, color: KioskColors.green, size: 28),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(empleado.nombre, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w600, color: KioskColors.ink)),
                Text('Cédula ${empleado.identificacion}', style: const TextStyle(fontSize: 16, color: KioskColors.muted)),
              ],
            ),
          ),
          Text(
            'Jornada ${contexto.jornada}\n${contexto.rangoLabel}',
            textAlign: TextAlign.right,
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: KioskColors.green, height: 1.3),
          ),
        ],
      ),
    );
  }
}

class _NovedadOptionCard extends StatelessWidget {
  const _NovedadOptionCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.selected,
    required this.onTap,
    this.maxHeight = 300,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final bool selected;
  final VoidCallback onTap;
  final double maxHeight;

  @override
  Widget build(BuildContext context) {
    return ConstrainedBox(
      constraints: BoxConstraints(maxHeight: maxHeight),
      child: Material(
        color: selected ? const Color(0xFFECFDF3) : Colors.white,
        elevation: selected ? 2 : 0,
        shadowColor: const Color(0x2216A34A),
        borderRadius: BorderRadius.circular(18),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(18),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 160),
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(18),
              border: Border.all(
                color: selected ? KioskColors.green : KioskColors.line,
                width: selected ? 2.2 : 1.5,
              ),
            ),
            child: Row(
              children: [
                Container(
                  width: 68,
                  height: 68,
                  decoration: BoxDecoration(
                    color: selected ? KioskColors.green : const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(icon, color: selected ? Colors.white : KioskColors.muted, size: 36),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        title,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 28,
                          fontWeight: FontWeight.w700,
                          color: selected ? KioskColors.greenDark : KioskColors.ink,
                          height: 1.15,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        subtitle,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 18,
                          color: selected ? KioskColors.greenDark.withValues(alpha: 0.85) : KioskColors.muted,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _VolverNovedad extends StatelessWidget {
  const _VolverNovedad({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ElevatedButton.icon(
      onPressed: onTap,
      icon: const Icon(Icons.arrow_back_outlined, color: KioskColors.red),
      label: const Text('Volver', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: KioskColors.red)),
      style: ElevatedButton.styleFrom(
        backgroundColor: const Color(0xFFFFE7E7),
        elevation: 0,
        padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 18),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        side: const BorderSide(color: KioskColors.red),
      ),
    );
  }
}
