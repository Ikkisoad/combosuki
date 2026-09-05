<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterQuery;
use App\Models\CombleAttempt;
use App\Models\Combo;
use App\Models\DiscordCommandUsage;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GameResource;
use App\Models\ListCategory;
use App\Models\ListModel;
use App\Models\ListPage;
use App\Models\ResourceValue;
use App\Models\TierList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DiscordInteractionTest extends TestCase
{
    use RefreshDatabase;

    private const DEFERRED_ORIGINAL_MESSAGE_URL = 'https://discord.com/api/v10/webhooks/test-application-id/test-interaction-token/messages/@original';

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
        // DiscordInteractionController::syncPrivateCombleFollowUp) on every
        // guess; fake it globally so tests don't make real requests.
        // The response always carries an 'id', like Discord's real webhook
        // message object, so that mechanism's cache-and-PATCH-on-next-guess
        // behavior engages the same way it would in production.
        //
        // Http::fake() stub callbacks accumulate and the *first* registered
        // one to return a non-null response wins — a test calling
        // Http::fake() again for the same 'discord.com/*' pattern can add
        // extra assertions, but can't override this default response body.
        Http::fake(['discord.com/*' => Http::response(['id' => 'test-message-id'], 200)]);
    }

    private function postInteraction(array $payload): TestResponse
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

    public function test_command_usage_is_recorded_per_subcommand_and_accumulates(): void
    {
        $game = Game::create(['name' => 'Empty Game', 'complete' => 1, 'modPass' => 'secret']);

        $this->postInteraction([
            'type' => 2,
            'data' => [
                'name' => 'csk',
                'options' => [[
                    'name' => 'search',
                    'options' => [
                        ['name' => 'game', 'value' => $game->name],
                        ['name' => 'query', 'value' => 'nothing'],
                    ],
                ]],
            ],
        ])->assertOk();

        $this->postInteraction([
            'type' => 2,
            'data' => ['name' => 'csk', 'options' => [['name' => 'challenge']]],
        ])->assertOk();

        $this->postInteraction([
            'type' => 2,
            'data' => [
                'name' => 'csk',
                'options' => [[
                    'name' => 'search',
                    'options' => [
                        ['name' => 'game', 'value' => $game->name],
                        ['name' => 'query', 'value' => 'nothing'],
                    ],
                ]],
            ],
        ])->assertOk();

        $this->assertSame(2, DiscordCommandUsage::where('command', 'search')->value('uses'));
        $this->assertSame(1, DiscordCommandUsage::where('command', 'challenge')->value('uses'));
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
                'name' => 'csk',
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
                'name' => 'csk',
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
                'name' => 'csk',
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

    public function test_combo_search_resolves_game_and_character_by_alias(): void
    {
        // Auto-generated on create (see Game/Character::booted()): "Street
        // Fighter 6" -> "SF6", "Wanted Character" -> "WC".
        $game = Game::create(['name' => 'Street Fighter 6', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Wanted Character', 'game_idgame' => $game->idgame]);
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
                'name' => 'csk',
                'options' => [
                    [
                        'name' => 'search',
                        'options' => [
                            ['name' => 'game', 'value' => 'sf6'],
                            ['name' => 'query', 'value' => 'A'],
                            ['name' => 'character', 'value' => 'wc'],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk()->assertJson(['type' => 4]);
        $this->assertSame($combo->combo, $response->json('data.embeds.0.title'));
    }

    public function test_combo_search_with_unknown_character_returns_ephemeral_message(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $response = $this->postInteraction([
            'type' => 2,
            'data' => [
                'name' => 'csk',
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
                'name' => 'csk',
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

    public function test_guide_search_returns_matching_guide_as_embed(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $guide = ListModel::create(['list_name' => 'Combo Theory Guide', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1]);

        $response = $this->postInteraction([
            'type' => 2,
            'data' => [
                'name' => 'csk',
                'options' => [
                    [
                        'name' => 'guide',
                        'options' => [
                            ['name' => 'game', 'value' => 'Test Game'],
                            ['name' => 'name', 'value' => 'Combo Theory'],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk()->assertJson(['type' => 4]);
        $this->assertSame($guide->list_name, $response->json('data.embeds.0.title'));
        $this->assertSame($game->name, $response->json('data.embeds.0.fields.0.value'));
    }

    public function test_guide_search_with_no_options_returns_top_guides_by_views(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        // 'views' isn't mass-assignable (see ListModel::$fillable), so set it
        // directly rather than through create()'s array, which would
        // silently drop it and leave both guides tied at the default 0.
        $popular = ListModel::create(['list_name' => 'Popular Guide', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1]);
        $popular->views = 100;
        $popular->save();

        $lessPopular = ListModel::create(['list_name' => 'Less Popular Guide', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1]);
        $lessPopular->views = 10;
        $lessPopular->save();

        $response = $this->postInteraction([
            'type' => 2,
            'data' => ['name' => 'csk', 'options' => [['name' => 'guide']]],
        ]);

        $response->assertOk()->assertJson(['type' => 4]);
        $this->assertSame($popular->list_name, $response->json('data.embeds.0.title'));
    }

    public function test_guide_search_filters_by_game_only(): void
    {
        $wanted = Game::create(['name' => 'Wanted Game', 'complete' => 1, 'modPass' => 'secret']);
        $other = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);

        $guide = ListModel::create(['list_name' => 'A Guide', 'game_idgame' => $wanted->idgame, 'password' => 'secret', 'type' => 1]);
        ListModel::create(['list_name' => 'Another Guide', 'game_idgame' => $other->idgame, 'password' => 'secret', 'type' => 1]);

        $response = $this->postInteraction([
            'type' => 2,
            'data' => [
                'name' => 'csk',
                'options' => [
                    ['name' => 'guide', 'options' => [['name' => 'game', 'value' => 'Wanted Game']]],
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertCount(1, $response->json('data.embeds'));
        $this->assertSame($guide->list_name, $response->json('data.embeds.0.title'));
    }

    public function test_guide_search_with_unknown_game_returns_ephemeral_message(): void
    {
        $response = $this->postInteraction([
            'type' => 2,
            'data' => [
                'name' => 'csk',
                'options' => [
                    ['name' => 'guide', 'options' => [['name' => 'game', 'value' => 'Nobody Game']]],
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertSame(64, $response->json('data.flags'));
    }

    public function test_guide_search_with_no_matches_returns_ephemeral_message(): void
    {
        $game = Game::create(['name' => 'Empty Game', 'complete' => 1, 'modPass' => 'secret']);

        $response = $this->postInteraction([
            'type' => 2,
            'data' => [
                'name' => 'csk',
                'options' => [
                    [
                        'name' => 'guide',
                        'options' => [
                            ['name' => 'game', 'value' => 'Empty Game'],
                            ['name' => 'name', 'value' => 'nothing'],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertSame(64, $response->json('data.flags'));
    }

    public function test_guide_browse_with_no_matches_returns_ephemeral_message(): void
    {
        $response = $this->postInteraction([
            'type' => 2,
            'data' => ['name' => 'csk', 'options' => [['name' => 'guide-browse']]],
        ]);

        $response->assertOk();
        $this->assertSame(64, $response->json('data.flags'));
        $this->assertSame('No guides found.', $response->json('data.content'));
    }

    public function test_guide_browse_with_one_matching_guide_skips_straight_to_the_page_step(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $guide = ListModel::create(['list_name' => 'Combo Theory Guide', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1]);
        $page = ListPage::create(['Title' => 'Neutral', 'idList' => $guide->idlist, 'order' => 0]);

        $response = $this->postInteraction([
            'type' => 2,
            'data' => [
                'name' => 'csk',
                'options' => [
                    ['name' => 'guide-browse', 'options' => [['name' => 'name', 'value' => 'Combo Theory']]],
                ],
            ],
        ]);

        $response->assertOk()->assertJson(['type' => 4]);
        $this->assertNull($response->json('data.flags'));

        $select = $response->json('data.components.0.components.0');
        $this->assertSame('gb:page::l='.$guide->idlist, $select['custom_id']);
        $this->assertContains((string) $page->idListPage, array_column($select['options'], 'value'));
    }

    public function test_guide_browse_with_multiple_matches_shows_a_guide_dropdown(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $first = ListModel::create(['list_name' => 'First Guide', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1]);
        $second = ListModel::create(['list_name' => 'Second Guide', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1]);

        $response = $this->postInteraction([
            'type' => 2,
            'data' => ['name' => 'csk', 'options' => [['name' => 'guide-browse']]],
        ]);

        $response->assertOk()->assertJson(['type' => 4]);
        $select = $response->json('data.components.0.components.0');
        $this->assertSame('gb:guide::', $select['custom_id']);
        $this->assertEqualsCanonicalizing(
            [(string) $first->idlist, (string) $second->idlist],
            array_column($select['options'], 'value')
        );
    }

    public function test_guide_browse_full_flow_narrows_combos_by_page_and_category(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);
        $guide = ListModel::create(['list_name' => 'Combo Theory Guide', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1]);
        $page = ListPage::create(['Title' => 'Neutral', 'idList' => $guide->idlist, 'order' => 0]);
        $category = ListCategory::create(['title' => 'Punishes', 'list_idlist' => $guide->idlist, 'order' => 0, 'idPage' => $page->idListPage]);

        $inCategory = Combo::create([
            'combo' => 'In category combo',
            'character_idcharacter' => $character->idcharacter,
            'type' => $listingType->entryid,
            'damage' => 500,
        ]);
        $inCategory->lists()->attach($guide->idlist, ['list_category_idlist_category' => $category->idlist_category]);

        $uncategorized = Combo::create([
            'combo' => 'Uncategorized combo',
            'character_idcharacter' => $character->idcharacter,
            'type' => $listingType->entryid,
            'damage' => 999,
        ]);
        $uncategorized->lists()->attach($guide->idlist);

        // Step 1: choose the guide.
        $pageStep = $this->postComponent('gb:guide::', [(string) $guide->idlist]);
        $pageStep->assertOk()->assertJson(['type' => 7]);
        $pageSelect = $pageStep->json('data.components.0.components.0');
        $this->assertSame('gb:page::l='.$guide->idlist, $pageSelect['custom_id']);

        // Step 2: choose the page.
        $categoryStep = $this->postComponent($pageSelect['custom_id'], [(string) $page->idListPage]);
        $categoryStep->assertOk();
        $categorySelect = $categoryStep->json('data.components.0.components.0');
        $this->assertStringStartsWith('gb:cat::', $categorySelect['custom_id']);
        $this->assertContains('Uncategorized', array_column($categorySelect['options'], 'label'));

        // Step 3: narrow to the "Punishes" category only.
        $results = $this->postComponent($categorySelect['custom_id'], [(string) $category->idlist_category]);
        $results->assertOk()->assertJson(['type' => 7]);
        $description = $results->json('data.embeds.0.description');
        $this->assertStringContainsString($inCategory->combo, $description);
        $this->assertStringNotContainsString($uncategorized->combo, $description);
    }

    public function test_guide_browse_skips_the_category_step_when_theres_nothing_to_categorize(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $guide = ListModel::create(['list_name' => 'Empty Guide', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1]);
        $page = ListPage::create(['Title' => 'Neutral', 'idList' => $guide->idlist, 'order' => 0]);

        $response = $this->postComponent('gb:page::l='.$guide->idlist, [(string) $page->idListPage]);

        $response->assertOk()->assertJson(['type' => 7]);
        $this->assertStringContainsString('No combos found for this selection.', $response->json('data.embeds.0.description'));
    }

    public function test_guide_browse_paginates_results(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);
        $guide = ListModel::create(['list_name' => 'Big Guide', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1]);

        foreach (range(1, 10) as $n) {
            $combo = Combo::create([
                'combo' => "Combo {$n}",
                'character_idcharacter' => $character->idcharacter,
                'type' => $listingType->entryid,
                'damage' => $n,
            ]);
            $combo->lists()->attach($guide->idlist);
        }

        // Guide has no pages -> pageStep skips straight to categoryStep; it
        // has no categories but does have uncategorized combos, so it still
        // offers "All categories" rather than skipping again.
        $categoryStep = $this->postComponent('gb:guide::', [(string) $guide->idlist]);
        $categoryStep->assertOk();
        $categorySelect = $categoryStep->json('data.components.0.components.0');
        $this->assertStringStartsWith('gb:cat::', $categorySelect['custom_id']);

        $page1 = $this->postComponent($categorySelect['custom_id'], ['_any_']);
        $page1->assertOk();
        $this->assertStringContainsString('Page 1 of 2', $page1->json('data.embeds.0.description'));

        $buttons = $page1->json('data.components.0.components');
        $previous = collect($buttons)->firstWhere('label', 'Previous');
        $next = collect($buttons)->firstWhere('label', 'Next');
        $this->assertTrue($previous['disabled']);
        $this->assertFalse($next['disabled']);

        $page2 = $this->postComponent($next['custom_id'], []);
        $page2->assertOk();
        $this->assertStringContainsString('Page 2 of 2', $page2->json('data.embeds.0.description'));

        $page2Buttons = $page2->json('data.components.0.components');
        $this->assertFalse(collect($page2Buttons)->firstWhere('label', 'Previous')['disabled']);
        $this->assertTrue(collect($page2Buttons)->firstWhere('label', 'Next')['disabled']);
    }

    private function characterInteractionPayload(array $characterOptions): array
    {
        return [
            'type' => 2,
            'data' => [
                'name' => 'csk',
                'options' => [
                    ['name' => 'character', 'options' => $characterOptions],
                ],
            ],
        ];
    }

    public function test_character_page_defers_then_posts_the_embed_as_a_follow_up(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame, 'image' => 'https://example.com/portrait.png']);

        $response = $this->postInteraction($this->characterInteractionPayload([
            ['name' => 'game', 'value' => 'Test Game'],
            ['name' => 'character', 'value' => 'Test Character'],
        ]));

        $response->assertOk()->assertJson(['type' => 5]);

        Http::assertSent(function ($request) use ($game, $character) {
            if ($request->url() !== self::DEFERRED_ORIGINAL_MESSAGE_URL) {
                return false;
            }

            $embed = $request['embeds'][0] ?? [];

            return ($embed['title'] ?? null) === $character->name
                && ($embed['fields'][0]['value'] ?? null) === $game->name
                && str_contains($embed['url'] ?? '', route('characters.show', [$game, $character], absolute: false))
                && ($embed['thumbnail']['url'] ?? null) === 'https://example.com/portrait.png';
        });
    }

    public function test_character_page_embed_lists_the_character_s_top_damage_combos(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);

        $strong = Combo::create(['combo' => '5HP>Shoryuken', 'character_idcharacter' => $character->idcharacter, 'type' => $listingType->entryid, 'damage' => 500]);
        $weak = Combo::create(['combo' => '2LP', 'character_idcharacter' => $character->idcharacter, 'type' => $listingType->entryid, 'damage' => 50]);

        $response = $this->postInteraction($this->characterInteractionPayload([
            ['name' => 'game', 'value' => 'Test Game'],
            ['name' => 'character', 'value' => 'Test Character'],
        ]));

        $response->assertOk()->assertJson(['type' => 5]);

        Http::assertSent(function ($request) use ($strong, $weak) {
            if ($request->url() !== self::DEFERRED_ORIGINAL_MESSAGE_URL) {
                return false;
            }

            $combosField = collect($request['embeds'][0]['fields'] ?? [])->firstWhere('name', 'Top Combos');

            return $combosField !== null
                && str_contains($combosField['value'], $strong->combo)
                && str_contains($combosField['value'], $weak->combo)
                && strpos($combosField['value'], $strong->combo) < strpos($combosField['value'], $weak->combo);
        });
    }

    public function test_character_page_embed_omits_the_combos_field_when_the_character_has_none(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);

        $response = $this->postInteraction($this->characterInteractionPayload([
            ['name' => 'game', 'value' => 'Test Game'],
            ['name' => 'character', 'value' => 'Test Character'],
        ]));

        $response->assertOk()->assertJson(['type' => 5]);

        Http::assertSent(function ($request) {
            if ($request->url() !== self::DEFERRED_ORIGINAL_MESSAGE_URL) {
                return false;
            }

            return collect($request['embeds'][0]['fields'] ?? [])->firstWhere('name', 'Top Combos') === null;
        });
    }

    public function test_character_page_embed_includes_combos_from_the_games_default_queries(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);

        $query = CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => 'Corner Combo',
            'filters' => [],
            'order' => 0,
        ]);

        $matching = Combo::create([
            'combo' => 'Corner starter > ender',
            'character_idcharacter' => $character->idcharacter,
            'type' => $listingType->entryid,
            'damage' => 400,
        ]);

        $response = $this->postInteraction($this->characterInteractionPayload([
            ['name' => 'game', 'value' => 'Test Game'],
            ['name' => 'character', 'value' => 'Test Character'],
        ]));

        $response->assertOk()->assertJson(['type' => 5]);

        Http::assertSent(function ($request) use ($query, $matching) {
            if ($request->url() !== self::DEFERRED_ORIGINAL_MESSAGE_URL) {
                return false;
            }

            $queryField = collect($request['embeds'][0]['fields'] ?? [])->firstWhere('name', $query->label);

            return $queryField !== null && str_contains($queryField['value'], $matching->combo);
        });
    }

    public function test_character_page_embed_omits_a_default_query_field_when_it_has_no_matching_combo(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);

        $query = CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => 'Corner Combo',
            'filters' => [],
            'order' => 0,
        ]);

        $response = $this->postInteraction($this->characterInteractionPayload([
            ['name' => 'game', 'value' => 'Test Game'],
            ['name' => 'character', 'value' => 'Test Character'],
        ]));

        $response->assertOk()->assertJson(['type' => 5]);

        Http::assertSent(function ($request) use ($query) {
            if ($request->url() !== self::DEFERRED_ORIGINAL_MESSAGE_URL) {
                return false;
            }

            return collect($request['embeds'][0]['fields'] ?? [])->firstWhere('name', $query->label) === null;
        });
    }

    public function test_character_page_resolves_game_and_character_by_alias(): void
    {
        // Auto-generated on create (see Game/Character::booted()): "Street
        // Fighter 6" -> "SF6", "Wanted Character" -> "WC".
        $game = Game::create(['name' => 'Street Fighter 6', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Wanted Character', 'game_idgame' => $game->idgame]);

        $response = $this->postInteraction($this->characterInteractionPayload([
            ['name' => 'game', 'value' => 'sf6'],
            ['name' => 'character', 'value' => 'wc'],
        ]));

        $response->assertOk()->assertJson(['type' => 5]);

        Http::assertSent(fn ($request) => $request->url() === self::DEFERRED_ORIGINAL_MESSAGE_URL
            && ($request['embeds'][0]['title'] ?? null) === $character->name);
    }

    public function test_character_page_with_unknown_game_reports_an_error_on_the_deferred_message(): void
    {
        $response = $this->postInteraction($this->characterInteractionPayload([
            ['name' => 'game', 'value' => 'Nobody Game'],
            ['name' => 'character', 'value' => 'Anyone'],
        ]));

        $response->assertOk()->assertJson(['type' => 5]);

        Http::assertSent(fn ($request) => $request->url() === self::DEFERRED_ORIGINAL_MESSAGE_URL
            && str_contains($request['content'] ?? '', 'No game found matching'));
    }

    public function test_character_page_with_unknown_character_reports_an_error_on_the_deferred_message(): void
    {
        Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $response = $this->postInteraction($this->characterInteractionPayload([
            ['name' => 'game', 'value' => 'Test Game'],
            ['name' => 'character', 'value' => 'Nobody'],
        ]));

        $response->assertOk()->assertJson(['type' => 5]);

        Http::assertSent(fn ($request) => $request->url() === self::DEFERRED_ORIGINAL_MESSAGE_URL
            && str_contains($request['content'] ?? '', 'No character found matching'));
    }

    public function test_character_page_without_a_character_option_reports_an_error_on_the_deferred_message(): void
    {
        Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $response = $this->postInteraction($this->characterInteractionPayload([
            ['name' => 'game', 'value' => 'Test Game'],
        ]));

        $response->assertOk()->assertJson(['type' => 5]);

        Http::assertSent(fn ($request) => $request->url() === self::DEFERRED_ORIGINAL_MESSAGE_URL
            && str_contains($request['content'] ?? '', 'Please provide both a game and a character name.'));
    }

    private function postComponent(string $customId, array $values, ?string $userId = null): TestResponse
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
            'data' => ['name' => 'csk', 'options' => [['name' => 'browse']]],
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

    private function postModalSubmit(string $customId, array $components, ?string $userId = null): TestResponse
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

    private function damageAndStarterModalRows(string $damage, string $starter): array
    {
        return [
            ['type' => 1, 'components' => [['type' => 4, 'custom_id' => 'damage', 'value' => $damage]]],
            ['type' => 1, 'components' => [['type' => 4, 'custom_id' => 'starter', 'value' => $starter]]],
        ];
    }

    public function test_combo_comble_launches_the_activity(): void
    {
        $response = $this->postInteraction(array_merge([
            'type' => 2,
            'data' => ['name' => 'csk', 'options' => [['name' => 'comble']]],
        ], $this->memberPayload('owner-1')));

        // Interaction callback type 12 (LAUNCH_ACTIVITY) opens the Activity
        // client-side; Discord needs no `data` alongside it.
        $response->assertOk()->assertExactJson(['type' => 12]);
    }

    /**
     * characterStep()/typeStep() update the same public message the game
     * dropdown lives on, so echoing "Game: X" / "Character: Y" back into it
     * — as the code used to — would show the whole channel exactly what one
     * player guessed while they're still mid-guess, well before it's even
     * scored. Neither intermediate step should name the game or character
     * just picked.
     */
    public function test_combo_comble_intermediate_steps_do_not_reveal_the_players_picks(): void
    {
        $game = Game::create(['name' => 'Secret Game Name', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Secret Character Name', 'game_idgame' => $game->idgame]);
        $type = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);
        Combo::create([
            'combo' => 'AAA BBB CCC DDD EEE',
            'character_idcharacter' => $character->idcharacter,
            'type' => $type->entryid,
            'damage' => 3000,
        ]);

        $owner = 'owner-1';

        $charStep = $this->postComponent('cb:game::u='.$owner, [(string) $game->idgame], $owner);
        $charStep->assertOk();
        $this->assertStringNotContainsString($game->name, $charStep->json('data.embeds.0.description'));

        $charSelect = $charStep->json('data.components.0.components.0');
        $typeStep = $this->postComponent($charSelect['custom_id'], [(string) $character->idcharacter], $owner);
        $typeStep->assertOk();
        $typeDescription = $typeStep->json('data.embeds.0.description');
        $this->assertStringNotContainsString($game->name, $typeDescription);
        $this->assertStringNotContainsString($character->name, $typeDescription);
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

        // Discord plays feed the same comble_attempts table (and therefore
        // the same site-wide CombleStats) as the web version, keyed by a
        // "discord:{id}" visitor_key so they dedup independently of any web
        // session.
        $this->assertSame(1, CombleAttempt::count());
        $attempt = CombleAttempt::first();
        $this->assertSame('discord:'.$owner, $attempt->visitor_key);
        $this->assertTrue($attempt->won);
        $this->assertSame(1, $attempt->guesses);

        // Public message: progress only — no notation reveal, no guessed
        // names, and (since this guess won) not the answer either, so it
        // doesn't spoil the puzzle for anyone else watching who hasn't
        // played yet.
        $description = $result->json('data.embeds.0.description');
        $this->assertStringContainsString('You got it!', $description);
        foreach (['AAA', 'BBB', 'CCC', 'DDD', 'EEE', '▁'] as $spoiler) {
            $this->assertStringNotContainsString($spoiler, $description);
        }
        $this->assertStringNotContainsString($character->name, $description);
        $this->assertStringNotContainsString($game->name, $description);

        // Finished: the game dropdown is replaced with a link button.
        $this->assertSame(5, $result->json('data.components.0.components.0.style'));

        // Private follow-up: the same guess, with the full reveal, names, and
        // the answer. The revealed token is scattered across the combo's
        // notation (not always the first one), so check that some token
        // shows up rather than asserting a specific one.
        Http::assertSent(function ($request) use ($character, $game) {
            $description = $request['embeds'][0]['description'] ?? '';

            return $request->url() === 'https://discord.com/api/v10/webhooks/test-application-id/test-interaction-token'
                && $request['flags'] === 64
                && str_contains($description, 'You got it!')
                && collect(['AAA', 'BBB', 'CCC', 'DDD', 'EEE'])->contains(fn ($token) => str_contains($description, $token))
                && str_contains($description, $character->name)
                && str_contains($description, $game->name);
        });
    }

    public function test_combo_comble_records_an_optional_starter_guess(): void
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

        $result = $this->postModalSubmit('cb:dmgsubmit::'.$stateRaw, $this->damageAndStarterModalRows('3000', 'AAA BB'), $owner);
        $result->assertOk()->assertJson(['type' => 7]);

        // Private follow-up: shows the guessed starter text (never on the
        // public message — see the no-notation-reveal test).
        Http::assertSent(function ($request) {
            $description = $request['embeds'][0]['description'] ?? '';

            return $request->url() === 'https://discord.com/api/v10/webhooks/test-application-id/test-interaction-token'
                && str_contains($description, 'AAA BB');
        });
    }

    public function test_combo_comble_edits_the_same_private_message_instead_of_sending_a_new_one_per_guess(): void
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

        // The first guess sends the first (and only ever POSTed) private
        // message; the fake response's 'id' (see setUp()) is what the second
        // guess below must reuse for a PATCH instead of another POST.
        $this->postModalSubmit('cb:dmgsubmit::'.$stateRaw, $this->damageModalRow('100'), $owner)->assertOk();
        $this->postModalSubmit('cb:dmgsubmit::'.$stateRaw, $this->damageModalRow('100'), $owner)->assertOk();

        $requests = collect(Http::recorded())->map(fn ($pair) => $pair[0]);

        // Laravel's test client reuses one Application instance across both
        // postInteraction() calls above, and Application::terminate() never
        // clears its terminating-callback list — so an afterResponse() job
        // from the first call can end up re-invoked during the second call's
        // termination too. That's a testing-only artifact (a real Discord
        // interaction is its own PHP request/process in production), not a
        // sign of duplicate messages: every one of those re-invocations
        // still resolves the same cached id and PATCHes it, never POSTs
        // again — which is exactly the guarantee this test cares about, so
        // assert on that property rather than an exact call count.
        $posts = $requests->filter(fn ($request) => $request->method() === 'POST');
        $patches = $requests->filter(fn ($request) => $request->method() === 'PATCH');

        $this->assertCount(1, $posts);
        $this->assertTrue($patches->isNotEmpty(), 'expected at least one PATCH editing the existing private message');
        $this->assertTrue(
            $patches->every(fn ($request) => str_contains($request->url(), '/messages/test-message-id')),
            'every PATCH should target the message created by the first guess'
        );
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

    public function test_combo_comble_progress_persists_across_separate_requests(): void
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

        // A second, separate HTTP request/guess: CombleDiscordProgress must
        // have persisted the first guess server-side (no custom_id state to
        // carry it) for this one to count as the second attempt.
        $response = $this->postModalSubmit('cb:dmgsubmit::'.$stateRaw, $this->damageModalRow('100'), $owner);

        // Public: both guesses count, but only as squares — no name.
        $description = $response->json('data.embeds.0.description');
        $this->assertStringContainsString('3 guesses left.', $description);
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

    public function test_challenge_shows_todays_challenge_and_its_current_winning_combo(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $query = CharacterQuery::create(['game_idgame' => $game->idgame, 'label' => 'Random Assist 1', 'filters' => [], 'order' => 0]);
        $query->forceFill(['created_at' => now()->subDay()])->save();
        $type = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);

        Combo::create([
            'combo' => 'A > B > C',
            'character_idcharacter' => $character->idcharacter,
            'type' => $type->entryid,
            'damage' => 1000,
        ]);
        $best = Combo::create([
            'combo' => 'A > D > E',
            'character_idcharacter' => $character->idcharacter,
            'type' => $type->entryid,
            'damage' => 5000,
        ]);

        $response = $this->postInteraction([
            'type' => 2,
            'data' => ['name' => 'csk', 'options' => [['name' => 'challenge']]],
        ]);

        $response->assertOk()->assertJson(['type' => 4]);

        $title = $response->json('data.embeds.0.title');
        $this->assertStringContainsString($game->name, $title);
        $this->assertStringContainsString($character->name, $title);
        $this->assertStringContainsString($query->label, $title);

        $description = $response->json('data.embeds.0.description');
        $this->assertStringContainsString('Character: '.$character->name, $description);

        $this->assertSame('Current winning combo', $response->json('data.embeds.0.fields.0.name'));
        $this->assertSame($best->combo, $response->json('data.embeds.0.fields.0.value'));
        $this->assertSame('5000', $response->json('data.embeds.0.fields.1.value'));
    }

    public function test_challenge_invites_the_first_submission_when_no_combo_qualifies_yet(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $query = CharacterQuery::create(['game_idgame' => $game->idgame, 'label' => 'Random Assist 1', 'filters' => [], 'order' => 0]);
        $query->forceFill(['created_at' => now()->subDay()])->save();

        $response = $this->postInteraction([
            'type' => 2,
            'data' => ['name' => 'csk', 'options' => [['name' => 'challenge']]],
        ]);

        $response->assertOk()->assertJson(['type' => 4]);
        $this->assertEmpty($response->json('data.embeds.0.fields'));
        $this->assertStringContainsString('be the first to submit one', $response->json('data.embeds.0.description'));
    }

    public function test_challenge_reports_none_available_when_no_queries_are_configured(): void
    {
        $response = $this->postInteraction([
            'type' => 2,
            'data' => ['name' => 'csk', 'options' => [['name' => 'challenge']]],
        ]);

        $response->assertOk()->assertJson(['type' => 4]);
        $this->assertStringContainsString('No challenge is available yet', $response->json('data.embeds.0.description'));
    }

    public function test_challenge_with_a_date_option_shows_that_days_challenge(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Test Character', 'game_idgame' => $game->idgame]);
        $query = CharacterQuery::create(['game_idgame' => $game->idgame, 'label' => 'Random Assist 1', 'filters' => [], 'order' => 0]);
        $query->forceFill(['created_at' => now()->subWeek()])->save();

        $targetDate = now('America/Sao_Paulo')->subDay();

        $response = $this->postInteraction([
            'type' => 2,
            'data' => ['name' => 'csk', 'options' => [[
                'name' => 'challenge',
                'options' => [['name' => 'date', 'value' => $targetDate->toDateString()]],
            ]]],
        ]);

        $response->assertOk()->assertJson(['type' => 4]);

        $title = $response->json('data.embeds.0.title');
        $this->assertStringContainsString($game->name, $title);
        $this->assertStringContainsString($targetDate->format('M j, Y'), $title);
    }

    public function test_challenge_with_an_unparsable_date_returns_an_immediate_ephemeral_error(): void
    {
        $response = $this->postInteraction([
            'type' => 2,
            'data' => ['name' => 'csk', 'options' => [[
                'name' => 'challenge',
                'options' => [['name' => 'date', 'value' => 'not-a-date']],
            ]]],
        ]);

        $response->assertOk()->assertJson(['type' => 4]);
        $this->assertSame(64, $response->json('data.flags'));
        $this->assertStringContainsString("doesn't look like a date", $response->json('data.content'));
    }

    public function test_challenge_with_a_future_date_returns_an_immediate_ephemeral_error(): void
    {
        $response = $this->postInteraction([
            'type' => 2,
            'data' => ['name' => 'csk', 'options' => [[
                'name' => 'challenge',
                'options' => [['name' => 'date', 'value' => now('America/Sao_Paulo')->addWeek()->toDateString()]],
            ]]],
        ]);

        $response->assertOk()->assertJson(['type' => 4]);
        $this->assertSame(64, $response->json('data.flags'));
        $this->assertStringContainsString('in the future', $response->json('data.content'));
    }

    private function tierListInteractionPayload(array $tierlistOptions): array
    {
        return [
            'type' => 2,
            'data' => [
                'name' => 'csk',
                'options' => [
                    ['name' => 'tierlist', 'options' => $tierlistOptions],
                ],
            ],
        ];
    }

    /** A tiny real PNG, so TierListImageRenderer's imagecreatefromstring() succeeds instead of falling back to a placeholder. */
    private function fakePortraitBytes(): string
    {
        $image = imagecreatetruecolor(4, 4);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 50, 50));
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    public function test_tierlist_defers_then_posts_the_image_as_a_follow_up(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame, 'image' => 'character-portraits/valentine.png']);
        Storage::disk('public')->put($character->image, $this->fakePortraitBytes());

        $listA = TierList::create(['title' => 'List A', 'game_idgame' => $game->idgame]);
        $listA->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'S', 'order' => 0]);

        $listB = TierList::create(['title' => 'List B', 'game_idgame' => $game->idgame]);
        $listB->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'A', 'order' => 0]);

        $response = $this->postInteraction($this->tierListInteractionPayload([
            ['name' => 'game', 'value' => 'Test Game'],
        ]));

        $response->assertOk()->assertJson(['type' => 5]);

        Http::assertSent(function ($request) use ($game) {
            if ($request->url() !== self::DEFERRED_ORIGINAL_MESSAGE_URL || ! $request->hasFile('files[0]')) {
                return false;
            }

            $payloadJsonPart = collect($request->data())->firstWhere('name', 'payload_json');
            $payload = json_decode($payloadJsonPart['contents'] ?? '', true);

            return str_contains($payload['embeds'][0]['title'] ?? '', $game->name)
                && str_contains($payload['embeds'][0]['description'] ?? '', '2 tier lists')
                && ($payload['embeds'][0]['image']['url'] ?? null) === 'attachment://tierlist.png';
        });
    }

    public function test_tierlist_filters_by_date_range(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        $oldList = TierList::create(['title' => 'Old List', 'game_idgame' => $game->idgame]);
        $oldList->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'F', 'order' => 0]);
        $oldList->forceFill(['created_at' => now()->subMonths(3)])->save();

        $recentList = TierList::create(['title' => 'Recent List', 'game_idgame' => $game->idgame]);
        $recentList->entries()->create(['character_idcharacter' => $character->idcharacter, 'tier' => 'S', 'order' => 0]);

        $response = $this->postInteraction($this->tierListInteractionPayload([
            ['name' => 'game', 'value' => 'Test Game'],
            ['name' => 'from', 'value' => now()->subWeek()->toDateString()],
        ]));

        $response->assertOk()->assertJson(['type' => 5]);

        Http::assertSent(function ($request) {
            if ($request->url() !== self::DEFERRED_ORIGINAL_MESSAGE_URL || ! $request->hasFile('files[0]')) {
                return false;
            }

            $payloadJsonPart = collect($request->data())->firstWhere('name', 'payload_json');
            $payload = json_decode($payloadJsonPart['contents'] ?? '', true);

            return str_contains($payload['embeds'][0]['description'] ?? '', '1 tier list');
        });
    }

    public function test_tierlist_with_no_submissions_reports_none_yet(): void
    {
        Game::create(['name' => 'Empty Game', 'complete' => 1, 'modPass' => 'secret']);

        $response = $this->postInteraction($this->tierListInteractionPayload([
            ['name' => 'game', 'value' => 'Empty Game'],
        ]));

        $response->assertOk()->assertJson(['type' => 5]);

        Http::assertSent(fn ($request) => $request->url() === self::DEFERRED_ORIGINAL_MESSAGE_URL
            && str_contains($request['content'] ?? '', 'No tier lists have been submitted'));
    }

    public function test_tierlist_with_unknown_game_reports_an_error_on_the_deferred_message(): void
    {
        $response = $this->postInteraction($this->tierListInteractionPayload([
            ['name' => 'game', 'value' => 'Nobody Game'],
        ]));

        $response->assertOk()->assertJson(['type' => 5]);

        Http::assertSent(fn ($request) => $request->url() === self::DEFERRED_ORIGINAL_MESSAGE_URL
            && str_contains($request['content'] ?? '', 'No game found matching'));
    }

    /**
     * A bad date is cheap to validate (no I/O) so it's rejected immediately
     * as an ephemeral error instead of deferring only to edit in the same
     * error a moment later.
     */
    public function test_tierlist_with_an_unparsable_date_returns_an_immediate_ephemeral_error(): void
    {
        $response = $this->postInteraction($this->tierListInteractionPayload([
            ['name' => 'game', 'value' => 'Test Game'],
            ['name' => 'from', 'value' => 'not-a-date'],
        ]));

        $response->assertOk()->assertJson(['type' => 4]);
        $this->assertSame(64, $response->json('data.flags'));
        $this->assertStringContainsString("doesn't look like a date", $response->json('data.content'));

        Http::assertNothingSent();
    }
}
