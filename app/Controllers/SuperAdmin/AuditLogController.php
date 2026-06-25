<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): void
    {
        $this->authorize('audit_logs.view');

        $page    = max(1, (int)$request->input('page', 1));
        $perPage = 30;

        $where    = ['1=1'];
        $params   = [];

        if ($search = $request->input('search')) {
            $where[] = "(al.action LIKE ? OR al.module LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ?)";
            $params  = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%"]);
        }
        if ($module = $request->input('module')) {
            $where[] = "al.module = ?";
            $params[] = $module;
        }
        if ($action = $request->input('action')) {
            $where[] = "al.action = ?";
            $params[] = $action;
        }
        if ($dateFrom = $request->input('date_from')) {
            $where[] = "DATE(al.created_at) >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo = $request->input('date_to')) {
            $where[] = "DATE(al.created_at) <= ?";
            $params[] = $dateTo;
        }

        $whereStr = implode(' AND ', $where);
        $sql = "SELECT al.*, CONCAT(u.first_name,' ',u.last_name) user_name, u.avatar, u.email user_email
                FROM audit_logs al
                LEFT JOIN users u ON u.id = al.user_id
                WHERE {$whereStr}
                ORDER BY al.created_at DESC";

        $result = $this->db->paginate($sql, $params, $page, $perPage);

        // Get unique modules and actions for filter dropdowns
        $modules = $this->db->select("SELECT DISTINCT module FROM audit_logs ORDER BY module");
        $actions = $this->db->select("SELECT DISTINCT action FROM audit_logs ORDER BY action");

        $this->render('super-admin.audit-logs.index', [
            'title'   => 'Audit Logs',
            'logs'    => $result['data'],
            'meta'    => $result,
            'modules' => $modules,
            'actions' => $actions,
            'filters' => $request->only(['search','module','action','date_from','date_to']),
        ]);
    }

    public function export(Request $request): void
    {
        $this->authorize('audit_logs.view');

        $where  = ['1=1'];
        $params = [];

        if ($module = $request->input('module')) {
            $where[] = "module = ?"; $params[] = $module;
        }
        if ($dateFrom = $request->input('date_from')) {
            $where[] = "DATE(created_at) >= ?"; $params[] = $dateFrom;
        }
        if ($dateTo = $request->input('date_to')) {
            $where[] = "DATE(created_at) <= ?"; $params[] = $dateTo;
        }

        $logs = $this->db->select(
            "SELECT al.*, CONCAT(u.first_name,' ',u.last_name) user_name, u.email
             FROM audit_logs al LEFT JOIN users u ON u.id=al.user_id
             WHERE " . implode(' AND ', $where) . " ORDER BY al.created_at DESC LIMIT 5000",
            $params
        );

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="audit-logs-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','User','Action','Module','Record ID','IP','Created At']);
        foreach ($logs as $log) {
            fputcsv($out, [
                $log['id'],
                $log['user_name'] ?? 'System',
                $log['action'],
                $log['module'],
                $log['record_id'] ?? '',
                $log['ip_address'] ?? '',
                $log['created_at'],
            ]);
        }
        fclose($out);
        exit;
    }
}
