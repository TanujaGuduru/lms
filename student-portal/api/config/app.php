<?php

return [
    'name' => $_ENV['APP_NAME'] ?? 'CodeGurukul Student Portal API',
    'url' => $_ENV['APP_URL'] ?? 'http://localhost/api',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata',

    // Same domain, different path as the frontend (web/) — see ../README.md.
    // No CORS needed in production since both are served from the same origin.
    'frontend_url' => $_ENV['FRONTEND_URL'] ?? 'http://localhost',

    // Signs local-file download URLs (App\Core\FileStorage) — must be a
    // long, unpredictable, stable secret. Not the same value as the Admin
    // panel's APP_KEY; each app has its own.
    'key' => $_ENV['APP_KEY'] ?? '',

    // The "policy moment" docs/student-module/03g/04f call out: once a
    // student's computed age crosses this, parent-side endpoints stop
    // serving that student on stale minor-era consent (ParentController).
    'adult_age_threshold' => (int) ($_ENV['ADULT_AGE_THRESHOLD'] ?? 18),

    // Course Completion (04g) — neither value has a dedicated schema column
    // (docs/student-module/03h describes the concepts but not a settings
    // table); both are plain config rather than invented database state.
    'grace_period_days' => (int) ($_ENV['GRACE_PERIOD_DAYS'] ?? 14),
    'attendance_required_percent' => (int) ($_ENV['ATTENDANCE_REQUIRED_PERCENT'] ?? 75),
];
