/// Horas para pantalla (`08:00 AM`). En BD se guarda 24 h (`08:00` / `08:00:00`).
class HoraFmt {
  HoraFmt._();

  static String of(DateTime dt, {bool seconds = false}) {
    final h12 = dt.hour % 12 == 0 ? 12 : dt.hour % 12;
    final ampm = dt.hour < 12 ? 'AM' : 'PM';
    final hm =
        '${h12.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
    if (!seconds) return '$hm $ampm';
    return '$hm:${dt.second.toString().padLeft(2, '0')} $ampm';
  }

  /// Acepta `HH:mm`, `HH:mm:ss`, `HHmm` o un timestamp `yyyy-MM-dd HH:mm:ss`.
  static String from(Object? value, {String empty = ''}) {
    if (value == null) return empty;
    final s = value.toString().trim();
    if (s.isEmpty) return empty;
    if (s == '--:--') return s;
    final dt = parse(s);
    return dt == null ? empty : of(dt);
  }

  static DateTime? parse(Object? value) {
    if (value == null) return null;
    if (value is DateTime) return value;
    final s = value.toString().trim();
    if (s.isEmpty || s == '--:--') return null;

    if (s.contains('-') || s.contains('T') || (s.contains(' ') && RegExp(r'\d{4}').hasMatch(s))) {
      return DateTime.tryParse(s.replaceFirst(' ', 'T'));
    }

    final digits = s.replaceAll(RegExp(r'\D'), '');
    if (digits.length < 3) return null;
    final padded = digits.length <= 2
        ? '${digits.padLeft(2, '0')}00'
        : digits.length == 3
            ? '0$digits'
            : digits;
    final h = int.tryParse(padded.substring(0, 2));
    final m = int.tryParse(padded.substring(2, 4));
    if (h == null || m == null) return null;
    return DateTime(2000, 1, 1, h.clamp(0, 23), m.clamp(0, 59));
  }
}
