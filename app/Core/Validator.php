<?php
declare(strict_types=1);

namespace App\Core;

final class Validator
{
    /**
     * @return array{ok:bool, errors:array<string,string>}
     */
    public static function required(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $f => $label) {
            $v = $data[$f] ?? null;
            if (!is_string($v)) {
                $v = is_scalar($v) ? (string)$v : '';
            }
            if (trim($v) === '') {
                $errors[$f] = 'Câmpul „' . $label . '” este obligatoriu.';
            }
        }
        return ['ok' => count($errors) === 0, 'errors' => $errors];
    }

    public static function email(?string $email): bool
    {
        if ($email === null) return false;
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function int(?string $val, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): ?int
    {
        if ($val === null) return null;
        if (!preg_match('/^-?\d+$/', trim($val))) return null;
        $i = (int)$val;
        if ($i < $min || $i > $max) return null;
        return $i;
    }

    public static function dec(?string $val): ?float
    {
        if ($val === null) return null;
        $v = str_replace(',', '.', trim($val));
        if ($v === '' || !is_numeric($v)) return null;
        return (float)$v;
    }
}

