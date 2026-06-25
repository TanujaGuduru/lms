<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

/**
 * Support System — docs/student-module/04g-apis-completion-growth.md.
 * Pure CRUD against the existing Admin-panel `support_tickets`/
 * `ticket_replies`/`support_categories` tables — no cloud adaptation
 * needed anywhere in this controller.
 */
class SupportController extends Controller
{
    public function categories(Request $request): void
    {
        $rows = $this->db->select('SELECT id, name, description, icon FROM support_categories WHERE is_active = 1 ORDER BY name');
        $this->success($rows);
    }

    public function index(Request $request): void
    {
        $userId = (int) $this->currentUser()['id'];
        $rows = $this->db->select(
            'SELECT id, ticket_number, subject, priority, status, created_at, resolved_at FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC',
            [$userId]
        );
        $this->success($rows);
    }

    public function create(Request $request): void
    {
        $userId = (int) $this->currentUser()['id'];
        $subject = trim((string) $request->input('subject', ''));
        $description = trim((string) $request->input('description', ''));
        $categoryId = $request->input('category_id');

        if (! $subject || ! $description) {
            $this->fail('subject and description are required.', ['subject' => ['required'], 'description' => ['required']]);
        }

        $ticketNumber = 'SUP-' . date('Y') . '-' . str_pad((string) $this->nextTicketSequence(), 5, '0', STR_PAD_LEFT);

        $id = $this->db->insertInto('support_tickets', [
            'ticket_number' => $ticketNumber,
            'user_id' => $userId,
            'category_id' => $categoryId ?: null,
            'subject' => $subject,
            'description' => $description,
            'priority' => 'medium',
            'status' => 'open',
            'channel' => 'web',
        ]);

        $this->success(['id' => (int) $id, 'ticket_number' => $ticketNumber, 'status' => 'open'], [], 201);
    }

    public function show(Request $request, string $id): void
    {
        $ticket = $this->ownTicket($id);

        // Internal staff notes never cross into the student/parent-facing
        // API, full stop, regardless of who the requester is (04g's explicit rule).
        $replies = $this->db->select(
            "SELECT tr.id, tr.user_id, tr.message, tr.attachments, tr.created_at
             FROM ticket_replies tr WHERE tr.ticket_id = ? AND tr.is_internal_note = 0 ORDER BY tr.created_at",
            [$id]
        );

        $this->success([
            'id' => (int) $ticket['id'],
            'ticket_number' => $ticket['ticket_number'],
            'subject' => $ticket['subject'],
            'description' => $ticket['description'],
            'priority' => $ticket['priority'],
            'status' => $ticket['status'],
            'first_response_at' => $ticket['first_response_at'],
            'resolved_at' => $ticket['resolved_at'],
            'satisfaction_rating' => $ticket['satisfaction_rating'] !== null ? (int) $ticket['satisfaction_rating'] : null,
            'replies' => array_map(fn (array $r) => [
                'id' => (int) $r['id'],
                'user_id' => (int) $r['user_id'],
                'message' => $r['message'],
                'attachments' => $r['attachments'] ? json_decode($r['attachments'], true) : null,
                'created_at' => $r['created_at'],
            ], $replies),
        ]);
    }

    public function addReply(Request $request, string $id): void
    {
        $userId = (int) $this->currentUser()['id'];
        $ticket = $this->ownTicket($id);

        $message = trim((string) $request->input('message', ''));
        if (! $message) {
            $this->fail('message is required.', ['message' => ['required']]);
        }

        $replyId = $this->db->insertInto('ticket_replies', [
            'ticket_id' => $ticket['id'],
            'user_id' => $userId,
            'message' => $message,
            'is_internal_note' => 0,
        ]);

        $this->success(['id' => (int) $replyId], [], 201);
    }

    public function satisfaction(Request $request, string $id): void
    {
        $ticket = $this->ownTicket($id);

        if (! in_array($ticket['status'], ['resolved', 'closed'], true)) {
            $this->fail('This ticket has not been resolved yet.', ['reason' => ['ticket_not_resolved']], 422);
        }

        $rating = (int) $request->input('satisfaction_rating', 0);
        if ($rating < 1 || $rating > 5) {
            $this->fail('satisfaction_rating must be 1-5.', ['satisfaction_rating' => ['required|integer|between:1,5']]);
        }

        $this->db->updateTable('support_tickets', [
            'satisfaction_rating' => $rating,
            'satisfaction_feedback' => (string) $request->input('satisfaction_feedback', ''),
        ], 'id = ?', [$ticket['id']]);

        $this->success(true);
    }

    private function nextTicketSequence(): int
    {
        return (int) $this->db->fetchOne("SELECT COUNT(*) + 1 AS n FROM support_tickets WHERE ticket_number LIKE ?", ['SUP-' . date('Y') . '-%'])['n'];
    }

    private function ownTicket(string $id): array
    {
        $userId = (int) $this->currentUser()['id'];
        $ticket = $this->db->fetchOne('SELECT * FROM support_tickets WHERE id = ? AND user_id = ?', [$id, $userId]);

        if (! $ticket) {
            $this->fail('No such ticket.', ['reason' => ['not_found']], 404);
        }

        return $ticket;
    }
}
