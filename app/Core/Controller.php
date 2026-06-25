<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected View $view;
    protected Database $db;

    public function __construct()
    {
        $this->view = new View();
        $this->db   = Database::getInstance();
    }

    protected function render(string $template, array $data = [], int $statusCode = 200): void
    {
        http_response_code($statusCode);
        $this->view->render($template, $data);
    }

    protected function json(mixed $data, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function success(mixed $data = null, string $message = 'Success', int $code = 200): never
    {
        $this->json(['success' => true, 'message' => $message, 'data' => $data], $code);
    }

    protected function error(string $message, int $code = 400, mixed $errors = null): never
    {
        $this->json(['success' => false, 'message' => $message, 'errors' => $errors], $code);
    }

    protected function redirect(string $url, int $statusCode = 302): never
    {
        header("Location: {$url}", true, $statusCode);
        exit;
    }

    protected function back(): never
    {
        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }

    protected function withFlash(string $type, string $message): static
    {
        Session::flash($type, $message);
        return $this;
    }

    protected function currentUser(): ?array
    {
        return Auth::user();
    }

    protected function authorize(string $permission): void
    {
        if (!Auth::can($permission)) {
            if ((new Request())->isAjax()) {
                $this->error('You do not have permission to perform this action.', 403);
            }
            $this->redirect('/super-admin/unauthorized');
        }
    }

    protected function validate(Request $request, array $rules): array
    {
        $validator = new Validator($request->all());
        $errors    = $validator->errors($rules);

        if (!empty($errors)) {
            if ($request->isAjax()) {
                $this->error('Validation failed.', 422, $errors);
            }
            Session::flash('errors', $errors);
            Session::flash('old', $request->all());
            $this->back();
        }

        return $validator->validated();
    }

    protected function paginate(string $sql, array $params, Request $request, int $perPage = 20): array
    {
        $page = max(1, (int)$request->input('page', 1));
        return $this->db->paginate($sql, $params, $page, $perPage);
    }
}
