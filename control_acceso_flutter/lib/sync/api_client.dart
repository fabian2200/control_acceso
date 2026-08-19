import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config.dart';
import '../data/db.dart';

class AccesoApi {
  Future<String> get baseUrl async {
    final stored = await AccesoDb.instance.setting('api_url');
    return (stored ?? AppConfig.defaultApiUrl).replaceAll(RegExp(r'/$'), '');
  }

  Future<Map<String, String>> _headers() async {
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ${AppConfig.apiToken}',
    };
  }

  Future<bool> health() async {
    try {
      final base = await baseUrl;
      final res = await http.get(Uri.parse('$base/api/health')).timeout(const Duration(seconds: 8));
      if (res.statusCode != 200) return false;
      final body = jsonDecode(res.body);
      return body is Map && body['ok'] == true;
    } catch (_) {
      return false;
    }
  }

  Future<Map<String, dynamic>> catalogo() async {
    final base = await baseUrl;
    final res = await http
        .get(Uri.parse('$base/api/sync/catalogo'), headers: await _headers())
        .timeout(const Duration(seconds: 120));
    if (res.statusCode != 200) {
      throw Exception('catalogo HTTP ${res.statusCode}');
    }
    final body = jsonDecode(res.body);
    if (body is! Map<String, dynamic> || body['ok'] != true) {
      throw Exception('catalogo inválido');
    }
    return body;
  }

  Future<Map<String, dynamic>> marcas(Map<String, dynamic> payload) async {
    final base = await baseUrl;
    final res = await http
        .post(
          Uri.parse('$base/api/sync/marcas'),
          headers: await _headers(),
          body: jsonEncode(payload),
        )
        .timeout(const Duration(seconds: 60));
    if (res.statusCode != 200) {
      throw Exception('marcas HTTP ${res.statusCode}');
    }
    final body = jsonDecode(res.body);
    if (body is! Map<String, dynamic>) {
      throw Exception('marcas inválido');
    }
    return body;
  }

  Future<Map<String, dynamic>> logsMes({required int empleadoId, required int anio, required int mes}) async {
    final base = await baseUrl;
    final uri = Uri.parse('$base/api/sync/logs').replace(queryParameters: {
      'empleado_id': '$empleadoId',
      'anio': '$anio',
      'mes': '$mes',
    });
    final res = await http.get(uri, headers: await _headers()).timeout(const Duration(seconds: 30));
    if (res.statusCode != 200) {
      throw Exception('logs HTTP ${res.statusCode}');
    }
    final body = jsonDecode(res.body);
    if (body is! Map<String, dynamic> || body['ok'] != true) {
      throw Exception('logs inválido');
    }
    return body;
  }
}
