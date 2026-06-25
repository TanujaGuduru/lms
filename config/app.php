<?php

return [
    'name'     => $_ENV['APP_NAME']     ?? 'CodeGurukul LMS',
    'url'      => $_ENV['APP_URL']      ?? 'http://localhost/codegurukul/public',
    'env'      => $_ENV['APP_ENV']      ?? 'production',
    'debug'    => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'key'      => $_ENV['APP_KEY']      ?? 'base64:CHANGE_ME_32_CHARS_APP_KEY_HERE_!!',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata',
    'locale'   => 'en',

    'upload_max_size_mb' => 50,
    'allowed_image_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
    'allowed_document_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'],

    'pagination_default' => 20,
    'pagination_options' => [10, 20, 50, 100],

    'version' => '1.0.0',
];
