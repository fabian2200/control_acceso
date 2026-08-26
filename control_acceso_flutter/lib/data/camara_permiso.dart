import 'package:camera/camera.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

import 'db.dart';

/// Pide el permiso de cámara la primera vez que arranca la app (diálogo del sistema).
Future<void> pedirPermisoCamaraAlInicio() async {
  if (kIsWeb) return;
  final ya = await AccesoDb.instance.setting('camara_permiso_pedido');
  if (ya == '1') return;
  try {
    final cams = await availableCameras();
    if (cams.isNotEmpty) {
      final cam = CameraController(
        cams.first,
        ResolutionPreset.low,
        enableAudio: false,
      );
      await cam.initialize();
      await cam.dispose();
    }
  } on CameraException catch (_) {
    // Denegado o sin cámara: CameraScreen mostrará el mensaje al marcar.
  } catch (_) {}
  await AccesoDb.instance.setSetting('camara_permiso_pedido', '1');
}
