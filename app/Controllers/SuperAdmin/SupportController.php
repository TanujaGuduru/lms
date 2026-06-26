<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;
use App\Services\NotificationService;

class SupportController extends Controller
{
    public function index(Request $request): void
    {
        $this->authorize('support.view');

        $where  = ['t.deleted_at IS NULL'];
        $params = [];

        if ($s = $request->input('search')) {
            $where[] = "(t.subject LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ?)";
            $params  = array_merge($params, ["%{$s}%", "%{$s}%"]);
        }
        if ($status = $request->input('status')) {
            $where[] = "t.status = ?"; $params[] = $status;
        }
        if ($priority = $request->input('priority')) {
            $where[] = "t.priority = ?"; $params[] = $priority;
        }

        $page   = max(1, (int)$request->input('page', 1));
        $result = $this->db->paginate(
            "SELECT t.*, CONCAT(u.first_name,' ',u.last_name) student_name, u.avatar,
             CONCAT(a.first_name,' ',a.last_name) assignee_name,
             COUNT(DISTINCT tr.id) reply_count
             FROM support_tickets t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN users a ON a.id = t.assigned_to
             LEFT JOIN ticket_replies tr ON tr.ticket_id = t.id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY t.id ORDER BY
             CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
             t.created_at DESC",
            $params, $page, 25
        );

        $stats = $this->db->selectOne(
            "SELECT COUNT(*) total,
             SUM(status='open') open_tickets,
             SUM(status='in_progress') in_progress,
             SUM(status='resolved') resolved,
             SUM(priority='urgent') urgent
             FROM support_tickets WHERE deleted_at IS NULL"
        ) ?: [];

        $agents = $this->db->select(
            "SELECT u.id, CONCAT(u.first_name,' ',u.last_name) name FROM users u
             JOIN user_roles ur ON ur.user_id=u.id
             JOIN roles r ON r.id=ur.role_id
             WHERE r.slug IN ('admin','super_admin') AND u.status='active'
             ORDER BY u.first_name"
        );

        $this->render('super-admin.support.index', [
            'title'   => 'Support Center',
            'tickets' => $result['data'],
            'meta'    => $result,
            'stats'   => $stats,
            'agents'  => $agents,
            'filters' => $request->only(['search','status','priority']),
        ]);
    }

    public function show(Request $request, int $id): void
    {
        $this->authorize('support.view');

        $ticket = $this->db->selectOne(
            "SELECT t.*, CONCAT(u.first_name,' ',u.last_name) student_name, u.email, u.avatar,
             CONCAT(a.first_name,' ',a.last_name) assignee_name
             FROM support_tickets t
             JOIN users u ON u.id=t.user_id
             LEFT JOIN users a ON a.id=t.assigned_to
             WHERE t.id=?",
            [$id]
        );

        if (!$ticket) $this->withFlash('error', 'Ticket not found.')->redirect('/super-admin/support');

        $replies = $this->db->select(
            "SELECT tr.*, CONCAT(u.first_name,' ',u.last_name) author_name, u.avatar, u.id user_id
             FROM ticket_replies tr JOIN users u ON u.id=tr.user_id
             WHERE tr.ticket_id=? ORDER BY tr.created_at ASC",
            [$id]
        );

        $agents = $this->db->select(
            "SELECT u.id, CONCAT(u.first_name,' ',u.last_name) name FROM users u
             JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id
             WHERE r.slug IN ('admin','super_admin') ORDER BY u.first_name"
        );

        $this->render('super-admin.support.show', [
            'title'   => 'Ticket #' . $ticket['ticket_number'],
            'ticket'  => $ticket,
            'replies' => $replies,
            'agents'  => $agents,
        ]);
    }

    public function assign(Request $request, int $id): never
    {
        $this->authorize('support.update');

        $assigneeId = (int)$request->input('assignee_id');
        $ticket     = $this->db->selectOne("SELECT subject FROM support_tickets WHERE id = ?", [$id]);

        $this->db->query(
            "UPDATE support_tickets SET assigned_to=?, status='in_progress', updated_at=NOW() WHERE id=?",
            [$assigneeId ?: null, $id]
        );

        AuditLogger::log('ticket_assigned', 'support', (string)$id, null, ['assignee_id' => $assigneeId]);

        if ($assigneeId > 0 && $ticket) {
            NotificationService::send(
                $assigneeId,
                'support.assigned',
                'Support Ticket Assigned to You',
                "Ticket \"{$ticket['subject']}\" has been assigned to you.",
                ['ticket_id' => $id, 'url' => '/super-admin/support/' . $id]
            );
        }

        $this->success(null, 'Ticket assigned.');
    }

    public function reply(Request $request, int $id): never
    {
        $this->authorize('support.reply');

        $data       = $this->validate($request, ['message' => 'required|min:5']);
        $ticket     = $this->db->selectOne("SELECT user_id, subject FROM support_tickets WHERE id = ?", [$id]);
        $isInternal = $request->has('is_internal');
        $currentId  = $this->currentUser()['id'];

        $this->db->insert(
            "INSERT INTO ticket_replies (ticket_id,user_id,message,is_internal_note,created_at)
             VALUES (?,?,?,?,NOW())",
            [$id, $currentId, $data['message'], $isInternal ? 1 : 0]
        );

        $this->db->query("UPDATE support_tickets SET updated_at=NOW() WHERE id=?", [$id]);
        AuditLogger::log('ticket_replied', 'support', (string)$id);

        if (!$isInternal && $ticket && (int)$ticket['user_id'] !== $currentId) {
            NotificationService::send(
                (int)$ticket['user_id'],
                'support.reply',
                'New Reply on Your Support Ticket',
                "A staff member replied to your ticket \"{$ticket['subject']}\".",
                ['ticket_id' => $id]
            );
        }

        $this->success(null, 'Reply sent.');
    }

    public function close(Request $request, int $id): never
    {
        $this->authorize('support.update');

        $ticket = $this->db->selectOne("SELECT user_id, subject FROM support_tickets WHERE id = ?", [$id]);

        $this->db->query(
            "UPDATE support_tickets SET status='resolved', resolved_at=NOW(), resolved_by=?, updated_at=NOW() WHERE id=?",
            [$this->currentUser()['id'], $id]
        );

        AuditLogger::log('ticket_closed', 'support', (string)$id);

        if ($ticket) {
            NotificationService::send(
                (int)$ticket['user_id'],
                'support.resolved',
                'Support Ticket Resolved',
                "Your ticket \"{$ticket['subject']}\" has been marked as resolved.",
                ['ticket_id' => $id]
            );
        }

        $this->success(null, 'Ticket resolved.');
    }
}
