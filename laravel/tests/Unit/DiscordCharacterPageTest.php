<?php

namespace Tests\Unit;

use App\Models\Character;
use App\Models\Game;
use App\Services\DiscordCharacterPage;
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

        $embed = $this->invokeToEmbed($game, $character);

        $viewsField = collect($embed['fields'])->firstWhere('name', 'Views');

        $this->assertSame('0', $viewsField['value']);
        $this->assertNotSame('', $viewsField['value']);
    }

    private function invokeToEmbed(Game $game, Character $character): array
    {
        $service = new DiscordCharacterPage();
        $method = new \ReflectionMethod($service, 'toEmbed');
        $method->setAccessible(true);

        return $method->invoke($service, $game, $character);
    }
}
