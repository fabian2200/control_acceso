import 'dart:async';

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart';

import '../config.dart';
import '../data/camara_permiso.dart';
import '../data/db.dart';
import '../domain/acceso_service.dart';
import '../domain/hora_fmt.dart';
import '../domain/logs_service.dart';
import '../domain/models.dart';
import '../domain/novedad_service.dart';
import '../sync/sync_service.dart';
import 'admin.dart';
import 'camera_screen.dart';
import 'novedad.dart';
import 'theme.dart';
import 'widgets.dart';

enum KioskScreen {
  cedula,
  reconocer,
  accion,
  motivo,
  mandado,
  mandadoEmpleado,
  permisos,
  hora,
  camara,
  regreso,
  preguntar,
  confirmacion,
  admin,
  adminLog,
  novedadCedula,
  novedadForm,
  novedadQuien,
  novedadAutoriza,
}

class KioskController extends ChangeNotifier {
  KioskController({AccesoService? acceso, AccesoSync? sync, LogsService? logs, NovedadService? novedades})
      : _acceso = acceso ?? AccesoService(),
        _sync = sync ?? AccesoSync(),
        _logs = logs ?? LogsService(),
        _novedades = novedades ?? NovedadService();

  final AccesoService _acceso;
  final AccesoSync _sync;
  final LogsService _logs;
  final NovedadService _novedades;

  KioskScreen screen = KioskScreen.cedula;
  SyncUi syncUi = const SyncUi();
  Identificado? empleado;
  OpenExit? openExit;
  Confirmacion? confirm;
  Confirmacion? cierre;
  List<BotonJornada> botones = const [];
  List<PermisoHoy> permisos = const [];
  String? error;
  String? homeNotice;
  bool entradaDespuesCierre = false;
  String? tipo;
  String? campo;
  String? origenOcasional;
  int? permisoId;
  String? motivoTexto;
  String? mandadoPor;
  bool mandadoDesdeLista = false;
  String? horaRegreso;
  String apiUrl = AppConfig.defaultApiUrl;
  bool busy = false;
  Timer? _syncTimer;
  List<AdminEmpleado> empleadosAdmin = const [];
  AdminEmpleado? empleadoAdmin;
  List<LogItem> logsMes = const [];
  bool logsDesdeNube = false;
  DateTime logsDesde = _inicioMes();
  DateTime logsHasta = _hoy();
  NovedadContexto? novedadContexto;
  String? novedadMotivo;
  String? novedadQuien;
  bool novedadQuienDesdeLista = false;
  bool confirmDesdeNovedad = false;

  Future<void> start() async {
    apiUrl = await AccesoDb.instance.setting('api_url') ?? AppConfig.defaultApiUrl;
    await tickSync();
    _syncTimer?.cancel();
    _syncTimer = Timer.periodic(const Duration(seconds: 60), (_) => tickSync());
  }

  Future<void> tickSync() async {
    syncUi = SyncUi(
      online: syncUi.online,
      pendientes: syncUi.pendientes,
      syncing: true,
      ultimaSync: syncUi.ultimaSync,
    );
    notifyListeners();
    final result = await _sync.ejecutar();
    syncUi = SyncUi(
      online: result.online,
      pendientes: result.pendientes,
      error: result.error,
      ultimaSync: result.ultimaSync ?? syncUi.ultimaSync,
    );
    notifyListeners();
  }

  Future<void> saveApiUrl(String url) async {
    apiUrl = url.replaceAll(RegExp(r'/$'), '');
    await AccesoDb.instance.setSetting('api_url', apiUrl);
    notifyListeners();
    await tickSync();
  }

  void limpiarError() {
    if (error == null) return;
    error = null;
    notifyListeners();
  }

  void reset() {
    screen = KioskScreen.cedula;
    empleado = null;
    openExit = null;
    confirm = null;
    cierre = null;
    botones = const [];
    permisos = const [];
    error = null;
    homeNotice = null;
    entradaDespuesCierre = false;
    tipo = null;
    campo = null;
    origenOcasional = null;
    permisoId = null;
    motivoTexto = null;
    mandadoPor = null;
    mandadoDesdeLista = false;
    horaRegreso = null;
    empleadoAdmin = null;
    logsMes = const [];
    logsDesdeNube = false;
    logsDesde = _inicioMes();
    logsHasta = _hoy();
    busy = false;
    novedadContexto = null;
    novedadMotivo = null;
    novedadQuien = null;
    novedadQuienDesdeLista = false;
    confirmDesdeNovedad = false;
    notifyListeners();
  }

  void abrirNovedad() {
    error = null;
    homeNotice = null;
    empleado = null;
    novedadContexto = null;
    novedadMotivo = null;
    novedadQuien = null;
    novedadQuienDesdeLista = false;
    screen = KioskScreen.novedadCedula;
    notifyListeners();
  }

  void cancelarNovedad() => reset();

  void volverNovedadCedula() {
    error = null;
    novedadContexto = null;
    novedadMotivo = null;
    novedadQuien = null;
    novedadQuienDesdeLista = false;
    empleado = null;
    screen = KioskScreen.novedadCedula;
    notifyListeners();
  }

  void volverNovedadForm() {
    error = null;
    novedadQuien = null;
    novedadQuienDesdeLista = false;
    screen = KioskScreen.novedadForm;
    notifyListeners();
  }

  void volverNovedadQuien() {
    error = null;
    screen = KioskScreen.novedadQuien;
    notifyListeners();
  }

  /// Devuelve mensaje de error o `null` si avanzó al formulario.
  Future<String?> identificarNovedad(String cedula) async {
    error = null;
    final digits = cedula.replaceAll(RegExp(r'\D'), '');
    if (digits.length < 5) {
      busy = false;
      notifyListeners();
      return 'Ingresa un número de identificación válido.';
    }
    busy = true;
    notifyListeners();
    try {
      final found = await _acceso.identificar(digits);
      if (found == null) {
        busy = false;
        notifyListeners();
        return 'Número de identificación no reconocido. Intenta de nuevo.';
      }
      final ctx = await _novedades.resolverContexto(found.id);
      empleado = found;
      novedadContexto = ctx;
      novedadMotivo = null;
      novedadQuien = null;
      novedadQuienDesdeLista = false;
      screen = KioskScreen.novedadForm;
      busy = false;
      notifyListeners();
      return null;
    } on StateError catch (e) {
      final msg = e.message.trim();
      busy = false;
      notifyListeners();
      return msg.isNotEmpty ? msg : 'No se pudo preparar la novedad.';
    } catch (e, st) {
      debugPrint('identificarNovedad: $e\n$st');
      busy = false;
      notifyListeners();
      return 'No se pudo preparar la novedad. Intenta de nuevo.';
    }
  }

  void elegirMotivoNovedad(String motivo) {
    novedadMotivo = motivo;
    novedadQuien = null;
    novedadQuienDesdeLista = false;
    error = null;
    notifyListeners();
  }

  /// Paso 1: si no es diligencia guarda; si es diligencia pasa a quién autoriza.
  /// Devuelve mensaje de error o `null`.
  Future<String?> continuarNovedadMotivo() async {
    final motivo = novedadMotivo;
    if (motivo == null || motivo.isEmpty || busy) return null;
    if (motivo == NovedadMotivos.diligencia) {
      novedadQuien = null;
      novedadQuienDesdeLista = false;
      error = null;
      screen = KioskScreen.novedadQuien;
      notifyListeners();
      return null;
    }
    return guardarNovedad();
  }

  Future<void> elegirAutorizaNovedad(String quien) async {
    if (quien == 'Otro') {
      novedadQuienDesdeLista = true;
      novedadQuien = null;
      empleadosAdmin = await AccesoDb.instance.empleadosAdmin();
      screen = KioskScreen.novedadAutoriza;
      notifyListeners();
      return;
    }
    novedadQuienDesdeLista = false;
    novedadQuien = quien;
    error = null;
    notifyListeners();
  }

  void elegirAutorizaEmpleadoNovedad(AdminEmpleado sel) {
    final nombre = sel.nombre.trim().isEmpty ? 'Empleado ${sel.identificacion}' : sel.nombre.trim();
    novedadQuien = nombre.length <= 80 ? nombre : '${nombre.substring(0, 79).trim()}…';
    novedadQuienDesdeLista = true;
    screen = KioskScreen.novedadQuien;
    error = null;
    notifyListeners();
  }

  /// Devuelve mensaje de error o `null` si guardó bien.
  Future<String?> guardarNovedad() async {
    final ctx = novedadContexto;
    final motivo = novedadMotivo;
    if (ctx == null || motivo == null || busy) return null;
    busy = true;
    error = null;
    notifyListeners();
    try {
      await _novedades.registrar(
        contexto: ctx,
        motivo: motivo,
        quienAutoriza: novedadQuien,
      );
      final acciones = <String>['Motivo: $motivo', 'Jornada ${ctx.jornada}'];
      final quien = (novedadQuien ?? '').trim();
      if (quien.isNotEmpty) acciones.add('Autoriza: $quien');
      confirm = Confirmacion(
        title: 'Novedad registrada',
        time: HoraFmt.of(DateTime.now()),
        color: ColorData.green,
        pillText: 'Pendiente de revisión',
        pillBg: const Color(0xFFECFDF5),
        pillFg: const Color(0xFF166534),
        meta: 'Jornada ${ctx.jornada}',
        acciones: acciones,
      );
      confirmDesdeNovedad = true;
      busy = false;
      screen = KioskScreen.confirmacion;
      notifyListeners();
      unawaited(tickSync());
      return null;
    } on StateError catch (e) {
      busy = false;
      notifyListeners();
      final msg = e.message.trim();
      return msg.isNotEmpty ? msg : 'No se pudo guardar la novedad.';
    } catch (_) {
      busy = false;
      notifyListeners();
      return 'No se pudo guardar la novedad.';
    }
  }

  /// Tras confirmar novedad: ir a Entrada/Salida del mismo empleado.
  Future<void> continuarTrasNovedad() async {
    if (empleado == null) return reset();
    novedadContexto = null;
    novedadMotivo = null;
    novedadQuien = null;
    novedadQuienDesdeLista = false;
    confirmDesdeNovedad = false;
    confirm = null;
    homeNotice = null;
    error = null;
    openExit = await _acceso.salidaAbierta(empleado!.id);
    if (openExit != null) {
      screen = KioskScreen.regreso;
      notifyListeners();
      return;
    }
    await _cargarAccion();
  }

  Future<void> identificar(String cedula) async {
    error = null;
    homeNotice = null;
    final digits = cedula.replaceAll(RegExp(r'\D'), '');
    if (digits == AppConfig.adminPin) {
      await abrirAdmin();
      return;
    }
    busy = true;
    notifyListeners();
    final found = await _acceso.identificar(cedula);
    busy = false;
    if (found == null) {
      error = 'Número de identificación no reconocido. Intenta de nuevo.';
      notifyListeners();
      return;
    }
    empleado = found;
    openExit = await _acceso.salidaAbierta(found.id);
    screen = KioskScreen.reconocer;
    notifyListeners();
  }

  Future<void> sincronizarAhora() async {
    homeNotice = null;
    error = null;
    notifyListeners();
    await tickSync();
    if (!syncUi.online) {
      homeNotice = 'Sin conexión. Las marcas quedan en este kiosko.';
    } else if (syncUi.error != null) {
      homeNotice = 'No se pudo sincronizar.';
    } else if (syncUi.pendientes > 0) {
      homeNotice = 'En línea. Quedan ${syncUi.pendientes} marcas por enviar.';
    } else {
      homeNotice = 'Sincronizado con la NUBE.';
    }
    notifyListeners();
  }

  Future<void> continuarReconocer() async {
    if (empleado == null) return reset();
    if (openExit != null) {
      screen = KioskScreen.regreso;
      notifyListeners();
      return;
    }
    await _cargarAccion();
  }

  Future<void> _cargarAccion() async {
    if (empleado == null) return;
    botones = await _acceso.botonesJornada(empleado!.id);
    screen = KioskScreen.accion;
    error = null;
    notifyListeners();
  }

  Future<void> refreshBotones() async {
    if (empleado == null || screen != KioskScreen.accion) return;
    botones = await _acceso.botonesJornada(empleado!.id);
    notifyListeners();
  }

  Future<void> volverAccion() => _cargarAccion();

  void volverMotivo() {
    error = null;
    screen = KioskScreen.motivo;
    notifyListeners();
  }

  void volverMandado() {
    screen = KioskScreen.mandado;
    notifyListeners();
  }

  void volverHora() {
    error = null;
    screen = mandadoDesdeLista ? KioskScreen.mandadoEmpleado : KioskScreen.mandado;
    notifyListeners();
  }

  Future<void> elegir(BotonJornada boton) async {
    if (!boton.enabled || empleado == null || busy) return;
    if (openExit != null && boton.tipo != 'salida_ocasional' && !entradaDespuesCierre) {
      screen = KioskScreen.regreso;
      notifyListeners();
      return;
    }
    if (!await _acceso.slotHabilitado(empleado!.id, boton.tipo, boton.campo)) {
      error = 'Esa marca no está disponible en este momento.';
      notifyListeners();
      return;
    }
    if (boton.tipo == 'salida_ocasional') {
      screen = KioskScreen.motivo;
      notifyListeners();
      return;
    }
    tipo = boton.tipo;
    campo = boton.campo;
    _irACamara();
  }

  void elegirOrigen(String origen) {
    origenOcasional = origen;
    permisoId = null;
    mandadoPor = null;
    mandadoDesdeLista = false;
    horaRegreso = null;
    if (origen == 'permiso') {
      motivoTexto = '';
      _cargarPermisos();
    } else {
      motivoTexto = 'Diligencia empresarial';
      screen = KioskScreen.mandado;
      notifyListeners();
    }
  }

  void elegirMandadoPor(String quien) {
    if (quien == 'Otro') {
      unawaited(_abrirMandadoEmpleados());
      return;
    }
    mandadoDesdeLista = false;
    mandadoPor = quien;
    screen = KioskScreen.hora;
    notifyListeners();
  }

  Future<void> _abrirMandadoEmpleados() async {
    mandadoDesdeLista = true;
    mandadoPor = null;
    empleadosAdmin = await AccesoDb.instance.empleadosAdmin();
    screen = KioskScreen.mandadoEmpleado;
    notifyListeners();
  }

  void elegirMandadoEmpleado(AdminEmpleado sel) {
    final nombre = sel.nombre.trim().isEmpty ? 'Empleado ${sel.identificacion}' : sel.nombre.trim();
    mandadoPor = nombre.length <= 80 ? nombre : '${nombre.substring(0, 79).trim()}…';
    mandadoDesdeLista = true;
    screen = KioskScreen.hora;
    notifyListeners();
  }

  Future<void> _cargarPermisos() async {
    final userId = empleado?.userId;
    permisos = userId == null ? const [] : await _acceso.permisosHoy(userId);
    screen = KioskScreen.permisos;
    notifyListeners();
  }

  Future<void> elegirPermiso(PermisoHoy permiso) async {
    if (!_acceso.horaRegresoEsValida(permiso.horaFin == '--:--' ? null : permiso.horaFinDigitos)) {
      error = 'La hora de regreso esperada debe ser posterior a la hora actual.';
      notifyListeners();
      return;
    }
    error = null;
    tipo = 'salida_ocasional';
    permisoId = permiso.id;
    motivoTexto = permiso.motivo;
    horaRegreso = permiso.horaFinDigitos;
    _irACamara();
  }

  Future<void> guardarHora(String digits) async {
    if (!_acceso.horaRegresoEsValida(digits)) {
      error = 'La hora de regreso esperada debe ser posterior a la hora actual.';
      notifyListeners();
      return;
    }
    error = null;
    tipo = 'salida_ocasional';
    horaRegreso = digits;
    _irACamara();
  }

  void confirmarRegreso() {
    if (empleado == null) return reset();
    tipo = 'regreso';
    _irACamara();
  }

  void _irACamara() {
    screen = KioskScreen.camara;
    notifyListeners();
  }

  String get etiquetaFoto {
    return switch (campo ?? tipo) {
      'entrada_jornada_1' => 'Foto de entrada jornada 1',
      'salida_jornada_1' => 'Foto de salida jornada 1',
      'entrada_jornada_2' => 'Foto de entrada jornada 2',
      'salida_jornada_2' => 'Foto de salida jornada 2',
      'salida' => 'Foto de salida',
      'salida_ocasional' => 'Foto de salida ocasional',
      'regreso' => 'Foto de regreso',
      _ => 'Foto de entrada',
    };
  }

  void cancelarCamara() {
    if (tipo == 'regreso') {
      screen = KioskScreen.regreso;
      notifyListeners();
      return;
    }
    if (tipo == 'salida_ocasional') {
      screen = origenOcasional == 'otro' ? KioskScreen.hora : KioskScreen.motivo;
      notifyListeners();
      return;
    }
    unawaited(_cargarAccion());
  }

  Future<void> guardarFotoYMarcar(String foto) async {
    await _marcar(foto);
  }

  Future<void> decidirEntrada(bool si) async {
    if (!si) {
      reset();
      return;
    }
    tipo = 'entrada';
    campo = 'entrada_jornada_2';
    _irACamara();
  }

  Future<void> _marcar(String? foto) async {
    if (empleado == null || tipo == null) return;
    busy = true;
    notifyListeners();
    try {
      confirm = await _acceso.registrar(
        empleadoId: empleado!.id,
        userId: empleado!.userId,
        tipo: tipo!,
        campo: campo,
        permisoId: permisoId,
        motivoTexto: motivoTexto,
        mandadoPor: mandadoPor,
        horaRegreso: horaRegreso,
        foto: foto,
      );
    } on StateError catch (e) {
      busy = false;
      final msg = e.message.trim();
      error = msg.isNotEmpty ? msg : 'Esa marca no está disponible en este momento.';
      await _cargarAccion();
      return;
    } catch (_) {
      busy = false;
      error = 'Esa marca no está disponible en este momento.';
      await _cargarAccion();
      return;
    }
    busy = false;
    if (tipo == 'regreso') {
      cierre = confirm;
      openExit = null;
      final preguntar = confirm?.preguntarEntradaJ2 == true;
      entradaDespuesCierre = preguntar;
      screen = preguntar ? KioskScreen.preguntar : KioskScreen.confirmacion;
    } else {
      screen = KioskScreen.confirmacion;
    }
    notifyListeners();
    unawaited(tickSync());
  }

  void openSettings() {
    unawaited(abrirAdmin());
  }

  Future<void> abrirAdmin() async {
    screen = KioskScreen.admin;
    notifyListeners();
    await cargarEmpleadosAdmin();
  }

  Future<void> cargarEmpleadosAdmin() async {
    empleadosAdmin = await AccesoDb.instance.empleadosAdmin();
    notifyListeners();
  }

  Future<void> abrirLogEmpleado(AdminEmpleado empleadoSel) async {
    empleadoAdmin = empleadoSel;
    logsMes = const [];
    logsDesdeNube = false;
    logsDesde = _inicioMes();
    logsHasta = _hoy();
    screen = KioskScreen.adminLog;
    await _cargarLogs();
  }

  Future<void> cambiarLogsDesde(DateTime fecha) async {
    logsDesde = _soloDia(fecha);
    if (logsDesde.isAfter(logsHasta)) logsHasta = logsDesde;
    await _cargarLogs();
  }

  Future<void> cambiarLogsHasta(DateTime fecha) async {
    logsHasta = _soloDia(fecha);
    if (logsHasta.isBefore(logsDesde)) logsDesde = logsHasta;
    await _cargarLogs();
  }

  Future<void> logsEsteMes() async {
    logsDesde = _inicioMes();
    logsHasta = _hoy();
    await _cargarLogs();
  }

  int _logsCarga = 0;

  Future<void> _cargarLogs() async {
    final emp = empleadoAdmin;
    if (emp == null) return;
    final carga = ++_logsCarga;
    busy = true;
    notifyListeners();
    final result = await _logs.rango(emp.id, desde: logsDesde, hasta: logsHasta);
    if (carga != _logsCarga || empleadoAdmin?.id != emp.id) return;
    logsMes = result.items;
    logsDesdeNube = result.desdeNube;
    busy = false;
    notifyListeners();
  }

  static DateTime _soloDia(DateTime d) => DateTime(d.year, d.month, d.day);

  static DateTime _hoy() {
    final n = DateTime.now();
    return DateTime(n.year, n.month, n.day);
  }

  static DateTime _inicioMes() {
    final n = DateTime.now();
    return DateTime(n.year, n.month, 1);
  }

  String get logsPeriodoLabel {
    final fmt = DateFormat("d MMM yyyy", 'es');
    if (logsDesde.year == logsHasta.year && logsDesde.month == logsHasta.month && logsDesde.day == logsHasta.day) {
      return fmt.format(logsDesde);
    }
    return '${fmt.format(logsDesde)} — ${fmt.format(logsHasta)}';
  }

  void volverAdmin() {
    screen = KioskScreen.admin;
    empleadoAdmin = null;
    logsMes = const [];
    notifyListeners();
  }

  void backToCedulaFromSettings() {
    reset();
  }

  @override
  void dispose() {
    _syncTimer?.cancel();
    super.dispose();
  }
}

class KioskApp extends StatefulWidget {
  const KioskApp({super.key, required this.controller});
  final KioskController controller;

  @override
  State<KioskApp> createState() => _KioskAppState();
}

class _KioskAppState extends State<KioskApp> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      unawaited(pedirPermisoCamaraAlInicio());
    });
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Control de Acceso',
      debugShowCheckedModeBanner: false,
      theme: kioskTheme(),
      locale: const Locale('es'),
      supportedLocales: const [Locale('es')],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: KioskShell(controller: widget.controller),
    );
  }
}

class KioskShell extends StatelessWidget {
  const KioskShell({super.key, required this.controller});
  final KioskController controller;

  @override
  Widget build(BuildContext context) {
    // ancho de la tablet calcular
    final width = MediaQuery.of(context).size.width;
    return ListenableBuilder(
      listenable: controller,
      builder: (context, _) {
        return Scaffold(
          body: Container(
            decoration: const BoxDecoration(
              gradient: RadialGradient(
                center: Alignment.topCenter,
                radius: 1.2,
                colors: [Color(0xFF1B1F27), KioskColors.page],
              ),
            ),
            child: Center(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: FittedBox(
                  child: Container(
                    width: width + 100,
                    height: 900,
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [KioskColors.frameTop, KioskColors.frameBottom],
                      ),
                      borderRadius: BorderRadius.circular(36),
                      boxShadow: const [
                        BoxShadow(color: Color(0x8C000000), blurRadius: 90, offset: Offset(0, 40)),
                      ],
                    ),
                    child: Container(
                      width: 1280,
                      height: 800,
                      decoration: BoxDecoration(
                        color: KioskColors.screen,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: Column(
                        children: [
                          if (controller.screen != KioskScreen.cedula) const KioskHeader(),
                          if (controller.screen != KioskScreen.cedula)
                            const Divider(height: 1, color: KioskColors.line),
                          Expanded(child: _ScreenHost(controller: controller)),
                          _StatusBar(controller: controller),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

class _StatusBar extends StatelessWidget {
  const _StatusBar({required this.controller});
  final KioskController controller;

  @override
  Widget build(BuildContext context) {
    final ui = controller.syncUi;
    return Container(
      height: 64,
      color: KioskColors.bar,
      padding: const EdgeInsets.symmetric(horizontal: 34),
      child: Row(
        children: [
          GestureDetector(
            onLongPress: controller.openSettings,
            child: Row(
              children: [
                Container(
                  width: 10,
                  height: 10,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: ui.online ? KioskColors.green : KioskColors.muted,
                    boxShadow: ui.online
                        ? const [BoxShadow(color: Color(0x6616A34A), blurRadius: 8)]
                        : null,
                  ),
                ),
                const SizedBox(width: 12),
                Text(
                  ui.etiquetaRed,
                  style: const TextStyle(color: Color(0xFFE2E8F0), fontWeight: FontWeight.w600, fontSize: 15),
                ),
              ],
            ),
          ),
          const Spacer(),
          Icon(
            ui.online ? Icons.cloud_done_outlined : Icons.cloud_off_outlined,
            size: 18,
            color: ui.online ? KioskColors.green : KioskColors.muted,
          ),
          const SizedBox(width: 8),
          Text(ui.etiquetaSync, style: const TextStyle(color: Color(0xFFCBD5E1), fontSize: 15, fontWeight: FontWeight.w500)),
          Container(width: 1, height: 22, margin: const EdgeInsets.symmetric(horizontal: 22), color: const Color(0x24FFFFFF)),
          Text(
            'Terminal ${AppConfig.terminalCodigo}  •  ${ui.etiquetaUltimaSync}  •  v2.4',
            style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 14, letterSpacing: 0.3),
          ),
        ],
      ),
    );
  }
}

class _ScreenHost extends StatelessWidget {
  const _ScreenHost({required this.controller});
  final KioskController controller;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: Colors.white,
      child: switch (controller.screen) {
        KioskScreen.cedula => CedulaScreen(controller: controller),
        KioskScreen.reconocer => ReconocerScreen(controller: controller),
        KioskScreen.accion => AccionScreen(controller: controller),
        KioskScreen.motivo => MotivoScreen(controller: controller),
        KioskScreen.mandado => MandadoScreen(controller: controller),
        KioskScreen.mandadoEmpleado => MandadoEmpleadoScreen(controller: controller),
        KioskScreen.permisos => PermisosScreen(controller: controller),
        KioskScreen.hora => HoraScreen(controller: controller),
        KioskScreen.camara => CameraScreen(
            etiqueta: controller.etiquetaFoto,
            onCaptured: controller.guardarFotoYMarcar,
            onCancel: controller.cancelarCamara,
          ),
        KioskScreen.regreso => RegresoScreen(controller: controller),
        KioskScreen.preguntar => PreguntarScreen(controller: controller),
        KioskScreen.confirmacion => ConfirmacionScreen(controller: controller),
        KioskScreen.admin => AdminScreen(controller: controller),
        KioskScreen.adminLog => AdminLogScreen(controller: controller),
        KioskScreen.novedadCedula => NovedadCedulaScreen(controller: controller),
        KioskScreen.novedadForm => NovedadFormScreen(controller: controller),
        KioskScreen.novedadQuien => NovedadQuienScreen(controller: controller),
        KioskScreen.novedadAutoriza => NovedadAutorizaEmpleadoScreen(controller: controller),
      },
    );
  }
}

class CedulaScreen extends StatefulWidget {
  const CedulaScreen({super.key, required this.controller});
  final KioskController controller;

  @override
  State<CedulaScreen> createState() => _CedulaScreenState();
}

class _CedulaScreenState extends State<CedulaScreen> {
  String cedula = '';
  Timer? _clock;
  DateTime now = DateTime.now();

  @override
  void initState() {
    super.initState();
    _clock = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() => now = DateTime.now());
    });
  }

  @override
  void dispose() {
    _clock?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final empty = cedula.isEmpty;
    return ColoredBox(
      color: Colors.white,
      child: Row(
        children: [
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(52, 32, 28, 28),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.center,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Center(
                    child: Image.asset('assets/logo.png', height: 192, filterQuality: FilterQuality.high),
                  ),
                  const SizedBox(height: 28),
                    const Text.rich(
                    TextSpan(
                      children: [
                        TextSpan(text: 'INGEER ', style: TextStyle(color: KioskColors.ink)),
                        TextSpan(text: 'BIOMETRIC', style: TextStyle(color: KioskColors.green)),
                      ],
                    ),
                    style: TextStyle(fontSize: 36, fontWeight: FontWeight.w800, height: 1.1, letterSpacing: -0.6),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Sistema de identificación biométrica',
                    style: TextStyle(fontSize: 28, color: KioskColors.muted),
                  ),
                  const SizedBox(height: 14),
                  Container(
                    width: 56,
                    height: 4,
                    decoration: BoxDecoration(color: KioskColors.green, borderRadius: BorderRadius.circular(4)),
                  ),
                  const SizedBox(height: 22),
                  Container(
                    width: 500,
                    padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 18),
                    decoration: BoxDecoration(
                      color: KioskColors.green.withValues(alpha: 0.05),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: KioskColors.green.withValues(alpha: 0.5), width: 1),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.start,
                      children: [
                        const Icon(Icons.calendar_today_outlined, size: 60, color: KioskColors.green),
                        const SizedBox(width: 20),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('FECHA', style: TextStyle(fontSize: 17, color: KioskColors.muted, fontWeight: FontWeight.w500)),
                            Text(DateFormat("d 'de' MMMM 'de' yyyy", 'es').format(now), style: TextStyle(fontSize: 30, color: KioskColors.muted, fontWeight: FontWeight.w500)),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 10),
                   Container(
                    width: 500,
                    padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 18),
                    decoration: BoxDecoration(
                      color: KioskColors.green.withValues(alpha: 0.05),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: KioskColors.green.withValues(alpha: 0.5), width: 1),
                    ),
                    child:
                    Row(
                      mainAxisAlignment: MainAxisAlignment.start,
                      children: [
                        const Icon(Icons.schedule, size: 60, color: KioskColors.green),
                        const SizedBox(width: 20),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('HORA ACTUAL', style: TextStyle(fontSize: 17, color: KioskColors.muted, fontWeight: FontWeight.w500)),
                            Text(HoraFmt.of(now, seconds: true), style: TextStyle(fontSize: 45, color: KioskColors.muted, fontWeight: FontWeight.w500)),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  const Spacer(),
                  if (widget.controller.error != null) AlertErr(widget.controller.error!),
                  if (widget.controller.homeNotice != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 14),
                      child: Text(widget.controller.homeNotice!, style: const TextStyle(fontSize: 17, color: KioskColors.muted)),
                    ),
                  const Spacer(),
                  SizedBox(
                    height: 94,
                    child: Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: widget.controller.abrirNovedad,
                            icon: const Icon(Icons.event_note_outlined, color: KioskColors.green, size: 32),
                            label: const Text(
                              'Registrar novedad',
                              style: TextStyle(fontSize: 24, fontWeight: FontWeight.w700, color: KioskColors.green),
                            ),
                            style: OutlinedButton.styleFrom(
                              side: const BorderSide(color: KioskColors.green, width: 2),
                              backgroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 28),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: widget.controller.syncUi.syncing ? null : widget.controller.sincronizarAhora,
                            icon: const Icon(Icons.sync, color: KioskColors.azul, size: 32),
                            label: Text(
                              widget.controller.syncUi.syncing ? 'Sincronizando…' : 'Sincronizar',
                              style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w700, color: KioskColors.azul),
                            ),
                            style: OutlinedButton.styleFrom(
                              side: const BorderSide(color: KioskColors.azul, width: 2),
                              backgroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 28),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(8, 28, 40, 28),
            child: Container(
              width: 680,
              padding: const EdgeInsets.fromLTRB(28, 24, 28, 24),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                boxShadow: const [
                  BoxShadow(color: Color(0x180F172A), blurRadius: 32, offset: Offset(0, 12)),
                ],
              ),
              child: Column(
                children: [
                  const SizedBox(height: 22),
                  const Row(
                    children: [
                      Icon(Icons.badge_outlined, color: KioskColors.green, size: 42),
                      SizedBox(width: 8),
                      Text('Número de identificación', style: TextStyle(fontSize: 35, fontWeight: FontWeight.w600, color: KioskColors.ink)),
                    ],
                  ),
                  const SizedBox(height: 12),
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
                            border: Border.all(
                              color: KioskColors.green,
                              width: 1.5,
                            ),
                          ),
                          child: Text(
                            empty
                                ? 'Ingresa tu número de identificación'
                                : cedula,
                            style: TextStyle(
                              fontSize: empty ? 26 : 36,
                              fontWeight: empty
                                  ? FontWeight.w400
                                  : FontWeight.w600,
                              color: empty
                                  ? KioskColors.faint
                                  : KioskColors.ink,
                              letterSpacing: empty ? 0 : 1.2,
                              fontFeatures: const [
                                FontFeature.tabularFigures(),
                              ],
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
                                cedula = cedula.substring(
                                  0,
                                  cedula.length - 1,
                                );
                              });
                            },
                            borderRadius: BorderRadius.circular(12),
                            child: DecoratedBox(
                              decoration: BoxDecoration(
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(
                                  color: KioskColors.amarillo,
                                ),
                              ),
                              child: Center(
                                child: Icon(
                                  Icons.backspace_outlined,
                                  color: KioskColors.amarillo,
                                  size: 28,
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 56),
                  Expanded(
                    child: KioskKeypad(
                      compact: true,
                      onAnyKey: widget.controller.limpiarError,
                      onDigit: (d) {
                        if (cedula.length >= 12) return;
                        setState(() => cedula += d);
                      },
                      onBack: () {
                        if (cedula.isEmpty) return;
                        setState(() => cedula = cedula.substring(0, cedula.length - 1));
                      },
                      onClear: () => setState(() => cedula = ''),
                      onOk: () => widget.controller.identificar(cedula),
                      okEnabled: cedula.length >= 5 && !widget.controller.busy,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class ReconocerScreen extends StatefulWidget {
  const ReconocerScreen({super.key, required this.controller});
  final KioskController controller;

  @override
  State<ReconocerScreen> createState() => _ReconocerScreenState();
}

class _ReconocerScreenState extends State<ReconocerScreen> {
  @override
  void initState() {
    super.initState();
    Future<void>.delayed(const Duration(seconds: 1), () {
      if (mounted) widget.controller.continuarReconocer();
    });
  }

  @override
  Widget build(BuildContext context) {
    final emp = widget.controller.empleado!;
    return Center(
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          EmployeePhoto(src: emp.foto),
          const SizedBox(width: 56),
          SizedBox(
            width: 460,
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                  decoration: BoxDecoration(
                    color: const Color(0xFFECFDF3),
                    border: Border.all(color: const Color(0xFFBBF7D0)),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: const Text('CÉDULA VERIFICADA', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF15803D), letterSpacing: 0.6)),
                ),
                const SizedBox(height: 20),
                Text(emp.nombre, style: const TextStyle(fontSize: 52, fontWeight: FontWeight.w600, color: KioskColors.ink, height: 1.05, letterSpacing: -1)),
                const SizedBox(height: 12),
                Text('${emp.cargo} \nIdentificación: ${emp.identificacion}', style: const TextStyle(fontSize: 22, color: KioskColors.muted)),
                const SizedBox(height: 34),
                const Row(
                  children: [
                    SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2)),
                    SizedBox(width: 14),
                    Text('Cargando opciones…', style: TextStyle(fontSize: 16, color: KioskColors.faint)),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class AccionScreen extends StatefulWidget {
  const AccionScreen({super.key, required this.controller});
  final KioskController controller;

  @override
  State<AccionScreen> createState() => _AccionScreenState();
}

class _AccionScreenState extends State<AccionScreen> {
  Timer? _clock;
  DateTime now = DateTime.now();

  @override
  void initState() {
    super.initState();
    _clock = Timer.periodic(const Duration(seconds: 1), (_) {
      final next = DateTime.now();
      final cambioMinuto = next.minute != now.minute;
      setState(() => now = next);
      if (cambioMinuto) widget.controller.refreshBotones();
    });
  }

  @override
  void dispose() {
    _clock?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = widget.controller;
    final slots = c.botones.where((b) => b.tipo != 'salida_ocasional').toList();
    final conHorario = slots.any((b) => b.campo != null);
    return Padding(
      padding: const EdgeInsets.fromLTRB(48, 28, 48, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Eyebrow('Hola, ${c.empleado?.nombre  ?? ''}', fontSize: 24),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('¿Qué vas a registrar?', style: TextStyle(fontSize: 54, fontWeight: FontWeight.w700, color: KioskColors.ink)),
                ElevatedButton.icon(
                  onPressed: c.reset,
                  icon: const Icon(
                    Icons.logout_outlined,
                    size: 32,
                    color: Color.fromARGB(255, 82, 82, 82),
                  ),
                  label: const Text(
                    'Cancelar y salir',
                    style: TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.w700,
                      color: Color.fromARGB(255, 82, 82, 82),
                    ),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color.fromARGB(255, 224, 224, 224),
                    elevation: 0,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 24,
                      vertical: 16,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
            ],
          ),
          if (c.error != null) ...[const SizedBox(height: 14), AlertErr(c.error!)],
          const Spacer(),
          _ActionGrid(botones: c.botones, conHorario: conHorario, onTap: c.elegir),
          const Spacer(),
        ],
      ),
    );
  }
}

class _ActionGrid extends StatelessWidget {
  const _ActionGrid({required this.botones, required this.conHorario, required this.onTap});
  final List<BotonJornada> botones;
  final bool conHorario;
  final void Function(BotonJornada) onTap;

  @override
  Widget build(BuildContext context) {
    final slots = botones.where((b) => b.tipo != 'salida_ocasional').toList();
    final occ = botones.where((b) => b.tipo == 'salida_ocasional');
    final filas = <List<BotonJornada>>[];
    if (slots.length <= 2) {
      filas.add(slots);
    } else {
      final mitad = (slots.length / 2).ceil();
      filas.add(slots.take(mitad).toList());
      filas.add(slots.skip(mitad).toList());
    }
    return Column(
      children: [
        for (var f = 0; f < filas.length; f++) ...[
          if (f > 0) const SizedBox(height: 14),
          Row(
            children: [
              for (var i = 0; i < filas[f].length; i++) ...[
                if (i > 0) const SizedBox(width: 14),
                Expanded(
                  child: _ActionCard(
                    boton: filas[f][i],
                    compact: conHorario,
                    onTap: () => onTap(filas[f][i]),
                  ),
                ),
              ],
            ],
          ),
        ],
        for (final b in occ) ...[
          const SizedBox(height: 24),
          _ActionCard(boton: b, compact: conHorario, wide: true, onTap: () => onTap(b)),
        ],
      ],
    );
  }
}

class _ActionCard extends StatelessWidget {
  const _ActionCard({required this.boton, required this.onTap, this.compact = false, this.wide = false});
  final BotonJornada boton;
  final VoidCallback onTap;
  final bool compact;
  final bool wide;

  @override
  Widget build(BuildContext context) {
    final occ = boton.clase == 'action-occ';
    final bg = switch (boton.clase) {
      'action-in' => KioskColors.green,
      'action-out' => KioskColors.red,
      _ => KioskColors.amberBg,
    };
    final fg = occ ? KioskColors.amberText : Colors.white;
    return Opacity(
      opacity: boton.enabled ? 1 : 0.4,
      child: Material(
        color: bg,
        elevation: boton.enabled ? 4 : 0,
        shadowColor: occ ? const Color(0x33D97706) : bg.withValues(alpha: 0.45),
        borderRadius: BorderRadius.circular(18),
        child: InkWell(
          onTap: boton.enabled ? onTap : null,
          borderRadius: BorderRadius.circular(18),
          child: Container(
            height: wide ? 116 : (compact ? 188 : 200),
            padding: const EdgeInsets.all(22),
            decoration: occ
                ? BoxDecoration(
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(color: KioskColors.amberBorder, width: 2),
                  )
                : null,
            child: wide
                ? Row(
                    children: [
                      Container(width: 20, height: 20, decoration: const BoxDecoration(color: KioskColors.amber, shape: BoxShape.circle)),
                      const SizedBox(width: 18),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(boton.label, style: TextStyle(fontSize: 26, fontWeight: FontWeight.w600, color: fg)),
                          Text(boton.sub, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 16, color: fg.withValues(alpha: 0.85))),
                        ],
                      ),
                    ],
                  )
                : Row(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // Primera columna: círculo centrado verticalmente
                      Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Container(
                            width: 20,
                            height: 20,
                            decoration: BoxDecoration(
                              color: fg.withValues(alpha: 0.5),
                              shape: BoxShape.circle,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(width: 16),
                      // Segunda columna: textos
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              boton.label,
                              style: TextStyle(
                                fontSize: compact ? 40 : 40,
                                fontWeight: FontWeight.w600,
                                color: fg,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              boton.sub,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                fontSize: 25,
                                color: fg.withValues(alpha: 0.9),
                              ),
                            ),
                            if (boton.nota != null)
                              Text(
                                boton.nota!,
                                style: TextStyle(
                                  fontSize: 20,
                                  color: fg,
                                ),
                              ),
                          ],
                        ),
                      ),
                    ],
                  )
          ),
        ),
      ),
    );
  }
}

class MotivoScreen extends StatelessWidget {
  const MotivoScreen({super.key, required this.controller});
  final KioskController controller;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(60, 48, 60, 40),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Eyebrow('Salida ocasional · paso 1', color: Color(0xFFB45309), fontSize: 18),
                  const SizedBox(height: 14),
                  const Text('Motivo de la salida', style: TextStyle(fontSize: 42, fontWeight: FontWeight.w600, color: KioskColors.ink)),
                  const SizedBox(height: 12),
                  const Text(
                    'Elige si sales con un permiso aprobado o registra otra salida e indica a qué hora regresas.',
                    style: TextStyle(fontSize: 24, color: KioskColors.muted),
                  ),
                ],
              ),
              ElevatedButton.icon(
                onPressed: controller.volverAccion,
                icon: const Icon(Icons.arrow_back_outlined, size: 32, color: KioskColors.red),
                label: const Text('Volver', style: TextStyle(fontSize: 24, fontWeight: FontWeight.w700, color: KioskColors.red)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color.fromARGB(255, 255, 231, 231),
                  elevation: 0,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 24,
                    vertical: 16,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  side: const BorderSide(color: KioskColors.red, width: 1),
                ),
              ),
            ],
          ),
          const Spacer(),
          Row(
            children: [
              Expanded(child: _ReasonCard(title: 'Permiso', sub: 'Usa un permiso aprobado de hoy', onTap: () => controller.elegirOrigen('permiso'))),
              const SizedBox(width: 20),
              Expanded(child: _ReasonCard(title: 'Diligencia empresarial', sub: 'Indica quién lo autorizó y la hora de regreso', onTap: () => controller.elegirOrigen('otro'))),
            ],
          ),
          const SizedBox(height: 30),
          const Spacer(),
        ],
      ),
    );
  }
}

class MandadoScreen extends StatelessWidget {
  const MandadoScreen({super.key, required this.controller});
  final KioskController controller;

  static const opciones = [
    ('Jefe inmediato', 'Tu jefe directo autorizó la salida'),
    ('Jefe de recursos Humanos', 'RRHH autorizó la salida'),
    ('Gerencia', 'Gerencia autorizó la salida'),
    ('Otro', 'Elige a la persona en la lista de empleados'),
  ];

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(60, 48, 60, 40),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Eyebrow('Salida ocasional · paso 2 de 4', color: Color(0xFFB45309), fontSize: 18),
                  const SizedBox(height: 14),
                  const Text('¿Quién lo autorizó?', style: TextStyle(fontSize: 42, fontWeight: FontWeight.w600, color: KioskColors.ink)),
                  const SizedBox(height: 12),
                  const Text(
                    'Indica quién autorizó esta diligencia empresarial.',
                    style: TextStyle(fontSize: 24, color: KioskColors.muted),
                  ),
                ],
              ),
              ElevatedButton.icon(
                onPressed: controller.volverMotivo,
                icon: const Icon(Icons.arrow_back_outlined, size: 32, color: KioskColors.red),
                label: const Text('Volver', style: TextStyle(fontSize: 24, fontWeight: FontWeight.w700, color: KioskColors.red)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color.fromARGB(255, 255, 231, 231),
                  elevation: 0,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 24,
                    vertical: 16,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  side: const BorderSide(color: KioskColors.red, width: 1),
                ),
              ),
            ],
          ),
          const Spacer(),
          Row(
            children: [
              Expanded(child: _ReasonCard(title: opciones[0].$1, sub: opciones[0].$2, height: 160, titleSize: 27, onTap: () => controller.elegirMandadoPor(opciones[0].$1))),
              const SizedBox(width: 20),
              Expanded(child: _ReasonCard(title: opciones[1].$1, sub: opciones[1].$2, height: 160, titleSize: 27, onTap: () => controller.elegirMandadoPor(opciones[1].$1))),
            ],
          ),
          const SizedBox(height: 20),
          Row(
            children: [
              Expanded(child: _ReasonCard(title: opciones[2].$1, sub: opciones[2].$2, height: 160, titleSize: 27, onTap: () => controller.elegirMandadoPor(opciones[2].$1))),
              const SizedBox(width: 20),
              Expanded(child: _ReasonCard(title: opciones[3].$1, sub: opciones[3].$2, height: 160, titleSize: 27, onTap: () => controller.elegirMandadoPor(opciones[3].$1))),
            ],
          ),
          const SizedBox(height: 30),
          const Spacer(),
        ],
      ),
    );
  }
}

class MandadoEmpleadoScreen extends StatefulWidget {
  const MandadoEmpleadoScreen({super.key, required this.controller});
  final KioskController controller;

  @override
  State<MandadoEmpleadoScreen> createState() => _MandadoEmpleadoScreenState();
}

class _MandadoEmpleadoScreenState extends State<MandadoEmpleadoScreen> {
  String filtro = '';

  @override
  Widget build(BuildContext context) {
    final yo = widget.controller.empleado?.id;
    final empleados = widget.controller.empleadosAdmin.where((e) {
      if (yo != null && e.id == yo) return false;
      if (filtro.isEmpty) return true;
      final q = filtro.toLowerCase();
      return e.nombre.toLowerCase().contains(q) || e.identificacion.contains(q);
    }).toList();

    return Padding(
      padding: const EdgeInsets.fromLTRB(60, 40, 60, 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Eyebrow('Salida ocasional · paso 2 de 4', color: Color(0xFFB45309), fontSize: 18),
                  const SizedBox(height: 14),
                  const Text('¿Quién lo autorizó?', style: TextStyle(fontSize: 42, fontWeight: FontWeight.w600, color: KioskColors.ink)),
                  const SizedBox(height: 10),
                  const Text(
                    'Selecciona al empleado que autorizó esta diligencia.',
                    style: TextStyle(fontSize: 24, color: KioskColors.muted),
                  ),
                ],
              ),
              ElevatedButton.icon(
                onPressed: widget.controller.volverMandado,
                icon: const Icon(Icons.arrow_back_outlined, size: 32, color: KioskColors.red),
                label: const Text('Volver', style: TextStyle(fontSize: 24, fontWeight: FontWeight.w700, color: KioskColors.red)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color.fromARGB(255, 255, 231, 231),
                  elevation: 0,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 24,
                    vertical: 16,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  side: const BorderSide(color: KioskColors.red, width: 1),
                ),
              ),
            ],
          ),
          const SizedBox(height: 22),
          TextField(
            onChanged: (v) => setState(() => filtro = v.trim()),
            style: const TextStyle(fontSize: 22),
            decoration: InputDecoration(
              hintText: 'Buscar por nombre o cédula',
              prefixIcon: const Icon(Icons.search, size: 28),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
            ),
          ),
          const SizedBox(height: 16),
          Expanded(
            child: empleados.isEmpty
                ? const Center(
                    child: Text(
                      'No hay empleados para mostrar. Sincroniza el kiosko o prueba otra búsqueda.',
                      style: TextStyle(fontSize: 20, color: KioskColors.muted),
                      textAlign: TextAlign.center,
                    ),
                  )
                : ListView.separated(
                    itemCount: empleados.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 10),
                    itemBuilder: (_, i) {
                      final e = empleados[i];
                      return Material(
                        color: const Color(0xFFF8FAFC),
                        borderRadius: BorderRadius.circular(16),
                        child: InkWell(
                          onTap: () => widget.controller.elegirMandadoEmpleado(e),
                          borderRadius: BorderRadius.circular(16),
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 20),
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(color: KioskColors.line),
                            ),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        e.nombre.isEmpty ? 'Empleado' : e.nombre,
                                        style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w600, color: KioskColors.ink),
                                      ),
                                      const SizedBox(height: 6),
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
        ],
      ),
    );
  }
}

class _ReasonCard extends StatelessWidget {
  const _ReasonCard({
    required this.title,
    required this.sub,
    required this.onTap,
    this.height = 170,
    this.titleSize = 36,
  });
  final String title;
  final String sub;
  final VoidCallback onTap;
  final double height;
  final double titleSize;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: const Color(0xFFF8FAFC),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          height: height,
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: KioskColors.line),
          ),
          child: Row(
            children: [
              Container(width: 16, height: 16, decoration: const BoxDecoration(color: KioskColors.amber, shape: BoxShape.circle)),
              const SizedBox(width: 20),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(fontSize: titleSize, fontWeight: FontWeight.w500, color: KioskColors.ink),
                    ),
                    const SizedBox(height: 8),
                    Text(sub, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 23, color: KioskColors.muted)),
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

class PermisosScreen extends StatelessWidget {
  const PermisosScreen({super.key, required this.controller});
  final KioskController controller;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(60, 48, 60, 40),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                   const Eyebrow('Salida ocasional · paso 2 de 3', color: Color(0xFFB45309), fontSize: 18),
                    const SizedBox(height: 14),
                    const Text('Tus permisos de hoy', style: TextStyle(fontSize: 42, fontWeight: FontWeight.w600, color: KioskColors.ink)),
                    const SizedBox(height: 10),
                    const Text(
                      'Selecciona el permiso con el que sales. El regreso esperado será la hora de fin del permiso.',
                      style: TextStyle(fontSize: 24, color: KioskColors.muted),
                    ),
                    if (controller.error != null) ...[
                      const SizedBox(height: 12),
                      SizedBox(width: 720, child: AlertErr(controller.error!)),
                    ],
                ],
              ),
              ElevatedButton.icon(
                onPressed: controller.volverMotivo,
                icon: const Icon(Icons.arrow_back_outlined, size: 32, color: KioskColors.red),
                label: const Text('Volver', style: TextStyle(fontSize: 24, fontWeight: FontWeight.w700, color: KioskColors.red)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color.fromARGB(255, 255, 231, 231),
                  elevation: 0,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 24,
                    vertical: 16,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  side: const BorderSide(color: KioskColors.red, width: 1),
                ),
              ),
            ],
          ),
          const Spacer(),
          if (controller.permisos.isEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(32),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFCBD5E1), style: BorderStyle.solid),
              ),
              child: const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('No tienes permisos activos hoy', style: TextStyle(fontSize: 26, fontWeight: FontWeight.w600, color: KioskColors.ink)),
                  SizedBox(height: 10),
                  Text('Si necesitas salir, vuelve y elige Diligencia empresarial para indicar la hora de regreso.', style: TextStyle(fontSize: 18, color: KioskColors.muted)),
                ],
              ),
            )
          else
            Expanded(
              child: ListView.separated(
                itemCount: controller.permisos.length,
                separatorBuilder: (_, _) => const SizedBox(height: 14),
                itemBuilder: (_, i) {
                  final p = controller.permisos[i];
                  return Material(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(16),
                    child: InkWell(
                      onTap: () => controller.elegirPermiso(p),
                      borderRadius: BorderRadius.circular(16),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 22),
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: KioskColors.line),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Icon(Icons.schedule, size: 42, color: Color(0xFFB45309)),
                                const SizedBox(width: 10),
                                Text('${HoraFmt.from(p.horaInicio)} – ${HoraFmt.from(p.horaFin)}', style: const TextStyle(fontSize: 27, fontWeight: FontWeight.w600, color: Color(0xFFB45309))),
                              ],
                            ),
                            const SizedBox(height: 5),
                            Text(p.motivo, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 27, fontWeight: FontWeight.w500, color: KioskColors.ink)),
                            const SizedBox(height: 5),
                            Text(
                              [if (p.rango.isNotEmpty) p.rango, 'Regreso esperado ${HoraFmt.from(p.horaFin)}'].join('   '),
                              style: const TextStyle(fontSize: 27, color: KioskColors.muted),
                            ),
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          const Spacer(),
        ],
      ),
    );
  }
}

class HoraScreen extends StatefulWidget {
  const HoraScreen({super.key, required this.controller});
  final KioskController controller;

  @override
  State<HoraScreen> createState() => _HoraScreenState();
}

class _HoraScreenState extends State<HoraScreen> {
  late DateTime selected;
  int pickerGen = 0;

  @override
  void initState() {
    super.initState();
    selected = _alMinuto(DateTime.now().add(const Duration(minutes: 30)));
  }

  DateTime get _minRegreso {
    final now = DateTime.now();
    final min = DateTime(now.year, now.month, now.day, now.hour, now.minute)
        .add(const Duration(minutes: 1));
    if (min.year != selected.year || min.month != selected.month || min.day != selected.day) {
      return DateTime(selected.year, selected.month, selected.day, 23, 59);
    }
    return DateTime(selected.year, selected.month, selected.day, min.hour, min.minute);
  }

  DateTime _alMinuto(DateTime dt) => DateTime(dt.year, dt.month, dt.day, dt.hour, dt.minute);

  String get digits => DateFormat('HHmm').format(selected);

  void addMins(int mins) {
    widget.controller.limpiarError();
    setState(() {
      selected = _alMinuto(DateTime.now().add(Duration(minutes: mins)));
      pickerGen++;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(56, 44, 56, 40),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('Salida ocasional · paso 3 de 4', color: Color(0xFFB45309), fontSize: 18),
          const SizedBox(height: 14),
          const Text('Hora de regreso esperada', style: TextStyle(fontSize: 42, fontWeight: FontWeight.w600, color: KioskColors.ink)),
          const SizedBox(height: 10),
          Text(
            [
              'Motivo: ${widget.controller.motivoTexto ?? 'Diligencia empresarial'}',
              if ((widget.controller.mandadoPor ?? '').isNotEmpty) 'Autorizado por: ${widget.controller.mandadoPor}',
              'Elige a qué hora esperas volver.',
            ].join('. '),
            style: const TextStyle(fontSize: 24, color: KioskColors.muted),
          ),
          if (widget.controller.error != null) ...[
            const SizedBox(height: 12),
            AlertErr(widget.controller.error!),
          ],
          const SizedBox(height: 22),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 18),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: KioskColors.line, width: 2),
            ),
            child: Row(
              children: [
                const Icon(Icons.schedule, size: 42, color: Color(0xFFB45309)),
                const SizedBox(width: 18),
                Text(
                  HoraFmt.of(selected),
                  style: const TextStyle(fontSize: 52, fontWeight: FontWeight.w600, color: KioskColors.ink, letterSpacing: 0.4),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Expanded(
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: KioskColors.line, width: 2),
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(16),
                child: MediaQuery(
                  data: MediaQuery.of(context).copyWith(alwaysUse24HourFormat: false),
                  child: CupertinoTheme(
                    data: const CupertinoThemeData(
                      textTheme: CupertinoTextThemeData(
                        dateTimePickerTextStyle: TextStyle(
                          fontSize: 30,
                          fontWeight: FontWeight.w600,
                          color: KioskColors.ink,
                        ),
                      ),
                    ),
                    child: CupertinoDatePicker(
                      key: ValueKey(pickerGen),
                      mode: CupertinoDatePickerMode.time,
                      use24hFormat: false,
                      initialDateTime: selected,
                      minimumDate: _minRegreso,
                      onDateTimeChanged: (dt) {
                        widget.controller.limpiarError();
                        setState(() => selected = _alMinuto(dt));
                      },
                    ),
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              _Quick('+30 min', () => addMins(30)),
              const SizedBox(width: 14),
              _Quick('+1 h', () => addMins(60)),
              const SizedBox(width: 14),
              _Quick('+2 h', () => addMins(120)),
            ],
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              ElevatedButton.icon(
                onPressed: widget.controller.volverHora,
                icon: const Icon(Icons.arrow_back_outlined, size: 32, color: KioskColors.red),
                label: const Text('Volver', style: TextStyle(fontSize: 24, fontWeight: FontWeight.w700, color: KioskColors.red)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color.fromARGB(255, 255, 231, 231),
                  elevation: 0,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 30,
                    vertical: 23,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  side: const BorderSide(color: KioskColors.red, width: 1),
                ),
              ),
              const SizedBox(width: 16),
              PrimaryButton(
                label: 'Registrar salida',
                onTap: () => widget.controller.guardarHora(digits),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Quick extends StatelessWidget {
  const _Quick(this.label, this.onTap);
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 68,
      child: OutlinedButton(
        onPressed: onTap,
        style: OutlinedButton.styleFrom(
          foregroundColor: const Color(0xFF334155),
          backgroundColor: const Color(0xFFF8FAFC),
          side: const BorderSide(color: KioskColors.line),
          padding: const EdgeInsets.symmetric(horizontal: 26),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          textStyle: const TextStyle(fontSize: 19, fontWeight: FontWeight.w500),
        ),
        child: Text(label),
      ),
    );
  }
}

class RegresoScreen extends StatelessWidget {
  const RegresoScreen({super.key, required this.controller});
  final KioskController controller;

  @override
  Widget build(BuildContext context) {
    final emp = controller.empleado!;
    final open = controller.openExit;
    return Center(
      child: SizedBox(
        width: 900,
        child: Row(
          children: [
            EmployeePhoto(src: emp.foto, small: true),
            const SizedBox(width: 48),
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                    decoration: BoxDecoration(
                      color: KioskColors.amberBg,
                      border: Border.all(color: const Color(0xFFFDE68A)),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: const Text('SALIDA OCASIONAL ABIERTA', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFFB45309), letterSpacing: 0.6)),
                  ),
                  const SizedBox(height: 20),
                  const Text('Tienes una salida ocasional abierta', style: TextStyle(fontSize: 40, fontWeight: FontWeight.w600, color: KioskColors.ink, height: 1.1)),
                  const SizedBox(height: 16),
                  Text.rich(
                    TextSpan(
                      style: const TextStyle(fontSize: 22, height: 1.6, color: Color(0xFF475569)),
                      children: [
                        TextSpan(text: open?.today == true ? 'Salida registrada hoy a las ' : 'Salida registrada el '),
                        if (open?.today != true) TextSpan(text: '${open?.date ?? ''} a las ', style: const TextStyle(fontWeight: FontWeight.w700, color: KioskColors.ink)),
                        TextSpan(text: open?.time ?? '', style: const TextStyle(fontWeight: FontWeight.w700, color: KioskColors.ink)),
                        if (open?.reason != null) ...[
                          const TextSpan(text: ' por '),
                          TextSpan(text: open!.reason, style: const TextStyle(fontWeight: FontWeight.w700, color: KioskColors.ink)),
                          const TextSpan(text: '.'),
                        ],
                        const TextSpan(text: ' Regreso esperado: '),
                        TextSpan(text: open?.back ?? '', style: const TextStyle(fontWeight: FontWeight.w700, color: KioskColors.ink)),
                        const TextSpan(text: '.'),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Al confirmar se cierra esta salida ocasional.',
                    style: TextStyle(fontSize: 22, height: 1.6, color: Color(0xFF475569)),
                  ),
                  const SizedBox(height: 34),
                  Row(
                    children: [
                      PrimaryButton(label: 'Cerrar salida', green: true, onTap: controller.confirmarRegreso),
                      const SizedBox(width: 16),
                      GhostButton(label: 'Cancelar', onTap: controller.reset, tall: true),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class PreguntarScreen extends StatelessWidget {
  const PreguntarScreen({super.key, required this.controller});
  final KioskController controller;

  @override
  Widget build(BuildContext context) {
    final emp = controller.empleado!;
    final cierre = controller.cierre;
    return Center(
      child: SizedBox(
        width: 900,
        child: Row(
          children: [
            EmployeePhoto(src: emp.foto, small: true),
            const SizedBox(width: 48),
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                    decoration: BoxDecoration(
                      color: const Color(0xFFECFDF3),
                      border: Border.all(color: const Color(0xFFBBF7D0)),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: const Text('SALIDA CERRADA', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF15803D), letterSpacing: 0.6)),
                  ),
                  const SizedBox(height: 20),
                  const Text('¿Deseas registrar entrada de jornada 2?', style: TextStyle(fontSize: 42, fontWeight: FontWeight.w600, color: KioskColors.ink)),
                  const SizedBox(height: 16),
                  Text(
                    'La salida ocasional quedó cerrada${cierre?.time != null ? ' a las ${cierre!.time}' : ''}. Si marcas entrada, se usará la hora actual.',
                    style: const TextStyle(fontSize: 22, height: 1.6, color: Color(0xFF475569)),
                  ),
                  const SizedBox(height: 34),
                  Row(
                    children: [
                      PrimaryButton(label: 'Sí, registrar', green: true, onTap: () => controller.decidirEntrada(true)),
                      const SizedBox(width: 16),
                      GhostButton(label: 'No, terminar', onTap: () => controller.decidirEntrada(false), tall: true),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class ConfirmacionScreen extends StatefulWidget {
  const ConfirmacionScreen({super.key, required this.controller});
  final KioskController controller;

  @override
  State<ConfirmacionScreen> createState() => _ConfirmacionScreenState();
}

class _ConfirmacionScreenState extends State<ConfirmacionScreen> {
  @override
  void initState() {
    super.initState();
    if (widget.controller.confirmDesdeNovedad) return;
    final varias = (widget.controller.confirm?.acciones.length ?? 0) > 1;
    Future<void>.delayed(Duration(seconds: varias ? 4 : 2), () {
      if (mounted && !widget.controller.confirmDesdeNovedad) {
        widget.controller.reset();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final c = widget.controller.confirm!;
    final emp = widget.controller.empleado!;
    final desdeNovedad = widget.controller.confirmDesdeNovedad;
    final esperaOk = !desdeNovedad && c.acciones.length > 1;
    return Center(
      child: SizedBox(
        width: 960,
        child: Row(
          children: [
            Icon(Icons.check_circle_outline, size: 160, color: c.color),
            const SizedBox(width: 48),
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Eyebrow('${emp.nombre} · ${DateFormat("EEEE d 'de' MMMM", 'es').format(DateTime.now())}'),
                  const SizedBox(height: 16),
                  Text(c.title, style: const TextStyle(fontSize: 46, fontWeight: FontWeight.w600, color: KioskColors.ink, height: 1.1)),
                  const SizedBox(height: 18),
                  Text(c.time, style: const TextStyle(fontSize: 96, fontWeight: FontWeight.w200, color: KioskColors.ink)),
                  const SizedBox(height: 20),
                  if (c.acciones.isNotEmpty) ...[
                    const Text(
                      'Se registró',
                      style: TextStyle(fontSize: 18, fontWeight: FontWeight.w600, color: Color(0xFF475569)),
                    ),
                    const SizedBox(height: 10),
                    ...c.acciones.map(
                      (accion) => Padding(
                        padding: const EdgeInsets.only(bottom: 6),
                        child: Text(
                          '· $accion',
                          style: const TextStyle(fontSize: 24, height: 1.3, color: KioskColors.ink),
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                  ],
                  Wrap(
                    spacing: 12,
                    runSpacing: 8,
                    children: [
                      _Pill(c.pillText, c.pillBg, c.pillFg),
                      if (c.meta != null) _Pill(c.meta!, const Color(0xFFF1F5F9), const Color(0xFF475569)),
                    ],
                  ),
                  const SizedBox(height: 24),
                  Text(
                    desdeNovedad
                        ? 'Novedad guardada en esta tablet. Se enviará a la NUBE cuando haya conexión.'
                        : 'Registro guardado en esta tablet. Se enviará a la NUBE cuando haya conexión.',
                    style: const TextStyle(fontSize: 18, color: KioskColors.faint),
                  ),
                  if (desdeNovedad) ...[
                    const SizedBox(height: 28),
                    ElevatedButton.icon(
                      iconAlignment: IconAlignment.end,
                      onPressed: () => widget.controller.continuarTrasNovedad(),
                      icon: const Icon(Icons.arrow_forward_outlined, color: Colors.white, size: 28),
                      label: const Text(
                        'Continuar',
                        style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: Colors.white),
                      ),
                      style: ElevatedButton.styleFrom(
                        minimumSize: const Size(800, 60),
                        backgroundColor: KioskColors.azul,
                        padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 18),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                    ),
                  ] else if (esperaOk) ...[
                    const SizedBox(height: 28),
                    ElevatedButton(
                      onPressed: () => widget.controller.reset(),
                      style: ElevatedButton.styleFrom(
                        minimumSize: const Size(800, 60),
                        backgroundColor: KioskColors.azul,
                        padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 18),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: const Text(
                        'OK, entendido',
                        style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: Colors.white),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill(this.text, this.bg, this.fg);
  final String text;
  final Color bg;
  final Color fg;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 56,
      padding: const EdgeInsets.symmetric(horizontal: 24),
      alignment: Alignment.center,
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
      child: Text(text, style: TextStyle(fontSize: 20, fontWeight: FontWeight.w600, color: fg)),
    );
  }
}
