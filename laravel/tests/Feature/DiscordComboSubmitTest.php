<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GameResource;
use App\Models\Resource;
use App\Models\ResourceValue;
use App\Models\User;
use App\Models\UserConnectedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DiscordComboSubmitTest extends TestCase
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
    }

    private function postInteraction(array $payload): TestResponse
    {
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

    private function memberPayload(string $userId): array
    {
        return ['member' => ['user' => ['id' => $userId]]];
    }

    private function postComponent(string $customId, array $values, string $userId): TestResponse
    {
        return $this->postInteraction(array_merge([
            'type' => 3,
            'data' => ['custom_id' => $customId, 'component_type' => 3, 'values' => $values],
        ], $this->memberPayload($userId)));
    }

    private function postModalSubmit(string $customId, array $components, string $userId): TestResponse
    {
        return $this->postInteraction(array_merge([
            'type' => 5,
            'data' => ['custom_id' => $customId, 'components' => $components],
        ], $this->memberPayload($userId)));
    }

    private function detailsModalRows(string $combo, string $damage = '', string $comments = '', string $video = ''): array
    {
        return [
            ['type' => 1, 'components' => [['type' => 4, 'custom_id' => 'combo', 'value' => $combo]]],
            ['type' => 1, 'components' => [['type' => 4, 'custom_id' => 'damage', 'value' => $damage]]],
            ['type' => 1, 'components' => [['type' => 4, 'custom_id' => 'comments', 'value' => $comments]]],
            ['type' => 1, 'components' => [['type' => 4, 'custom_id' => 'video', 'value' => $video]]],
        ];
    }

    private function numberModalRow(int $gameResourceId, string $value): array
    {
        return [['type' => 1, 'components' => [['type' => 4, 'custom_id' => 'f'.$gameResourceId, 'value' => $value]]]];
    }

    private function linkDiscordUser(string $discordId): User
    {
        $user = User::create(['nickname' => 'linked-user', 'password' => 'password123']);

        UserConnectedAccount::create([
            'provider' => 'discord',
            'provider_user_id' => $discordId,
            'user_iduser' => $user->iduser,
        ]);

        return $user;
    }

    private function findButton(TestResponse $response, string $label): ?array
    {
        return collect($response->json('data.components'))
            ->flatMap(fn ($row) => $row['components'])
            ->firstWhere('label', $label);
    }

    public function test_submit_rejects_an_unlinked_discord_user(): void
    {
        $response = $this->postInteraction(array_merge([
            'type' => 2,
            'data' => ['name' => 'csk', 'options' => [['name' => 'submit']]],
        ], $this->memberPayload('unlinked-1')));

        $response->assertOk();
        $this->assertSame(64, $response->json('data.flags'));
        $this->assertStringContainsString('connect your Discord account', $response->json('data.content'));
        $this->assertDatabaseCount('combo', 0);
    }

    public function test_submit_full_flow_creates_an_unverified_combo_with_the_games_current_patch(): void
    {
        $discordId = 'linked-1';
        $user = $this->linkDiscordUser($discordId);

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret', 'patch' => '1.03']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);
        $resource = GameResource::create(['game_idgame' => $game->idgame, 'text_name' => 'Where?', 'type' => 1, 'primaryORsecundary' => 1]);
        $where = ResourceValue::create(['value' => 'Corner', 'order' => 0, 'game_resources_idgame_resources' => $resource->idgame_resources]);

        $start = $this->postInteraction(array_merge([
            'type' => 2,
            'data' => ['name' => 'csk', 'options' => [['name' => 'submit']]],
        ], $this->memberPayload($discordId)));
        $start->assertOk()->assertJson(['type' => 4]);
        $gameSelect = $start->json('data.components.0.components.0');
        $this->assertSame('sub:game::', $gameSelect['custom_id']);

        $charStep = $this->postComponent($gameSelect['custom_id'], [(string) $game->idgame], $discordId);
        $charSelect = $charStep->json('data.components.0.components.0');
        $this->assertStringStartsWith('sub:char::g='.$game->idgame, $charSelect['custom_id']);

        $typeStep = $this->postComponent($charSelect['custom_id'], [(string) $character->idcharacter], $discordId);
        $typeSelect = $typeStep->json('data.components.0.components.0');
        $this->assertStringStartsWith('sub:ltype::', $typeSelect['custom_id']);

        $detailsPrompt = $this->postComponent($typeSelect['custom_id'], [(string) $listingType->entryid], $discordId);
        $detailsButton = $this->findButton($detailsPrompt, 'Enter combo details');
        $this->assertNotNull($detailsButton);

        $modal = $this->postComponent($detailsButton['custom_id'], [], $discordId);
        $modal->assertOk()->assertJson(['type' => 9]);
        $modalCustomId = $modal->json('data.custom_id');
        $this->assertStringStartsWith('sub:detailsubmit::', $modalCustomId);

        $resourceStep = $this->postModalSubmit($modalCustomId, $this->detailsModalRows('5A > 5B > 236B', '250'), $discordId);
        $resourceStep->assertOk()->assertJson(['type' => 7]);

        // The game's only resource has a single value, so it's already the
        // default selection — advancing straight to review exercises that
        // default-resolution path without an explicit dropdown pick.
        $reviewButton = $this->findButton($resourceStep, 'Review & submit');
        $this->assertNotNull($reviewButton);

        $review = $this->postComponent($reviewButton['custom_id'], [], $discordId);
        $review->assertOk();
        $description = $review->json('data.embeds.0.description');
        $this->assertStringContainsString('5A > 5B > 236B', $description);
        $this->assertStringContainsString('**Where?:** Corner', $description);

        $confirmButton = collect($review->json('data.components.0.components'))->firstWhere('label', 'Submit combo');
        $this->assertNotNull($confirmButton);

        $result = $this->postComponent($confirmButton['custom_id'], [], $discordId);
        $result->assertOk();
        $this->assertStringContainsString('Combo submitted!', $result->json('data.content'));

        $combo = Combo::firstOrFail();
        $this->assertSame('5A > 5B > 236B', $combo->combo);
        $this->assertSame($character->idcharacter, $combo->character_idcharacter);
        $this->assertSame($listingType->entryid, $combo->type);
        $this->assertSame('250', (string) (int) $combo->damage);
        $this->assertSame('1.03', $combo->patch);
        $this->assertSame($user->iduser, $combo->user_iduser);
        $this->assertNull($combo->verified);

        $this->assertSame(1, Resource::count());
        $this->assertSame($where->idResources_values, Resource::first()->Resources_values_idResources_values);
    }

    public function test_submit_rejects_a_non_numeric_damage_without_creating_a_combo(): void
    {
        $discordId = 'linked-2';
        $this->linkDiscordUser($discordId);

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);

        $stateRaw = 'g='.$game->idgame.';c='.$character->idcharacter.';l='.$listingType->entryid;

        $result = $this->postModalSubmit('sub:detailsubmit::'.$stateRaw, $this->detailsModalRows('5A > 5B', 'not-a-number'), $discordId);

        $result->assertOk()->assertJson(['type' => 7]);
        $this->assertStringContainsString('Damage must be a non-negative number.', $result->json('data.embeds.0.description'));
        $this->assertDatabaseCount('combo', 0);
    }

    public function test_submit_routes_a_numeric_primary_resource_through_the_more_details_modal(): void
    {
        $discordId = 'linked-3';
        $user = $this->linkDiscordUser($discordId);

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);

        $resource = GameResource::create(['game_idgame' => $game->idgame, 'text_name' => 'Meter', 'type' => 2, 'primaryORsecundary' => 1]);
        $valueA = ResourceValue::create(['value' => 'Bar 1', 'order' => 0, 'game_resources_idgame_resources' => $resource->idgame_resources]);
        $valueB = ResourceValue::create(['value' => 'Bar 2', 'order' => 1, 'game_resources_idgame_resources' => $resource->idgame_resources]);

        $stateRaw = 'g='.$game->idgame.';c='.$character->idcharacter.';l='.$listingType->entryid;
        $resourceStep = $this->postModalSubmit('sub:detailsubmit::'.$stateRaw, $this->detailsModalRows('5A > 5B'), $discordId);

        $moreButton = $this->findButton($resourceStep, 'More details');
        $this->assertNotNull($moreButton);

        $modal = $this->postComponent($moreButton['custom_id'], [], $discordId);
        $modal->assertOk()->assertJson(['type' => 9]);
        $field = $modal->json('data.components.0.components.0');
        $this->assertSame('f'.$resource->idgame_resources, $field['custom_id']);
        $this->assertSame('0', $field['value']);
        $this->assertTrue($field['required']);

        $moreCustomId = $modal->json('data.custom_id');
        $this->assertStringStartsWith('sub:moresubmit::', $moreCustomId);

        $afterMore = $this->postModalSubmit($moreCustomId, $this->numberModalRow($resource->idgame_resources, '450'), $discordId);
        $afterMore->assertOk();

        $reviewButton = $this->findButton($afterMore, 'Review & submit');
        $review = $this->postComponent($reviewButton['custom_id'], [], $discordId);
        $this->assertStringContainsString('**Meter:** 450', $review->json('data.embeds.0.description'));

        $confirmButton = collect($review->json('data.components.0.components'))->firstWhere('label', 'Submit combo');
        $this->postComponent($confirmButton['custom_id'], [], $discordId)->assertOk();

        $combo = Combo::firstOrFail();
        $this->assertSame($user->iduser, $combo->user_iduser);
        $this->assertSame(2, Resource::count());
        $this->assertTrue(Resource::all()->every(fn (Resource $r) => (float) $r->number_value === 450.0));
        $this->assertEqualsCanonicalizing(
            [$valueA->idResources_values, $valueB->idResources_values],
            Resource::pluck('Resources_values_idResources_values')->all()
        );
    }

    public function test_submit_rejects_confirm_if_the_discord_account_was_unlinked_mid_wizard(): void
    {
        $discordId = 'linked-4';
        $this->linkDiscordUser($discordId);

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);

        // This game has no primary resources at all, so the details submit
        // skips the resource step entirely and lands straight on review.
        $stateRaw = 'g='.$game->idgame.';c='.$character->idcharacter.';l='.$listingType->entryid;
        $review = $this->postModalSubmit('sub:detailsubmit::'.$stateRaw, $this->detailsModalRows('5A > 5B'), $discordId);
        $confirmButton = $this->findButton($review, 'Submit combo');

        UserConnectedAccount::where('provider_user_id', $discordId)->delete();

        $result = $this->postComponent($confirmButton['custom_id'], [], $discordId);

        $result->assertOk();
        $this->assertStringContainsString('connect your Discord account', $result->json('data.content'));
        $this->assertDatabaseCount('combo', 0);
    }

    public function test_submit_confirm_with_an_expired_details_cache_asks_to_restart(): void
    {
        $discordId = 'linked-5';
        $this->linkDiscordUser($discordId);

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);

        $stateRaw = 'g='.$game->idgame.';c='.$character->idcharacter.';l='.$listingType->entryid.';d=never-cached-token';

        $result = $this->postComponent('sub:confirm::'.$stateRaw, [], $discordId);

        $result->assertOk();
        $this->assertStringContainsString('expired', $result->json('data.content'));
        $this->assertDatabaseCount('combo', 0);
    }

    public function test_submit_cancel_does_not_create_a_combo(): void
    {
        $discordId = 'linked-6';
        $this->linkDiscordUser($discordId);

        $result = $this->postComponent('sub:cancel::', [], $discordId);

        $result->assertOk()->assertJson(['type' => 7]);
        $this->assertSame('Cancelled.', $result->json('data.content'));
        $this->assertDatabaseCount('combo', 0);
    }
}
