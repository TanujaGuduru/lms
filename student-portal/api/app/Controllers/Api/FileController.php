<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\FileStorage;
use App\Core\Request;

/**
 * Streams a file from local disk given a short-lived signed token —
 * deliberately a *public* route (no Bearer auth) since the signed token
 * itself is the access control, generated only after MaterialController/
 * RecordingController have already done the real enrollment check. This is
 * the local-disk replacement for an S3 presigned URL — same shape from the
 * client's point of view (a temporary direct link), no cloud storage
 * account behind it.
 */
class FileController extends Controller
{
    public function serve(Request $request): void
    {
        $token = (string) $request->input('token', '');
        $relativePath = FileStorage::validate($token);

        if ($relativePath === false) {
            $this->fail('This link is invalid or has expired.', ['reason' => ['invalid_or_expired']], 403);
        }

        $absolutePath = FileStorage::absolutePath($relativePath);
        if (! is_file($absolutePath)) {
            $this->fail('File not found.', ['reason' => ['not_found']], 404);
        }

        $mimeType = mime_content_type($absolutePath) ?: 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($absolutePath));
        header('Content-Disposition: inline; filename="' . basename($absolutePath) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($absolutePath);
        exit;
    }
}
