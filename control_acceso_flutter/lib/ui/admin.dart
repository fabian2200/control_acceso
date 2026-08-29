import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../config.dart';
import '../domain/hora_fmt.dart';
import 'kiosk.dart';
import 'theme.dart';
import 'widgets.dart';

class AdminScreen extends StatefulWidget {
  const AdminScreen({super.key, required this.controller});
  final KioskController controller;

  @override
  State<AdminScreen> createState() => _AdminScreenState();
}

class _AdminScreenState extends State<AdminScreen> {
  late final TextEditingController url;
  String filtro = '';

  @override
  void initState() {
    super.initState();
    url = TextEditingController(text: widget.controller.apiUrl);
    widget.controller.cargarEmpleadosAdmin();
  }

  @override
  void dispose() {
    url.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = widget.controller;
    final empleados = c.empleadosAdmin.where((e) {
      if (filtro.isEmpty) return true;
      final q = filtro.toLowerCase();
      return e.nombre.toLowerCase().contains(q) || e.identificacion.contains(q);
    }).toList();

    return Padding(
      padding: const EdgeInsets.fromLTRB(48, 36, 48, 28),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('Panel administrador'),
          const SizedBox(height: 10),
          const Text('Administración del kiosko', style: TextStyle(fontSize: 36, fontWeight: FontWeight.w600, color: KioskColors.ink)),
          const SizedBox(height: 18),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: url,
                  style: const TextStyle(fontSize: 20),
                  decoration: InputDecoration(
                    labelText: 'URL del servidor NUBE',
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                ),
              ),
              const SizedBox(width: 16),
              PrimaryButton(
                label: 'Guardar URL',
                onTap: () => c.saveApiUrl(url.text.trim()),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            'Terminal ${AppConfig.terminalCodigo} · ${c.syncUi.etiquetaRed} · ${c.syncUi.etiquetaSync}',
            style: const TextStyle(fontSize: 16, color: KioskColors.muted),
          ),
          const SizedBox(height: 22),
          Row(
            children: [
              const Text('Logs', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w600, color: KioskColors.ink)),
              const Spacer(),
              SizedBox(
                width: 320,
                child: TextField(
                  onChanged: (v) => setState(() => filtro = v.trim()),
                  decoration: InputDecoration(
                    hintText: 'Buscar empleado o cédula',
                    prefixIcon: const Icon(Icons.search),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    isDense: true,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Expanded(
            child: empleados.isEmpty
                ? const Center(child: Text('No hay empleados en este kiosko. Sincroniza primero.', style: TextStyle(fontSize: 18, color: KioskColors.muted)))
                : ListView.separated(
                    itemCount: empleados.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 8),
                    itemBuilder: (_, i) {
                      final e = empleados[i];
                      return Material(
                        color: const Color(0xFFF8FAFC),
                        borderRadius: BorderRadius.circular(14),
                        child: InkWell(
                          onTap: () => c.abrirLogEmpleado(e),
                          borderRadius: BorderRadius.circular(14),
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 16),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(e.nombre.isEmpty ? 'Empleado' : e.nombre, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w600, color: KioskColors.ink)),
                                      const SizedBox(height: 4),
                                      Text(
                                        'C.C. ${e.identificacion}${e.cargo == null || e.cargo!.isEmpty ? '' : ' · ${e.cargo}'}',
                                        style: const TextStyle(fontSize: 16, color: KioskColors.muted),
                                      ),
                                    ],
                                  ),
                                ),
                                const Icon(Icons.chevron_right, size: 32, color: KioskColors.faint),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
          ),
          const SizedBox(height: 16),
          GhostButton(label: 'Salir', onTap: c.reset, tall: true),
        ],
      ),
    );
  }
}

class AdminLogScreen extends StatelessWidget {
  const AdminLogScreen({super.key, required this.controller});
  final KioskController controller;

  Future<void> _elegirFecha(
    BuildContext context, {
    required DateTime actual,
    required Future<void> Function(DateTime) onPicked,
    required String helpText,
  }) async {
    final hoy = DateTime.now();
    final limite = DateTime(hoy.year, hoy.month, hoy.day);
    final inicial = actual.isAfter(limite) ? limite : actual;
    final picked = await showDatePicker(
      context: context,
      initialDate: inicial,
      firstDate: DateTime(2020),
      lastDate: limite,
      helpText: helpText,
      cancelText: 'Cancelar',
      confirmText: 'Aceptar',
      fieldLabelText: 'Fecha',
    );
    if (picked != null) await onPicked(picked);
  }

  @override
  Widget build(BuildContext context) {
    final emp = controller.empleadoAdmin;
    final origen = controller.logsDesdeNube ? 'NUBE' : 'solo local';
    return Padding(
      padding: const EdgeInsets.fromLTRB(48, 36, 48, 28),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('Logs'),
          const SizedBox(height: 10),
          Text(emp?.nombre ?? 'Empleado', style: const TextStyle(fontSize: 36, fontWeight: FontWeight.w600, color: KioskColors.ink)),
          const SizedBox(height: 6),
          Text(
            'C.C. ${emp?.identificacion ?? ''} · ${controller.logsPeriodoLabel} · $origen',
            style: const TextStyle(fontSize: 18, color: KioskColors.muted),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _DateFilterChip(
                  label: 'Desde',
                  fecha: controller.logsDesde,
                  onTap: controller.busy
                      ? null
                      : () => _elegirFecha(
                            context,
                            actual: controller.logsDesde,
                            helpText: 'Desde',
                            onPicked: controller.cambiarLogsDesde,
                          ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _DateFilterChip(
                  label: 'Hasta',
                  fecha: controller.logsHasta,
                  onTap: controller.busy
                      ? null
                      : () => _elegirFecha(
                            context,
                            actual: controller.logsHasta,
                            helpText: 'Hasta',
                            onPicked: controller.cambiarLogsHasta,
                          ),
                ),
              ),
              const SizedBox(width: 12),
              SizedBox(
                height: 64,
                child: OutlinedButton(
                  onPressed: controller.busy ? null : controller.logsEsteMes,
                  style: OutlinedButton.styleFrom(
                    foregroundColor: KioskColors.ink,
                    side: const BorderSide(color: KioskColors.line),
                    padding: const EdgeInsets.symmetric(horizontal: 22),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    textStyle: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600),
                  ),
                  child: const Text('Este mes'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Expanded(
            child: controller.busy && controller.logsMes.isEmpty
                ? const Center(child: CircularProgressIndicator())
                : controller.logsMes.isEmpty
                    ? const Center(child: Text('Sin marcas en este periodo.', style: TextStyle(fontSize: 20, color: KioskColors.muted)))
                    : ListView.separated(
                        itemCount: controller.logsMes.length,
                        separatorBuilder: (_, _) => const SizedBox(height: 8),
                        itemBuilder: (_, i) {
                          final item = controller.logsMes[i];
                          return Container(
                            padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 16),
                            decoration: BoxDecoration(
                              color: item.alerta ? const Color(0xFFFFFBEB) : const Color(0xFFF8FAFC),
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: item.alerta ? const Color(0xFFFDE68A) : KioskColors.line),
                            ),
                            child: Row(
                              children: [
                                LogPhoto(src: item.foto),
                                const SizedBox(width: 16),
                                SizedBox(
                                  width: 168,
                                  child: Text(
                                    '${DateFormat("d MMM", 'es').format(HoraFmt.wall(item.cuando))} ${HoraFmt.of(item.cuando)}',
                                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600, color: KioskColors.ink),
                                  ),
                                ),
                                SizedBox(
                                  width: 200,
                                  child: Text(item.titulo, style: TextStyle(fontSize: 20, fontWeight: FontWeight.w600, color: item.alerta ? const Color(0xFFB45309) : KioskColors.ink)),
                                ),
                                Expanded(
                                  child: Text(item.detalle, style: const TextStyle(fontSize: 17, color: KioskColors.muted)),
                                ),
                              ],
                            ),
                          );
                        },
                      ),
          ),
          const SizedBox(height: 16),
          GhostButton(label: 'Volver', onTap: controller.volverAdmin, tall: true),
        ],
      ),
    );
  }
}

class _DateFilterChip extends StatelessWidget {
  const _DateFilterChip({required this.label, required this.fecha, this.onTap});

  final String label;
  final DateTime fecha;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: const Color(0xFFF8FAFC),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          height: 64,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: KioskColors.line),
          ),
          child: Row(
            children: [
              const Icon(Icons.calendar_today_outlined, size: 22, color: KioskColors.muted),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(label, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: KioskColors.muted)),
                    Text(
                      DateFormat("d MMM yyyy", 'es').format(fecha),
                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600, color: KioskColors.ink),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
