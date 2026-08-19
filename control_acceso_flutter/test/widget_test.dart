import 'package:flutter_test/flutter_test.dart';

import 'package:control_acceso_flutter/config.dart';

void main() {
  test('terminal por defecto', () {
    expect(AppConfig.terminalCodigo, 'REC-01');
  });
}
