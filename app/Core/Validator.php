<?php

declare(strict_types=1);

namespace App\Core;

class Validator
{
    private array $data;
    private array $errors = [];
    private array $validated = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function validate(array $rules): array
    {
        $this->errors($rules);
        if (!empty($this->errors)) {
            throw new \RuntimeException(json_encode($this->errors));
        }
        return $this->validated;
    }

    public function errors(array $rules): array
    {
        $this->errors    = [];
        $this->validated = [];

        foreach ($rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            $rulesArr = explode('|', $ruleString);

            $nullable = in_array('nullable', $rulesArr);
            if ($nullable && ($value === null || $value === '')) {
                $this->validated[$field] = $value;
                continue;
            }

            foreach ($rulesArr as $rule) {
                if ($rule === 'nullable') continue;

                $error = $this->applyRule($field, $value, $rule);
                if ($error) {
                    $this->errors[$field] = $error;
                    break;
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }

        return $this->errors;
    }

    private function applyRule(string $field, mixed $value, string $rule): ?string
    {
        $label = ucwords(str_replace('_', ' ', $field));
        [$ruleName, $param] = array_pad(explode(':', $rule, 2), 2, null);

        return match($ruleName) {
            'required'  => ($value === null || $value === '') ? "{$label} is required." : null,
            'email'     => !filter_var($value, FILTER_VALIDATE_EMAIL) ? "{$label} must be a valid email address." : null,
            'min'       => strlen((string)$value) < (int)$param ? "{$label} must be at least {$param} characters." : null,
            'max'       => strlen((string)$value) > (int)$param ? "{$label} must not exceed {$param} characters." : null,
            'min_val'   => (float)$value < (float)$param ? "{$label} must be at least {$param}." : null,
            'max_val'   => (float)$value > (float)$param ? "{$label} must not exceed {$param}." : null,
            'numeric'   => !is_numeric($value) ? "{$label} must be a number." : null,
            'integer'   => filter_var($value, FILTER_VALIDATE_INT) === false ? "{$label} must be an integer." : null,
            'alpha'     => !ctype_alpha((string)$value) ? "{$label} must contain only letters." : null,
            'alpha_num' => !ctype_alnum((string)$value) ? "{$label} must contain only letters and numbers." : null,
            'url'       => !filter_var($value, FILTER_VALIDATE_URL) ? "{$label} must be a valid URL." : null,
            'date'      => !strtotime((string)$value) ? "{$label} must be a valid date." : null,
            'confirmed' => $value !== ($this->data[$field . '_confirmation'] ?? null) ? "{$label} confirmation does not match." : null,
            'in'        => !in_array($value, explode(',', $param)) ? "{$label} is invalid." : null,
            'not_in'    => in_array($value, explode(',', $param)) ? "{$label} value is not allowed." : null,
            'regex'     => !preg_match($param, (string)$value) ? "{$label} format is invalid." : null,
            'phone'     => !preg_match('/^[+]?[0-9]{10,15}$/', (string)$value) ? "{$label} must be a valid phone number." : null,
            'password_strength' => $this->checkPassword((string)$value, $label),
            'unique'    => $this->checkUnique($value, $param, $field),
            default     => null,
        };
    }

    private function checkPassword(string $value, string $label): ?string
    {
        if (strlen($value) < 8) return "{$label} must be at least 8 characters.";
        if (!preg_match('/[A-Z]/', $value)) return "{$label} must contain at least one uppercase letter.";
        if (!preg_match('/[a-z]/', $value)) return "{$label} must contain at least one lowercase letter.";
        if (!preg_match('/[0-9]/', $value)) return "{$label} must contain at least one number.";
        if (!preg_match('/[^A-Za-z0-9]/', $value)) return "{$label} must contain at least one special character.";
        return null;
    }

    private function checkUnique(mixed $value, ?string $param, string $field): ?string
    {
        if (!$param) return null;
        [$table, $column] = array_pad(explode(',', $param), 2, $field);
        $db = Database::getInstance();
        if ($db->count($table, "`{$column}` = ?", [$value]) > 0) {
            return ucwords(str_replace('_', ' ', $field)) . ' already exists.';
        }
        return null;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
