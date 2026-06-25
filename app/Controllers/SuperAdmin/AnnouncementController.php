<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;
use App\Services\NotificationService;

class AnnouncementController extends Controller
{
    public function index(Request $request): void
    {
        $this->authorize('announcements.view');
        $this->render('super-admin.announcements.index', ['title' => 'Announcement Center']);
    }

    public function create(Request $request): void
    {
        $this->authorize('announcements.create');
        $this->render('super-admin.announcements.create', ['title' => 'New Announcement']);
    }

    public function store(Request $request): void
    {
        $this->authorize('announcements.create');

        $data = $this->validate($request, [
            'title'    => 'required|min:3|max:200',
            'content'  => 'required|min:10',
            'type'     => 'required|in:general,urgent,event,maintenance,feature',
            'priority' => 'required|in:low,medium,high,critical',
        ]);

        $channels  = $request->input('channels', []);
        $audience  = $request->input('audience',  []);
        $scheduledAt = $request->input('scheduled_at');
        $status    = $scheduledAt ? 'scheduled' : ($request->input('send_now') ? 'sent' : 'draft');

        $id = $this->db->insert(
            "INSERT INTO announcements (title,content,type,priority,channels,audience,status,is_pinned,scheduled_at,created_by,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,NOW())",
            [
                $data['title'], $data['content'], $data['type'], $data['priority'],
                json_encode(is_array($channels) ? $channels : []),
                json_encode(is_array($audience) ? $audience : []),
                $status,
                $request->has('is_pinned') ? 1 : 0,
                $scheduledAt ?: null,
                $this->currentUser()['id'],
            ]
        );

        AuditLogger::log('announcement_created', 'announcements', (string)$id, null, ['title' => $data['title'], 'status' => $status]);

        if ($status === 'sent') {
            $this->dispatchAnnouncementNotifications($id, is_array($audience) ? $audience : [], $data['title'], $data['content']);
        }

        $this->withFlash('success', "Announcement \"{$data['title']}\" {$status}.")
             ->redirect('/super-admin/announcements');
    }

    public function send(Request $request, int $id): never
    {
        $this->authorize('announcements.update');

        $ann = $this->db->selectOne("SELECT * FROM announcements WHERE id = ?", [$id]);
        if (!$ann) $this->error('Announcement not found.', 404);

        $this->db->query(
            "UPDATE announcements SET status='sent', sent_at=NOW() WHERE id=?", [$id]
        );

        AuditLogger::log('announcement_sent', 'announcements', (string)$id);

        $audience = json_decode($ann['audience'] ?? '[]', true) ?: [];
        $this->dispatchAnnouncementNotifications($id, $audience, $ann['title'], $ann['content']);

        $this->success(null, 'Announcement sent successfully.');
    }

    public function edit(Request $request, int $id): void
    {
        $this->authorize('announcements.update');

        $ann = $this->db->selectOne("SELECT * FROM announcements WHERE id = ?", [$id]);
        if (!$ann) $this->withFlash('error', 'Not found.')->redirect('/super-admin/announcements');

        $this->render('super-admin.announcements.edit', [
            'title' => 'Edit Announcement',
            'ann'   => $ann,
        ]);
    }

    public function update(Request $request, int $id): void
    {
        $this->authorize('announcements.update');

        $ann = $this->db->selectOne("SELECT * FROM announcements WHERE id = ?", [$id]);
        if (!$ann) $this->withFlash('error', 'Not found.')->back();

        $data = $this->validate($request, [
            'title'   => 'required|min:3|max:200',
            'content' => 'required|min:10',
            'type'    => 'required|in:general,urgent,event,maintenance,feature',
            'priority'=> 'required|in:low,medium,high,critical',
        ]);

        $this->db->query(
            "UPDATE announcements SET title=?,content=?,type=?,priority=?,is_pinned=?,updated_at=NOW() WHERE id=?",
            [$data['title'],$data['content'],$data['type'],$data['priority'],$request->has('is_pinned')?1:0,$id]
        );

        AuditLogger::log('announcement_updated', 'announcements', (string)$id, $ann, $data);
        $this->withFlash('success', 'Announcement updated.')->redirect('/super-admin/announcements');
    }

    public function delete(Request $request, int $id): void
    {
        $this->authorize('announcements.delete');

        $ann = $this->db->selectOne("SELECT * FROM announcements WHERE id = ?", [$id]);
        if (!$ann) {
            if ((new Request())->isAjax()) $this->error('Not found.', 404);
            $this->withFlash('error', 'Not found.')->back();
        }

        $this->db->query("DELETE FROM announcements WHERE id = ?", [$id]);
        AuditLogger::log('announcement_deleted', 'announcements', (string)$id, $ann);

        if ((new Request())->isAjax()) $this->success(null, 'Deleted.');
        $this->withFlash('success', 'Announcement deleted.')->redirect('/super-admin/announcements');
    }

    private function dispatchAnnouncementNotifications(int $id, array $audience, string $title, string $content): void
    {
        if (empty($audience) || in_array('all', $audience, true)) {
            $rows = $this->db->select(
                "SELECT id FROM users WHERE status='active' AND deleted_at IS NULL"
            );
        } else {
            $in   = implode(',', array_fill(0, count($audience), '?'));
            $rows = $this->db->select(
                "SELECT DISTINCT u.id FROM users u
                 JOIN user_roles ur ON ur.user_id = u.id
                 JOIN roles r ON r.id = ur.role_id
                 WHERE r.slug IN ({$in}) AND u.status = 'active' AND u.deleted_at IS NULL",
                $audience
            );
        }

        NotificationService::broadcast(
            array_column($rows, 'id'),
            'announcement',
            $title,
            substr(strip_tags($content), 0, 120),
            ['announcement_id' => $id]
        );
    }
}
