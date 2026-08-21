import 'package:flutter/foundation.dart';

class AppConfig {
  AppConfig._();

  static const url = 'https://ingeer.co/nube/public';
  static const urlLocal = 'http://192.168.1.66:8001';

  static const defaultApiUrl = String.fromEnvironment(
    'ACCESO_API_URL',
    defaultValue: urlLocal,
  );

  static const apiToken = String.fromEnvironment(
    'ACCESO_API_TOKEN',
    defaultValue: 'e329d6926b529b6fa6133580c19b3382fcf9d4bbda240850cceeba61058ed3ac',
  );
  static const terminalCodigo = String.fromEnvironment(
    'ACCESO_TERMINAL',
    defaultValue: 'REC-01',
  );
  static const adminPin = String.fromEnvironment(
    'ACCESO_ADMIN_PIN',
    defaultValue: '1234567876',
  );
  static const ubicacion = String.fromEnvironment(
    'ACCESO_UBICACION',
    defaultValue: 'Recepción · INGEER S.A.S.',
  );

  static bool get isEmulatorDefault =>
      defaultApiUrl.contains('10.0.2.2') || defaultApiUrl.contains('192.168.1.66') || defaultApiUrl.contains('localhost');

  static void logBoot() {
    debugPrint('API $defaultApiUrl terminal $terminalCodigo');
  }
}
