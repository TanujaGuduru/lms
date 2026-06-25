<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

/**
 * Video Lecture Library — docs/student-module/04c-apis-classroom-content.md.
 */
class LessonController extends Controller
{
    public function courseModules(Request $request, string $courseId): void
    {
        $studentId = (int) $this->currentUser()['id'];

        if (! $this->isEnrolled($studentId, (int) $courseId)) {
            $this->fail('No such course.', ['reason' => ['not_found']], 404);
        }

        $modules = $this->db->select(
            "SELECT * FROM course_modules WHERE course_id = ? AND is_published = 1 ORDER BY sort_order",
            [$courseId]
        );

        foreach ($modules as &$module) {
            $module['lessons'] = $this->db->select(
                "SELECT l.id, l.title, l.type, l.video_duration, lp.status, lp.progress_seconds
                 FROM lessons l
                 LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = ?
                 WHERE l.module_id = ? AND l.is_published = 1 AND l.deleted_at IS NULL
                 ORDER BY l.sort_order",
                [$studentId, $module['id']]
            );
            $module['id'] = (int) $module['id'];
        }

        $this->success($modules);
    }

    public function show(Request $request, string $lessonId): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $lesson = $this->accessibleLesson($studentId, $lessonId);

        $this->success([
            'id' => (int) $lesson['id'],
            'title' => $lesson['title'],
            'type' => $lesson['type'],
            'video_url' => $lesson['video_url'],
            'video_duration' => (int) $lesson['video_duration'],
            'subtitle_url' => $lesson['subtitle_url'],
            'transcript_text' => $lesson['transcript_text'],
        ]);
    }

    public function progress(Request $request, string $lessonId): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $lesson = $this->accessibleLesson($studentId, $lessonId);

        $enrollment = $this->db->fetchOne(
            "SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? AND status = 'active'",
            [$studentId, $lesson['course_id']]
        );
        if (! $enrollment) {
            $this->fail('Not enrolled in this course.', ['reason' => ['not_enrolled']], 403);
        }

        $progressSeconds = (int) $request->input('progress_seconds', 0);
        $threshold = $lesson['video_duration'] > 0 ? $lesson['video_duration'] * 0.9 : null;
        $completed = $threshold !== null && $progressSeconds >= $threshold;

        $existing = $this->db->fetchOne(
            'SELECT * FROM lesson_progress WHERE enrollment_id = ? AND lesson_id = ?',
            [$enrollment['id'], $lessonId]
        );

        $data = [
            'progress_seconds' => $progressSeconds,
            'status' => $completed ? 'completed' : 'in_progress',
            'last_accessed_at' => date('Y-m-d H:i:s'),
        ];
        if ($completed) {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }

        if ($existing) {
            $this->db->updateTable('lesson_progress', $data, 'id = ?', [$existing['id']]);
        } else {
            $this->db->insertInto('lesson_progress', array_merge($data, [
                'enrollment_id' => $enrollment['id'],
                'lesson_id' => $lessonId,
                'user_id' => $studentId,
            ]));
        }

        $this->success(['status' => $data['status'], 'completed' => $completed]);
    }

    /**
     * docs/student-module/04i. Each event applies the same completion-
     * threshold logic as progress() above, using the client-recorded
     * `recorded_at` as when it actually happened — explicitly *not* treated
     * as live for anything time-sensitive (attendance, a live quiz
     * response), only progress/completion state is back-filled this way.
     * Only `content_type='video_lesson'` is handled — the doc's own
     * example never shows another content type for this endpoint.
     * Monotonic: an offline event never overwrites *more* progress than
     * what's already recorded, since events can arrive out of order
     * relative to whatever already synced while online.
     */
    public function syncOffline(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $events = $request->input('events', []);

        $applied = 0;
        foreach ($events as $event) {
            if (($event['content_type'] ?? '') !== 'video_lesson') {
                continue;
            }
            if ($this->applyOfflineLessonEvent($studentId, $event)) {
                $applied++;
            }
        }

        $this->success(['events_received' => count($events), 'events_applied' => $applied]);
    }

    private function applyOfflineLessonEvent(int $studentId, array $event): bool
    {
        $lessonId = (int) ($event['content_id'] ?? 0);
        $progressSeconds = (int) ($event['progress_seconds'] ?? 0);
        $recordedAt = $event['recorded_at'] ?? null;

        if (! $lessonId || ! $recordedAt) {
            return false;
        }

        $lesson = $this->db->fetchOne('SELECT course_id, video_duration FROM lessons WHERE id = ? AND is_published = 1 AND deleted_at IS NULL', [$lessonId]);
        if (! $lesson) {
            return false;
        }

        $enrollment = $this->db->fetchOne("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? AND status = 'active'", [$studentId, $lesson['course_id']]);
        if (! $enrollment) {
            return false;
        }

        $existing = $this->db->fetchOne('SELECT * FROM lesson_progress WHERE enrollment_id = ? AND lesson_id = ?', [$enrollment['id'], $lessonId]);
        if ($existing && (int) $existing['progress_seconds'] >= $progressSeconds) {
            return false; // never regress already-recorded progress with stale offline data.
        }

        $threshold = $lesson['video_duration'] > 0 ? $lesson['video_duration'] * 0.9 : null;
        $completed = $threshold !== null && $progressSeconds >= $threshold;

        $data = [
            'progress_seconds' => $progressSeconds,
            'status' => $completed ? 'completed' : 'in_progress',
            'last_accessed_at' => $recordedAt,
        ];
        if ($completed) {
            $data['completed_at'] = $recordedAt;
        }

        if ($existing) {
            $this->db->updateTable('lesson_progress', $data, 'id = ?', [$existing['id']]);
        } else {
            $this->db->insertInto('lesson_progress', array_merge($data, [
                'enrollment_id' => $enrollment['id'],
                'lesson_id' => $lessonId,
                'user_id' => $studentId,
            ]));
        }

        return true;
    }

    public function bookmarks(Request $request, string $lessonId): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $this->accessibleLesson($studentId, $lessonId);

        $progress = $this->db->fetchOne(
            'SELECT id FROM lesson_progress WHERE lesson_id = ? AND user_id = ?',
            [$lessonId, $studentId]
        );
        if (! $progress) {
            $this->success([]);
            return;
        }

        $rows = $this->db->select(
            'SELECT * FROM lesson_bookmarks WHERE lesson_progress_id = ? ORDER BY timestamp_seconds',
            [$progress['id']]
        );
        $this->success($rows);
    }

    public function createBookmark(Request $request, string $lessonId): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $lesson = $this->accessibleLesson($studentId, $lessonId);
        $timestamp = (int) $request->input('timestamp_seconds', -1);
        $note = (string) $request->input('note', '');

        if ($timestamp < 0) {
            $this->fail('timestamp_seconds is required.', ['timestamp_seconds' => ['required']]);
        }

        $enrollment = $this->db->fetchOne(
            "SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? AND status = 'active'",
            [$studentId, $lesson['course_id']]
        );
        if (! $enrollment) {
            $this->fail('Not enrolled in this course.', ['reason' => ['not_enrolled']], 403);
        }

        $progress = $this->db->fetchOne('SELECT id FROM lesson_progress WHERE enrollment_id = ? AND lesson_id = ?', [$enrollment['id'], $lessonId]);
        if (! $progress) {
            $progressId = $this->db->insertInto('lesson_progress', [
                'enrollment_id' => $enrollment['id'],
                'lesson_id' => $lessonId,
                'user_id' => $studentId,
            ]);
        } else {
            $progressId = $progress['id'];
        }

        $bookmarkId = $this->db->insertInto('lesson_bookmarks', [
            'lesson_progress_id' => $progressId,
            'timestamp_seconds' => $timestamp,
            'note' => $note ?: null,
        ]);

        $this->success(['id' => (int) $bookmarkId], [], 201);
    }

    private function isEnrolled(int $studentId, int $courseId): bool
    {
        return (bool) $this->db->fetchOne(
            'SELECT 1 FROM enrollments WHERE user_id = ? AND course_id = ?',
            [$studentId, $courseId]
        );
    }

    private function accessibleLesson(int $studentId, string $lessonId): array
    {
        $lesson = $this->db->fetchOne(
            'SELECT l.* FROM lessons l
             JOIN enrollments e ON e.course_id = l.course_id AND e.user_id = ?
             WHERE l.id = ? AND l.deleted_at IS NULL',
            [$studentId, $lessonId]
        );

        if (! $lesson) {
            $this->fail('No such lesson.', ['reason' => ['not_found']], 404);
        }

        return $lesson;
    }
}
