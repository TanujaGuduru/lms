<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;

define('IS_API', str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/api'));

$router->group(['prefix' => 'api/v1'], function ($router) {

    // Public API
    $router->get('/health', 'Api\HealthController@index');
    $router->post('/auth/login', 'Api\AuthController@login');
    $router->post('/auth/refresh', 'Api\AuthController@refresh');

    // Protected API
    $router->group(['middleware' => [AuthMiddleware::class, CsrfMiddleware::class]], function ($router) {
        $router->post('/auth/logout', 'Api\AuthController@logout');
        $router->get('/me', 'Api\AuthController@me');

        // Dashboard stats
        $router->get('/dashboard', 'Api\DashboardController@index');
        $router->get('/dashboard/stats', 'Api\DashboardController@index');
        $router->get('/dashboard/charts', 'Api\DashboardController@index');
        $router->get('/dashboard/activity', 'Api\DashboardController@index');

        // Users
        $router->get('/users', 'Api\UserController@index');
        $router->post('/users', 'Api\UserController@store');
        $router->get('/users/{id}', 'Api\UserController@show');
        $router->put('/users/{id}', 'Api\UserController@update');
        $router->delete('/users/{id}', 'Api\UserController@destroy');

        // Courses
        $router->get('/courses', 'Api\CourseController@index');
        $router->post('/courses', 'Api\CourseController@store');
        $router->get('/courses/{id}', 'Api\CourseController@show');
        $router->put('/courses/{id}', 'Api\CourseController@update');
        $router->delete('/courses/{id}', 'Api\CourseController@destroy');

        // Batches
        $router->get('/batches', 'Api\BatchController@index');
        $router->post('/batches', 'Api\BatchController@store');
        $router->get('/batches/{id}', 'Api\BatchController@show');

        // Notifications
        $router->get('/notifications', 'Api\NotificationController@index');
        $router->post('/notifications/read-all', 'Api\NotificationController@markAllRead');
        $router->post('/notifications/{id}/read', 'Api\NotificationController@markRead');
        $router->delete('/notifications/{id}', 'Api\NotificationController@destroy');

        // Reports
        $router->get('/reports/overview', 'Api\ReportController@overview');
        $router->get('/reports/students', 'Api\ReportController@students');
        $router->get('/reports/export', 'Api\ReportController@overview');

        // Uploads
        $router->post('/upload', 'Api\UploadController@upload');
        $router->post('/upload/image', 'Api\UploadController@upload');
        $router->post('/upload/file', 'Api\UploadController@upload');

        // Search
        $router->get('/search', 'Api\SearchController@index');

        // Settings (AJAX helpers)
        $router->post('/settings/test-email', 'SuperAdmin\SettingsController@testEmail');
    });
});
