<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    protected function json(mixed $data, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Standard success envelope — docs/student-module/04a-apis-conventions-enrollment-billing.md.
     */
    protected function success(mixed $data = null, array $meta = [], int $code = 200): never
    {
        $payload = ['success' => true, 'data' => $data];
        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }
        $this->json($payload, $code);
    }

    /**
     * Standard error envelope — docs/student-module/04a-apis-conventions-enrollment-billing.md.
     */
    protected function fail(string $message, array $errors = [], int $code = 422): never
    {
        // Cast to object so an empty array still serializes as JSON `{}`, not
        // `[]` — a client typed against "errors: Record<string, string[]>"
        // would otherwise see a type mismatch on the common no-field-errors case.
        $this->json(['success' => false, 'message' => $message, 'errors' => (object) $errors], $code);
    }

    protected function currentUser(): ?array
    {
        return Auth::userFromToken();
    }
}
