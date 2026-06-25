<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;

class ReportController extends Controller
{
    public function index(Request $request): void
    {
        $this->authorize('reports.view');
        $this->render('super-admin.reports.index', ['title' => 'Reports & Analytics']);
    }

    public function students(Request $request): void
    {
        $this->authorize('reports.view');

        $period = $request->input('period', '30d');
        $days   = $this->periodToDays($period);

        $enrollmentTrend = $this->db->select(
            "SELECT DATE(enrolled_at) day, COUNT(*) cnt
             FROM enrollments WHERE enrolled_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(enrolled_at) ORDER BY day",
            [$days]
        );

        $completionRates = $this->db->select(
            "SELECT c.title, COUNT(e.id) enrolled, SUM(e.completed_at IS NOT NULL) completed
             FROM enrollments e JOIN courses c ON c.id=e.course_id
             GROUP BY e.course_id ORDER BY enrolled DESC LIMIT 10"
        );

        $this->render('super-admin.reports.students', [
            'title'           => 'Student Reports',
            'enrollmentTrend' => $enrollmentTrend,
            'completionRates' => $completionRates,
            'period'          => $period,
        ]);
    }

    public function courses(Request $request): void
    {
        $this->authorize('reports.view');

        $courses = $this->db->select(
            "SELECT c.title, c.enrolled_count, c.rating_avg, c.price,
             COUNT(DISTINCT lp.id) completions, COALESCE(SUM(p.total_amount),0) revenue
             FROM courses c
             LEFT JOIN enrollments e ON e.course_id=c.id
             LEFT JOIN (SELECT course_id, id FROM enrollments WHERE completed_at IS NOT NULL) lp ON lp.course_id=c.id
             LEFT JOIN payments p ON p.course_id=c.id AND p.status='success'
             WHERE c.deleted_at IS NULL
             GROUP BY c.id ORDER BY c.enrolled_count DESC LIMIT 20"
        );

        $this->render('super-admin.reports.courses', [
            'title'   => 'Course Reports',
            'courses' => $courses,
        ]);
    }

    public function attendance(Request $request): void
    {
        $this->authorize('reports.view');

        $batchId = (int)$request->input('batch_id', 0);
        $month   = $request->input('month', date('Y-m'));

        $batches = $this->db->select("SELECT id, name FROM batches WHERE deleted_at IS NULL ORDER BY name");

        $attendance = [];
        if ($batchId) {
            $attendance = $this->db->select(
                "SELECT a.*, CONCAT(u.first_name,' ',u.last_name) student_name
                 FROM attendance a JOIN users u ON u.id=a.student_id
                 WHERE a.batch_id=? AND DATE_FORMAT(a.session_date,'%Y-%m')=?
                 ORDER BY a.session_date, u.first_name",
                [$batchId, $month]
            );
        }

        $this->render('super-admin.reports.attendance', [
            'title'      => 'Attendance Reports',
            'batches'    => $batches,
            'attendance' => $attendance,
            'batchId'    => $batchId,
            'month'      => $month,
        ]);
    }

    public function exams(Request $request): void
    {
        $this->authorize('reports.view');

        $examStats = $this->db->select(
            "SELECT ex.title, COUNT(DISTINCT ea.id) attempts, AVG(ea.percentage) avg_score,
             SUM(ea.is_passed=1) passed, SUM(ea.status='completed' AND ea.is_passed=0) failed
             FROM exams ex LEFT JOIN exam_attempts ea ON ea.exam_id=ex.id
             WHERE ex.deleted_at IS NULL
             GROUP BY ex.id ORDER BY attempts DESC LIMIT 15"
        );

        $this->render('super-admin.reports.exams', [
            'title'     => 'Exam & Assessment Reports',
            'examStats' => $examStats,
        ]);
    }

    public function placement(Request $request): void
    {
        $this->authorize('reports.view');

        $totalApplications = (int)($this->db->selectOne(
            "SELECT COUNT(*) cnt FROM placement_applications"
        )['cnt'] ?? 0);

        $statusBreakdown = $this->db->select(
            "SELECT status, COUNT(*) cnt FROM placement_applications GROUP BY status"
        );

        $topCompanies = $this->db->select(
            "SELECT co.name, COUNT(pa.id) applications
             FROM placement_applications pa
             JOIN job_openings jo ON jo.id = pa.job_id
             JOIN companies co ON co.id = jo.company_id
             GROUP BY co.id ORDER BY applications DESC LIMIT 10"
        );

        $avgCtc = (float)($this->db->selectOne(
            "SELECT AVG(ctc_offered) avg_ctc FROM placement_applications WHERE status = 'accepted'"
        )['avg_ctc'] ?? 0);

        $this->render('super-admin.reports.placement', [
            'title'              => 'Placement Reports',
            'totalApplications'  => $totalApplications,
            'statusBreakdown'    => $statusBreakdown,
            'topCompanies'       => $topCompanies,
            'avgCtc'             => $avgCtc,
        ]);
    }

    public function custom(Request $request): void
    {
        $this->authorize('reports.view');

        $this->render('super-admin.reports.custom', [
            'title'        => 'Custom Report Builder',
            'reportTypes'  => [
                'students'    => 'Students',
                'enrollments' => 'Enrollments',
            ],
            'periods'      => [
                '7d'  => 'Last 7 Days',
                '30d' => 'Last 30 Days',
                '90d' => 'Last 90 Days',
                '1y'  => 'Last 1 Year',
            ],
        ]);
    }

    public function export(Request $request): void
    {
        $this->authorize('reports.view');

        $type   = $request->input('type', 'students');
        $period = $request->input('period', '30d');
        $format = $request->input('format', 'csv');
        $days   = $this->periodToDays($period);

        $data    = [];
        $headers = [];

        switch ($type) {
            case 'students':
                $headers = ['Name','Email','Phone','Role','Status','Enrolled','Joined'];
                $data    = $this->db->select(
                    "SELECT CONCAT(u.first_name,' ',u.last_name) name, u.email, u.phone, r.name role,
                     u.status, COUNT(DISTINCT e.id) enrolled, u.created_at
                     FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id
                     LEFT JOIN enrollments e ON e.user_id=u.id
                     WHERE r.slug='student' AND u.deleted_at IS NULL AND u.created_at >= DATE_SUB(NOW(),INTERVAL ? DAY)
                     GROUP BY u.id ORDER BY u.created_at DESC",
                    [$days]
                );
                break;

            case 'enrollments':
                $headers = ['Student','Email','Course','Status','Enrolled At','Completed At'];
                $data    = $this->db->select(
                    "SELECT CONCAT(u.first_name,' ',u.last_name), u.email, c.title, e.status, e.enrolled_at, e.completed_at
                     FROM enrollments e JOIN users u ON u.id=e.user_id JOIN courses c ON c.id=e.course_id
                     WHERE e.enrolled_at >= DATE_SUB(NOW(),INTERVAL ? DAY) ORDER BY e.enrolled_at DESC",
                    [$days]
                );
                break;
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $type . '-report-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($data as $row) fputcsv($out, array_values($row));
        fclose($out);

        AuditLogger::log('report_exported', 'reports', null, null, ['type' => $type, 'period' => $period]);
        exit;
    }

    private function periodToDays(string $period): int
    {
        return match($period) {
            '7d'  => 7,
            '90d' => 90,
            '1y'  => 365,
            default => 30,
        };
    }
}
