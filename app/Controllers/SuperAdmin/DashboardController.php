<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;

class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $this->authorize('dashboard.view');

        $stats    = $this->getDashboardStats();
        $recent   = $this->getRecentActivity();
        $charts   = $this->getChartData();
        $health   = $this->getSystemHealth();
        $upcoming = $this->getUpcomingEvents();

        $this->render('super-admin.dashboard.index', [
            'title'    => 'Dashboard — CodeGurukul LMS',
            'stats'    => $stats,
            'recent'   => $recent,
            'charts'   => $charts,
            'health'   => $health,
            'upcoming' => $upcoming,
        ]);
    }

    public function stats(Request $request): never
    {
        $this->success($this->getDashboardStats());
    }

    public function activityFeed(Request $request): never
    {
        $page = max(1, (int)$request->input('page', 1));
        $logs = $this->db->select(
            "SELECT al.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.avatar, r.name as role_name
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             LEFT JOIN roles r ON r.id = u.role_id
             ORDER BY al.created_at DESC
             LIMIT 20 OFFSET ?",
            [($page - 1) * 20]
        );
        $this->success($logs);
    }

    public function unauthorized(Request $request): void
    {
        http_response_code(403);
        $this->render('errors.403', ['title' => 'Access Denied']);
    }

    private function getDashboardStats(): array
    {
        $db = $this->db;

        $users     = $db->selectOne("SELECT
            SUM(CASE WHEN role_id = 4 THEN 1 ELSE 0 END) as students,
            SUM(CASE WHEN role_id = 3 THEN 1 ELSE 0 END) as teachers,
            SUM(CASE WHEN role_id = 2 THEN 1 ELSE 0 END) as admins,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as new_today,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_month
            FROM users WHERE deleted_at IS NULL") ?: [];

        $courses   = $db->selectOne("SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published
            FROM courses WHERE deleted_at IS NULL") ?: [];

        $batches   = $db->selectOne("SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
            FROM batches") ?: [];

        $revenue   = $db->selectOne("SELECT
            COALESCE(SUM(total_amount), 0) as total,
            COALESCE(SUM(CASE WHEN DATE(paid_at) = CURDATE() THEN total_amount ELSE 0 END), 0) as today,
            COALESCE(SUM(CASE WHEN paid_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN total_amount ELSE 0 END), 0) as month
            FROM payments WHERE status = 'success'") ?: [];

        $certs     = $db->selectOne("SELECT COUNT(*) as total FROM certificates WHERE is_revoked = 0") ?: [];
        $tickets   = $db->selectOne("SELECT COUNT(*) as open FROM support_tickets WHERE status = 'open'") ?: [];
        $exams     = $db->selectOne("SELECT COUNT(*) as live FROM exams WHERE status = 'active'") ?: [];
        $pending   = $db->selectOne("SELECT COUNT(*) as total FROM users WHERE status = 'pending' AND deleted_at IS NULL") ?: [];

        $prevMonth = $db->selectOne("SELECT
            COALESCE(SUM(total_amount), 0) as total
            FROM payments WHERE status = 'success'
            AND paid_at BETWEEN DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m-01')
            AND DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m-31')") ?: ['total' => 0];

        $revenueGrowth = $prevMonth['total'] > 0
            ? round((($revenue['month'] - $prevMonth['total']) / $prevMonth['total']) * 100, 1)
            : 0;

        return compact('users', 'courses', 'batches', 'revenue', 'certs', 'tickets', 'exams', 'pending', 'revenueGrowth');
    }

    private function getRecentActivity(): array
    {
        return $this->db->select(
            "SELECT al.action, al.module, al.description, al.created_at,
                    CONCAT(u.first_name, ' ', u.last_name) as user_name,
                    u.avatar, r.name as role_name, r.color as role_color
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             LEFT JOIN roles r ON r.id = u.role_id
             ORDER BY al.created_at DESC LIMIT 15"
        );
    }

    private function getChartData(): array
    {
        $months = 6;

        $studentGrowth = $this->db->select(
            "SELECT DATE_FORMAT(created_at, '%b') as label, COUNT(*) as value
             FROM users WHERE role_id = 4 AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY created_at ASC",
            [$months]
        );

        $revenueByMonth = $this->db->select(
            "SELECT DATE_FORMAT(paid_at, '%b') as label, COALESCE(SUM(total_amount), 0) as value
             FROM payments WHERE status = 'success' AND paid_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY DATE_FORMAT(paid_at, '%Y-%m')
             ORDER BY paid_at ASC",
            [$months]
        );

        $coursesByLevel = $this->db->select(
            "SELECT level as label, COUNT(*) as value FROM courses WHERE status = 'published' AND deleted_at IS NULL
             GROUP BY level"
        );

        $attendanceTrend = $this->db->select(
            "SELECT DATE_FORMAT(session_date, '%b %d') as label,
                    ROUND(SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) as value
             FROM attendance WHERE session_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY session_date ORDER BY session_date ASC LIMIT 14"
        );

        return compact('studentGrowth', 'revenueByMonth', 'coursesByLevel', 'attendanceTrend');
    }

    private function getSystemHealth(): array
    {
        $diskTotal = disk_total_space(BASE_PATH);
        $diskFree  = disk_free_space(BASE_PATH);
        $diskUsed  = $diskTotal - $diskFree;
        $diskPct   = $diskTotal > 0 ? round($diskUsed / $diskTotal * 100, 1) : 0;

        $lastBackup = $this->db->selectOne(
            "SELECT created_at, status FROM backups ORDER BY created_at DESC LIMIT 1"
        );

        $dbStatus = 'operational';
        try { $this->db->selectOne("SELECT 1"); } catch (\Throwable) { $dbStatus = 'error'; }

        return [
            'db_status'       => $dbStatus,
            'storage_used_gb' => round($diskUsed / 1073741824, 2),
            'storage_total_gb'=> round($diskTotal / 1073741824, 2),
            'storage_pct'     => $diskPct,
            'last_backup'     => $lastBackup,
            'php_version'     => PHP_VERSION,
            'memory_usage_mb' => round(memory_get_usage(true) / 1048576, 2),
        ];
    }

    private function getUpcomingEvents(): array
    {
        return $this->db->select(
            "SELECT id, title, type, start_datetime, status FROM events
             WHERE start_datetime >= NOW() AND status IN ('draft','published')
             ORDER BY start_datetime ASC LIMIT 5"
        );
    }
}
