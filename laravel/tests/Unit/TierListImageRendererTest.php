<?php

namespace Tests\Unit;

use App\Models\Character;
use App\Models\CharacterResourceValueAlias;
use App\Models\Game;
use App\Models\ResourceValue;
use App\Models\TierList;
use App\Models\TierListEntry;
use App\Models\User;
use App\Services\TierListImageRenderer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TierListImageRendererTest extends TestCase
{
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    private function emptyTiers(): Collection
    {
        return collect(['S' => collect(), 'A' => collect(), 'B' => collect(), 'C' => collect(), 'D' => collect(), 'E' => collect(), 'F' => collect()]);
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

    /**
     * The badge should use the viewing character's alias icon in preference
     * to the resource value's own (global) icon. Points the value's own
     * icon at a path that doesn't exist, so if drawEntry() ever fell back to
     * it instead, loadSquare() would fail to decode it and skip the badge
     * entirely — leaving the badge area background-colored rather than the
     * alias icon's solid red, which is what this test samples for.
     */
    public function test_badge_prefers_the_characters_alias_icon_over_the_resource_values_default_icon(): void
    {
        Storage::fake('public');

        $badge = imagecreatetruecolor(4, 4);
        imagefill($badge, 0, 0, imagecolorallocate($badge, 255, 0, 0));
        ob_start();
        imagepng($badge);
        Storage::disk('public')->put('resource-value-icons/alias.png', ob_get_clean());
        imagedestroy($badge);

        $character = (new Character)->forceFill(['idcharacter' => 1, 'name' => 'Ryu', 'image' => null]);
        $character->exists = true;

        $resourceValue = (new ResourceValue)->forceFill(['idResources_values' => 1, 'value' => '1', 'icon' => 'resource-value-icons/does-not-exist.png']);
        $resourceValue->exists = true;

        $alias = (new CharacterResourceValueAlias)->forceFill([
            'character_idcharacter' => 1,
            'resources_values_idResources_values' => 1,
            'alias' => 'A',
            'icon' => 'resource-value-icons/alias.png',
        ]);
        $resourceValue->setRelation('characterAliases', collect([$alias]));

        $tiers = $this->emptyTiers();
        $tiers['S'] = collect([['character' => $character, 'resourceValue' => $resourceValue, 'tier' => 'S', 'votes' => 1]]);

        $png = (new TierListImageRenderer)->render(['tiers' => $tiers, 'tierListCount' => 1], 'Test Game', null, null);

        $image = imagecreatefromstring($png);
        // The badge is drawn at the thumbnail's bottom-right corner (see
        // BADGE_SIZE/THUMB_SIZE/PADDING/LABEL_WIDTH/TITLE_HEIGHT in
        // TierListImageRenderer); (150, 95) sits inside that badge area for
        // the first entry of the first non-empty tier.
        $color = imagecolorsforindex($image, imagecolorat($image, 150, 95));
        imagedestroy($image);

        $this->assertSame(['red' => 255, 'green' => 0, 'blue' => 0, 'alpha' => 0], $color);
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

    private function tierList(Collection $entries): TierList
    {
        $game = (new Game)->forceFill(['idgame' => 1, 'name' => 'Test Game']);
        $game->exists = true;

        $user = (new User)->forceFill(['iduser' => 1, 'nickname' => 'Aegis']);
        $user->exists = true;

        $tierList = (new TierList)->forceFill(['idtier_list' => 1, 'title' => 'My Test Tier List']);
        $tierList->exists = true;
        $tierList->setRelation('game', $game);
        $tierList->setRelation('user', $user);
        $tierList->setRelation('entries', $entries);

        return $tierList;
    }

    private function entry(string $tier, Character $character, ?ResourceValue $resourceValue = null): TierListEntry
    {
        $entry = (new TierListEntry)->forceFill(['idtier_list_entry' => 1, 'tier' => $tier, 'order' => 0]);
        $entry->exists = true;
        $entry->setRelation('character', $character);
        $entry->setRelation('resourceValue', $resourceValue);

        return $entry;
    }

    public function test_render_for_tier_list_produces_a_valid_png_for_a_character_with_no_portrait(): void
    {
        $character = (new Character)->forceFill(['idcharacter' => 1, 'name' => 'Valentine', 'image' => null]);
        $character->exists = true;

        $tierList = $this->tierList(collect([$this->entry('S', $character)]));

        $png = (new TierListImageRenderer)->renderForTierList($tierList);

        $this->assertStringStartsWith(self::PNG_SIGNATURE, $png);
    }

    public function test_render_for_tier_list_falls_back_to_a_placeholder_for_a_missing_portrait_file(): void
    {
        $character = (new Character)->forceFill(['idcharacter' => 1, 'name' => 'Ryu', 'image' => 'character-portraits/does-not-exist.png']);
        $character->exists = true;

        $tierList = $this->tierList(collect([$this->entry('A', $character)]));

        $png = (new TierListImageRenderer)->renderForTierList($tierList);

        $this->assertStringStartsWith(self::PNG_SIGNATURE, $png);
    }

    public function test_render_for_tier_list_handles_no_entries_in_any_tier(): void
    {
        $tierList = $this->tierList(collect());

        $png = (new TierListImageRenderer)->renderForTierList($tierList);

        $this->assertStringStartsWith(self::PNG_SIGNATURE, $png);
    }
}
