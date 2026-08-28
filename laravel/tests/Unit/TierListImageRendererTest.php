<?php

namespace Tests\Unit;

use App\Models\Character;
use App\Services\TierListImageRenderer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TierListImageRendererTest extends TestCase
{
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    private function emptyTiers(): Collection
    {
        return collect(['S' => collect(), 'A' => collect(), 'B' => collect(), 'C' => collect(), 'D' => collect(), 'F' => collect()]);
    }

    /**
     * The character has no `image` at all — loadThumbnail() must fall back to
     * the initial-letter placeholder tile instead of trying to read a file,
     * and rendering must still succeed.
     */
    public function test_render_produces_a_valid_png_for_a_character_with_no_portrait(): void
    {
        $character = (new Character)->forceFill(['idcharacter' => 1, 'name' => 'Valentine', 'image' => null]);
        $character->exists = true;

        $tiers = $this->emptyTiers();
        $tiers['S'] = collect([['character' => $character, 'resourceValue' => null, 'tier' => 'S', 'votes' => 1]]);

        $png = (new TierListImageRenderer)->render(['tiers' => $tiers, 'tierListCount' => 1], 'Test Game', null, null);

        $this->assertStringStartsWith(self::PNG_SIGNATURE, $png);
    }

    /**
     * A path that doesn't exist on the `public` disk is the same "can't
     * decode this portrait" case as a corrupt/unsupported file — loadImage()
     * must degrade to the placeholder rather than throwing.
     */
    public function test_render_falls_back_to_a_placeholder_for_a_missing_portrait_file(): void
    {
        $character = (new Character)->forceFill(['idcharacter' => 1, 'name' => 'Ryu', 'image' => 'character-portraits/does-not-exist.png']);
        $character->exists = true;

        $tiers = $this->emptyTiers();
        $tiers['A'] = collect([['character' => $character, 'resourceValue' => null, 'tier' => 'A', 'votes' => 1]]);

        $png = (new TierListImageRenderer)->render(['tiers' => $tiers, 'tierListCount' => 1], 'Test Game', null, null);

        $this->assertStringStartsWith(self::PNG_SIGNATURE, $png);
    }

    public function test_render_handles_no_entries_in_any_tier(): void
    {
        $png = (new TierListImageRenderer)->render(['tiers' => $this->emptyTiers(), 'tierListCount' => 0], 'Test Game', null, null);

        $this->assertStringStartsWith(self::PNG_SIGNATURE, $png);
    }

    public function test_render_with_a_date_range_still_produces_a_valid_png(): void
    {
        $character = (new Character)->forceFill(['idcharacter' => 1, 'name' => 'Ryu', 'image' => null]);
        $character->exists = true;

        $tiers = $this->emptyTiers();
        $tiers['F'] = collect([['character' => $character, 'resourceValue' => null, 'tier' => 'F', 'votes' => 3]]);

        $png = (new TierListImageRenderer)->render(
            ['tiers' => $tiers, 'tierListCount' => 3],
            'Test Game',
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-02-01'),
        );

        $this->assertStringStartsWith(self::PNG_SIGNATURE, $png);
    }
}
