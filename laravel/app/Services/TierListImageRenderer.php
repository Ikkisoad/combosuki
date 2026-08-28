<?php

namespace App\Services;

use App\Models\Character;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pure GD compositing for the `/csk tierlist` Discord image — kept free of
 * any Discord/HTTP concerns so it can be unit tested on its own. GD (not
 * Browsershot/Puppeteer) was chosen specifically because production disables
 * PHP's exec()/symlink(), which rules out shelling out to a headless
 * browser; GD needs no subprocess.
 */
class TierListImageRenderer
{
    private const TIER_ORDER = ['S', 'A', 'B', 'C', 'D', 'F'];

    /** Hex colors copied from resources/css/app.css .tier-* classes, for visual parity with the website. */
    private const TIER_COLORS = [
        'S' => [0xC0, 0x39, 0x2B],
        'A' => [0xE6, 0x7E, 0x22],
        'B' => [0xF1, 0xC4, 0x0F],
        'C' => [0x2E, 0xCC, 0x71],
        'D' => [0x34, 0x98, 0xDB],
        'F' => [0x7F, 0x8C, 0x8D],
    ];

    /** Tiers whose swatch background is light enough to need dark label text (matches app.css). */
    private const DARK_TEXT_TIERS = ['B', 'C'];

    private const CANVAS_WIDTH = 900;

    private const THUMB_SIZE = 64;

    private const BADGE_SIZE = 22;

    private const PADDING = 10;

    private const LABEL_WIDTH = 80;

    private const TITLE_HEIGHT = 44;

    /**
     * @param  array{tiers: Collection, tierListCount: int}  $aggregate  TierListAggregator::aggregate()'s return value.
     */
    public function render(array $aggregate, string $gameName, ?Carbon $from, ?Carbon $to): string
    {
        $tiers = $aggregate['tiers'];
        $tierListCount = $aggregate['tierListCount'];

        $nonEmptyTiers = collect(self::TIER_ORDER)->filter(fn ($tier) => $tiers[$tier]->isNotEmpty());

        $usableWidth = self::CANVAS_WIDTH - self::LABEL_WIDTH - (3 * self::PADDING);
        $perRow = max(1, intdiv($usableWidth, self::THUMB_SIZE + self::PADDING));

        $rowHeights = $nonEmptyTiers->mapWithKeys(function ($tier) use ($tiers, $perRow) {
            $rows = max(1, (int) ceil($tiers[$tier]->count() / $perRow));

            return [$tier => $rows * (self::THUMB_SIZE + self::PADDING) + self::PADDING];
        });

        $height = self::TITLE_HEIGHT + max($rowHeights->sum(), self::PADDING);

        $image = imagecreatetruecolor(self::CANVAS_WIDTH, $height);
        $background = imagecolorallocate($image, 0x2B, 0x2B, 0x2B);
        imagefill($image, 0, 0, $background);

        $white = imagecolorallocate($image, 255, 255, 255);
        $muted = imagecolorallocate($image, 0xAA, 0xAA, 0xAA);

        imagestring($image, 5, self::PADDING, 6, $gameName.' Tier List', $white);
        imagestring($image, 2, self::PADDING, 26, $this->subtitle($tierListCount, $from, $to), $muted);

        $y = self::TITLE_HEIGHT;

        foreach ($nonEmptyTiers as $tier) {
            $entries = $tiers[$tier];
            $rowHeight = $rowHeights[$tier];

            $this->drawTierLabel($image, $tier, $y, $rowHeight, $white);
            $this->drawEntries($image, $entries, $y, $perRow);

            $y += $rowHeight;
        }

        return $this->encode($image);
    }

    private function subtitle(int $tierListCount, ?Carbon $from, ?Carbon $to): string
    {
        $range = match (true) {
            $from && $to => ' between '.$from->toDateString().' and '.$to->toDateString(),
            (bool) $from => ' since '.$from->toDateString(),
            (bool) $to => ' through '.$to->toDateString(),
            default => '',
        };

        return 'Based on '.$tierListCount.' tier list'.($tierListCount === 1 ? '' : 's').$range;
    }

    private function drawTierLabel(\GdImage $image, string $tier, int $y, int $rowHeight, int $whiteColor): void
    {
        [$r, $g, $b] = self::TIER_COLORS[$tier];
        $swatchColor = imagecolorallocate($image, $r, $g, $b);
        $labelColor = in_array($tier, self::DARK_TEXT_TIERS, true)
            ? imagecolorallocate($image, 0x2E, 0x2E, 0x2E)
            : $whiteColor;

        $swatchHeight = $rowHeight - self::PADDING;
        imagefilledrectangle($image, self::PADDING, $y, self::PADDING + self::LABEL_WIDTH, $y + $swatchHeight, $swatchColor);

        $textX = self::PADDING + (int) (self::LABEL_WIDTH / 2) - 4;
        $textY = $y + (int) ($swatchHeight / 2) - 8;
        imagestring($image, 5, $textX, $textY, $tier, $labelColor);
    }

    /** @param  Collection  $entries  Each entry: ['character' => Character, 'resourceValue' => ?ResourceValue, 'tier' => string, 'votes' => int]. */
    private function drawEntries(\GdImage $image, Collection $entries, int $rowStartY, int $perRow): void
    {
        $x = self::PADDING * 2 + self::LABEL_WIDTH;
        $y = $rowStartY;

        foreach ($entries->values() as $index => $entry) {
            if ($index > 0 && $index % $perRow === 0) {
                $y += self::THUMB_SIZE + self::PADDING;
                $x = self::PADDING * 2 + self::LABEL_WIDTH;
            }

            $this->drawEntry($image, $entry, $x, $y);

            $x += self::THUMB_SIZE + self::PADDING;
        }
    }

    private function drawEntry(\GdImage $image, array $entry, int $x, int $y): void
    {
        $character = $entry['character'];
        $resourceValue = $entry['resourceValue'] ?? null;

        $thumb = $this->loadThumbnail($character);
        imagecopy($image, $thumb, $x, $y, 0, 0, self::THUMB_SIZE, self::THUMB_SIZE);
        imagedestroy($thumb);

        $badgeIcon = $resourceValue?->aliasFor($character)?->icon ?? $resourceValue?->icon;

        if ($badgeIcon) {
            $badge = $this->loadSquare($badgeIcon, self::BADGE_SIZE);

            if ($badge) {
                $badgeX = $x + self::THUMB_SIZE - self::BADGE_SIZE;
                $badgeY = $y + self::THUMB_SIZE - self::BADGE_SIZE;
                imagecopy($image, $badge, $badgeX, $badgeY, 0, 0, self::BADGE_SIZE, self::BADGE_SIZE);
                imagedestroy($badge);
            }
        }
    }

    private function loadThumbnail(Character $character): \GdImage
    {
        $square = $character->image ? $this->loadSquare($character->image, self::THUMB_SIZE) : null;

        return $square ?? $this->placeholder($character->name);
    }

    /** Loads $path, center-crops it to a square, and resizes it to $size — or null if it can't be read/decoded. */
    private function loadSquare(string $path, int $size): ?\GdImage
    {
        $source = $this->loadImage($path);

        if (! $source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $srcX = (int) (($width - $side) / 2);
        $srcY = (int) (($height - $side) / 2);

        $square = imagecreatetruecolor($size, $size);
        imagealphablending($square, false);
        imagesavealpha($square, true);
        $transparent = imagecolorallocatealpha($square, 0, 0, 0, 127);
        imagefilledrectangle($square, 0, 0, $size, $size, $transparent);
        imagealphablending($square, true);

        imagecopyresampled($square, $source, 0, 0, $srcX, $srcY, $size, $size, $side, $side);
        imagedestroy($source);

        return $square;
    }

    /** Decode failures (missing file, or a format GD can't handle — e.g. some avif/heic builds) return null rather than throwing. */
    private function loadImage(string $path): ?\GdImage
    {
        try {
            if (! Storage::disk('public')->exists($path)) {
                return null;
            }

            $bytes = Storage::disk('public')->get($path);
            $image = @imagecreatefromstring($bytes);

            return $image ?: null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function placeholder(string $name): \GdImage
    {
        $image = imagecreatetruecolor(self::THUMB_SIZE, self::THUMB_SIZE);
        $background = imagecolorallocate($image, 0x55, 0x55, 0x55);
        imagefill($image, 0, 0, $background);

        $white = imagecolorallocate($image, 255, 255, 255);
        $initial = Str::upper(Str::substr($name, 0, 1)) ?: '?';
        imagestring($image, 5, (int) (self::THUMB_SIZE / 2) - 4, (int) (self::THUMB_SIZE / 2) - 7, $initial, $white);

        return $image;
    }

    private function encode(\GdImage $image): string
    {
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
