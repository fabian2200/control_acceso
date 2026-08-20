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
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Eyebrow('Novedades'),
                const SizedBox(height: 10),
                const Text(
                  'Registrar novedad',
                  style: TextStyle(fontSize: 36, fontWeight: FontWeight.w600, color: KioskColors.ink),
                ),
                const SizedBox(height: 12),
                const Text(
                  'Ingrese número de cédula',
                  style: TextStyle(fontSize: 22, color: KioskColors.muted),
                ),
                const Spacer(),
                if (c.error != null) AlertErr(c.error!),
                ElevatedButton.icon(
                  onPressed: c.cancelarNovedad,
                  icon: const Icon(Icons.arrow_back_outlined, color: KioskColors.red),
                  label: const Text('Volver', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: KioskColors.red)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFFFE7E7),
                    elevation: 0,
                    padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 18),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    side: const BorderSide(color: KioskColors.red),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 28),
          SizedBox(
            width: 560,
            child: Column(
              children: [
                Container(
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
                const SizedBox(height: 24),
                Expanded(
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
                    onOk: () => c.identificarNovedad(cedula),
                    okEnabled: cedula.length >= 5 && !c.busy,
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

class NovedadFormScreen extends StatelessWidget {
  const NovedadFormScreen({super.key, required this.controller});
  final KioskController controller;

  @override
  Widget build(BuildContext context) {
    final emp = controller.empleado;
    final ctx = controller.novedadContexto;
    if (emp == null || ctx == null) {
      return const Center(child: Text('Sin datos de novedad'));
    }
    final motivo = controller.novedadMotivo;
    final esDiligencia = motivo == NovedadMotivos.diligencia;
    final puedeGuardar = motivo != null &&
        motivo.isNotEmpty &&
        (!esDiligencia || (controller.novedadQuien ?? '').isNotEmpty) &&
        !controller.busy;

    return Padding(
      padding: const EdgeInsets.fromLTRB(48, 24, 48, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('Novedades'),
          const SizedBox(height: 8),
          const Text(
            'Registrar novedad',
            style: TextStyle(fontSize: 34, fontWeight: FontWeight.w600, color: KioskColors.ink),
          ),
          const SizedBox(height: 16),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: KioskColors.line, width: 2),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(emp.nombre, style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w600, color: KioskColors.ink)),
                const SizedBox(height: 4),
                Text('Cédula ${emp.identificacion}', style: const TextStyle(fontSize: 18, color: KioskColors.muted)),
                const SizedBox(height: 10),
                Text(
                  'Jornada ${ctx.jornada} · ${ctx.rangoLabel}',
                  style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w600, color: KioskColors.green),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          const Text('Motivo', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w600, color: KioskColors.ink)),
          const SizedBox(height: 10),
          Expanded(
            child: GridView.count(
              crossAxisCount: 3,
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 2.4,
              children: [
                for (final m in NovedadMotivos.todos)
                  _MotivoChip(
                    label: m,
                    selected: motivo == m,
                    onTap: () => controller.elegirMotivoNovedad(m),
                  ),
              ],
            ),
          ),
          if (esDiligencia) ...[
            const SizedBox(height: 8),
            const Text('Quién autoriza', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w600, color: KioskColors.ink)),
            const SizedBox(height: 10),
            SizedBox(
              height: 120,
              child: Row(
                children: [
                  for (final op in const [
                    ('Jefe inmediato', 'Tu jefe directo'),
                    ('Jefe de recursos Humanos', 'RRHH'),
                    ('Gerencia', 'Gerencia'),
                    ('Otro', 'Buscar empleado'),
                  ]) ...[
                    Expanded(
                      child: _MotivoChip(
                        label: op.$1,
                        sub: op.$2,
                        selected: controller.novedadQuien == op.$1 ||
                            (op.$1 == 'Otro' && controller.novedadQuienDesdeLista),
                        onTap: () => controller.elegirAutorizaNovedad(op.$1),
                      ),
                    ),
                    if (op.$1 != 'Otro') const SizedBox(width: 10),
                  ],
                ],
              ),
            ),
            if ((controller.novedadQuien ?? '').isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  'Autoriza: ${controller.novedadQuien}',
                  style: const TextStyle(fontSize: 18, color: KioskColors.muted),
                ),
              ),
          ],
          if (controller.error != null) ...[
            const SizedBox(height: 10),
            AlertErr(controller.error!),
          ],
          const SizedBox(height: 14),
          Row(
            children: [
              ElevatedButton.icon(
                onPressed: controller.volverNovedadCedula,
                icon: const Icon(Icons.arrow_back_outlined, color: KioskColors.red),
                label: const Text('Volver', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: KioskColors.red)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFFFE7E7),
                  elevation: 0,
                  padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 18),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  side: const BorderSide(color: KioskColors.red),
                ),
              ),
              const Spacer(),
              PrimaryButton(
                label: 'Registrar novedad',
                green: true,
                enabled: puedeGuardar,
                onTap: controller.guardarNovedad,
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
          const Text('Quién autoriza', style: TextStyle(fontSize: 34, fontWeight: FontWeight.w600, color: KioskColors.ink)),
          const SizedBox(height: 14),
          TextField(
            onChanged: (v) => setState(() => filtro = v),
            decoration: InputDecoration(
              hintText: 'Buscar empleado',
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
            ),
          ),
          const SizedBox(height: 14),
          Expanded(
            child: ListView.separated(
              itemCount: empleados.length,
              separatorBuilder: (_, _) => const SizedBox(height: 8),
              itemBuilder: (_, i) {
                final e = empleados[i];
                return ListTile(
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                    side: const BorderSide(color: KioskColors.line),
                  ),
                  title: Text(e.nombre, style: const TextStyle(fontWeight: FontWeight.w600)),
                  subtitle: Text(e.identificacion),
                  onTap: () => c.elegirAutorizaEmpleadoNovedad(e),
                );
              },
            ),
          ),
          const SizedBox(height: 12),
          ElevatedButton.icon(
            onPressed: c.volverNovedadForm,
            icon: const Icon(Icons.arrow_back_outlined, color: KioskColors.red),
            label: const Text('Volver', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: KioskColors.red)),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFFFE7E7),
              elevation: 0,
              padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 18),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              side: const BorderSide(color: KioskColors.red),
            ),
          ),
        ],
      ),
    );
  }
}

class _MotivoChip extends StatelessWidget {
  const _MotivoChip({
    required this.label,
    required this.selected,
    required this.onTap,
    this.sub,
  });

  final String label;
  final String? sub;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? const Color(0xFFECFDF3) : Colors.white,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: selected ? KioskColors.green : KioskColors.line,
              width: selected ? 2 : 1.5,
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                label,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w600,
                  color: selected ? KioskColors.green : KioskColors.ink,
                ),
              ),
              if (sub != null)
                Text(sub!, style: const TextStyle(fontSize: 14, color: KioskColors.muted)),
            ],
          ),
        ),
      ),
    );
  }
}
