<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;

class FinanceController extends Controller
{
    public function index(Request $request): void
    {
        $this->authorize('finance.view');
        $this->render('super-admin.finance.index', ['title' => 'Finance & Revenue']);
    }

    public function payments(Request $request): void
    {
        $this->authorize('finance.view');

        $where  = ['1=1'];
        $params = [];

        if ($s = $request->input('search')) {
            $where[] = "(p.invoice_number LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ? OR u.email LIKE ?)";
            $params  = array_merge($params, ["%{$s}%", "%{$s}%", "%{$s}%"]);
        }
        if ($status = $request->input('status')) {
            $where[] = "p.status = ?"; $params[] = $status;
        }
        if ($gateway = $request->input('gateway')) {
            $where[] = "p.gateway = ?"; $params[] = $gateway;
        }
        if ($dateFrom = $request->input('date_from')) {
            $where[] = "DATE(p.paid_at) >= ?"; $params[] = $dateFrom;
        }
        if ($dateTo = $request->input('date_to')) {
            $where[] = "DATE(p.paid_at) <= ?"; $params[] = $dateTo;
        }

        $page   = max(1, (int)$request->input('page', 1));
        $result = $this->db->paginate(
            "SELECT p.*, CONCAT(u.first_name,' ',u.last_name) student_name, u.email student_email, c.title course_title
             FROM payments p
             LEFT JOIN users u ON u.id = p.user_id
             LEFT JOIN courses c ON c.id = p.course_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY p.created_at DESC",
            $params, $page, 25
        );

        $this->render('super-admin.finance.payments', [
            'title'    => 'All Payments',
            'payments' => $result['data'],
            'meta'     => $result,
            'filters'  => $request->only(['search','status','gateway','date_from','date_to']),
        ]);
    }

    public function feeStructures(Request $request): void
    {
        $this->authorize('finance.view');

        $structures = $this->db->select(
            "SELECT fs.*, c.title course_title FROM fee_structures fs
             LEFT JOIN courses c ON c.id = fs.course_id
             WHERE fs.deleted_at IS NULL ORDER BY fs.created_at DESC"
        );
        $courses = $this->db->select("SELECT id, title FROM courses WHERE status='published' AND deleted_at IS NULL ORDER BY title");

        $this->render('super-admin.finance.fee-structures', [
            'title'      => 'Fee Structures',
            'structures' => $structures,
            'courses'    => $courses,
        ]);
    }

    public function createFeeStructure(Request $request): void
    {
        $this->authorize('finance.update');

        $data = $this->validate($request, [
            'name'          => 'required|min:3|max:150',
            'course_id'     => 'integer',
            'amount'        => 'required|numeric|min_val:0',
            'gst_percentage'=> 'numeric|min_val:0|max_val:100',
        ]);

        $data['created_by'] = $this->currentUser()['id'];
        $data['is_active']  = 1;

        $id = $this->db->insert(
            "INSERT INTO fee_structures (name,course_id,amount,gst_percentage,is_active,created_by,created_at)
             VALUES (?,?,?,?,?,?,NOW())",
            [$data['name'],$data['course_id']??null,$data['amount'],$data['gst_percentage']??0,$data['is_active'],$data['created_by']]
        );

        AuditLogger::log('fee_structure_created', 'finance', (string)$id, null, $data);
        $this->withFlash('success', 'Fee structure created.')->redirect('/super-admin/finance/fee-structures');
    }

    public function reports(Request $request): void
    {
        $this->authorize('finance.view');

        $format = $request->input('format');
        $period = $request->input('period', 'month');

        if ($format === 'csv' || $format === 'excel') {
            $this->exportReport($period);
        }

        $this->render('super-admin.finance.reports', [
            'title' => 'Finance Reports',
        ]);
    }

    private function exportReport(string $period): never
    {
        $conditions = match($period) {
            'today' => "DATE(paid_at) = CURDATE()",
            'week'  => "paid_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'year'  => "paid_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)",
            default => "paid_at >= DATE_FORMAT(NOW(),'%Y-%m-01')",
        };

        $payments = $this->db->select(
            "SELECT p.invoice_number, CONCAT(u.first_name,' ',u.last_name) student, u.email,
             c.title course, p.total_amount, p.gateway, p.payment_method, p.status, p.paid_at
             FROM payments p LEFT JOIN users u ON u.id=p.user_id LEFT JOIN courses c ON c.id=p.course_id
             WHERE p.status='success' AND {$conditions} ORDER BY p.paid_at DESC"
        );

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="finance-report-' . $period . '-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Invoice','Student','Email','Course','Amount','Gateway','Method','Status','Date']);
        foreach ($payments as $p) {
            fputcsv($out, array_values($p));
        }
        fclose($out);
        AuditLogger::log('finance_report_exported', 'finance', null, null, ['period' => $period]);
        exit;
    }
}
