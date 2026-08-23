<?php

namespace App\Support;

use Illuminate\Support\Str;

class AliasGenerator
{
    /**
     * Build an auto-generated alias from $name: initials (first letter of
     * each word, uppercased) for multi-word names, or the first
     * $fallbackLength letters uppercased for single-word names, since a
     * single-word "initial" would just be one letter.
     */
    public static function initials(string $name, int $fallbackLength): string
    {
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        return count($words) > 1
            ? Str::upper(implode('', array_map(fn (string $word) => mb_substr($word, 0, 1), $words)))
            : Str::upper(mb_substr($words[0] ?? $name, 0, $fallbackLength));
    }

    /**
     * Split a comma-separated aliases field into a trimmed, non-empty,
     * case-insensitively deduped list, preserving the first-seen casing.
     */
    public static function parseList(string $raw): array
    {
        $aliases = [];

        foreach (explode(',', $raw) as $alias) {
            $alias = trim($alias);

            if ($alias === '') {
                continue;
            }

            $key = Str::lower($alias);

            if (! isset($aliases[$key])) {
                $aliases[$key] = $alias;
            }
        }

        return array_values($aliases);
    }
}
