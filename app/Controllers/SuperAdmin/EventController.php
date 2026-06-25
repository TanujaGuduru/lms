<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;

class EventController extends Controller
{
    public function index(Request $request): void
    {
        $this->authorize('events.view');

        $where  = ['e.deleted_at IS NULL'];
        $params = [];

        if ($s = $request->input('search')) {
            $where[] = "e.title LIKE ?"; $params[] = "%{$s}%";
        }
        if ($type = $request->input('type')) {
            $where[] = "e.type = ?"; $params[] = $type;
        }

        $page   = max(1, (int)$request->input('page', 1));
        $result = $this->db->paginate(
            "SELECT e.*, COUNT(DISTINCT er.id) registrations,
             CONCAT(u.first_name,' ',u.last_name) creator_name
             FROM events e
             LEFT JOIN event_registrations er ON er.event_id=e.id
             LEFT JOIN users u ON u.id=e.created_by
             WHERE " . implode(' AND ', $where) . "
             GROUP BY e.id ORDER BY e.start_datetime DESC",
            $params, $page, 20
        );

        $stats = $this->db->selectOne(
            "SELECT COUNT(*) total,
             SUM(start_datetime > NOW()) upcoming,
             SUM(start_datetime <= NOW() AND end_datetime >= NOW()) live,
             SUM(end_datetime < NOW()) past
             FROM events WHERE deleted_at IS NULL"
        ) ?: [];

        $this->render('super-admin.events.index', [
            'title'   => 'Events',
            'events'  => $result['data'],
            'meta'    => $result,
            'stats'   => $stats,
            'filters' => $request->only(['search','type']),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize('events.create');
        $this->render('super-admin.events.create', ['title' => 'New Event']);
    }

    public function store(Request $request): void
    {
        $this->authorize('events.create');

        $data = $this->validate($request, [
            'title'           => 'required|min:3|max:200',
            'type'            => 'required|in:webinar,workshop,seminar,hackathon,other',
            'start_datetime'  => 'required|date',
            'end_datetime'    => 'required|date',
            'max_participants' => 'integer|min_val:1',
        ]);

        if (!empty($_FILES['banner']['tmp_name']) && is_uploaded_file($_FILES['banner']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'], true)) {
                $filename = 'event_' . time() . '.' . $ext;
                $dest     = PUBLIC_PATH . '/uploads/events/' . $filename;
                @mkdir(dirname($dest), 0755, true);
                if (move_uploaded_file($_FILES['banner']['tmp_name'], $dest)) {
                    $data['thumbnail'] = '/uploads/events/' . $filename;
                }
            }
        }

        $data['created_by'] = $this->currentUser()['id'];
        $data['is_paid']    = $request->has('is_free') ? 0 : 1;

        $id = $this->db->insert(
            "INSERT INTO events (title,type,description,thumbnail,start_datetime,end_datetime,venue,meeting_link,meeting_password,max_participants,registration_deadline,is_paid,fee,certificate_on_completion,status,created_by,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())",
            [$data['title'],$data['type'],$request->input('description',''),$data['thumbnail']??null,
             $data['start_datetime'],$data['end_datetime'],$request->input('venue',''),
             $request->input('meeting_link',''),$request->input('meeting_password',''),
             $data['max_participants']??100,$request->input('registration_deadline') ?: null,
             $data['is_paid'],$request->input('price', 0) ?: 0,
             $request->has('certificate_on_completion') ? 1 : 0,
             'draft',$data['created_by']]
        );

        AuditLogger::log('event_created', 'events', (string)$id, null, ['title' => $data['title']]);
        $this->withFlash('success', "Event \"{$data['title']}\" created.")->redirect('/super-admin/events');
    }

    public function show(Request $request, int $id): void
    {
        $this->authorize('events.view');

        $event = $this->db->selectOne("SELECT * FROM events WHERE id=? AND deleted_at IS NULL", [$id]);
        if (!$event) $this->withFlash('error', 'Event not found.')->redirect('/super-admin/events');

        $registrations = $this->db->select(
            "SELECT er.*, CONCAT(u.first_name,' ',u.last_name) student_name, u.email, u.avatar
             FROM event_registrations er JOIN users u ON u.id=er.user_id
             WHERE er.event_id=? ORDER BY er.registered_at DESC",
            [$id]
        );

        $this->render('super-admin.events.show', [
            'title'         => $event['title'],
            'event'         => $event,
            'registrations' => $registrations,
        ]);
    }

    public function edit(Request $request, int $id): void
    {
        $this->authorize('events.update');
        $event = $this->db->selectOne("SELECT * FROM events WHERE id=? AND deleted_at IS NULL", [$id]);
        if (!$event) $this->withFlash('error', 'Not found.')->redirect('/super-admin/events');
        $this->render('super-admin.events.edit', ['title' => 'Edit Event', 'event' => $event]);
    }

    public function update(Request $request, int $id): void
    {
        $this->authorize('events.update');
        $event = $this->db->selectOne("SELECT * FROM events WHERE id=? AND deleted_at IS NULL", [$id]);
        if (!$event) $this->withFlash('error', 'Not found.')->back();

        $data = $this->validate($request, [
            'title'          => 'required|min:3|max:200',
            'start_datetime' => 'required|date',
            'end_datetime'   => 'required|date',
        ]);

        $this->db->query(
            "UPDATE events SET title=?,description=?,start_datetime=?,end_datetime=?,max_participants=?,meeting_link=?,meeting_password=?,venue=?,is_paid=?,updated_at=NOW() WHERE id=?",
            [$data['title'],$request->input('description',''),$data['start_datetime'],$data['end_datetime'],
             $request->input('max_participants',100),
             $request->input('meeting_link',''),$request->input('meeting_password',''),$request->input('venue',''),
             $request->has('is_free') ? 0 : 1,$id]
        );

        AuditLogger::log('event_updated', 'events', (string)$id);
        $this->withFlash('success', 'Event updated.')->redirect('/super-admin/events');
    }
}
