<?php

use App\Middleware\AuthMiddleware;

/*
|--------------------------------------------------------------------------
| API Routes — reachable at https://yourdomain.com/api/v1/...
|--------------------------------------------------------------------------
| Standard success/error envelopes, Bearer-token auth —
| docs/student-module/04a-apis-conventions-enrollment-billing.md.
|
| 04a (Auth, Account, Parent Consent, Credit Wallet), 04b (Placement, Batch,
| Scheduling), and 04c (Live Classroom, Attendance, Recordings, Video
| Library, Materials) are wired up so far. Everything else catalogued in
| docs/student-module/04d-04j gets its own controller/route group as each
| feature is implemented next.
*/

// The request's path is always /api/v1/... regardless of where these PHP
// files physically live — Apache's RewriteRule routes the request to this
// index.php without stripping the /api segment from REQUEST_URI, so the
// route table has to match the real path, including the /api prefix.
$router->group(['prefix' => 'api/v1'], function ($router) {

    $router->get('/health', 'Api\HealthController@index');
    $router->get('/health/metrics', 'Api\HealthController@metrics');

    $router->post('/auth/login', 'Api\AuthController@login');
    $router->post('/auth/forgot-password', 'Api\AuthController@forgotPassword');
    $router->post('/auth/reset-password', 'Api\AuthController@resetPassword');

    // Public by necessity — the signed, short-lived token IS the access
    // control (App\Core\FileStorage), generated only after
    // MaterialController/RecordingController already did the real
    // enrollment check. A Bearer requirement here would be redundant, not
    // additional security.
    $router->get('/files/serve', 'Api\FileController@serve');

    // Public by necessity — has to be reachable by recruiters/family with
    // no platform account at all (04j's explicit reasoning).
    $router->get('/public/portfolio/{slug}', 'Api\PortfolioController@publicShow');

    $router->group(['middleware' => [AuthMiddleware::class]], function ($router) {
        $router->get('/auth/me', 'Api\AuthController@me');
        $router->post('/auth/logout', 'Api\AuthController@logout');
        $router->post('/auth/refresh', 'Api\AuthController@refresh');

        $router->get('/account/profile', 'Api\AccountController@show');
        $router->patch('/account/profile', 'Api\AccountController@update');
        $router->post('/account/complete-onboarding', 'Api\AccountController@completeOnboarding');

        $router->get('/notifications', 'Api\NotificationController@index');
        $router->post('/notifications/{id}/mark-read', 'Api\NotificationController@markRead');
        $router->post('/notifications/mark-all-read', 'Api\NotificationController@markAllRead');
        $router->delete('/notifications/{id}', 'Api\NotificationController@destroy');

        $router->get('/parent/consent-requests', 'Api\ParentConsentController@index');
        $router->post('/parent/consent/{studentId}/initiate', 'Api\ParentConsentController@initiate');
        $router->post('/parent/consent/{studentId}/grant', 'Api\ParentConsentController@grant');
        $router->post('/parent/consent/{studentId}/revoke', 'Api\ParentConsentController@revoke');

        $router->get('/wallet', 'Api\WalletController@show');
        $router->get('/wallet/transactions', 'Api\WalletController@transactions');
        $router->post('/wallet/purchase', 'Api\WalletController@purchase');
        $router->post('/wallet/freeze', 'Api\WalletController@freeze');
        $router->post('/wallet/resume', 'Api\WalletController@resume');

        $router->get('/placement/status', 'Api\PlacementController@status');
        $router->post('/placement/start', 'Api\PlacementController@start');
        $router->post('/placement/{attemptId}/answer', 'Api\PlacementController@answer');
        $router->post('/placement/{attemptId}/submit', 'Api\PlacementController@submit');
        $router->get('/placement/{attemptId}/result', 'Api\PlacementController@result');
        $router->post('/placement/{attemptId}/request-recheck', 'Api\PlacementController@requestRecheck');

        $router->get('/batch/current', 'Api\BatchController@current');
        $router->get('/batch/waitlist-status', 'Api\BatchController@waitlistStatus');

        $router->get('/schedule/upcoming', 'Api\ScheduleController@upcoming');
        $router->get('/schedule/calendar', 'Api\ScheduleController@calendar');
        $router->get('/schedule/classes/{id}', 'Api\ScheduleController@classDetail');
        $router->post('/reschedule-requests', 'Api\ScheduleController@createRescheduleRequest');
        $router->get('/reschedule-requests', 'Api\ScheduleController@rescheduleRequests');
        $router->post('/teacher-change-requests', 'Api\ScheduleController@createTeacherChangeRequest');
        $router->get('/teacher-change-requests', 'Api\ScheduleController@teacherChangeRequests');

        // Pure P2P WebRTC — see ClassroomController's class docblock for why
        // there's no Agora/Pusher/Ably anywhere in this catalog.
        $router->post('/classes/{id}/join', 'Api\ClassroomController@join');
        $router->post('/classes/{id}/heartbeat', 'Api\ClassroomController@heartbeat');
        $router->post('/classes/{id}/leave', 'Api\ClassroomController@leave');
        $router->get('/classes/{id}', 'Api\ClassroomController@show');
        $router->post('/classes/{id}/signal', 'Api\ClassroomController@signal');
        $router->get('/classes/{id}/signal', 'Api\ClassroomController@pollSignals');
        $router->post('/classes/{id}/chat', 'Api\ClassroomController@sendChatMessage');
        $router->get('/classes/{id}/chat', 'Api\ClassroomController@pollChatMessages');

        $router->get('/attendance/history', 'Api\AttendanceController@history');
        $router->get('/attendance/summary', 'Api\AttendanceController@summary');

        // /recordings/search MUST be registered before /recordings/{id} —
        // the router matches in registration order and {id}'s pattern
        // ([^/]+) would otherwise swallow the literal "search" segment.
        $router->get('/recordings/search', 'Api\RecordingController@search');
        $router->get('/recordings', 'Api\RecordingController@index');
        $router->get('/recordings/{id}', 'Api\RecordingController@show');
        $router->post('/recordings/{id}/progress', 'Api\RecordingController@progress');
        $router->get('/recordings/{id}/bookmarks', 'Api\RecordingController@bookmarks');
        $router->post('/recordings/{id}/bookmarks', 'Api\RecordingController@createBookmark');
        $router->post('/recordings/{id}/bookmarks/{bookmarkId}/save-to-note', 'Api\RecordingController@saveBookmarkToNote');

        $router->get('/courses/{id}/modules', 'Api\LessonController@courseModules');
        $router->get('/lessons/{id}', 'Api\LessonController@show');
        $router->post('/lessons/{id}/progress', 'Api\LessonController@progress');
        $router->get('/lessons/{id}/bookmarks', 'Api\LessonController@bookmarks');
        $router->post('/lessons/{id}/bookmarks', 'Api\LessonController@createBookmark');

        $router->get('/courses/{id}/materials', 'Api\MaterialController@courseMaterials');
        $router->get('/materials/{id}/download', 'Api\MaterialController@download');
        $router->get('/materials/{id}/versions', 'Api\MaterialController@versions');

        // No Coding Sandbox routes here — see AssignmentController's class
        // docblock. type='code' submissions are pasted text, never executed.
        $router->get('/courses/{id}/assignments', 'Api\AssignmentController@courseAssignments');
        $router->get('/assignments/{id}', 'Api\AssignmentController@show');
        $router->get('/assignments/{id}/submission', 'Api\AssignmentController@showSubmission');
        $router->put('/assignments/{id}/submission', 'Api\AssignmentController@upsertSubmission');
        $router->post('/assignments/{id}/submission/submit', 'Api\AssignmentController@submit');

        $router->get('/ai/conversations', 'Api\AiController@index');
        $router->post('/ai/conversations', 'Api\AiController@create');
        $router->get('/ai/conversations/{id}/messages', 'Api\AiController@messages');
        $router->post('/ai/conversations/{id}/messages', 'Api\AiController@sendMessage');
        $router->post('/ai/conversations/{id}/escalate', 'Api\AiController@escalate');
        $router->get('/ai/quota', 'Api\AiController@quota');

        // Assessments (Exams) — no /attempts/{id}/proctor-token route here;
        // see ExamController's class docblock for why (no Agora/video
        // service anywhere in this build, cheating-flag is the only signal).
        $router->get('/exams', 'Api\ExamController@index');
        $router->get('/exams/{id}', 'Api\ExamController@show');
        $router->post('/exams/{id}/attempts', 'Api\ExamController@startAttempt');
        $router->get('/attempts/{id}', 'Api\ExamController@attemptDetail');
        $router->put('/attempts/{id}/responses/{questionId}', 'Api\ExamController@autosaveResponse');
        $router->post('/attempts/{id}/cheating-flag', 'Api\ExamController@cheatingFlag');
        $router->post('/attempts/{id}/submit', 'Api\ExamController@submit');
        $router->get('/attempts/{id}/result', 'Api\ExamController@result');

        // Project Lifecycle + Publishing — reuses the Assignments endpoints
        // above (type='project'); these three are what's new in 04e.
        // /submissions/mine/publish-requests MUST be registered before
        // /submissions/{id} for the same reason /recordings/search precedes
        // /recordings/{id} above.
        $router->get('/submissions/mine/publish-requests', 'Api\AssignmentController@myPublishRequests');
        $router->get('/submissions/{id}', 'Api\AssignmentController@showSubmissionById');
        $router->post('/submissions/{id}/publish-request', 'Api\AssignmentController@publishRequest');

        $router->get('/progress/snapshot', 'Api\ProgressController@snapshot');
        $router->get('/progress/history', 'Api\ProgressController@history');
        $router->get('/progress/insights', 'Api\ProgressController@insights');

        // Parent Visibility, Risk Visibility, Monthly Parent Reports.
        $router->get('/parent/children', 'Api\ParentController@children');
        $router->get('/parent/students/{id}/risk-summary', 'Api\ParentController@riskSummary');
        $router->get('/parent/students/{id}/dashboard', 'Api\ParentController@studentDashboard');
        $router->get('/parent/students/{id}/attendance', 'Api\ParentController@studentAttendance');
        $router->get('/parent/students/{id}/recordings', 'Api\ParentController@studentRecordings');
        $router->get('/parent/students/{id}/wallet', 'Api\ParentController@studentWallet');
        $router->get('/parent/students/{id}/reports', 'Api\ParentController@studentReports');
        $router->get('/reports/{id}', 'Api\ParentController@reportDetail');
        $router->post('/reports/{id}/viewed', 'Api\ParentController@markReportViewed');

        // Achievement Showcase Wall — the minor gate (04j).
        $router->get('/parent/students/{id}/portfolio', 'Api\ParentController@portfolioPreview');
        $router->post('/parent/students/{id}/portfolio/approve', 'Api\ParentController@portfolioApprove');

        // Payments / Refunds / Freeze.
        $router->get('/payments', 'Api\PaymentController@index');
        $router->get('/payments/{id}', 'Api\PaymentController@show');
        $router->post('/payments/{id}/retry', 'Api\PaymentController@retry');
        $router->post('/wallet/refund-request', 'Api\PaymentController@refundRequest');
        $router->get('/wallet/freeze-history', 'Api\PaymentController@freezeHistory');

        // Course Completion — read-only; the actual status flip is
        // cron/check-course-completion.php (no client-triggered action exists).
        $router->get('/enrollments/{id}', 'Api\EnrollmentController@show');
        $router->get('/enrollments/{id}/completion-requirements', 'Api\EnrollmentController@completionRequirements');
        $router->post('/enrollments/{id}/confirm-certificate-name', 'Api\CertificateController@confirmCertificateName');

        // Certificates.
        $router->get('/certificates', 'Api\CertificateController@index');
        $router->get('/certificates/{id}', 'Api\CertificateController@show');
        $router->get('/certificates/{id}/download', 'Api\CertificateController@download');
        $router->post('/certificates/{id}/reissue-request', 'Api\CertificateController@reissueRequest');

        // Renewal / Upsell.
        $router->get('/recommendations', 'Api\RecommendationController@index');

        // Referral System.
        $router->get('/referrals/my-code', 'Api\ReferralController@myCode');
        $router->get('/referrals', 'Api\ReferralController@index');

        // Support System.
        $router->get('/support/categories', 'Api\SupportController@categories');
        $router->get('/support/tickets', 'Api\SupportController@index');
        $router->post('/support/tickets', 'Api\SupportController@create');
        $router->get('/support/tickets/{id}', 'Api\SupportController@show');
        $router->post('/support/tickets/{id}/replies', 'Api\SupportController@addReply');
        $router->post('/support/tickets/{id}/satisfaction', 'Api\SupportController@satisfaction');

        // Gamification — no POST anywhere here; XP/badges/streaks are
        // hooked into existing event endpoints (App\Core\Gamification), never a separate client call.
        $router->get('/gamification/profile', 'Api\GamificationController@profile');
        $router->get('/gamification/xp-history', 'Api\GamificationController@xpHistory');
        $router->get('/gamification/badges', 'Api\GamificationController@badges');
        $router->get('/leaderboard', 'Api\GamificationController@leaderboard');

        // Digital Notebook. No /notes/voice-transcribe — see NoteController's
        // class docblock for why (no speech-to-text provider integrated).
        $router->get('/notes', 'Api\NoteController@index');
        $router->post('/notes', 'Api\NoteController@create');
        $router->get('/notes/{id}', 'Api\NoteController@show');
        $router->patch('/notes/{id}', 'Api\NoteController@update');
        $router->delete('/notes/{id}', 'Api\NoteController@destroy');
        $router->get('/notes/{id}/versions', 'Api\NoteController@versions');
        $router->post('/notes/{id}/summarize', 'Api\NoteController@summarize');
        $router->post('/notes/{id}/flashcards', 'Api\NoteController@flashcards');
        $router->post('/notes/{id}/generate-quiz', 'Api\NoteController@generateQuiz');
        $router->post('/recordings/{id}/generate-notes', 'Api\RecordingController@generateNotes');

        $router->get('/flashcards/due', 'Api\FlashcardController@due');
        $router->post('/flashcards/{id}/review', 'Api\FlashcardController@review');

        // Collaborative Coding — see CollabSessionController's class
        // docblock for the full no-cloud/no-frontend-yet scope.
        $router->post('/collab-sessions', 'Api\CollabSessionController@create');
        $router->get('/collab-sessions/{id}', 'Api\CollabSessionController@show');
        $router->post('/collab-sessions/{id}/join', 'Api\CollabSessionController@join');
        $router->post('/collab-sessions/{id}/leave', 'Api\CollabSessionController@leave');
        $router->post('/collab-sessions/{id}/end', 'Api\CollabSessionController@end');
        $router->post('/collab-sessions/{id}/signal', 'Api\CollabSessionController@signal');
        $router->get('/collab-sessions/{id}/signal', 'Api\CollabSessionController@pollSignals');

        // Live Quizzes — no Pusher/Ably push; clients poll /classes/{id}/live-quiz/current
        // the same ~2s cadence already used for chat/signal polling (see LiveQuizController).
        $router->get('/classes/{id}/live-quiz/current', 'Api\LiveQuizController@current');
        $router->get('/live-quizzes/{id}', 'Api\LiveQuizController@show');
        $router->post('/live-quizzes/{id}/respond', 'Api\LiveQuizController@respond');
        $router->get('/live-quizzes/{id}/results', 'Api\LiveQuizController@results');
        $router->post('/live-quizzes/{id}/explain-mode', 'Api\LiveQuizController@explainMode');

        // Code Replay is not implemented — see ExamController/AssignmentController's
        // "no Coding Sandbox" docblocks; code_replay_sessions.workspace_id is a
        // required FK to code_workspaces, a table nothing in this build ever
        // populates, since live code execution was dropped entirely.

        // Offline Access / Download Mode — see OfflineDownloadController's
        // class docblock for the AES-128 HLS/DRM -> signed-URL adaptation.
        $router->post('/offline-downloads', 'Api\OfflineDownloadController@request');
        $router->get('/offline-downloads', 'Api\OfflineDownloadController@index');
        $router->post('/offline-downloads/{id}/validate', 'Api\OfflineDownloadController@validate');
        $router->post('/offline-downloads/{id}/revoke', 'Api\OfflineDownloadController@revoke');
        $router->post('/lesson-progress/sync-offline', 'Api\LessonController@syncOffline');

        // Calendar Integration — providers/disconnect are real; connect/reconnect
        // (real OAuth) are deferred, see CalendarController's class docblock.
        $router->get('/calendar/providers', 'Api\CalendarController@providers');
        $router->delete('/calendar/connections/{id}', 'Api\CalendarController@disconnect');

        // Achievement Showcase Wall — the public page is registered above,
        // outside this auth group (PortfolioController@publicShow).
        $router->get('/portfolio', 'Api\PortfolioController@show');
        $router->put('/portfolio', 'Api\PortfolioController@update');
        $router->get('/portfolio/preview', 'Api\PortfolioController@preview');

        // PTM Booking — join/signal/poll are this build's own addition for
        // a real meeting_link (see PtmController's class docblock).
        $router->get('/ptm/slots', 'Api\PtmController@slots');
        $router->post('/ptm/bookings', 'Api\PtmController@book');
        $router->get('/ptm/bookings', 'Api\PtmController@myBookings');
        $router->get('/ptm/bookings/{id}', 'Api\PtmController@show');
        $router->post('/ptm/bookings/{id}/cancel', 'Api\PtmController@cancel');
        $router->post('/ptm/bookings/{id}/join', 'Api\PtmController@join');
        $router->post('/ptm/bookings/{id}/signal', 'Api\PtmController@signal');
        $router->get('/ptm/bookings/{id}/signal', 'Api\PtmController@pollSignals');
    });

});
