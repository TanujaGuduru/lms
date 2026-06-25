<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;

class ReportController
{
    public function overview(Request $request): void
    {
        if (!Auth::check()) { $this->json(['success'=>false,'message'=>'Unauthenticated'], 401); return; }

        $db     = Database::getInstance();
        $period = $request->get('period', '30d');
        $days   = $this->periodToDays($period);

        $data = [
            'total_students'  => (int)$db->fetchOne("SELECT COUNT(*) as c FROM users WHERE role_id = (SELECT id FROM roles WHERE slug='student') AND deleted_at IS NULL")['c'],
            'total_courses'   => (int)$db->fetchOne("SELECT COUNT(*) as c FROM courses WHERE deleted_at IS NULL")['c'],
            'total_revenue'   => (float)$db->fetchOne("SELECT COALESCE(SUM(amount),0) as c FROM payments WHERE status='success'")['c'],
            'active_batches'  => (int)$db->fetchOne("SELECT COUNT(*) as c FROM batches WHERE status='active' AND deleted_at IS NULL")['c'],
            'new_enrollments' => (int)$db->fetchOne("SELECT COUNT(*) as c FROM enrollments WHERE created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)")['c'],
            'completion_rate' => (float)$db->fetchOne(
                "SELECT ROUND(AVG(progress_percentage), 1) as c FROM enrollments WHERE created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)"
            )['c'],
        ];

        // Daily enrollments for chart
        $chart = $db->fetchAll(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM enrollments WHERE created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
             GROUP BY DATE(created_at) ORDER BY date"
        );

        $this->json(['success'=>true,'data'=>$data,'chart'=>$chart,'period'=>$period]);
    }

    public function students(Request $request): void
    {
        if (!Auth::check()) { $this->json(['success'=>false,'message'=>'Unauthenticated'], 401); return; }

        $db = Database::getInstance();

        $data = [
            'by_batch'  => $db->fetchAll(
                "SELECT b.name, COUNT(bs.student_id) as count
                 FROM batches b LEFT JOIN batch_students bs ON bs.batch_id = b.id
                 GROUP BY b.id ORDER BY count DESC LIMIT 10"
            ),
            'by_course' => $db->fetchAll(
                "SELECT c.title, COUNT(e.id) as count
                 FROM courses c LEFT JOIN enrollments e ON e.course_id = c.id
                 GROUP BY c.id ORDER BY count DESC LIMIT 10"
            ),
            'progress_dist' => $db->fetchAll(
                "SELECT
                    CASE WHEN progress_percentage < 25 THEN '0-25%'
                         WHEN progress_percentage < 50 THEN '25-50%'
                         WHEN progress_percentage < 75 THEN '50-75%'
                         ELSE '75-100%' END as range,
                    COUNT(*) as count
                 FROM enrollments GROUP BY 1"
            ),
        ];

        $this->json(['success'=>true,'data'=>$data]);
    }

    private function periodToDays(string $period): int
    {
        return match($period) {
            '7d'  => 7,
            '30d' => 30,
            '90d' => 90,
            '1y'  => 365,
            default => 30,
        };
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
