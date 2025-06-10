class User {
  final int userId;
  final String? username;
  final String fullName;
  final String? email;
  final String roleName;
  final int? specificId; // teacher_id or student_id
  final String? subjectInfo; // for teachers
  final String? gradeLevel; // for students

  User({
    required this.userId,
    this.username,
    required this.fullName,
    this.email,
    required this.roleName,
    this.specificId,
    this.subjectInfo,
    this.gradeLevel,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      userId: json['user_id'],
      username: json['username'],
      fullName: json['full_name'] ?? '',
      email: json['email'],
      roleName: json['role_name'] ?? '',
      specificId: json['specific_id'],
      subjectInfo: json['subject_info'],
      gradeLevel: json['grade_level'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'user_id': userId,
      'username': username,
      'full_name': fullName,
      'email': email,
      'role_name': roleName,
      'specific_id': specificId,
      'subject_info': subjectInfo,
      'grade_level': gradeLevel,
    };
  }
}
