<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GameResource;
use App\Models\ResourceValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscordInteractionTest extends TestCase
{
    use RefreshDatabase;

    private string $publicKey;

    private string $secretKey;

    protected function setUp(): void
    {
        parent::setUp();

        $keypair = sodium_crypto_sign_keypair();
        $this->publicKey = sodium_crypto_sign_publickey($keypair);
        $this->secretKey = sodium_crypto_sign_secretkey($keypair);

        config(['services.discord.public_key' => sodium_bin2hex($this->publicKey)]);

        // The "array" cache store (see phpunit.xml) lives for the whole test
        // process, not just one test — Comble's per-user guess cache would
        // otherwise leak between test methods.
        Cache::flush();

        // Comble sends a private follow-up (see
        // DiscordInteractionController::postPrivateCombleFollowUp) on every
        // command/guess; fake it globally so tests don't make real requests.
        // Individual tests can still call Http::fake() again to add
        // assertions of their own (e.g. the video follow-up test).
        Http::fake(['discord.com/*' => Http::response([], 200)]);
    }

    private function postInteraction(array $payload): \Illuminate\Testing\TestResponse
    {
        // A real interaction always carries these; default them here so
        // every test payload has a valid target for the private-follow-up
        // webhook call without every test having to set them itself.
        $payload += ['application_id' => 'test-application-id', 'token' => 'test-interaction-token'];

        $body = json_encode($payload);
        $timestamp = (string) time();
        $signature = sodium_bin2hex(sodium_crypto_sign_detached($timestamp.$body, $this->secretKey));

        return $this->call('POST', route('discord.interactions'), server: [
            'HTTP_X-Signature-Ed25519' => $signature,
            'HTTP_X-Signature-Timestamp' => $timestamp,
            'CONTENT_TYPE' => 'application/json',
        ], content: $body);
    }

    public function test_ping_with_valid_signature_returns_pong(): void
    {
        $this->postInteraction(['type' => 1])
            ->assertOk()
            ->assertJson(['type' => 1]);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $body = json_encode(['type' => 1]);

        $this->call('POST', route('discord.interactions'), server: [
            'HTTP_X-Signature-Ed25519' => str_repeat('0', 128),
            'HTTP_X-Signature-Timestamp' => (string) time(),
            'CONTENT_TYPE' => 'application/json',
        ], content: $body)->assertStatus(401);

        $this->assertDatabaseCount('combo', 0);
    }

    public function test_combo_search_returns_matching_combo_as_embed(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);

        $combo = Combo::create([
            'combo' => 'A > B > C',
            'character_idcharacter' => $character->idcharacter,
            'type' => $listingType->entryid,
            'damage' => 250,
        ]);

        $response = $this->postInteraction([
            'type' => 2,
            'data' => [
                'name' => 'combo',
                'options' => [
                    [
                        'name' => 'search',
                        'options' => [
                            ['name' => 'game', 'value' => 'Test Game'],
                            ['name' => 'query', 'value' => 'A'],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk()->assertJson(['type' => 4]);
        $this->assertSame($combo->combo, $response->json('data.embeds.0.title'));
    }

    public function test_combo_search_with_video_returns_single_result_and_posts_video_follow_up(): void
    {
        Http::fake(['discord.com/*' => Http::response([], 200)]);

        config(['services.discord.bot_token' => 'fake-token']);

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);

        $withVideo = Combo::create([
            'combo' => 'A > B > C',
            'character_idcharacter' => $character->idcharacter,
            'type' => $listingType->entryid,
            'damage' => 500,
            'video' => 'https://youtu.be/dQw4w9WgXcQ',
        ]);

        Combo::create([
            'combo' => 'A > D > E',
            'character_idcharacter' => $character->idcharacter,
            'type' => $listingType->entryid,
            'damage' => 100,
        ]);

        $response = $this->postInteraction([
            'type' => 2,
            'channel_id' => '999888777',
            'data' => [
                'name' => 'combo',
                'options' => [
                    [
                        'name' => 'search',
                        'options' => [
                            ['name' => 'game', 'value' => 'Test Game'],
                            ['name' => 'query', 'value' => 'A'],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertCount(1, $response->json('data.embeds'));
        $this->assertSame($withVideo->combo, $response->json('data.embeds.0.title'));
        $this->assertNull($response->json('data.content'));

        Http::assertSent(fn ($request) => $request->url() === 'https://discord.com/api/v10/channels/999888777/messages'
            && $request['content'] === $withVideo->video
        );
    }

    public function test_combo_search_filters_by_character(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $wanted = Character::create(['name' => 'Wanted Character', 'game_idgame' => $game->idgame]);
        $other = Character::create(['name' => 'Other Character', 'game_idgame' => $game->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);

        Combo::create([
            'combo' => 'A > B > C',
            'character_idcharacter' => $wanted->idcharacter,
            'type' => $listingType->entryid,
            'damage' => 250,
        ]);

        Combo::create([
            'combo' => 'A > X > Y',
            'character_idcharacter' => $other->idcharacter,
            'type' => $listingType->entryid,
            'damage' => 999,
        ]);

        $response = $this->postInteraction([
            'type' => 2,
            'data' => [
                'name' => 'combo',
                'options' => [
                    [
                        'name' => 'search',
                        'options' => [
                            ['name' => 'game', 'value' => 'Test Game'],
                            ['name' => 'query', 'value' => 'A'],
                            ['name' => 'character', 'value' => 'Wanted'],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertCount(1, $response->json('data.embeds'));
        $this->assertSame('Wanted Character', $response->json('data.embeds.0.fields.0.value'));
    }

    public function test_combo_search_with_unknown_character_returns_ephemeral_message(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $response = $this->postInteraction([
            'type' => 2,
            'data' => [
                'name' => 'combo',
                'options' => [
                    [
                        'name' => 'search',
                        'options' => [
                            ['name' => 'game', 'value' => 'Test Game'],
                            ['name' => 'query', 'value' => 'A'],
                            ['name' => 'character', 'value' => 'Nobody'],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertSame(64, $response->json('data.flags'));
    }

    public function test_combo_search_with_no_matches_returns_ephemeral_message(): void
    {
        $game = Game::create(['name' => 'Empty Game', 'complete' => 1, 'modPass' => 'secret']);

        $response = $this->postInteraction([
            'type' => 2,
            'data' => [
                'name' => 'combo',
                'options' => [
                    [
                        'name' => 'search',
                        'options' => [
                            ['name' => 'game', 'value' => 'Empty Game'],
                            ['name' => 'query', 'value' => 'nothing'],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertSame(64, $response->json('data.flags'));
    }

    private function postComponent(string $customId, array $values, ?string $userId = null): \Illuminate\Testing\TestResponse
    {
        return $this->postInteraction(array_merge([
            'type' => 3,
            'data' => [
                'custom_id' => $customId,
                'component_type' => 3,
                'values' => $values,
            ],
        ], $this->memberPayload($userId)));
    }

    private function memberPayload(?string $userId): array
    {
        return $userId === null ? [] : ['member' => ['user' => ['id' => $userId]]];
    }

    public function test_combo_browse_starts_with_a_game_dropdown(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $response = $this->postInteraction([
            'type' => 2,
            'data' => ['name' => 'combo', 'options' => [['name' => 'browse']]],
        ]);

        $response->assertOk()->assertJson(['type' => 4]);
        $this->assertSame(64, $response->json('data.flags'));

        $select = $response->json('data.components.0.components.0');
        $this->assertSame('w:game::', $select['custom_id']);
        $this->assertContains((string) $game->idgame, array_column($select['options'], 'value'));
    }

    public function test_combo_browse_full_flow_narrows_results_by_resource(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);

        $resource = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Where?',
            'type' => 1,
            'primaryORsecundary' => 1,
        ]);
        $midscreen = ResourceValue::create(['value' => 'Midscreen', 'order' => 0, 'game_resources_idgame_resources' => $resource->idgame_resources]);
        $corner = ResourceValue::create(['value' => 'Corner', 'order' => 1, 'game_resources_idgame_resources' => $resource->idgame_resources]);

        $midscreenCombo = Combo::create([
            'combo' => 'Midscreen combo',
            'character_idcharacter' => $character->idcharacter,
            'type' => $listingType->entryid,
            'damage' => 100,
        ]);
        $midscreenCombo->resources()->create(['Resources_values_idResources_values' => $midscreen->idResources_values]);

        $cornerCombo = Combo::create([
            'combo' => 'Corner combo',
            'character_idcharacter' => $character->idcharacter,
            'type' => $listingType->entryid,
            'damage' => 999,
        ]);
        $cornerCombo->resources()->create(['Resources_values_idResources_values' => $corner->idResources_values]);

        // Step 1: choose the game.
        $step2 = $this->postComponent('w:game::', [(string) $game->idgame]);
        $step2->assertOk()->assertJson(['type' => 7]);
        $charSelect = $step2->json('data.components.0.components.0');
        $this->assertStringStartsWith('w:char::g='.$game->idgame, $charSelect['custom_id']);

        // Step 2: choose the character.
        $step3 = $this->postComponent($charSelect['custom_id'], [(string) $character->idcharacter]);
        $step3->assertOk();
        $resourceSelect = $step3->json('data.components.0.components.0');
        $this->assertStringStartsWith('w:res:'.$resource->idgame_resources.':', $resourceSelect['custom_id']);
        $this->assertContains('Corner', array_column($resourceSelect['options'], 'label'));

        // Step 3: narrow by the "Corner" resource value.
        $step4 = $this->postComponent($resourceSelect['custom_id'], [(string) $corner->idResources_values]);
        $step4->assertOk();
        $searchButton = collect($step4->json('data.components'))
            ->flatMap(fn ($row) => $row['components'])
            ->first(fn ($component) => ($component['label'] ?? null) === 'Search');
        $this->assertNotNull($searchButton);

        // Step 4: run the search — only the corner combo should match.
        $results = $this->postComponent($searchButton['custom_id'], []);
        $results->assertOk()->assertJson(['type' => 7]);
        $this->assertCount(1, $results->json('data.embeds'));
        $this->assertSame('Corner combo', $results->json('data.embeds.0.title'));
    }

    public function test_combo_browse_paginates_resources_and_keeps_selections_across_pages(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);

        // 6 primary resources force 2 pages (ceil(6/4) = 2, 4 per page).
        $resources = collect(range(1, 6))->map(fn ($n) => GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => "Resource {$n}",
            'type' => 1,
            'primaryORsecundary' => 1,
        ]));

        $values = $resources->mapWithKeys(fn ($resource, $i) => [
            $i + 1 => ResourceValue::create([
                'value' => "R{$resource->idgame_resources}-match",
                'order' => 0,
                'game_resources_idgame_resources' => $resource->idgame_resources,
            ]),
        ]);

        $matching = Combo::create([
            'combo' => 'Matches both filters',
            'character_idcharacter' => $character->idcharacter,
            'type' => $listingType->entryid,
            'damage' => 100,
        ]);
        $matching->resources()->create(['Resources_values_idResources_values' => $values[1]->idResources_values]);
        $matching->resources()->create(['Resources_values_idResources_values' => $values[5]->idResources_values]);

        $partial = Combo::create([
            'combo' => 'Matches only the first filter',
            'character_idcharacter' => $character->idcharacter,
            'type' => $listingType->entryid,
            'damage' => 999,
        ]);
        $partial->resources()->create(['Resources_values_idResources_values' => $values[1]->idResources_values]);

        // Reach the resource step (page 1 of 2).
        $step2 = $this->postComponent('w:game::', [(string) $game->idgame]);
        $charSelect = $step2->json('data.components.0.components.0');
        $page1 = $this->postComponent($charSelect['custom_id'], [(string) $character->idcharacter]);

        $page1->assertOk();
        $this->assertCount(5, $page1->json('data.components')); // 4 selects + 1 button row
        $this->assertStringContainsString('page 1 of 2', $page1->json('data.embeds.0.description'));

        $buttons = $page1->json('data.components.4.components');
        $previous = collect($buttons)->firstWhere('label', 'Previous');
        $next = collect($buttons)->firstWhere('label', 'Next');
        $this->assertTrue($previous['disabled']);
        $this->assertFalse($next['disabled']);

        // Select the first page's first resource (Resource 1 -> value 1).
        $resource1Select = $page1->json('data.components.0.components.0');
        $afterResource1 = $this->postComponent($resource1Select['custom_id'], [(string) $values[1]->idResources_values]);

        // Navigate to page 2 — the custom_id on the Next button carries the
        // resource-1 selection forward.
        $nextOnPage1 = collect($afterResource1->json('data.components.4.components'))->firstWhere('label', 'Next');
        $page2 = $this->postComponent($nextOnPage1['custom_id'], []);

        $page2->assertOk();
        $this->assertStringContainsString('page 2 of 2', $page2->json('data.embeds.0.description'));
        $page2Buttons = $page2->json('data.components.'.(count($page2->json('data.components')) - 1).'.components');
        $this->assertTrue(collect($page2Buttons)->firstWhere('label', 'Previous')['disabled'] === false);

        // Select page 2's resource (Resource 5 -> value 5).
        $resource5Select = collect($page2->json('data.components'))
            ->first(fn ($row) => str_starts_with($row['components'][0]['custom_id'] ?? '', 'w:res:'.$resources[4]->idgame_resources.':'));
        $afterResource5 = $this->postComponent($resource5Select['components'][0]['custom_id'], [(string) $values[5]->idResources_values]);

        // Search should now apply both the page-1 and page-2 selections.
        $searchButton = collect($afterResource5->json('data.components'))
            ->flatMap(fn ($row) => $row['components'])
            ->first(fn ($component) => ($component['label'] ?? null) === 'Search');

        $results = $this->postComponent($searchButton['custom_id'], []);

        $results->assertOk();
        $this->assertCount(1, $results->json('data.embeds'));
        $this->assertSame($matching->combo, $results->json('data.embeds.0.title'));
    }

    public function test_combo_browse_reset_button_returns_to_game_step(): void
    {
        Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $response = $this->postComponent('w:reset::', []);

        $response->assertOk();
        $this->assertSame('w:game::', $response->json('data.components.0.components.0.custom_id'));
    }

    private function postModalSubmit(string $customId, array $components, ?string $userId = null): \Illuminate\Testing\TestResponse
    {
        return $this->postInteraction(array_merge([
            'type' => 5,
            'data' => ['custom_id' => $customId, 'components' => $components],
        ], $this->memberPayload($userId)));
    }

    private function damageModalRow(string $value): array
    {
        return [['type' => 1, 'components' => [['type' => 4, 'custom_id' => 'damage', 'value' => $value]]]];
    }

    public function test_combo_comble_starts_with_a_public_game_dropdown_and_the_hidden_reveal(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $type = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);
        Combo::create([
            'combo' => 'AAA BBB CCC DDD EEE',
            'character_idcharacter' => $character->idcharacter,
            'type' => $type->entryid,
            'damage' => 3000,
        ]);

        $response = $this->postInteraction(array_merge([
            'type' => 2,
            'data' => ['name' => 'combo', 'options' => [['name' => 'comble']]],
        ], $this->memberPayload('owner-1')));

        $response->assertOk()->assertJson(['type' => 4]);
        // Public (not ephemeral), so other channel members can watch — no
        // "flags" key at all rather than the 64 (ephemeral) the wizard uses.
        $this->assertNull($response->json('data.flags'));
        $this->assertSame('cb:game::u=owner-1', $response->json('data.components.0.components.0.custom_id'));
        $this->assertContains((string) $game->idgame, array_column($response->json('data.components.0.components.0.options'), 'value'));
        $this->assertStringContainsString('▁', $response->json('data.embeds.0.description'));
        $this->assertStringNotContainsString('AAA', $response->json('data.embeds.0.description'));
    }

    public function test_combo_comble_full_flow_records_a_winning_guess(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $type = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);
        Combo::create([
            'combo' => 'AAA BBB CCC DDD EEE',
            'character_idcharacter' => $character->idcharacter,
            'type' => $type->entryid,
            'damage' => 3000,
        ]);

        $owner = 'owner-1';

        $charStep = $this->postComponent('cb:game::u='.$owner, [(string) $game->idgame], $owner);
        $charStep->assertOk()->assertJson(['type' => 7]);
        $charSelect = $charStep->json('data.components.0.components.0');
        $this->assertStringStartsWith('cb:char::', $charSelect['custom_id']);
        $this->assertStringContainsString('g='.$game->idgame, $charSelect['custom_id']);
        $this->assertStringContainsString('u='.$owner, $charSelect['custom_id']);

        $typeStep = $this->postComponent($charSelect['custom_id'], [(string) $character->idcharacter], $owner);
        $typeStep->assertOk();
        $typeSelect = $typeStep->json('data.components.0.components.0');
        $this->assertStringStartsWith('cb:type::', $typeSelect['custom_id']);
        $this->assertStringContainsString('g='.$game->idgame, $typeSelect['custom_id']);
        $this->assertStringContainsString('c='.$character->idcharacter, $typeSelect['custom_id']);
        $this->assertStringContainsString('u='.$owner, $typeSelect['custom_id']);

        $modal = $this->postComponent($typeSelect['custom_id'], [(string) $type->entryid], $owner);
        $modal->assertOk()->assertJson(['type' => 9]);
        $modalCustomId = $modal->json('data.custom_id');
        $this->assertStringStartsWith('cb:dmgsubmit::', $modalCustomId);
        $this->assertStringContainsString('t='.$type->entryid, $modalCustomId);

        $result = $this->postModalSubmit($modalCustomId, $this->damageModalRow('3000'), $owner);
        $result->assertOk()->assertJson(['type' => 7]);

        // Public message: progress only — no guessed names, and (since this
        // guess won) not the answer either, so it doesn't spoil the puzzle
        // for anyone else watching who hasn't played yet.
        $description = $result->json('data.embeds.0.description');
        $this->assertStringContainsString('You got it!', $description);
        // The reveal is scattered across the combo's tokens (not always the
        // first one), so check that some token is revealed rather than
        // asserting a specific one.
        $this->assertTrue(
            collect(['AAA', 'BBB', 'CCC', 'DDD', 'EEE'])->contains(fn ($token) => str_contains($description, $token)),
            'expected at least one combo token to be revealed'
        );
        $this->assertStringNotContainsString($character->name, $description);
        $this->assertStringNotContainsString($game->name, $description);

        // Finished: the game dropdown is replaced with a link button.
        $this->assertSame(5, $result->json('data.components.0.components.0.style'));

        // Private follow-up: the same guess, but with names and the answer.
        Http::assertSent(function ($request) use ($character, $game) {
            $description = $request['embeds'][0]['description'] ?? '';

            return $request->url() === 'https://discord.com/api/v10/webhooks/test-application-id/test-interaction-token'
                && $request['flags'] === 64
                && str_contains($description, 'You got it!')
                && str_contains($description, $character->name)
                && str_contains($description, $game->name);
        });
    }

    public function test_combo_comble_rejects_a_non_numeric_damage_guess_without_recording_it(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $type = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);
        Combo::create([
            'combo' => 'AAA BBB CCC DDD EEE',
            'character_idcharacter' => $character->idcharacter,
            'type' => $type->entryid,
            'damage' => 3000,
        ]);

        $owner = 'owner-1';
        $stateRaw = 'g='.$game->idgame.';c='.$character->idcharacter.';t='.$type->entryid.';u='.$owner;

        $result = $this->postModalSubmit('cb:dmgsubmit::'.$stateRaw, $this->damageModalRow('not-a-number'), $owner);

        $result->assertOk()->assertJson(['type' => 7]);
        $this->assertStringContainsString('Damage must be a non-negative number.', $result->json('data.embeds.0.description'));
        $this->assertStringStartsWith('cb:game::u='.$owner, $result->json('data.components.0.components.0.custom_id'));
    }

    public function test_combo_comble_progress_persists_across_separate_command_invocations(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $wrongCharacter = Character::create(['name' => 'Wrong Character', 'game_idgame' => $game->idgame]);
        $type = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);
        Combo::create([
            'combo' => 'AAA BBB CCC DDD EEE',
            'character_idcharacter' => $character->idcharacter,
            'type' => $type->entryid,
            'damage' => 3000,
        ]);

        $owner = 'owner-1';
        $stateRaw = 'g='.$game->idgame.';c='.$wrongCharacter->idcharacter.';t='.$type->entryid.';u='.$owner;
        $this->postModalSubmit('cb:dmgsubmit::'.$stateRaw, $this->damageModalRow('100'), $owner)->assertOk();

        $response = $this->postInteraction(array_merge([
            'type' => 2,
            'data' => ['name' => 'combo', 'options' => [['name' => 'comble']]],
        ], $this->memberPayload($owner)));

        // Public: the prior guess still counts, but only as a square — no name.
        $description = $response->json('data.embeds.0.description');
        $this->assertStringContainsString('4 guesses left.', $description);
        $this->assertStringNotContainsString($wrongCharacter->name, $description);

        // Private follow-up carries the name.
        Http::assertSent(fn ($request) => $request->url() === 'https://discord.com/api/v10/webhooks/test-application-id/test-interaction-token'
            && str_contains($request['embeds'][0]['description'] ?? '', $wrongCharacter->name));
    }

    public function test_combo_comble_bounces_a_click_from_someone_else_with_a_private_reply(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $owner = 'owner-1';
        $intruder = 'intruder-2';

        $response = $this->postComponent('cb:game::u='.$owner, [(string) $game->idgame], $intruder);

        $response->assertOk()->assertJson(['type' => 4]);
        $this->assertSame(64, $response->json('data.flags'));
        $this->assertStringContainsString("isn't your Comble game", $response->json('data.content'));
    }
}
