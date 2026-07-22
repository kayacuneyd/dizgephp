<?php

declare(strict_types=1);

namespace Dizge\Cms;

/** Deterministic ASCII slugs; uniqueness belongs to the repository/database boundary. */
final class Slug
{
    public static function make(string $value): string
    {
        $value = trim($value);
        $value = strtr($value, ['ı' => 'i', 'İ' => 'I', 'ş' => 's', 'Ş' => 'S', 'ğ' => 'g', 'Ğ' => 'G', 'ü' => 'u', 'Ü' => 'U', 'ö' => 'o', 'Ö' => 'O', 'ç' => 'c', 'Ç' => 'C']);
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = $converted === false ? $value : $converted;
        $value = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value));
        return trim($value, '-');
    }
}
