<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validador sencillo con reglas encadenadas y mensajes traducidos.
 *
 * Reglas soportadas: required, email, min:n, max:n, confirmed, same:campo,
 * in:a,b,c, numeric, accepted, password (complejidad).
 */
final class Validator
{
    /** @var array<string,array<int,string>> */
    private array $errors = [];

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $rules  campo => "required|email|min:3"
     * @param array<string,string> $labels etiquetas legibles por campo
     */
    public function __construct(
        private array $data,
        private array $rules,
        private array $labels = [],
    ) {
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        $v = new self($data, $rules, $labels);
        $v->validate();
        return $v;
    }

    public function validate(): bool
    {
        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            foreach (explode('|', $ruleString) as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $this->apply($field, (string) $name, $param, $value);
            }
        }
        return $this->passes();
    }

    private function apply(string $field, string $rule, ?string $param, mixed $value): void
    {
        $label = $this->labels[$field] ?? $field;
        $str = is_string($value) ? trim($value) : $value;

        switch ($rule) {
            case 'required':
                if ($str === null || $str === '' || $str === []) {
                    $this->addError($field, __('validation.required', ['field' => $label]));
                }
                break;
            case 'email':
                if ($str !== null && $str !== '' && !filter_var($str, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, __('validation.email', ['field' => $label]));
                }
                break;
            case 'min':
                if ($str !== null && $str !== '' && mb_strlen((string) $str) < (int) $param) {
                    $this->addError($field, __('validation.min', ['field' => $label, 'min' => (int) $param]));
                }
                break;
            case 'max':
                if ($str !== null && mb_strlen((string) $str) > (int) $param) {
                    $this->addError($field, __('validation.max', ['field' => $label, 'max' => (int) $param]));
                }
                break;
            case 'confirmed':
                if (($this->data[$field . '_confirmation'] ?? null) !== $value) {
                    $this->addError($field, __('validation.confirmed', ['field' => $label]));
                }
                break;
            case 'same':
                if (($this->data[$param] ?? null) !== $value) {
                    $this->addError($field, __('validation.confirmed', ['field' => $label]));
                }
                break;
            case 'in':
                $options = explode(',', (string) $param);
                if ($str !== null && $str !== '' && !in_array((string) $str, $options, true)) {
                    $this->addError($field, __('validation.invalid', ['field' => $label]));
                }
                break;
            case 'numeric':
                if ($str !== null && $str !== '' && !is_numeric($str)) {
                    $this->addError($field, __('validation.numeric', ['field' => $label]));
                }
                break;
            case 'accepted':
                if (!in_array($value, ['1', 'on', 'true', true, 1], true)) {
                    $this->addError($field, __('validation.accepted', ['field' => $label]));
                }
                break;
            case 'password':
                if (is_string($str) && !self::strongPassword($str)) {
                    $this->addError($field, __('validation.password', ['field' => $label]));
                }
                break;
        }
    }

    public static function strongPassword(string $password): bool
    {
        return strlen($password) >= 12
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /** @return array<string,array<int,string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            return $messages[0] ?? null;
        }
        return null;
    }
}
