<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;

class LiveClassController extends Controller
{
    private const MAX_OCCURRENCES = 52; // safety cap so a far-future end date can't generate a runaway number of rows

    public function index(Request $request): void
    {
        $this->authorize('batches.view');

        $where  = ['1=1'];
        $params = [];

        if ($batchId = $request->input('batch_id')) {
            $where[]  = 'lc.batch_id = ?';
            $params[] = $batchId;
        }
        if ($status = $request->input('status')) {
            $where[]  = 'lc.status = ?';
            $params[] = $status;
        }

        $page   = max(1, (int)$request->input('page', 1));
        $result = $this->db->paginate(
            "SELECT lc.*, b.name batch_name, CONCAT(u.first_name,' ',u.last_name) teacher_name
             FROM live_classes lc
             JOIN batches b ON b.id = lc.batch_id
             JOIN users u ON u.id = lc.teacher_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY lc.start_datetime DESC",
            $params, $page, 20
        );

        $stats = $this->db->selectOne(
            "SELECT COUNT(*) total,
             SUM(status='scheduled' AND start_datetime >= NOW()) upcoming,
             SUM(status='completed') completed,
             SUM(status='cancelled') cancelled
             FROM live_classes"
        ) ?: [];

        $batches  = $this->db->select("SELECT id, name FROM batches WHERE deleted_at IS NULL AND status != 'completed' ORDER BY name");
        $teachers = $this->db->select(
            "SELECT u.id, CONCAT(u.first_name,' ',u.last_name) name FROM users u
             JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id
             WHERE r.slug='teacher' AND u.status='active' ORDER BY u.first_name"
        );

        $this->render('super-admin.live-classes.index', [
            'title'    => 'Live Classes',
            'classes'  => $result['data'],
            'meta'     => $result,
            'stats'    => $stats,
            'batches'  => $batches,
            'teachers' => $teachers,
            'filters'  => $request->only(['batch_id', 'status']),
        ]);
    }

    public function store(Request $request): void
    {
        $this->authorize('batches.update');

        $data = $this->validate($request, [
            'batch_id'         => 'required|integer',
            'teacher_id'       => 'required|integer',
            'title'            => 'required|min:3|max:200',
            'start_date'       => 'required|date',
            'start_time'       => 'required',
            'duration_minutes' => 'required|integer|min_val:10',
            'recurrence_rule'  => 'required|in:none,daily,weekly',
        ]);

        $batch = $this->db->selectOne("SELECT name FROM batches WHERE id = ? AND deleted_at IS NULL", [$data['batch_id']]);
        if (!$batch) $this->error('Batch not found.', 404);

        $recurrenceEnd = $request->input('recurrence_end_date') ?: null;
        if ($data['recurrence_rule'] !== 'none' && !$recurrenceEnd) {
            $this->error('An end date is required for a recurring class.');
        }

        $dates       = $this->buildOccurrenceDates($data['start_date'], $data['recurrence_rule'], $recurrenceEnd);
        $description = (string) $request->input('description', '');
        $createdIds  = [];
        $seriesId    = null;

        foreach ($dates as $date) {
            $id = $this->db->insert(
                "INSERT INTO live_classes
                 (batch_id, teacher_id, title, description, platform, join_url, start_datetime,
                  duration_minutes, recurrence_rule, recurrence_end_date, parent_class_id, status, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?, 'scheduled', NOW())",
                [
                    $data['batch_id'], $data['teacher_id'], $data['title'], $description,
                    'custom',
                    // Placeholder — this build's actual join flow is the Student
                    // Portal's own in-app WebRTC classroom (peer-to-peer signaling
                    // over the shared DB), not an external meeting link. join_url
                    // is NOT NULL on the schema but nothing currently reads it.
                    'in-app',
                    "{$date} {$data['start_time']}:00",
                    $data['duration_minutes'],
                    $data['recurrence_rule'], $recurrenceEnd, $seriesId,
                ]
            );
            $createdIds[] = $id;
            $seriesId ??= $id; // first occurrence anchors the rest of the series
        }

        AuditLogger::log('live_class_scheduled', 'live_classes', (string) $createdIds[0], null, [
            'batch_id' => $data['batch_id'], 'occurrences' => count($createdIds),
        ]);

        // Admin panel and Student Portal are separate codebases sharing only
        // the database — this outbox row is how the Student Portal's own
        // cron/process-domain-events.php learns a class was scheduled and
        // notifies parents (email + in-app) via App\Core\Notifier. Same
        // pattern already used for the existing teacher.reassigned event.
        foreach ($createdIds as $classId) {
            $this->db->insertInto('domain_events', [
                'event_type'     => 'live_class.scheduled',
                'aggregate_type' => 'live_class',
                'aggregate_id'   => $classId,
                'payload'        => json_encode(['batch_id' => $data['batch_id']]),
            ]);
        }

        $this->withFlash('success', count($createdIds) > 1
            ? count($createdIds) . ' classes scheduled.'
            : 'Class scheduled.')->redirect('/super-admin/live-classes');
    }

    public function cancel(Request $request, int $id): never
    {
        $this->authorize('batches.update');

        $class = $this->db->selectOne("SELECT * FROM live_classes WHERE id = ?", [$id]);
        if (!$class) $this->error('Class not found.', 404);

        $this->db->query("UPDATE live_classes SET status='cancelled' WHERE id=?", [$id]);
        AuditLogger::log('live_class_cancelled', 'live_classes', (string) $id, $class);

        $this->success(null, 'Class cancelled.');
    }

    /** @return list<string> Y-m-d dates, capped at MAX_OCCURRENCES */
    private function buildOccurrenceDates(string $startDate, string $rule, ?string $endDate): array
    {
        if ($rule === 'none' || !$endDate) {
            return [$startDate];
        }

        $dates  = [];
        $cursor = new \DateTimeImmutable($startDate);
        $end    = new \DateTimeImmutable($endDate);
        $step   = $rule === 'daily' ? '+1 day' : '+1 week';

        while ($cursor <= $end && count($dates) < self::MAX_OCCURRENCES) {
            $dates[]= $cursor->format('Y-m-d');
            $cursor = $cursor->modify($step);
        }

        return $dates;
    }
}
