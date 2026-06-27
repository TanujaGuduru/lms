<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\SuperAdminMiddleware;
use App\Middleware\CsrfMiddleware;

// ── Public routes ─────────────────────────────────────────────────────────────
$router->get('/', 'Auth\LoginController@showLogin', 'home');
$router->get('/login', 'Auth\LoginController@showLogin', 'login');
$router->post('/login', 'Auth\LoginController@login');
$router->get('/logout', 'Auth\LoginController@logout', 'logout');
$router->post('/logout', 'Auth\LoginController@logout');

$router->get('/forgot-password', 'Auth\ForgotPasswordController@show', 'forgot-password');
$router->post('/forgot-password', 'Auth\ForgotPasswordController@send');
$router->get('/reset-password/{token}', 'Auth\ForgotPasswordController@showReset', 'reset-password');
$router->post('/reset-password', 'Auth\ForgotPasswordController@reset');

$router->get('/verify-email/{token}', 'Auth\VerifyEmailController@verify', 'verify-email');
$router->get('/certificate/verify/{code}', 'Public\CertificateController@verify', 'certificate.verify');

// ── Super Admin module ────────────────────────────────────────────────────────
$router->group([
    'prefix'     => 'super-admin',
    'middleware' => [SuperAdminMiddleware::class, CsrfMiddleware::class],
], function ($router) {

    // Dashboard
    $router->get('/', 'SuperAdmin\DashboardController@index', 'sa.dashboard');
    $router->get('/dashboard', 'SuperAdmin\DashboardController@index');
    $router->get('/dashboard/stats', 'SuperAdmin\DashboardController@stats', 'sa.dashboard.stats');
    $router->get('/dashboard/activity', 'SuperAdmin\DashboardController@activityFeed', 'sa.dashboard.activity');

    // ── Users ──────────────────────────────────────────────────────────────────
    $router->get('/users', 'SuperAdmin\UserController@index', 'sa.users');
    $router->get('/users/create', 'SuperAdmin\UserController@create', 'sa.users.create');
    $router->post('/users/store', 'SuperAdmin\UserController@store', 'sa.users.store');
    $router->get('/users/{id}', 'SuperAdmin\UserController@show', 'sa.users.show');
    $router->get('/users/{id}/edit', 'SuperAdmin\UserController@edit', 'sa.users.edit');
    $router->post('/users/{id}/update', 'SuperAdmin\UserController@update', 'sa.users.update');
    $router->post('/users/{id}/delete', 'SuperAdmin\UserController@destroy', 'sa.users.delete');
    $router->post('/users/{id}/toggle-status', 'SuperAdmin\UserController@toggleStatus');
    $router->post('/users/bulk-action', 'SuperAdmin\UserController@bulkAction');
    $router->get('/users/export', 'SuperAdmin\UserController@export');
    $router->post('/users/import', 'SuperAdmin\UserController@import');
    $router->get('/users/{id}/activity', 'SuperAdmin\UserController@activityHistory');
    $router->get('/users/{id}/sessions', 'SuperAdmin\UserController@sessions');

    // ── Roles & Permissions ────────────────────────────────────────────────────
    $router->get('/roles', 'SuperAdmin\RoleController@index', 'sa.roles');
    $router->post('/roles/create', 'SuperAdmin\RoleController@create');
    $router->post('/roles/{id}/delete', 'SuperAdmin\RoleController@delete');
    $router->post('/roles/{id}/clone', 'SuperAdmin\RoleController@clone');
    $router->post('/roles/permissions/save', 'SuperAdmin\RoleController@savePermissions');

    // ── Courses ────────────────────────────────────────────────────────────────
    $router->get('/courses', 'SuperAdmin\CourseController@index', 'sa.courses');
    $router->get('/courses/create', 'SuperAdmin\CourseController@create', 'sa.courses.create');
    $router->post('/courses', 'SuperAdmin\CourseController@store');
    $router->post('/courses/store', 'SuperAdmin\CourseController@store');
    $router->get('/courses/{id}/edit', 'SuperAdmin\CourseController@edit', 'sa.courses.edit');
    $router->post('/courses/{id}', 'SuperAdmin\CourseController@update');
    $router->post('/courses/{id}/update', 'SuperAdmin\CourseController@update');
    $router->post('/courses/{id}/delete', 'SuperAdmin\CourseController@delete');
    $router->post('/courses/{id}/publish', 'SuperAdmin\CourseController@publish');

    // ── Batches ────────────────────────────────────────────────────────────────
    $router->get('/batches', 'SuperAdmin\BatchController@index', 'sa.batches');
    $router->post('/batches/store', 'SuperAdmin\BatchController@store');
    $router->post('/batches/{id}/delete', 'SuperAdmin\BatchController@delete');
    $router->post('/batches/{id}/add-student', 'SuperAdmin\BatchController@addStudent');

    // ── Live Classes ───────────────────────────────────────────────────────────
    $router->get('/live-classes', 'SuperAdmin\LiveClassController@index', 'sa.live-classes');
    $router->post('/live-classes/store', 'SuperAdmin\LiveClassController@store');
    $router->post('/live-classes/{id}/cancel', 'SuperAdmin\LiveClassController@cancel');

    // ── Departments ────────────────────────────────────────────────────────────
    $router->get('/departments', 'SuperAdmin\DepartmentController@index', 'sa.departments');
    $router->post('/departments/store', 'SuperAdmin\DepartmentController@store');
    $router->post('/departments/{id}/update', 'SuperAdmin\DepartmentController@update');
    $router->post('/departments/{id}/delete', 'SuperAdmin\DepartmentController@destroy');

    // ── Question Bank ──────────────────────────────────────────────────────────
    $router->get('/question-bank', 'SuperAdmin\QuestionBankController@index', 'sa.questions');
    $router->get('/question-bank/create', 'SuperAdmin\QuestionBankController@create', 'sa.questions.create');
    $router->post('/question-bank/store', 'SuperAdmin\QuestionBankController@store');
    $router->post('/question-bank/store-bulk', 'SuperAdmin\QuestionBankController@storeBulk');
    $router->get('/question-bank/{id}/edit', 'SuperAdmin\QuestionBankController@edit');
    $router->post('/question-bank/{id}/update', 'SuperAdmin\QuestionBankController@update');
    $router->post('/question-bank/{id}/delete', 'SuperAdmin\QuestionBankController@destroy');
    $router->post('/question-bank/{id}/approve', 'SuperAdmin\QuestionBankController@approve');
    $router->post('/question-bank/import', 'SuperAdmin\QuestionBankController@import');
    $router->get('/question-bank/export', 'SuperAdmin\QuestionBankController@export');

    // ── Exams ──────────────────────────────────────────────────────────────────
    $router->get('/exams', 'SuperAdmin\ExamController@index', 'sa.exams');
    $router->get('/exams/create', 'SuperAdmin\ExamController@create', 'sa.exams.create');
    $router->post('/exams', 'SuperAdmin\ExamController@store');
    $router->post('/exams/store', 'SuperAdmin\ExamController@store');
    $router->get('/exams/{id}', 'SuperAdmin\ExamController@show');
    $router->get('/exams/{id}/edit', 'SuperAdmin\ExamController@edit');
    $router->post('/exams/{id}/update', 'SuperAdmin\ExamController@update');
    $router->post('/exams/{id}/publish', 'SuperAdmin\ExamController@publish');
    $router->get('/exams/{id}/results', 'SuperAdmin\ExamController@results');

    // ── Assignments ────────────────────────────────────────────────────────────
    // Routes disabled: App\Controllers\SuperAdmin\AssignmentController was never
    // implemented (no controller file, no view folder exists either) - these
    // routes 500'd with "Class not found" rather than a normal 404. The sidebar
    // nav link at resources/views/layouts/super-admin.php still points to
    // /super-admin/assignments and will now 404 instead of crashing, until a
    // real implementation is built.
    // $router->get('/assignments', 'SuperAdmin\AssignmentController@index', 'sa.assignments');
    // $router->get('/assignments/create', 'SuperAdmin\AssignmentController@create');
    // $router->post('/assignments/store', 'SuperAdmin\AssignmentController@store');
    // $router->get('/assignments/{id}', 'SuperAdmin\AssignmentController@show');
    // $router->get('/assignments/{id}/submissions', 'SuperAdmin\AssignmentController@submissions');

    // ── Certificates ───────────────────────────────────────────────────────────
    $router->get('/certificates', 'SuperAdmin\CertificateController@index', 'sa.certificates');
    $router->get('/certificates/templates', 'SuperAdmin\CertificateController@templates', 'sa.cert.templates');
    $router->get('/certificates/templates/create', 'SuperAdmin\CertificateController@createTemplate');
    $router->post('/certificates/templates/store', 'SuperAdmin\CertificateController@storeTemplate');
    $router->post('/certificates/issue/{userId}/{courseId}', 'SuperAdmin\CertificateController@issue');
    $router->post('/certificates/{id}/revoke', 'SuperAdmin\CertificateController@revoke');

    // ── Announcements ──────────────────────────────────────────────────────────
    $router->get('/announcements', 'SuperAdmin\AnnouncementController@index', 'sa.announcements');
    $router->get('/announcements/create', 'SuperAdmin\AnnouncementController@create', 'sa.announcements.create');
    $router->post('/announcements/store', 'SuperAdmin\AnnouncementController@store');
    $router->get('/announcements/{id}/edit', 'SuperAdmin\AnnouncementController@edit');
    $router->post('/announcements/{id}/update', 'SuperAdmin\AnnouncementController@update');
    $router->post('/announcements/{id}/delete', 'SuperAdmin\AnnouncementController@delete');
    $router->post('/announcements/{id}/send', 'SuperAdmin\AnnouncementController@send');

    // ── Events ─────────────────────────────────────────────────────────────────
    $router->get('/events', 'SuperAdmin\EventController@index', 'sa.events');
    $router->get('/events/create', 'SuperAdmin\EventController@create');
    $router->post('/events/store', 'SuperAdmin\EventController@store');
    $router->get('/events/{id}', 'SuperAdmin\EventController@show');
    $router->get('/events/{id}/edit', 'SuperAdmin\EventController@edit');
    $router->post('/events/{id}/update', 'SuperAdmin\EventController@update');

    // ── Support ────────────────────────────────────────────────────────────────
    $router->get('/support', 'SuperAdmin\SupportController@index', 'sa.support');
    $router->get('/support/{id}', 'SuperAdmin\SupportController@show');
    $router->post('/support/{id}/assign', 'SuperAdmin\SupportController@assign');
    $router->post('/support/{id}/reply', 'SuperAdmin\SupportController@reply');
    $router->post('/support/{id}/close', 'SuperAdmin\SupportController@close');

    // ── Finance ────────────────────────────────────────────────────────────────
    $router->get('/finance', 'SuperAdmin\FinanceController@index', 'sa.finance');
    $router->get('/finance/payments', 'SuperAdmin\FinanceController@payments', 'sa.finance.payments');
    $router->get('/finance/fee-structures', 'SuperAdmin\FinanceController@feeStructures');
    $router->post('/finance/fee-structures/store', 'SuperAdmin\FinanceController@createFeeStructure');
    $router->get('/finance/reports', 'SuperAdmin\FinanceController@reports');

    // ── Placement ──────────────────────────────────────────────────────────────
    $router->get('/placement', 'SuperAdmin\PlacementController@index', 'sa.placement');
    $router->get('/placement/companies', 'SuperAdmin\PlacementController@companies');
    $router->post('/placement/companies/store', 'SuperAdmin\PlacementController@storeCompany');
    $router->get('/placement/jobs', 'SuperAdmin\PlacementController@jobs');
    $router->post('/placement/jobs/store', 'SuperAdmin\PlacementController@storeJob');
    $router->get('/placement/applications', 'SuperAdmin\PlacementController@applications');
    $router->post('/placement/applications/{id}/status', 'SuperAdmin\PlacementController@updateApplicationStatus');
    $router->post('/placement/companies/{id}', 'SuperAdmin\PlacementController@updateCompany');
    $router->post('/placement/jobs/{id}', 'SuperAdmin\PlacementController@updateJob');
    $router->get('/placement/reports', 'SuperAdmin\PlacementController@reports');

    // ── Reports ────────────────────────────────────────────────────────────────
    $router->get('/reports', 'SuperAdmin\ReportController@index', 'sa.reports');
    $router->get('/reports/students', 'SuperAdmin\ReportController@students');
    $router->get('/reports/teachers', 'SuperAdmin\ReportController@teachers');
    $router->get('/reports/courses', 'SuperAdmin\ReportController@courses');
    $router->get('/reports/attendance', 'SuperAdmin\ReportController@attendance');
    $router->get('/reports/finance', 'SuperAdmin\ReportController@finance');
    $router->get('/reports/placement', 'SuperAdmin\ReportController@placement');
    $router->get('/reports/custom', 'SuperAdmin\ReportController@custom');
    $router->post('/reports/export', 'SuperAdmin\ReportController@export');

    // ── AI Center ──────────────────────────────────────────────────────────────
    $router->get('/ai-center', 'SuperAdmin\AiCenterController@index', 'sa.ai');
    $router->post('/ai-center/generate-quiz', 'SuperAdmin\AiCenterController@generateQuiz');
    $router->post('/ai-center/generate-content', 'SuperAdmin\AiCenterController@generateContent');
    $router->post('/ai-center/generate-assignment', 'SuperAdmin\AiCenterController@generateAssignment');

    // ── Security ───────────────────────────────────────────────────────────────
    $router->get('/security', 'SuperAdmin\SecurityController@index', 'sa.security');
    $router->get('/security/sessions', 'SuperAdmin\SecurityController@sessions');
    $router->post('/security/sessions/{token}/revoke', 'SuperAdmin\SecurityController@revokeSession');
    $router->post('/security/sessions/revoke-all', 'SuperAdmin\SecurityController@revokeAllSessions');
    $router->get('/security/ip-restrictions', 'SuperAdmin\SecurityController@ipRestrictions');
    $router->post('/security/ip-restrictions/store', 'SuperAdmin\SecurityController@addIpRule');
    $router->post('/security/ip-restrictions/{id}/delete', 'SuperAdmin\SecurityController@removeIpRule');
    $router->post('/security/accounts/{userId}/unlock', 'SuperAdmin\SecurityController@unlockAccount');
    $router->get('/security/login-logs', 'SuperAdmin\SecurityController@loginLogs');

    // ── Audit Logs ─────────────────────────────────────────────────────────────
    $router->get('/audit-logs', 'SuperAdmin\AuditLogController@index', 'sa.audit');
    $router->get('/audit-logs/export', 'SuperAdmin\AuditLogController@export');

    // ── Backup ─────────────────────────────────────────────────────────────────
    $router->get('/backup', 'SuperAdmin\BackupController@index', 'sa.backup');
    $router->post('/backup/create', 'SuperAdmin\BackupController@create');
    $router->post('/backup/{id}/delete', 'SuperAdmin\BackupController@delete');
    $router->get('/backup/{id}/download', 'SuperAdmin\BackupController@download');

    // ── Settings ───────────────────────────────────────────────────────────────
    $router->get('/settings', 'SuperAdmin\SettingsController@index', 'sa.settings');
    $router->get('/settings/{group}', 'SuperAdmin\SettingsController@show', 'sa.settings.group');
    $router->post('/settings/{group}/save', 'SuperAdmin\SettingsController@save');

    // ── Integrations ───────────────────────────────────────────────────────────
    $router->get('/integrations', 'SuperAdmin\IntegrationController@index', 'sa.integrations');
    $router->post('/integrations/{id}/toggle', 'SuperAdmin\IntegrationController@toggle');
    $router->post('/integrations/{id}/test', 'SuperAdmin\IntegrationController@test');
    $router->post('/integrations/{id}/save', 'SuperAdmin\IntegrationController@save');

    // ── Profile ────────────────────────────────────────────────────────────────
    $router->get('/profile', 'SuperAdmin\ProfileController@index', 'sa.profile');
    $router->post('/profile/update', 'SuperAdmin\ProfileController@update');
    $router->post('/profile/change-password', 'SuperAdmin\ProfileController@changePassword');
    $router->post('/profile/avatar', 'SuperAdmin\ProfileController@updateAvatar');
    $router->post('/profile/2fa/enable', 'SuperAdmin\ProfileController@enable2FA');
    $router->post('/profile/2fa/disable', 'SuperAdmin\ProfileController@disable2FA');

    // Unauthorized page
    $router->get('/unauthorized', 'SuperAdmin\DashboardController@unauthorized');
});
