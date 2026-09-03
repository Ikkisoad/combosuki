<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;

class CombleGuessEvaluator
{
    /**
     * Compares one guess against the day's target combo. Only game/character
     * correctness determines a win; type and damage are hint-only columns.
     *
     * IDs are cast to int before comparing: game_entry.entryid (and other
     * PKs) are BIGINT UNSIGNED, while combo.type is a plain signed INT — on
     * real MySQL, PDO returns those as a string and a native int
     * respectively, so a strict === here would wrongly mark a correct guess
     * as wrong. SQLite's dynamic typing hides this, which is why it only
     * ever surfaces against the production database.
     */
    public function evaluate(Combo $target, Game $guessedGame, Character $guessedCharacter, GameEntry $guessedType, ?float $guessedDamage, ?string $guessedStarter = null): array
    {
        $gameCorrect = (int) $guessedGame->idgame === (int) $target->character->game_idgame;
        $characterCorrect = (int) $guessedCharacter->idcharacter === (int) $target->character_idcharacter;
        $typeCorrect = (int) $guessedType->entryid === (int) $target->type
            || $this->sameTypeTitle($guessedType, $target->listingType);

        return [
            'game_correct' => $gameCorrect,
            'character_correct' => $characterCorrect,
            'type_correct' => $typeCorrect,
            // "correct"/"partial"/"wrong" for display (orange for a close but
            // wrong guess, e.g. "Street Fighter V" guessed for "Street
            // Fighter 6", or "Ibuki" guessed in the wrong game). game_correct/
            // character_correct above stay plain booleans since they gate the
            // win condition and drive the share-text squares.
            'game_result' => $gameCorrect ? 'correct' : ($this->isSimilarName($guessedGame->name, $target->character->game->name) ? 'partial' : 'wrong'),
            'character_result' => $characterCorrect ? 'correct' : ($this->isSimilarName($guessedCharacter->name, $target->character->name) ? 'partial' : 'wrong'),
            ...$this->starterMatch($target, $guessedStarter),
            'damage_hint' => $this->damageHint($target, $guessedDamage),
            'won' => $gameCorrect && $characterCorrect,
        ];
    }

    /**
     * Compares a guess at the combo's opening 6 characters against the real
     * notation string (literal characters, not tokens), position by
     * position, case-insensitively. Spaces are stripped from both sides
     * first — notation spacing (see the " > " separator between chained
     * moves) is a display convention, not something a player can be
     * expected to reproduce blindly by counting characters, so it's
     * excluded from both the target's opening 6 and the guess itself.
     * Never guessed, never gates a win: same non-blocking "bonus hint" role
     * as damage.
     *
     * Returns four keys, merged straight into evaluate()'s result:
     * - starter_result: 'correct' (identical, same length), 'partial' (at
     *   least one character right in its own position, but not a full
     *   match), or 'wrong' (no positions match, or nothing was guessed) —
     *   drives win/share/sticky-guess logic elsewhere, which only cares
     *   about those three tiers.
     * - starter_match_count / starter_total: how many of the target's
     *   opening (up to 6) characters landed in the right position, out of
     *   how many there are to get right.
     * - starter_color: an hsl() string scaled by match_count/total (red at
     *   0, green at a full match, everything else a smooth ramp between)
     *   so a "close" guess reads as visually closer than a "way off" one,
     *   instead of every non-exact guess getting the same flat orange.
     */
    private function starterMatch(Combo $target, ?string $guessedStarter): array
    {
        $actual = mb_strtolower(mb_substr(str_replace(' ', '', $target->combo), 0, 6));
        $total = mb_strlen($actual);

        $guessed = $guessedStarter === null || $guessedStarter === ''
            ? ''
            : mb_strtolower(str_replace(' ', '', $guessedStarter));

        $matchCount = 0;
        $length = min(mb_strlen($guessed), $total);

        for ($i = 0; $i < $length; $i++) {
            if (mb_substr($guessed, $i, 1) === mb_substr($actual, $i, 1)) {
                $matchCount++;
            }
        }

        $result = match (true) {
            // $guessed !== '' guards a pathological empty-notation target
            // ($total === 0): without it, never guessing anything would
            // match trivially (0 === 0 on both sides) and read as "correct".
            $guessed !== '' && $matchCount === $total && mb_strlen($guessed) === $total => 'correct',
            $matchCount > 0 => 'partial',
            default => 'wrong',
        };

        return [
            'starter_result' => $result,
            'starter_match_count' => $matchCount,
            'starter_total' => $total,
            'starter_color' => $this->starterColor($matchCount, $total),
        ];
    }

    /**
     * hue 0 (red) at zero matches, hue 120 (green) at a full match, linear
     * in between — a standard red→yellow→green heatmap ramp. Saturation/
     * lightness are fixed so every point on the ramp stays readable with
     * the white table text used elsewhere in the guess row.
     */
    private function starterColor(int $matchCount, int $total): string
    {
        $hue = $total > 0 ? (int) round(($matchCount / $total) * 120) : 0;

        return "hsl({$hue}, 75%, 38%)";
    }

    /**
     * A "Combo"/"Okizeme"/etc. category name is meaningful on its own — each
     * game defines its own game_entry row per category, so the same title
     * exists under a *different* entryid in every game. Comparing titles too
     * (not just entryid) means guessing "Combo" is correct as long as the
     * target's type is also literally titled "Combo", even for a different
     * game than the one actually guessed.
     */
    private function sameTypeTitle(GameEntry $guessedType, ?GameEntry $targetType): bool
    {
        if ($targetType === null) {
            return false;
        }

        return mb_strtolower(trim($guessedType->title)) === mb_strtolower(trim($targetType->title));
    }

    /**
     * How much of the shorter name's significant words also appear in the
     * other name — used to give partial ("orange") credit for a close but
     * wrong game/character guess, e.g. "Street Fighter V" guessed for
     * "Street Fighter 6", or "Ibuki" guessed in the wrong game. A ratio of at
     * least half the shorter name's words is treated as similar; below that,
     * two names sharing only an incidental word (e.g. "The") stay "wrong".
     */
    private function isSimilarName(string $a, string $b): bool
    {
        $wordsA = $this->nameWords($a);
        $wordsB = $this->nameWords($b);

        if ($wordsA === [] || $wordsB === []) {
            return false;
        }

        $shared = count(array_intersect($wordsA, $wordsB));

        return $shared / min(count($wordsA), count($wordsB)) >= 0.5;
    }

    /**
     * Lowercased, punctuation-stripped significant words from a name, with
     * common connector words dropped so two otherwise-unrelated titles don't
     * count as similar just for both containing "the" or "vs".
     */
    private function nameWords(string $name): array
    {
        $stopwords = ['the', 'a', 'an', 'of', 'and', 'vs', 'in', 'on'];

        $normalized = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name));
        $words = array_filter(explode(' ', trim($normalized)), fn ($word) => $word !== '' && ! in_array($word, $stopwords, true));

        return array_values($words);
    }

    /**
     * Direction ("higher"/"lower") plus a closeness tier ("close"/"far"), so
     * the UI can show a single arrow for a near miss and a double arrow for
     * a wild guess — turning damage into something you can actually home in
     * on over several guesses, instead of a coin-flip direction hint.
     */
    private function damageHint(Combo $target, ?float $guessedDamage): string
    {
        if ($target->damage === null || $guessedDamage === null) {
            return 'unknown';
        }

        $actual = (float) $target->damage;
        $diff = $actual - $guessedDamage;

        if (abs($diff) < 0.01) {
            return 'equal';
        }

        return ($diff > 0 ? 'higher' : 'lower').'_'.$this->damageCloseness($actual, $guessedDamage);
    }

    /**
     * Combo damage in this dataset spans from double digits to well over a
     * million, so "close" can't be a fixed percentage of the actual value:
     * that band is impossibly tight for a small combo (e.g. ±15% of 18 is
     * ±2.7) and trivially wide for a huge one (±15% of 1,000,000 is
     * ±150,000) — which is exactly why "close" was barely ever reachable in
     * practice. Comparing the two values as a ratio instead (whichever is
     * bigger divided by whichever is smaller) reads the same regardless of
     * scale: "within 50% either way" is achievable whether the answer is in
     * the hundreds or the hundred-thousands. Falls back to an absolute
     * 500-damage cutoff when either side is zero, since a ratio against zero
     * is undefined.
     */
    private function damageCloseness(float $actual, float $guessedDamage): string
    {
        $smaller = min($actual, $guessedDamage);

        if ($smaller <= 0) {
            return abs($actual - $guessedDamage) <= 500 ? 'close' : 'far';
        }

        return max($actual, $guessedDamage) / $smaller <= 1.5 ? 'close' : 'far';
    }
}
