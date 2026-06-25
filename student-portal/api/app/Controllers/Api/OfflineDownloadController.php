<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\FileStorage;
use App\Core\Logger;
use App\Core\Request;
use App\Core\SimplePdf;

/**
 * Offline Access / Download Mode — docs/student-module/04i-apis-quizzes-replay-offline.md.
 * **The most significant adaptation in this catalog.** The original design
 * is AES-128 HLS-encrypted video with a server-issued `decryption_key` per
 * validation call — a real DRM-shaped pipeline (segmenting + per-file
 * encryption-at-rest) that needs infrastructure well beyond hand-rolling on
 * shared hosting, and this build never even has an HLS pipeline to begin
 * with (recordings are already a single progressively-downloaded file, no
 * adaptive-bitrate transcoding — an earlier, separate scope cut).
 *
 * What's actually built: the *access-control* shape survives intact
 * (`download_token`, bounded `expires_at`, periodic re-validation, student-
 * initiated revoke, account-sharing flagging) — only the cryptographic
 * payload is gone. `validate()` returns a freshly-signed local-file URL
 * (the same `App\Core\FileStorage` mechanism as Materials/Recordings)
 * instead of a `decryption_key` for a file that was never actually
 * encrypted. The plain video bytes already sit on local disk either way;
 * what this protects is *access* to them, not the bytes themselves.
 *
 * No per-course/lesson "offline enabled" toggle exists in the schema
 * (confirmed: no such column anywhere) — every piece of content this
 * student can already access is treated as offline-eligible.
 */
class OfflineDownloadController extends Controller
{
    private const DEFAULT_EXPIRY_DAYS = 14;
    private const MAX_CONCURRENT_ACTIVE = 5;
    private const MAX_DEVICE_FINGERPRINTS_PER_DAY = 3;

    public function request(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $contentType = (string) $request->input('content_type', '');
        $contentId = (int) $request->input('content_id', 0);

        if (! in_array($contentType, ['video_lesson', 'recording', 'material', 'note_export'], true) || ! $contentId) {
            $this->fail('A valid content_type and content_id are required.', ['content_type' => ['required|in:video_lesson,recording,material,note_export']]);
        }

        $relativePath = $this->resolveContentPath($studentId, $contentType, $contentId);

        $token = bin2hex(random_bytes(24));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::DEFAULT_EXPIRY_DAYS . ' days'));
        $deviceFingerprint = (string) $request->input('device_fingerprint', '');

        $this->db->insertInto('offline_downloads', [
            'student_id' => $studentId,
            'content_type' => $contentType,
            'content_id' => $contentId,
            'download_token' => $token,
            'device_fingerprint' => $deviceFingerprint ?: null,
            'expires_at' => $expiresAt,
        ]);

        $this->flagIfSuspicious($studentId, $deviceFingerprint);

        $this->success([
            'download_token' => $token,
            'package_url' => FileStorage::signedUrl($relativePath, self::DEFAULT_EXPIRY_DAYS * 86400),
            'expires_at' => $expiresAt,
        ]);
    }

    public function index(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $rows = $this->db->select(
            'SELECT id, content_type, content_id, granted_at, expires_at, status, last_validated_at FROM offline_downloads WHERE student_id = ? ORDER BY granted_at DESC',
            [$studentId]
        );
        $this->success($rows);
    }

    /** The app refuses to use the locally-stored file without this succeeding — access is tied to the token, never permanently embedded client-side (04i's explicit point, restated for a signed-URL world instead of a decryption-key one). */
    public function validate(Request $request, string $id): void
    {
        $download = $this->ownDownload($id);

        if (strtotime($download['expires_at']) < time()) {
            $this->db->updateTable('offline_downloads', ['status' => 'expired'], 'id = ?', [$download['id']]);
            $this->fail('This download has expired.', ['reason' => ['download_expired']], 403);
        }
        if ($download['status'] !== 'active') {
            $this->fail('This download is no longer active.', ['reason' => ['download_' . $download['status']]], 403);
        }

        $relativePath = $this->resolveContentPath((int) $download['student_id'], $download['content_type'], (int) $download['content_id']);
        $this->db->updateTable('offline_downloads', ['last_validated_at' => date('Y-m-d H:i:s')], 'id = ?', [$download['id']]);

        $this->success([
            'status' => 'active',
            'download_url' => FileStorage::signedUrl($relativePath, 600),
        ]);
    }

    public function revoke(Request $request, string $id): void
    {
        $download = $this->ownDownload($id);
        $this->db->updateTable('offline_downloads', ['status' => 'revoked'], 'id = ?', [$download['id']]);
        $this->success(true);
    }

    private function resolveContentPath(int $studentId, string $contentType, int $contentId): string
    {
        return match ($contentType) {
            'video_lesson' => $this->lessonVideoPath($studentId, $contentId),
            'recording' => $this->recordingPath($studentId, $contentId),
            'material' => $this->materialPath($studentId, $contentId),
            'note_export' => $this->noteExportPath($studentId, $contentId),
        };
    }

    private function lessonVideoPath(int $studentId, int $lessonId): string
    {
        $lesson = $this->db->fetchOne(
            "SELECT l.video_url, l.video_provider FROM lessons l
             JOIN enrollments e ON e.course_id = l.course_id AND e.user_id = ?
             WHERE l.id = ? AND l.is_published = 1 AND l.deleted_at IS NULL",
            [$studentId, $lessonId]
        );
        if (! $lesson) {
            $this->fail('No such lesson.', ['reason' => ['not_found']], 404);
        }
        if ($lesson['video_provider'] !== 'upload' || ! $lesson['video_url']) {
            $this->fail('This lesson is not available for offline download.', ['reason' => ['not_downloadable']], 422);
        }
        return $this->relativePathFor($lesson['video_url']);
    }

    private function recordingPath(int $studentId, int $recordingId): string
    {
        $recording = $this->db->fetchOne(
            "SELECT cr.processed_video_url, cr.raw_recording_url FROM class_recordings cr
             JOIN live_classes lc ON lc.id = cr.live_class_id
             JOIN batch_students bs ON bs.batch_id = lc.batch_id AND bs.student_id = ?
             WHERE cr.id = ?",
            [$studentId, $recordingId]
        );
        if (! $recording) {
            $this->fail('No such recording.', ['reason' => ['not_found']], 404);
        }
        $url = $recording['processed_video_url'] ?: $recording['raw_recording_url'];
        if (! $url) {
            $this->fail('This recording is not ready for offline download yet.', ['reason' => ['not_downloadable']], 422);
        }
        return $this->relativePathFor($url);
    }

    private function materialPath(int $studentId, int $materialId): string
    {
        $material = $this->db->fetchOne(
            "SELECT cm.file_url, cm.is_downloadable FROM course_materials cm
             JOIN enrollments e ON e.course_id = cm.course_id AND e.user_id = ?
             WHERE cm.id = ?",
            [$studentId, $materialId]
        );
        if (! $material) {
            $this->fail('No such material.', ['reason' => ['not_found']], 404);
        }
        if (! $material['is_downloadable']) {
            $this->fail('This material is not downloadable.', ['reason' => ['not_downloadable']], 422);
        }
        return $this->relativePathFor($material['file_url']);
    }

    /** Generated on demand via the same SimplePdf writer as parent reports/certificates — a note has no file to begin with until offline access asks for one. */
    private function noteExportPath(int $studentId, int $noteId): string
    {
        $note = $this->db->fetchOne('SELECT * FROM notes WHERE id = ? AND student_id = ? AND deleted_at IS NULL', [$noteId, $studentId]);
        if (! $note) {
            $this->fail('No such note.', ['reason' => ['not_found']], 404);
        }

        $pdf = new SimplePdf();
        $pdf->addHeading($note['title'] ?: 'Untitled note');
        $pdf->addParagraph($note['content']);

        $relativePath = "offline/{$studentId}/note-{$noteId}.pdf";
        $absolutePath = FileStorage::absolutePath($relativePath);
        @mkdir(dirname($absolutePath), 0755, true);
        file_put_contents($absolutePath, $pdf->toBytes());

        return $relativePath;
    }

    /** Mirrors MaterialController's helper — see its docblock. */
    private function relativePathFor(string $fileUrl): string
    {
        return ltrim((string) parse_url($fileUrl, PHP_URL_PATH), '/') ?: $fileUrl;
    }

    /**
     * Still succeeds either way — surfaced for a human to check, never
     * auto-blocked, since a legitimate case (a new phone) shouldn't be
     * punished by an automatic hard rule (04i's explicit reasoning). No
     * `flagged_for_review` column exists on `offline_downloads`; logged
     * instead, the same convention PaymentController::refundRequest()
     * already established for its own fraud-signal check.
     */
    private function flagIfSuspicious(int $studentId, string $deviceFingerprint): void
    {
        $activeCount = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM offline_downloads WHERE student_id = ? AND status = 'active' AND expires_at > NOW()",
            [$studentId]
        )['c'];

        $distinctDevices = (int) $this->db->fetchOne(
            "SELECT COUNT(DISTINCT device_fingerprint) AS c FROM offline_downloads
             WHERE student_id = ? AND device_fingerprint IS NOT NULL AND granted_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)",
            [$studentId]
        )['c'];

        if ($activeCount > self::MAX_CONCURRENT_ACTIVE || $distinctDevices > self::MAX_DEVICE_FINGERPRINTS_PER_DAY) {
            Logger::error('Offline download flagged for account-sharing review', [
                'student_id' => $studentId,
                'active_downloads' => $activeCount,
                'distinct_devices_today' => $distinctDevices,
            ]);
        }
    }

    private function ownDownload(string $id): array
    {
        $studentId = (int) $this->currentUser()['id'];
        $download = $this->db->fetchOne('SELECT * FROM offline_downloads WHERE id = ? AND student_id = ?', [$id, $studentId]);

        if (! $download) {
            $this->fail('No such download.', ['reason' => ['not_found']], 404);
        }

        return $download;
    }
}
