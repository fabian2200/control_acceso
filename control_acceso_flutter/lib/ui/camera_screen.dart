import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../data/foto.dart';
import 'widgets.dart';

class CameraScreen extends StatefulWidget {
  const CameraScreen({
    super.key,
    required this.etiqueta,
    required this.onCaptured,
    required this.onCancel,
  });

  final String etiqueta;
  final Future<void> Function(String dataUrl) onCaptured;
  final VoidCallback onCancel;

  @override
  State<CameraScreen> createState() => _CameraScreenState();
}

class _CameraScreenState extends State<CameraScreen> {
  CameraController? _cam;
  String? _error;
  bool _capturando = false;

  @override
  void initState() {
    super.initState();
    _abrir();
  }

  Future<void> _abrir() async {
    await _cam?.dispose();
    if (mounted) {
      setState(() {
        _cam = null;
        _capturando = false;
      });
    }
    try {
      final cams = await availableCameras();
      if (cams.isEmpty) {
        if (mounted) setState(() => _error = 'No hay cámara en este dispositivo.');
        return;
      }
      final frontal = cams.where((c) => c.lensDirection == CameraLensDirection.front);
      final desc = frontal.isNotEmpty ? frontal.first : cams.first;
      final cam = CameraController(
        desc,
        ResolutionPreset.medium,
        enableAudio: false,
        imageFormatGroup: ImageFormatGroup.jpeg,
      );
      await cam.initialize();
      await cam.lockCaptureOrientation(DeviceOrientation.landscapeLeft);
      if (!mounted) {
        await cam.dispose();
        return;
      }
      setState(() => _cam = cam);
      await Future<void>.delayed(const Duration(seconds: 1, milliseconds: 200));
      if (!mounted || _cam == null) return;
      await _capturar();
    } on CameraException catch (e) {
      if (mounted) {
        setState(() => _error = e.code == 'CameraAccessDenied'
            ? 'Activa el permiso de cámara para registrar la foto.'
            : 'No se pudo abrir la cámara.');
      }
    } catch (_) {
      if (mounted) setState(() => _error = 'No se pudo abrir la cámara.');
    }
  }

  Future<void> _capturar() async {
    final cam = _cam;
    if (cam == null || !cam.value.isInitialized || _capturando) return;
    setState(() {
      _capturando = true;
    });
    try {
      final shot = await cam.takePicture();
      final jpg = FotoKiosko.aJpgDataUrl(
        await shot.readAsBytes(),
        sensorOrientation: cam.description.sensorOrientation,
        deviceOrientation: cam.value.deviceOrientation,
        frontal: cam.description.lensDirection == CameraLensDirection.front,
      );
      if (!mounted) return;
      if (jpg == null) {
        setState(() {
          _error = 'No se pudo procesar la foto.';
          _capturando = false;
        });
        return;
      }
      await widget.onCaptured(jpg);
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = 'No se pudo tomar la foto. Intenta de nuevo.';
          _capturando = false;
        });
      }
    }
  }

  @override
  void dispose() {
    _cam?.dispose();
    super.dispose();
  }

  String get _etiqueta => widget.etiqueta;

  @override
  Widget build(BuildContext context) {
    final cam = _cam;
    final landscape = MediaQuery.orientationOf(context) == Orientation.landscape;
    return ColoredBox(
      color: const Color(0xFF0F1115),
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (cam != null && cam.value.isInitialized)
            _Preview(cam: cam, landscape: landscape),
          Positioned(
            top: 38,
            left: 44,
            child: Row(
              children: [
                Container(
                  width: 12,
                  height: 12,
                  decoration: const BoxDecoration(color: Color(0xFFDC2626), shape: BoxShape.circle),
                ),
                const SizedBox(width: 12),
                Text(
                  _etiqueta.toUpperCase(),
                  style: const TextStyle(
                    fontFamily: 'monospace',
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 2.2,
                    color: Color(0xFFE2E8F0),
                  ),
                ),
              ],
            ),
          ),
          Center(
            child: Container(
              width: landscape ? 520 : 360,
              height: landscape ? 360 : 440,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.all(
                  Radius.elliptical(landscape ? 260 : 180, landscape ? 180 : 220),
                ),
                border: Border.all(color: const Color(0x59FFFFFF), width: 3),
              ),
            ),
          ),
          if (_error != null)
            Center(
              child: Container(
                width: 520,
                padding: const EdgeInsets.all(32),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(18)),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    AlertErr(_error!),
                    const SizedBox(height: 22),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        GhostButton(label: 'Cancelar', onTap: widget.onCancel, tall: true),
                        const SizedBox(width: 16),
                        PrimaryButton(
                          label: 'Reintentar',
                          onTap: () {
                            setState(() {
                              _error = null;
                              _capturando = false;
                            });
                            _abrir();
                          },
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          Positioned(
            bottom: 36,
            left: 0,
            right: 0,
            child: Column(
              children: [
                Text(
                  _capturando ? 'Guardando foto 300x300…' : 'Mira a la cámara',
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontSize: 22, color: Color(0xB8FFFFFF)),
                ),
                const SizedBox(height: 16),
                GhostButton(label: 'Cancelar', onTap: widget.onCancel),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Preview extends StatelessWidget {
  const _Preview({required this.cam, required this.landscape});

  final CameraController cam;
  final bool landscape;

  @override
  Widget build(BuildContext context) {
    final preview = cam.value.previewSize;
    if (preview == null) return CameraPreview(cam);
    final width = landscape ? preview.width : preview.height;
    final height = landscape ? preview.height : preview.width;
    return ClipRect(
      child: FittedBox(
        fit: BoxFit.cover,
        child: SizedBox(
          width: width,
          height: height,
          child: CameraPreview(cam),
        ),
      ),
    );
  }
}
