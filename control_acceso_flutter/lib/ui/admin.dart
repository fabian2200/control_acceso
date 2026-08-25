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
    final mes = DateFormat("MMMM yyyy", 'es').format(DateTime.now());
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
              Text('Log de $mes', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w600, color: KioskColors.ink)),
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

  @override
  Widget build(BuildContext context) {
    final emp = controller.empleadoAdmin;
    final mes = DateFormat("MMMM yyyy", 'es').format(DateTime.now());
    return Padding(
      padding: const EdgeInsets.fromLTRB(48, 36, 48, 28),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('Log del mes'),
          const SizedBox(height: 10),
          Text(emp?.nombre ?? 'Empleado', style: const TextStyle(fontSize: 36, fontWeight: FontWeight.w600, color: KioskColors.ink)),
          const SizedBox(height: 6),
          Text(
            'C.C. ${emp?.identificacion ?? ''} · $mes${controller.logsDesdeNube ? ' · NUBE' : ' · solo local'}',
            style: const TextStyle(fontSize: 18, color: KioskColors.muted),
          ),
          const SizedBox(height: 18),
          Expanded(
            child: controller.busy && controller.logsMes.isEmpty
                ? const Center(child: CircularProgressIndicator())
                : controller.logsMes.isEmpty
                    ? const Center(child: Text('Sin marcas este mes.', style: TextStyle(fontSize: 20, color: KioskColors.muted)))
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
