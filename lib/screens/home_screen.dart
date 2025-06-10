import 'package:flutter/material.dart';
import '../models/user.dart';
import '../services/auth_service.dart';
import 'login_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final AuthService _authService = AuthService();
  User? _currentUser;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadUserData();
  }

  Future<void> _loadUserData() async {
    final user = await _authService.getCurrentUser();
    setState(() {
      _currentUser = user;
      _isLoading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Scora'),
        backgroundColor: const Color(0xFF3F51B5),
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () async {
              await _authService.logout();
              if (context.mounted) {
                Navigator.of(context).pushReplacement(
                  MaterialPageRoute(builder: (context) => const LoginScreen()),
                );
              }
            },
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _currentUser == null
          ? const Center(child: Text('Error loading user data'))
          : Center(
              child: Padding(
                padding: const EdgeInsets.all(24.0),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      _currentUser!.roleName == 'Student'
                          ? Icons.school
                          : _currentUser!.roleName == 'Teacher'
                          ? Icons.person_search
                          : Icons.admin_panel_settings,
                      size: 100,
                      color: const Color(0xFF4CAF50),
                    ),
                    const SizedBox(height: 24),
                    Text(
                      'Welcome, ${_currentUser!.fullName}!',
                      style: const TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF3F51B5),
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Role: ${_currentUser!.roleName}',
                      style: const TextStyle(fontSize: 18, color: Colors.grey),
                    ),
                    const SizedBox(height: 8),
                    if (_currentUser!.email != null)
                      Text(
                        'Email: ${_currentUser!.email}',
                        style: const TextStyle(
                          fontSize: 16,
                          color: Colors.grey,
                        ),
                      ),
                    const SizedBox(height: 24),
                    const Divider(),
                    const SizedBox(height: 24),
                    _buildDashboardForRole(),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _buildDashboardForRole() {
    if (_currentUser == null) return const SizedBox();

    switch (_currentUser!.roleName) {
      case 'Student':
        return _buildStudentDashboard();
      case 'Teacher':
        return _buildTeacherDashboard();
      case 'Admin':
        return _buildAdminDashboard();
      default:
        return const Text('Unknown role');
    }
  }

  Widget _buildStudentDashboard() {
    return Column(
      children: [
        const Text(
          'Student Dashboard',
          style: TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.bold,
            color: Color(0xFF3F51B5),
          ),
        ),
        const SizedBox(height: 24),
        _buildDashboardButton(
          icon: Icons.assignment,
          label: 'My Assessments',
          onTap: () {
            // TODO: Navigate to assessments screen
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Assessments feature coming soon!')),
            );
          },
        ),
        const SizedBox(height: 16),
        _buildDashboardButton(
          icon: Icons.school,
          label: 'My Lessons',
          onTap: () {
            // TODO: Navigate to lessons screen
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Lessons feature coming soon!')),
            );
          },
        ),
      ],
    );
  }

  Widget _buildTeacherDashboard() {
    return Column(
      children: [
        const Text(
          'Teacher Dashboard',
          style: TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.bold,
            color: Color(0xFF3F51B5),
          ),
        ),
        const SizedBox(height: 24),
        _buildDashboardButton(
          icon: Icons.assignment,
          label: 'Manage Assessments',
          onTap: () {
            // TODO: Navigate to manage assessments screen
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('Assessment management coming soon!'),
              ),
            );
          },
        ),
        const SizedBox(height: 16),
        _buildDashboardButton(
          icon: Icons.people,
          label: 'My Students',
          onTap: () {
            // TODO: Navigate to students screen
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Student management coming soon!')),
            );
          },
        ),
        const SizedBox(height: 16),
        _buildDashboardButton(
          icon: Icons.school,
          label: 'Manage Lessons',
          onTap: () {
            // TODO: Navigate to lessons screen
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Lesson management coming soon!')),
            );
          },
        ),
      ],
    );
  }

  Widget _buildAdminDashboard() {
    return Column(
      children: [
        const Text(
          'Admin Dashboard',
          style: TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.bold,
            color: Color(0xFF3F51B5),
          ),
        ),
        const SizedBox(height: 24),
        _buildDashboardButton(
          icon: Icons.people,
          label: 'Manage Users',
          onTap: () {
            // TODO: Navigate to users screen
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('User management coming soon!')),
            );
          },
        ),
        const SizedBox(height: 16),
        _buildDashboardButton(
          icon: Icons.settings,
          label: 'System Settings',
          onTap: () {
            // TODO: Navigate to settings screen
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('System settings coming soon!')),
            );
          },
        ),
      ],
    );
  }

  Widget _buildDashboardButton({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return SizedBox(
      width: double.infinity,
      child: ElevatedButton(
        style: ElevatedButton.styleFrom(
          foregroundColor: const Color(0xFF3F51B5),
          backgroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 24),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(8),
            side: const BorderSide(color: Color(0xFF3F51B5), width: 1),
          ),
          elevation: 0,
        ),
        onPressed: onTap,
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon),
            const SizedBox(width: 12),
            Text(label, style: const TextStyle(fontSize: 16)),
          ],
        ),
      ),
    );
  }
}
