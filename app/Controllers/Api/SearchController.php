<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;

class SearchController
{
    public function index(Request $request): void
    {
        if (!Auth::check()) { $this->json(['success'=>false,'message'=>'Unauthenticated'], 401); return; }

        $query = trim($request->get('q', ''));
        if (strlen($query) < 2) {
            $this->json(['success'=>true,'data'=>[]]);
            return;
        }

        $db     = Database::getInstance();
        $like   = "%{$query}%";
        $results = [];

        // Courses
        $courses = $db->fetchAll(
            "SELECT id, title, 'course' as type, '/super-admin/courses' as base_url
             FROM courses WHERE title LIKE ? AND deleted_at IS NULL LIMIT 5",
            [$like]
        );
        foreach ($courses as $c) {
            $results[] = [
                'id'    => $c['id'],
                'type'  => 'Course',
                'label' => $c['title'],
                'url'   => "/super-admin/courses/{$c['id']}/edit",
                'icon'  => 'fa-book',
            ];
        }

        // Users
        $users = $db->fetchAll(
            "SELECT id, CONCAT(first_name,' ',last_name) as name, email, role_id
             FROM users WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?) AND deleted_at IS NULL LIMIT 5",
            [$like, $like, $like]
        );
        foreach ($users as $u) {
            $results[] = [
                'id'    => $u['id'],
                'type'  => 'User',
                'label' => $u['name'] . ' (' . $u['email'] . ')',
                'url'   => "/super-admin/users/{$u['id']}",
                'icon'  => 'fa-user',
            ];
        }

        // Batches
        $batches = $db->fetchAll(
            "SELECT id, name FROM batches WHERE name LIKE ? AND deleted_at IS NULL LIMIT 3",
            [$like]
        );
        foreach ($batches as $b) {
            $results[] = [
                'id'    => $b['id'],
                'type'  => 'Batch',
                'label' => $b['name'],
                'url'   => "/super-admin/batches",
                'icon'  => 'fa-layer-group',
            ];
        }

        // Support tickets
        $tickets = $db->fetchAll(
            "SELECT st.id, st.subject, u.first_name, u.last_name
             FROM support_tickets st
             LEFT JOIN users u ON u.id = st.user_id
             WHERE st.subject LIKE ? LIMIT 3",
            [$like]
        );
        foreach ($tickets as $t) {
            $results[] = [
                'id'    => $t['id'],
                'type'  => 'Ticket',
                'label' => $t['subject'],
                'url'   => "/super-admin/support/{$t['id']}",
                'icon'  => 'fa-ticket-alt',
            ];
        }

        $this->json(['success'=>true,'data'=>$results,'query'=>$query]);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
