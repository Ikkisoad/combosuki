<?php

namespace Tests\Unit;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Services\DiscordCharacterPage;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DiscordCharacterPageTest extends TestCase
{
    /**
     * Regression test for a production bug: `/csk character` deferred (type
     * 5) then got stuck on "Combosuki is thinking..." forever, because
     * Discord rejects an embed field with an empty-string `value`, and a
     * null `views` (e.g. the column missing on a given environment — schema
     * drift, see the migrations-table-can-lie note) rendered as "". That 400
     * from Discord was never surfaced anywhere, since nothing checked the
     * follow-up PATCH's response, so the deferred message just never got
     * edited. Built entirely in memory with forceFill(), no DB involved,
     * since the `character.views` column is NOT NULL with a default and so
     * can't actually hold null through Eloquent/the DB in a test.
     */
    public function test_embed_falls_back_to_zero_views_instead_of_an_empty_field_value(): void
    {
        $game = (new Game())->forceFill(['idgame' => 5, 'name' => 'Test Game']);
        $game->exists = true;

        $character = (new Character())->forceFill([
            'idcharacter' => 9,
            'name' => 'Ryu',
            'game_idgame' => 5,
            'views' => null,
        ]);
        $character->exists = true;

        $embed = $this->invokeToEmbed($game, $character, new Collection());

        $viewsField = collect($embed['fields'])->firstWhere('name', 'Views');

        $this->assertSame('0', $viewsField['value']);
        $this->assertNotSame('', $viewsField['value']);
    }

    /**
     * Regression test for a second production occurrence of the same
     * "stuck on thinking" bug: a legacy character had a malformed `image`
     * value (free-text, from before uploads existed — see
     * Character::imageUrl) that wasn't a well-formed absolute URL. Discord
     * rejected the whole embed (thumbnail.url "Not a well formed URL"), and
     * again nothing checked the follow-up PATCH's response, so the deferred
     * message never got edited.
     */
    public function test_embed_omits_the_thumbnail_for_a_malformed_legacy_image_url(): void
    {
        $game = (new Game())->forceFill(['idgame' => 5, 'name' => 'Test Game']);
        $game->exists = true;

        $character = (new Character())->forceFill([
            'idcharacter' => 9,
            'name' => 'Ryu',
            'game_idgame' => 5,
            'views' => 0,
            'image' => 'not a well formed url',
        ]);
        $character->exists = true;

        $embed = $this->invokeToEmbed($game, $character, new Collection());

        $this->assertArrayNotHasKey('thumbnail', $embed);
    }

    /**
     * Regression guard for the `/csk character` embed missing the combos
     * that are visible on the character's own page (characters.show) — the
     * embed used to only show the Game/Views fields, so a searcher couldn't
     * tell whether the character had any combos without following the link.
     */
    public function test_embed_lists_the_given_top_combos(): void
    {
        $game = (new Game())->forceFill(['idgame' => 5, 'name' => 'Test Game']);
        $game->exists = true;

        $character = (new Character())->forceFill([
            'idcharacter' => 9,
            'name' => 'Ryu',
            'game_idgame' => 5,
            'views' => 0,
        ]);
        $character->exists = true;

        $combo = (new Combo())->forceFill([
            'idcombo' => 1,
            'combo' => '2LP2LP5HP>Shoryuken',
            'damage' => 350,
        ]);

        $embed = $this->invokeToEmbed($game, $character, new Collection([$combo]));

        $combosField = collect($embed['fields'])->firstWhere('name', 'Top Combos');

        $this->assertNotNull($combosField);
        $this->assertSame('2LP2LP5HP>Shoryuken — 350 dmg', $combosField['value']);
    }

    public function test_embed_omits_the_combos_field_when_there_are_none(): void
    {
        $game = (new Game())->forceFill(['idgame' => 5, 'name' => 'Test Game']);
        $game->exists = true;

        $character = (new Character())->forceFill([
            'idcharacter' => 9,
            'name' => 'Ryu',
            'game_idgame' => 5,
            'views' => 0,
        ]);
        $character->exists = true;

        $embed = $this->invokeToEmbed($game, $character, new Collection());

        $this->assertNull(collect($embed['fields'])->firstWhere('name', 'Top Combos'));
    }

    /**
     * Regression guard for the `/csk character` embed not surfacing the
     * same "default query" combos the character's own page shows (see
     * CharacterController::show()) — before this, the embed only ever
     * listed the plain top-damage combos.
     */
    public function test_embed_lists_the_given_default_query_combos(): void
    {
        $game = (new Game())->forceFill(['idgame' => 5, 'name' => 'Test Game']);
        $game->exists = true;

        $character = (new Character())->forceFill([
            'idcharacter' => 9,
            'name' => 'Ryu',
            'game_idgame' => 5,
            'views' => 0,
        ]);
        $character->exists = true;

        $combo = (new Combo())->forceFill([
            'idcombo' => 1,
            'combo' => 'Corner starter > ender',
            'damage' => 400,
        ]);

        $embed = $this->invokeToEmbed($game, $character, new Collection(), new Collection([
            ['label' => 'Corner Combo', 'combo' => $combo],
        ]));

        $queryField = collect($embed['fields'])->firstWhere('name', 'Corner Combo');

        $this->assertNotNull($queryField);
        $this->assertSame('Corner starter > ender — 400 dmg', $queryField['value']);
    }

    private function invokeToEmbed(Game $game, Character $character, Collection $combos, ?Collection $queryCombos = null): array
    {
        $service = new DiscordCharacterPage();
        $method = new \ReflectionMethod($service, 'toEmbed');
        $method->setAccessible(true);

        return $method->invoke($service, $game, $character, $combos, $queryCombos);
    }
}
