import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models/user.dart';
import '../config/app_config.dart';

class AuthService {
  Future<User> login(String username, String password) async {
    final response = await http.post(
      Uri.parse('${AppConfig.apiUrl}/auth.php'),
      headers: <String, String>{
        'Content-Type': 'application/json; charset=UTF-8',
      },
      body: jsonEncode(<String, dynamic>{
        'action': 'login',
        'username': username,
        'password': password,
      }),
    );

    if (response.statusCode == 200) {
      final Map<String, dynamic> data = jsonDecode(response.body);

      if (data.containsKey('user')) {
        final user = User.fromJson(data['user']);
        // Save user data in SharedPreferences
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('user', jsonEncode(user.toJson()));
        return user;
      } else {
        throw Exception(data['message'] ?? 'Login failed');
      }
    } else {
      throw Exception('Failed to login. Please try again.');
    }
  }

  Future<Map<String, dynamic>> register({
    required String username,
    required String password,
    required String fullName,
    required String roleName,
    required String email,
    required Map<String, dynamic> additionalData,
  }) async {
    final Map<String, dynamic> requestBody = {
      'action': 'register',
      'username': username,
      'password': password,
      'full_name': fullName,
      'role_name': roleName,
      'email': email,
      // Add all additional data
      ...additionalData,
    };

    final response = await http.post(
      Uri.parse('${AppConfig.apiUrl}/auth.php'),
      headers: <String, String>{
        'Content-Type': 'application/json; charset=UTF-8',
      },
      body: jsonEncode(requestBody),
    );

    if (response.statusCode == 200) {
      final Map<String, dynamic> data = jsonDecode(response.body);

      if (data.containsKey('user_id')) {
        return data;
      } else {
        throw Exception(data['message'] ?? 'Registration failed');
      }
    } else {
      throw Exception('Failed to register. Please try again.');
    }
  }

  Future<User?> getCurrentUser() async {
    final prefs = await SharedPreferences.getInstance();
    final userData = prefs.getString('user');

    if (userData != null) {
      return User.fromJson(jsonDecode(userData));
    }
    return null;
  }

  Future<bool> isLoggedIn() async {
    final user = await getCurrentUser();
    return user != null;
  }

  Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('user');
  }

  Future<List<Map<String, dynamic>>> getTeachers() async {
    try {
      final response = await http.get(
        Uri.parse('${AppConfig.apiUrl}/auth.php?action=get_teachers'),
        headers: <String, String>{
          'Content-Type': 'application/json; charset=UTF-8',
        },
      );

      if (response.statusCode == 200) {
        return List<Map<String, dynamic>>.from(jsonDecode(response.body));
      } else {
        throw Exception('Failed to load teachers');
      }
    } catch (e) {
      throw Exception('Error fetching teachers: $e');
    }
  }
}
