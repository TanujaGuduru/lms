<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Request;

class HealthController
{
    public function index(Request $request): void
    {
        $checks  = [];
        $healthy = true;

        // DB check
        try {
            $db = Database::getInstance();
            $db->fetchOne("SELECT 1");
            $checks['database'] = ['status'=>'ok','latency_ms'=>0];
        } catch (\Throwable $e) {
            $checks['database'] = ['status'=>'error','message'=>'DB unreachable'];
            $healthy = false;
        }

        // Disk check
        $free  = disk_free_space(BASE_PATH ?? '/');
        $total = disk_total_space(BASE_PATH ?? '/');
        $pct   = $total > 0 ? round(($total - $free) / $total * 100) : 0;
        $checks['disk'] = [
            'status'        => $pct < 90 ? 'ok' : 'warning',
            'used_percent'  => $pct,
            'free_gb'       => round($free / 1073741824, 2),
        ];

        // PHP version
        $checks['php'] = [
            'status'  => 'ok',
            'version' => PHP_VERSION,
        ];

        http_response_code($healthy ? 200 : 503);
        header('Content-Type: application/json');
        echo json_encode([
            'status'    => $healthy ? 'healthy' : 'degraded',
            'timestamp' => date('c'),
            'checks'    => $checks,
            'version'   => defined('APP_VERSION') ? APP_VERSION : '1.0.0',
        ]);
    }
}
