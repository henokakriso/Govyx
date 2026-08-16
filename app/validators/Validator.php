<?php

declare(strict_types=1);

namespace Govyx\Validators;

final class Validator
{
    private array $errors = [];

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function fail(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }

    public function required(mixed $value, string $field, string $label): self
    {
        if ($value === null || $value === '' || (is_array($value) && $value === [])) {
            $this->fail($field, "$label is required.");
        }
        return $this;
    }

    public function string(mixed $value, string $field, string $label, int $max = 255): self
    {
        if ($value === null || $value === '') {
            return $this;
        }
        if (!is_scalar($value)) {
            $this->fail($field, "$label must be a string.");
        } elseif (mb_strlen((string) $value) > $max) {
            $this->fail($field, "$label must not exceed $max characters.");
        }
        return $this;
    }

    public function int(mixed $value, string $field, string $label, int $min = -1, int $max = -1): self
    {
        if ($value === null || $value === '') {
            return $this;
        }
        if (!ctype_digit((string) $value) && !is_int($value)) {
            $this->fail($field, "$label must be an integer.");
            return $this;
        }
        $v = (int) $value;
        if ($min >= 0 && $v < $min) {
            $this->fail($field, "$label must be at least $min.");
        }
        if ($max >= 0 && $v > $max) {
            $this->fail($field, "$label must be at most $max.");
        }
        return $this;
    }

    public function in(mixed $value, array $allowed, string $field, string $label): self
    {
        if ($value === null || $value === '') {
            return $this;
        }
        if (!in_array($value, $allowed, true)) {
            $this->fail($field, "$label must be one of: " . implode(', ', $allowed));
        }
        return $this;
    }

    public function date(mixed $value, string $field, string $label): self
    {
        if ($value === null || $value === '') {
            return $this;
        }
        $d = \DateTime::createFromFormat('Y-m-d', (string) $value);
        if ($d === false || $d->format('Y-m-d') !== $value) {
            $this->fail($field, "$label must be a valid date (YYYY-MM-DD).");
        }
        return $this;
    }

    public function email(mixed $value, string $field, string $label): self
    {
        if ($value === null || $value === '') {
            return $this;
        }
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->fail($field, "$label must be a valid email address.");
        }
        return $this;
    }

    public function numeric(mixed $value, string $field, string $label): self
    {
        if ($value === null || $value === '') {
            return $this;
        }
        if (!is_numeric($value)) {
            $this->fail($field, "$label must be numeric.");
        }
        return $this;
    }

    public function exists(mixed $value, string $field, string $label, string $table, string $column = 'id'): self
    {
        if ($value === null || $value === '') {
            return $this;
        }
        $found = \Govyx\Core\App::db()->scalar(
            "SELECT COUNT(*) FROM $table WHERE $column = ?", [$value]
        );
        if (!(int) $found) {
            $this->fail($field, "$label does not exist.");
        }
        return $this;
    }
}