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
        $v = trim($val);
        if ($v === '') return null;

        // Permite formate uzuale RO/EN:
        // - 350,00
        // - 1.234,50
        // - 1 234,50
        // - 1234.56
        // - 1,234.56
        $v = str_replace(["\xC2\xA0", ' '], '', $v); // NBSP + spații

        $hasComma = str_contains($v, ',');
        $hasDot = str_contains($v, '.');

        if ($hasComma && $hasDot) {
            // Avem ambele: decidem separatorul zecimal după ultimul separator.
            $lastComma = strrpos($v, ',');
            $lastDot = strrpos($v, '.');
            if ($lastComma !== false && $lastDot !== false) {
                if ($lastComma > $lastDot) {
                    // 1.234,56 -> '.' mii, ',' zecimal
                    $v = str_replace('.', '', $v);
                    $v = str_replace(',', '.', $v);
                } else {
                    // 1,234.56 -> ',' mii, '.' zecimal
                    $v = str_replace(',', '', $v);
                    // '.' rămâne zecimal
                }
            }
        } elseif ($hasComma) {
            // 1234,56 -> ',' zecimal
            $v = str_replace(',', '.', $v);
        } else {
            // doar '.' sau nimic -> '.' zecimal (sau întreg)
        }

        if ($v === '' || !is_numeric($v)) return null;
        return (float)$v;
    }
}

