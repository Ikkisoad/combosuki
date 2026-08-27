<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Nickname rules for self-service registration.
 *
 * `nickname` is doing two jobs at once in this app: it is the login identifier
 * (Auth::attempt matches on it) *and* the public display name rendered next to
 * the "Admin"/"Trusted" badges on the profile page. That was tolerable while
 * only trusted users could create accounts; a public sign-up endpoint makes it
 * an attack surface, so this deliberately asks for more than the
 * `['required','string','max:45','unique:user,nickname']` the existing
 * trusted-user flow uses.
 *
 * Applied only to the Discord registration flow. Tightening /users/create and
 * admin.users.store is a separate decision — those already work.
 */
class NicknamePolicy
{
    public const MIN_LENGTH = 3;

    public const MAX_LENGTH = 45;

    /**
     * ASCII letters, digits, underscore, dot and hyphen only. Restricting the
     * character set is what makes homoglyph impersonation impossible: there is
     * no Cyrillic "а" to pass off as a Latin "a".
     */
    public const PATTERN = '/^[A-Za-z0-9_.-]+$/';

    /**
     * Names that would let an account misrepresent itself as staff. Compared
     * case-insensitively.
     */
    private const RESERVED = [
        'admin', 'administrator', 'moderator', 'mod', 'root', 'system',
        'staff', 'support', 'official', 'combosuki', 'anonymous', 'deleted',
    ];

    /**
     * @return array<int, mixed>
     */
    public static function rules(): array
    {
        return [
            'required',
            'string',
            'min:'.self::MIN_LENGTH,
            'max:'.self::MAX_LENGTH,
            'regex:'.self::PATTERN,
            Rule::notIn(self::RESERVED),
            function (string $attribute, mixed $value, callable $fail): void {
                if (self::isReserved((string) $value)) {
                    $fail('That nickname is reserved.');
                }

                if (self::isTaken((string) $value)) {
                    $fail('That nickname is already taken.');
                }
            },
        ];
    }

    public static function isReserved(string $nickname): bool
    {
        return in_array(mb_strtolower($nickname), self::RESERVED, true);
    }

    /**
     * Case-insensitive on purpose, and done with an explicit LOWER() rather
     * than the `unique:user,nickname` rule.
     *
     * MySQL (production) uses a _ci collation, so "Bob" already collides with
     * "bob" there; SQLite (tests) compares case-sensitively, so the plain
     * unique rule would disagree between the two environments — the exact
     * divergence CLAUDE.md warns about. Comparing lowercased values makes both
     * behave the same, and matches the database's own uniqueness guarantee on
     * production instead of fighting it.
     */
    public static function isTaken(string $nickname): bool
    {
        return User::whereRaw('LOWER(nickname) = ?', [mb_strtolower($nickname)])->exists();
    }

    /**
     * Best-effort suggestion from a Discord username: strip everything the
     * pattern rejects. Returns null when nothing usable survives, so the
     * registration form shows an empty field rather than a mangled one.
     */
    public static function suggestFrom(?string $raw): ?string
    {
        $candidate = preg_replace('/[^A-Za-z0-9_.-]/', '', (string) $raw) ?? '';
        $candidate = mb_substr($candidate, 0, self::MAX_LENGTH);

        if (mb_strlen($candidate) < self::MIN_LENGTH || self::isReserved($candidate) || self::isTaken($candidate)) {
            return null;
        }

        return $candidate;
    }
}
