import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

const _canal = MethodChannel('control_acceso/kiosk');

/// Ancla la app (lock task / screen pinning) para que no se abra el menú de Android.
Future<void> anclarKiosko() async {
  if (kIsWeb || !Platform.isAndroid) return;
  try {
    await _canal.invokeMethod<void>('anclar');
  } catch (e) {
    debugPrint('anclarKiosko: $e');
  }
}
