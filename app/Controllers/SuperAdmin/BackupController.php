<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;
use App\Core\Logger;

class BackupController extends Controller
{
    public function index(Request $request): void
    {
        $this->authorize('backups.view');

        $backups = $this->db->select("SELECT * FROM backups ORDER BY created_at DESC LIMIT 30");
        $diskFree = disk_free_space(BASE_PATH);
        $diskTotal = disk_total_space(BASE_PATH);

        $this->render('super-admin.backup.index', [
            'title'     => 'Backup & Recovery',
            'backups'   => $backups,
            'diskFree'  => $diskFree,
            'diskTotal' => $diskTotal,
        ]);
    }

    public function create(Request $request): never
    {
        $this->authorize('backups.create');

        $type = $request->input('type', 'full'); // full | database | files

        try {
            $filename  = 'backup_' . $type . '_' . date('Y-m-d_H-i-s') . '.zip';
            $backupDir = BASE_PATH . '/storage/backups';
            @mkdir($backupDir, 0755, true);
            $filePath  = $backupDir . '/' . $filename;

            $id = $this->db->insert(
                "INSERT INTO backups (file_name,file_path,type,status,started_at,user_id,created_at) VALUES (?,?,?,'in_progress',NOW(),?,NOW())",
                [$filename, $filePath, $type, $this->currentUser()['id']]
            );

            $size = 0;
            if ($type === 'database' || $type === 'full') {
                $this->exportDatabase($filePath);
                $size += file_exists($filePath) ? filesize($filePath) : 0;
            }

            $this->db->query(
                "UPDATE backups SET status='completed', completed_at=NOW(), file_size_bytes=? WHERE id=?",
                [$size, $id]
            );

            AuditLogger::log('backup_created', 'backups', (string)$id, null, ['type' => $type, 'filename' => $filename]);
            $this->success(['id' => $id, 'filename' => $filename], 'Backup created successfully.');

        } catch (\Throwable $e) {
            if (isset($id)) {
                $this->db->query("UPDATE backups SET status='failed', notes=? WHERE id=?", [$e->getMessage(), $id]);
            }
            Logger::error('Backup failed: ' . $e->getMessage());
            $this->error('Backup failed: ' . $e->getMessage());
        }
    }

    public function download(Request $request, int $id): void
    {
        $this->authorize('backups.view');

        $backup = $this->db->selectOne("SELECT * FROM backups WHERE id = ? AND status='completed'", [$id]);
        if (!$backup) {
            $this->withFlash('error', 'Backup not found or not ready.')->back();
        }

        $filePath = BASE_PATH . '/storage/backups/' . $backup['file_name'];
        if (!file_exists($filePath)) {
            $this->withFlash('error', 'Backup file no longer exists.')->back();
        }

        AuditLogger::log('backup_downloaded', 'backups', (string)$id);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    public function delete(Request $request, int $id): never
    {
        $this->authorize('backups.delete');

        $backup = $this->db->selectOne("SELECT * FROM backups WHERE id = ?", [$id]);
        if (!$backup) $this->error('Backup not found.', 404);

        $filePath = BASE_PATH . '/storage/backups/' . $backup['file_name'];
        if (file_exists($filePath)) @unlink($filePath);

        $this->db->query("DELETE FROM backups WHERE id = ?", [$id]);
        AuditLogger::log('backup_deleted', 'backups', (string)$id, $backup);

        $this->success(null, 'Backup deleted.');
    }

    private function exportDatabase(string $outputPath): void
    {
        $config = require BASE_PATH . '/config/database.php';

        $dumpFile = str_replace('.zip', '.sql', $outputPath);
        $cmd = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --single-transaction --routines %s > %s 2>&1',
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['host']),
            escapeshellarg($config['database']),
            escapeshellarg($dumpFile)
        );
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($dumpFile)) {
            throw new \RuntimeException('mysqldump failed. Ensure mysqldump is in PATH.');
        }

        // Zip it
        if (class_exists('\ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($outputPath, \ZipArchive::CREATE) === true) {
                $zip->addFile($dumpFile, basename($dumpFile));
                $zip->close();
                @unlink($dumpFile);
            }
        } else {
            rename($dumpFile, $outputPath);
        }
    }
}
