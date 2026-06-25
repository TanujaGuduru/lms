<?php

declare(strict_types=1);

namespace App\Core;

class AuditLogger
{
    public static function log(
        string          $action,
        string          $module,
        int|string|null $recordId = null,
        ?array          $oldValues = null,
        ?array          $newValues = null,
        ?string         $description = null
    ): void {
        try {
            $request = new Request();
            $db      = Database::getInstance();

            $db->insertInto('audit_logs', [
                'user_id'     => Auth::id(),
                'action'      => $action,
                'module'      => $module,
                'record_id'   => $recordId !== null ? (int)$recordId : null,
                'old_values'  => $oldValues ? json_encode($oldValues) : null,
                'new_values'  => $newValues ? json_encode($newValues) : null,
                'description' => $description,
                'ip_address'  => $request->ip(),
                'user_agent'  => $request->userAgent(),
                'route'       => $request->uri(),
                'method'      => $request->method(),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Audit log failed: ' . $e->getMessage());
        }
    }
}
