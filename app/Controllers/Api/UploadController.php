<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;

class UploadController
{
    private const ALLOWED_IMAGE  = ['jpg','jpeg','png','gif','webp'];
    private const ALLOWED_DOC    = ['pdf','doc','docx','ppt','pptx','xls','xlsx','txt','csv'];
    private const ALLOWED_VIDEO  = ['mp4','webm','ogv','mov'];
    private const MAX_IMAGE_SIZE = 5 * 1024 * 1024;   // 5 MB
    private const MAX_DOC_SIZE   = 20 * 1024 * 1024;  // 20 MB
    private const MAX_VIDEO_SIZE = 500 * 1024 * 1024; // 500 MB

    public function upload(Request $request): void
    {
        if (!Auth::check()) { $this->json(['success'=>false,'message'=>'Unauthenticated'], 401); return; }

        if (empty($_FILES['file'])) {
            $this->json(['success'=>false,'message'=>'No file provided'], 422);
            return;
        }

        $file    = $_FILES['file'];
        $type    = $request->post('type', 'image'); // image|document|video
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = match($type) {
            'document' => self::ALLOWED_DOC,
            'video'    => self::ALLOWED_VIDEO,
            default    => self::ALLOWED_IMAGE,
        };
        $maxSize = match($type) {
            'document' => self::MAX_DOC_SIZE,
            'video'    => self::MAX_VIDEO_SIZE,
            default    => self::MAX_IMAGE_SIZE,
        };

        if (!in_array($ext, $allowed)) {
            $this->json(['success'=>false,'message'=>"File type .{$ext} not allowed"], 422);
            return;
        }
        if ($file['size'] > $maxSize) {
            $this->json(['success'=>false,'message'=>'File too large'], 422);
            return;
        }

        $uploadDir = rtrim(PUBLIC_PATH ?? '', '/') . '/uploads/' . $type . 's/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            $this->json(['success'=>false,'message'=>'Failed to save file'], 500);
            return;
        }

        $url = '/uploads/' . $type . 's/' . $filename;
        $this->json([
            'success'  => true,
            'url'      => $url,
            'filename' => $filename,
            'size'     => $file['size'],
            'type'     => $type,
        ]);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
