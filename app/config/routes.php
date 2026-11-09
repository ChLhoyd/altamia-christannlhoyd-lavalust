<?php

// Existing PHP website routes.
$router->get('/', 'Auth::index');
$router->get('/login', 'Auth::index');
$router->post('/login', 'Auth::login');
$router->get('/logout', 'Auth::logout');
$router->get('/teacher/dashboard', 'Teacher::index');
$router->get('/student/dashboard', 'Student::index');
$router->get('teacher/students', 'Teacher/students');

// React + LavaLust authentication and profile.
$router->post('/api/login', 'Auth::api_login');
$router->get('/api/me', 'Auth::api_me');
$router->post('/api/logout', 'Auth::api_logout');
$router->post('/api/refresh', 'Auth::api_refresh');
$router->post('/api/profile', 'Auth::api_update_profile');

// Existing attendance functionality.
$router->get('/api/teacher/dashboard', 'AttendanceApiController::teacher_dashboard');
$router->post('/api/teacher/attendance', 'AttendanceApiController::save_attendance');
$router->get('/api/student/dashboard', 'AttendanceApiController::student_dashboard');
$router->post('/api/student/manual-attendance', 'AttendanceApiController::student_manual_attendance');

// Student absence letters.
$router->get('/api/student/appeals', 'AccountsApiController::student_appeals');
$router->post('/api/student/appeals', 'AccountsApiController::submit_appeal');

// Teacher absence-letter review.
$router->get('/api/teacher/appeals', 'AccountsApiController::teacher_appeals');
$router->post('/api/teacher/appeals/review', 'AccountsApiController::review_appeal');

// Secure download of an uploaded absence letter.
$router->get('/api/appeal-letter', 'AccountsApiController::appeal_letter');

// Teacher Settings: create accounts, list students, delete student accounts.
$router->get('/api/teacher/accounts', 'AccountsApiController::teacher_accounts');
$router->post('/api/teacher/accounts', 'AccountsApiController::create_account');
$router->post('/api/teacher/accounts/delete', 'AccountsApiController::delete_student_account');

// Student Settings: delete own account.
$router->post('/api/account/delete', 'AccountsApiController::delete_own_student_account');

// Teacher student notes.
$router->get('/api/teacher/notes', 'TeacherNotesApiController::index');
$router->post('/api/teacher/notes', 'TeacherNotesApiController::create');

// Administrator: accounts, notes log, teacher login logs.
$router->get('/api/admin/accounts', 'AdminApiController::accounts');
$router->post('/api/admin/accounts', 'AdminApiController::create_account');
$router->post('/api/admin/accounts/delete', 'AdminApiController::delete_student');
$router->get('/api/admin/notes', 'AdminApiController::notes');
$router->post('/api/admin/notes', 'AdminApiController::create_note');
$router->get('/api/admin/teacher-logins', 'AdminTeacherLoginApiController::index');
$router->post('/api/teacher/notes/update', 'TeacherNotesApiController::update');
$router->post('/api/teacher/notes/delete', 'TeacherNotesApiController::delete');
$router->post('/api/admin/accounts/update', 'AdminApiController::update_account');
$router->post('/api/admin/accounts/restore', 'AdminApiController::restore_account');

// Student RFID attendance.

// Class schedules, enrollment and transfer requests.
$router->get('/api/admin/classes', 'ClassManagementApiController::admin_index');
$router->post('/api/admin/classes', 'ClassManagementApiController::admin_create');
$router->post('/api/admin/classes/update', 'ClassManagementApiController::admin_update');
$router->post('/api/admin/classes/delete', 'ClassManagementApiController::admin_delete');
$router->post('/api/admin/classes/enroll', 'ClassManagementApiController::admin_enroll');
$router->post('/api/admin/classes/unenroll', 'ClassManagementApiController::admin_unenroll');
$router->get('/api/admin/transfers', 'ClassManagementApiController::admin_transfers');
$router->post('/api/admin/transfers/review', 'ClassManagementApiController::admin_review_transfer');
$router->get('/api/teacher/transfers', 'ClassManagementApiController::teacher_transfers');
$router->post('/api/teacher/transfers', 'ClassManagementApiController::teacher_request_transfer');
$router->post('/api/teacher/transfers/cancel', 'ClassManagementApiController::teacher_cancel_transfer');
$router->get('/api/admin/rfid', 'AdminApiController::rfid_list');
$router->post('/api/admin/rfid/assign', 'AdminApiController::rfid_assign');

// Standalone RFID attendance kiosks (no student/teacher login required).
$router->get('/api/rfid/active-class', 'PublicRfidApiController::active_class');
$router->get('/api/rfid/open-rooms', 'PublicRfidApiController::open_rooms');
$router->post('/api/rfid/scan', 'PublicRfidApiController::scan');
$router->post('/api/rfid/close-sessions', 'PublicRfidApiController::close_sessions');
$router->get('/api/admin/gate-logs', 'AdminApiController::gate_logs');

// NLP chatbot (students, teachers, admins).
$router->post('/api/chat', 'ChatbotApiController::message');

// Teacher face recognition (webcam).
$router->get('/api/admin/faces', 'FaceApiController::admin_list');
$router->post('/api/admin/faces/enroll', 'FaceApiController::admin_enroll');
$router->post('/api/admin/faces/delete', 'FaceApiController::admin_delete');
$router->post('/api/face/identify', 'FaceApiController::identify');

// Face recognition for teachers, admins and staff.
$router->post('/api/face/scan', 'FaceApiController::scan');
$router->get('/api/admin/face', 'FaceApiController::admin_list');
$router->post('/api/admin/face/enroll', 'FaceApiController::admin_enroll');
$router->post('/api/admin/face/delete', 'FaceApiController::admin_delete');

// NLP chatbot (students, teachers, admins).
$router->post('/api/chatbot/ask', 'ChatbotApiController::ask');
