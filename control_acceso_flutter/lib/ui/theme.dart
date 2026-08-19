import 'package:flutter/material.dart';

class KioskColors {
  static const page = Color(0xFF0F1115);
  static const frameTop = Color(0xFF1C2027);
  static const frameBottom = Color(0xFF12151A);
  static const screen = Color(0xFFF7F8FA);
  static const ink = Color(0xFF0F172A);
  static const muted = Color(0xFF64748B);
  static const faint = Color(0xFF94A3B8);
  static const line = Color(0xFFE2E8F0);
  static const bar = Color(0xFF0B1F3A);
  static const green = Color(0xFF16A34A);
  static const greenDark = Color(0xFF15803D);
  static const blue = Color(0xFF1D4ED8);
  static const amber = Color(0xFFD97706);
  static const amberText = Color(0xFF92400E);
  static const amberBg = Color(0xFFFFFBEB);
  static const amberBorder = Color(0xFFFCD34D);
  static const red = Color(0xFFDC2626);
  static const amarillo = Color(0xFFeca200);
  static const azul = Color(0xFF018EB2);
}

ThemeData kioskTheme() {
  return ThemeData(
    useMaterial3: true,
    fontFamily: 'Roboto',
    scaffoldBackgroundColor: KioskColors.page,
    colorScheme: ColorScheme.fromSeed(seedColor: KioskColors.green),
  );
}
