import 'dart:convert';

import 'package:flutter/services.dart';
import 'package:image/image.dart' as img;

class FotoKiosko {
  FotoKiosko._();

  static const lado = 300;
  static const calidad = 70;

  static String? aJpgDataUrl(
    Uint8List bytes, {
    required int sensorOrientation,
    required DeviceOrientation deviceOrientation,
    required bool frontal,
  }) {
    var decoded = img.decodeImage(bytes);
    if (decoded == null) return null;

    final exif = decoded.exif.imageIfd.orientation ?? decoded.exif.exifIfd.orientation ?? 1;
    if (exif != 1) {
      decoded = img.bakeOrientation(decoded);
    } else {
      final grados = _grados(sensorOrientation, deviceOrientation, frontal);
      if (grados != 0) {
        decoded = img.copyRotate(decoded, angle: grados);
      }
    }

    final cuadrada = img.copyResizeCropSquare(decoded, size: lado);
    final jpg = img.encodeJpg(cuadrada, quality: calidad);
    return 'data:image/jpeg;base64,${base64Encode(jpg)}';
  }

  static int _grados(int sensor, DeviceOrientation device, bool frontal) {
    final dispositivo = switch (device) {
      DeviceOrientation.portraitUp => 0,
      DeviceOrientation.landscapeLeft => 90,
      DeviceOrientation.portraitDown => 180,
      DeviceOrientation.landscapeRight => 270,
    };
    if (frontal) {
      return (sensor + dispositivo) % 360;
    }
    return (sensor - dispositivo + 360) % 360;
  }

  static Map<String, String>? payload(Object? stored) {
    final value = stored as String?;
    if (value == null || value.isEmpty) return null;
    if (!value.startsWith('data:image') || !value.contains(',')) return null;
    return {
      'contenido': value.split(',').last,
      'ext': 'jpg',
    };
  }
}
