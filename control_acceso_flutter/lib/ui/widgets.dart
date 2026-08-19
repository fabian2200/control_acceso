import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import 'theme.dart';

class KioskHeader extends StatefulWidget {
  const KioskHeader({super.key});

  @override
  State<KioskHeader> createState() => _KioskHeaderState();
}

class _KioskHeaderState extends State<KioskHeader> {
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
    return Container(
      height: 80,
      color: Colors.white,
      padding: const EdgeInsets.symmetric(horizontal: 28),
      child: Row(
        children: [
          Image.asset('assets/logo.png', height: 44, filterQuality: FilterQuality.high),
          const Spacer(),
          Icon(Icons.calendar_today_outlined, size: 18, color: KioskColors.muted),
          const SizedBox(width: 8),
          Text(
            DateFormat("d 'de' MMMM 'de' yyyy", 'es').format(now),
            style: const TextStyle(fontSize: 16, color: KioskColors.muted, fontWeight: FontWeight.w500),
          ),
          const SizedBox(width: 28),
          const Icon(Icons.schedule, size: 26, color: KioskColors.green),
          const SizedBox(width: 8),
          Text(
            DateFormat('hh:mm:ss a').format(now).toUpperCase(),
            style: const TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.w700,
              color: KioskColors.green,
              letterSpacing: 0.4,
              fontFeatures: [FontFeature.tabularFigures()],
            ),
          ),
        ],
      ),
    );
  }
}

class KioskKeypad extends StatelessWidget {
  const KioskKeypad({
    super.key,
    required this.onDigit,
    required this.onBack,
    this.onOk,
    this.compact = false,
    this.okEnabled = true,
    this.okLabel = 'Continuar',
  });

  final void Function(String digit) onDigit;
  final VoidCallback onBack;
  final VoidCallback? onOk;
  final bool compact;
  final bool okEnabled;
  final String okLabel;

  @override
  Widget build(BuildContext context) {
    final h = compact ? 99.0 : 105.0;
    final gap = compact ? 10.0 : 12.0;

    Widget key({
      required Widget child,
      VoidCallback? onTap,
      Color? color,
      bool elevated = true,
    }) {
      return SizedBox(
        height: h,
        child: Material(
          color: color ?? Colors.white,
          elevation: elevated ? 1 : 0,
          shadowColor: const Color(0x220F172A),
          borderRadius: BorderRadius.circular(12),
          child: InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(12),
            child: DecoratedBox(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: color == null ? KioskColors.line : Colors.transparent),
              ),
              child: Center(child: child),
            ),
          ),
        ),
      );
    }

    Widget digit(String label) {
      return key(
        onTap: () => onDigit(label),
        child: Text(
          label,
          style: const TextStyle(fontSize: 47, fontWeight: FontWeight.w500, color: KioskColors.ink),
        ),
      );
    }

    Widget row(List<Widget> children) {
      return Row(
        children: [
          for (var i = 0; i < children.length; i++) ...[
            if (i > 0) SizedBox(width: gap),
            Expanded(child: children[i]),
          ],
        ],
      );
    }

    const digits = ['1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return Column(
      children: [
        for (var r = 0; r < 3; r++) ...[
          if (r > 0) SizedBox(height: gap),
          row([for (var c = 0; c < 3; c++) digit(digits[r * 3 + c])]),
        ],
        SizedBox(height: gap),
        row([
          key(
            onTap: onBack,
            color: KioskColors.green,
            elevated: false,
            child: const Icon(Icons.backspace_outlined, color: Colors.white, size: 28),
          ),
          digit('0'),
          const SizedBox.shrink(),
        ]),
        if (onOk != null) ...[
          SizedBox(height: gap + 4),
          SizedBox(
            height: 72,
            width: double.infinity,
            child: FilledButton(
              onPressed: okEnabled ? onOk : null,
              style: FilledButton.styleFrom(
                backgroundColor: KioskColors.green,
                disabledBackgroundColor: const Color(0xFF86EFAC),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                textStyle: const TextStyle(fontSize: 24, fontWeight: FontWeight.w700),
              ),
              child: Text(okLabel),
            ),
          ),
        ],
      ],
    );
  }
}

class GhostButton extends StatelessWidget {
  const GhostButton({super.key, required this.label, required this.onTap, this.tall = false});

  final String label;
  final VoidCallback onTap;
  final bool tall;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: tall ? 76 : 64,
      child: OutlinedButton(
        onPressed: onTap,
        style: OutlinedButton.styleFrom(
          foregroundColor: KioskColors.muted,
          side: const BorderSide(color: KioskColors.line),
          padding: EdgeInsets.symmetric(horizontal: tall ? 30 : 28),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          textStyle: TextStyle(fontSize: tall ? 20 : 19, fontWeight: FontWeight.w500),
        ),
        child: Text(label),
      ),
    );
  }
}

class PrimaryButton extends StatelessWidget {
  const PrimaryButton({
    super.key,
    required this.label,
    required this.onTap,
    this.green = false,
    this.enabled = true,
  });

  final String label;
  final VoidCallback? onTap;
  final bool green;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    final color = green ? KioskColors.green : KioskColors.blue;
    return SizedBox(
      height: 80,
      child: FilledButton(
        onPressed: enabled ? onTap : null,
        style: FilledButton.styleFrom(
          backgroundColor: color,
          disabledBackgroundColor: color.withValues(alpha: 0.45),
          padding: const EdgeInsets.symmetric(horizontal: 42),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          textStyle: const TextStyle(fontSize: 24, fontWeight: FontWeight.w600),
        ),
        child: Text(label),
      ),
    );
  }
}

class EmployeePhoto extends StatelessWidget {
  const EmployeePhoto({super.key, this.src, this.small = false});

  final String? src;
  final bool small;

  @override
  Widget build(BuildContext context) {
    final w = small ? 200.0 : 260.0;
    final h = small ? 250.0 : 320.0;
    Widget child = const Center(child: Icon(Icons.person, size: 72, color: Color(0xFFCBD5E1)));
    final value = src?.trim();
    if (value != null && value.isNotEmpty) {
      if (value.startsWith('data:image')) {
        final b64 = value.split(',').last;
        try {
          child = Image.memory(base64Decode(b64), fit: BoxFit.cover, width: w, height: h);
        } catch (_) {}
      } else if (value.startsWith('http')) {
        child = Image.network(value, fit: BoxFit.cover, width: w, height: h, errorBuilder: (_, _, _) => child);
      }
    }
    return Container(
      width: w,
      height: h,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        boxShadow: const [BoxShadow(color: Color(0x1A0F172A), blurRadius: 30, offset: Offset(0, 12))],
        color: const Color(0xFFEEF2F7),
      ),
      clipBehavior: Clip.antiAlias,
      child: child,
    );
  }
}

class Eyebrow extends StatelessWidget {
  const Eyebrow(this.text, {super.key, this.color = KioskColors.faint});
  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Text(
      text.toUpperCase(),
      style: TextStyle(
        fontFamily: 'monospace',
        fontSize: 12,
        fontWeight: FontWeight.w600,
        letterSpacing: 1.8,
        color: color,
      ),
    );
  }
}

class AlertErr extends StatelessWidget {
  const AlertErr(this.message, {super.key});
  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 18),
      decoration: BoxDecoration(
        color: const Color(0xFFFEF2F2),
        border: Border.all(color: const Color(0xFFFECACA)),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          const CircleAvatar(radius: 5, backgroundColor: KioskColors.red),
          const SizedBox(width: 12),
          Expanded(
            child: Text(message, style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w600, color: KioskColors.red)),
          ),
        ],
      ),
    );
  }
}
