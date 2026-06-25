<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\AiGateway;
use App\Core\AiQuota;
use App\Core\AiUsageLog;
use App\Core\Controller;
use App\Core\FileStorage;
use App\Core\Moderation;
use App\Core\Request;

/**
 * Recordings — docs/student-module/04c-apis-classroom-content.md.
 * Access control is a *live* join against `batch_students`, never a stored
 * ACL — and deliberately against the student's full enrollment *history*
 * (any batch they were ever in), not just current active membership, per
 * the doc's explicit "outside the requester's enrollment history" framing.
 * A miss is always 404, never 403, so a recording's existence is never
 * confirmed to someone not entitled to know about it.
 *
 * Recorded files live on local disk (storage/app/recordings/...), played
 * back directly as a single file — no S3, no adaptive-bitrate transcoding
 * pipeline. This platform runs entirely on GoDaddy shared hosting by
 * deliberate choice, which can't run a transcoding pipeline either; a single
 * progressively-downloaded file is the honest tradeoff, not a placeholder
 * for one.
 */
class RecordingController extends Controller
{
    public function index(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $batchId = (int) $request->input('batch_id', 0);

        if (! $batchId || ! $this->hasEverBeenInBatch($studentId, $batchId)) {
            $this->fail('No such batch.', ['reason' => ['not_found']], 404);
        }

        $rows = $this->db->select(
            'SELECT cr.id, cr.processing_status, cr.thumbnail_url, cr.duration_seconds, cr.available_at, lc.title
             FROM class_recordings cr JOIN live_classes lc ON lc.id = cr.live_class_id
             WHERE lc.batch_id = ? ORDER BY lc.start_datetime DESC',
            [$batchId]
        );

        $this->success(array_map(fn (array $r) => [
            'id' => (int) $r['id'],
            'title' => $r['title'],
            'processing_status' => $r['processing_status'],
            'thumbnail_url' => $r['thumbnail_url'],
            'duration_seconds' => (int) $r['duration_seconds'],
            'available_at' => $r['available_at'],
        ], $rows));
    }

    public function show(Request $request, string $id): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $recording = $this->accessibleRecording($studentId, $id);

        if ($recording['processing_status'] !== 'completed') {
            $this->success([
                'id' => (int) $recording['id'],
                'processing_status' => $recording['processing_status'],
                'available_at' => $recording['available_at'],
            ]);
            return;
        }

        $view = $this->db->fetchOne(
            'SELECT last_position_seconds FROM recording_views WHERE recording_id = ? AND student_id = ?',
            [$recording['id'], $studentId]
        );

        $this->success([
            'id' => (int) $recording['id'],
            'processing_status' => 'completed',
            // Signed for the same reason material downloads are — the video
            // file itself lives outside the public webroot on local disk;
            // this URL is the only way to actually reach it, and expires.
            'processed_video_url' => FileStorage::signedUrl($this->relativePathFor($recording['processed_video_url']), 3600),
            'thumbnail_url' => $recording['thumbnail_url'],
            'duration_seconds' => (int) $recording['duration_seconds'],
            'transcript_available' => $recording['transcript_status'] === 'completed',
            'last_position_seconds' => (int) ($view['last_position_seconds'] ?? 0),
        ]);
    }

    public function progress(Request $request, string $id): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $recording = $this->accessibleRecording($studentId, $id);
        $position = (int) $request->input('position_seconds', 0);

        $existing = $this->db->fetchOne(
            'SELECT * FROM recording_views WHERE recording_id = ? AND student_id = ?',
            [$recording['id'], $studentId]
        );

        $watchedSeconds = max($existing['watched_seconds'] ?? 0, $position);
        $completed = $recording['duration_seconds'] > 0 && $watchedSeconds >= $recording['duration_seconds'] * 0.9;

        if ($existing) {
            $this->db->updateTable('recording_views', [
                'last_position_seconds' => $position,
                'watched_seconds' => $watchedSeconds,
                'completed' => $completed ? 1 : 0,
            ], 'id = ?', [$existing['id']]);
        } else {
            $this->db->insertInto('recording_views', [
                'recording_id' => $recording['id'],
                'student_id' => $studentId,
                'last_position_seconds' => $position,
                'watched_seconds' => $watchedSeconds,
                'completed' => $completed ? 1 : 0,
            ]);
        }

        $this->success(['status' => $completed ? 'completed' : 'in_progress', 'completed' => $completed]);
    }

    public function bookmarks(Request $request, string $id): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $recording = $this->accessibleRecording($studentId, $id);

        $rows = $this->db->select(
            'SELECT * FROM recording_bookmarks WHERE recording_id = ? AND student_id = ? ORDER BY timestamp_seconds',
            [$recording['id'], $studentId]
        );

        $this->success($rows);
    }

    public function createBookmark(Request $request, string $id): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $recording = $this->accessibleRecording($studentId, $id);
        $timestamp = (int) $request->input('timestamp_seconds', -1);
        $label = (string) $request->input('label', '');

        if ($timestamp < 0) {
            $this->fail('timestamp_seconds is required.', ['timestamp_seconds' => ['required']]);
        }

        $bookmarkId = $this->db->insertInto('recording_bookmarks', [
            'student_id' => $studentId,
            'recording_id' => $recording['id'],
            'timestamp_seconds' => $timestamp,
            'label' => $label ?: null,
        ]);

        $this->success(['id' => (int) $bookmarkId], [], 201);
    }

    /**
     * "Recording 32:15 — explains recursion" becomes a permanent, searchable
     * note rather than just sitting in the player's bookmark list (03i).
     */
    public function saveBookmarkToNote(Request $request, string $id, string $bookmarkId): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $recording = $this->accessibleRecording($studentId, $id);

        $bookmark = $this->db->fetchOne(
            'SELECT * FROM recording_bookmarks WHERE id = ? AND recording_id = ? AND student_id = ?',
            [$bookmarkId, $recording['id'], $studentId]
        );
        if (! $bookmark) {
            $this->fail('No such bookmark.', ['reason' => ['not_found']], 404);
        }

        $noteId = (int) $request->input('note_id', 0);
        $minutes = (int) floor($bookmark['timestamp_seconds'] / 60);
        $seconds = $bookmark['timestamp_seconds'] % 60;
        $entry = sprintf("Recording %d:%02d — %s\n", $minutes, $seconds, $bookmark['label'] ?: '');

        if ($noteId) {
            $note = $this->db->fetchOne('SELECT * FROM notes WHERE id = ? AND student_id = ?', [$noteId, $studentId]);
            if (! $note) {
                $this->fail('No such note.', ['reason' => ['not_found']], 404);
            }
            $this->db->updateTable('notes', ['content' => $note['content'] . "\n" . $entry], 'id = ?', [$noteId]);
        } else {
            $noteId = (int) $this->db->insertInto('notes', [
                'student_id' => $studentId,
                'title' => 'Recording bookmarks',
                'content' => $entry,
            ]);
        }

        $this->db->updateTable('recording_bookmarks', ['linked_note_id' => $noteId], 'id = ?', [$bookmark['id']]);

        $this->success(['note_id' => $noteId]);
    }

    private const MAP_REDUCE_MAX_CHUNKS = 9;

    /**
     * One-click, never automatic per class — every class auto-spawning a
     * summary would clutter the notebook with notes nobody asked for
     * (04h's explicit reasoning). Only callable once the transcript is ready.
     *
     * docs/student-module/05c §2 — map-reduce, not a single pass: a 60-90
     * minute transcript truncated to fit one call (the previous
     * implementation, `mb_substr(..., 0, 12000)`) silently lost everything
     * past the cutoff. Each chunk is summarized independently on the fast
     * tier (the "map"), then one deep-tier call reorganizes the chunk
     * summaries by topic rather than by chunk boundary (the "reduce") —
     * a topic discussed across two chunks should read as one section, not
     * two disconnected bullet lists glued together.
     *
     * Timestamps are a real limitation, stated plainly: `transcript_text`
     * is plain text with no per-word timing (no real speech-to-text
     * pipeline exists in this build, per NoteController's docblock on why
     * voice-transcribe isn't implemented either). Each chunk's start time
     * is approximated proportionally from `duration_seconds` and the
     * chunk's position in the raw text — an estimate, not the precise
     * per-word timestamp a real transcription service would provide.
     */
    public function generateNotes(Request $request, string $id): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $recording = $this->accessibleRecording($studentId, $id);

        if ($recording['transcript_status'] !== 'completed' || ! $recording['transcript_text']) {
            $this->fail('The transcript for this recording is not ready yet.', ['reason' => ['transcript_not_ready']], 422);
        }

        // Same shared quota/moderation gate as every other AI feature
        // (05a's "every feature... relies on" requirement) — one
        // reservation for the whole click, regardless of how many
        // underlying map/reduce calls it takes.
        if (! AiQuota::tryReserve($this->db, $studentId)) {
            $this->fail('Daily AI help limit reached.', ['reason' => 'quota_exhausted', 'resets_at' => AiQuota::resetsAt()], 429);
        }

        try {
            $chunkSummaries = $this->mapTranscriptChunks($studentId, $recording);
            $noteContent = $this->reduceChunkSummaries($studentId, $chunkSummaries);
        } catch (\Throwable $e) {
            $this->fail('The AI assistant is temporarily unavailable. Please try again shortly.', ['reason' => ['ai_gateway_error']], 503);
        }

        $noteContent = Moderation::isResponseSafe($noteContent) ? $noteContent : Moderation::fallbackMessage();
        $liveClass = $this->db->fetchOne('SELECT title FROM live_classes WHERE id = ?', [$recording['live_class_id']]);

        $noteId = $this->db->insertInto('notes', [
            'student_id' => $studentId,
            'title' => 'Notes: ' . ($liveClass['title'] ?? 'Class'),
            'content' => $noteContent,
            'linked_live_class_id' => $recording['live_class_id'],
            'is_ai_generated' => 1,
        ]);

        $note = $this->db->fetchOne('SELECT * FROM notes WHERE id = ?', [$noteId]);
        $this->success($note);
    }

    /** Map step: each chunk summarized independently on the fast tier into timestamp-tagged bullet points. */
    private function mapTranscriptChunks(int $studentId, array $recording): array
    {
        $transcript = $recording['transcript_text'];
        $durationSeconds = (int) $recording['duration_seconds'];
        $totalChars = mb_strlen($transcript);
        $chunkCount = min(self::MAP_REDUCE_MAX_CHUNKS, max(1, (int) ceil($totalChars / 3000)));
        $chunkSize = (int) ceil($totalChars / $chunkCount);

        $summaries = [];
        for ($i = 0; $i < $chunkCount; $i++) {
            $chunkText = mb_substr($transcript, $i * $chunkSize, $chunkSize);
            if (trim($chunkText) === '') {
                continue;
            }

            $startSeconds = $durationSeconds > 0 ? (int) round(($i / $chunkCount) * $durationSeconds) : null;
            $timeLabel = $startSeconds !== null ? sprintf('%d:%02d', intdiv($startSeconds, 60), $startSeconds % 60) : null;

            $result = AiGateway::complete(
                [['role' => 'user', 'content' => $chunkText]],
                'This is one segment of a class transcript' . ($timeLabel ? ", starting at approximately {$timeLabel}" : '')
                    . '. Summarize it into a few bullet points covering what was taught. ' . Moderation::SAFETY_INSTRUCTION,
                AiGateway::tierFor('notebook.generate_notes_map'),
                'notebook_generate_notes_map_v1'
            );
            $this->logUsage($studentId, 'notebook.generate_notes_map', $result);

            $summaries[] = ($timeLabel ? "[{$timeLabel}] " : '') . $result['content'];
        }

        return $summaries;
    }

    /** Reduce step: one deep-tier call reorganizing every chunk's bullets by topic, preserving each point's timestamp tag. */
    private function reduceChunkSummaries(int $studentId, array $chunkSummaries): string
    {
        if (empty($chunkSummaries)) {
            throw new \RuntimeException('Transcript produced no summarizable content.');
        }

        $result = AiGateway::complete(
            [['role' => 'user', 'content' => implode("\n\n", $chunkSummaries)]],
            'These are timestamp-tagged bullet-point summaries of consecutive segments of a class transcript. '
                . 'Organize by concept covered, not by time order — a topic discussed across two segments should read as one '
                . 'coherent section, not two separate lists. Preserve the [MM:SS] timestamp tag next to each point so the '
                . 'student can jump back to that moment in the recording. Write clear, well-organized study notes (headings + bullet points). '
                . Moderation::SAFETY_INSTRUCTION,
            AiGateway::tierFor('notebook.generate_notes'),
            'notebook_generate_notes_reduce_v1'
        );
        $this->logUsage($studentId, 'notebook.generate_notes', $result);

        return $result['content'];
    }

    private function logUsage(int $studentId, string $feature, array $gatewayResult): void
    {
        $costUsd = AiGateway::estimateCostUsd($gatewayResult['model'], $gatewayResult['tokens_input'], $gatewayResult['tokens_output']);
        AiUsageLog::record($this->db, $studentId, $feature, $gatewayResult, $costUsd);
        AiQuota::finalize($this->db, $studentId, $gatewayResult['tokens_input'] + $gatewayResult['tokens_output'], $costUsd);
    }

    public function search(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $query = trim((string) $request->input('q', ''));

        if (mb_strlen($query) < 2) {
            $this->fail('q must be at least 2 characters.', ['q' => ['min:2']]);
        }

        $rows = $this->db->select(
            "SELECT cr.id, lc.title, MATCH(cr.transcript_text) AGAINST (? IN NATURAL LANGUAGE MODE) AS relevance
             FROM class_recordings cr
             JOIN live_classes lc ON lc.id = cr.live_class_id
             JOIN batch_students bs ON bs.batch_id = lc.batch_id AND bs.student_id = ?
             WHERE cr.transcript_status = 'completed' AND MATCH(cr.transcript_text) AGAINST (? IN NATURAL LANGUAGE MODE)
             ORDER BY relevance DESC LIMIT 20",
            [$query, $studentId, $query]
        );

        $this->success(array_map(fn (array $r) => [
            'id' => (int) $r['id'],
            'title' => $r['title'],
        ], $rows));
    }

    /** Mirrors MaterialController's helper — see its docblock. */
    private function relativePathFor(?string $fileUrl): string
    {
        return ltrim((string) parse_url((string) $fileUrl, PHP_URL_PATH), '/') ?: (string) $fileUrl;
    }

    private function hasEverBeenInBatch(int $studentId, int $batchId): bool
    {
        return (bool) $this->db->fetchOne(
            'SELECT 1 FROM batch_students WHERE batch_id = ? AND student_id = ?',
            [$batchId, $studentId]
        );
    }

    private function accessibleRecording(int $studentId, string $recordingId): array
    {
        $recording = $this->db->fetchOne(
            'SELECT cr.* FROM class_recordings cr
             JOIN live_classes lc ON lc.id = cr.live_class_id
             JOIN batch_students bs ON bs.batch_id = lc.batch_id AND bs.student_id = ?
             WHERE cr.id = ?',
            [$studentId, $recordingId]
        );

        if (! $recording) {
            $this->fail('No such recording.', ['reason' => ['not_found']], 404);
        }

        return $recording;
    }
}
