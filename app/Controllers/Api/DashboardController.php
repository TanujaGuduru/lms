<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;

class DashboardController
{
    public function index(Request $request): void
    {
        if (!Auth::check()) { $this->json(['success'=>false,'message'=>'Unauthenticated'], 401); return; }

        $db   = Database::getInstance();
        $user = Auth::user();

        $studentRoleId = (int)($db->fetchOne("SELECT id FROM roles WHERE slug='student'")['id'] ?? 0);

        $kpis = [
            'total_students'  => (int)$db->fetchOne("SELECT COUNT(*) as c FROM users WHERE role_id = ? AND deleted_at IS NULL", [$studentRoleId])['c'],
            'total_courses'   => (int)$db->fetchOne("SELECT COUNT(*) as c FROM courses WHERE deleted_at IS NULL")['c'],
            'active_batches'  => (int)$db->fetchOne("SELECT COUNT(*) as c FROM batches WHERE status='active' AND deleted_at IS NULL")['c'],
            'total_revenue'   => (float)$db->fetchOne("SELECT COALESCE(SUM(amount),0) as c FROM payments WHERE status='success'")['c'],
            'open_tickets'    => (int)$db->fetchOne("SELECT COUNT(*) as c FROM support_tickets WHERE status IN ('open','in_progress')")['c'],
            'pending_approvals' => (int)$db->fetchOne("SELECT COUNT(*) as c FROM questions WHERE status='pending_review'")['c'],
        ];

        // Recent enrollments for sparkline (last 7 days)
        $enrollmentTrend = $db->fetchAll(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM enrollments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(created_at) ORDER BY date"
        );

        // Recent revenue (last 7 days)
        $revenueTrend = $db->fetchAll(
            "SELECT DATE(created_at) as date, SUM(amount) as amount
             FROM payments WHERE status='success' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(created_at) ORDER BY date"
        );

        // Latest activity
        $recentActivity = $db->fetchAll(
            "SELECT al.action, al.module, al.description, al.created_at,
                    CONCAT(u.first_name,' ',u.last_name) as user_name
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC LIMIT 10"
        );

        $this->json([
            'success'         => true,
            'kpis'            => $kpis,
            'enrollment_trend'=> $enrollmentTrend,
            'revenue_trend'   => $revenueTrend,
            'recent_activity' => $recentActivity,
            'generated_at'    => date('c'),
        ]);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
