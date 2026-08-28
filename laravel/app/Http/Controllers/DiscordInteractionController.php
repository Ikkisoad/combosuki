<?php

namespace App\Http\Controllers;

use App\Exceptions\DiscordInteractionUnauthorized;
use App\Services\DiscordChallenge;
use App\Services\DiscordCombleGame;
use App\Services\DiscordComboSearch;
use App\Services\DiscordComboSubmit;
use App\Services\DiscordComboWizard;
use App\Services\DiscordGuideSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DiscordInteractionController extends Controller
{
    public function __construct(
        private DiscordComboSearch $comboSearch,
        private DiscordComboWizard $wizard,
        private DiscordCombleGame $comble,
        private DiscordChallenge $challenge,
        private DiscordGuideSearch $guideSearch,
        private DiscordComboSubmit $comboSubmit,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        return match ($payload['type'] ?? null) {
            2 => $this->handleCommand($payload),
            3 => $this->handleComponent($payload),
            5 => $this->handleModalSubmit($payload),
            // Type 1 is Discord's PING (sent when the Interactions Endpoint
            // URL is saved in the portal); any other/unknown type also
            // falls back here rather than risking a 5xx on a payload shape
            // we don't handle.
            default => response()->json(['type' => 1]),
        };
    }

    private function handleCommand(array $payload): JsonResponse
    {
        $data = $payload['data'] ?? [];
        $channelId = $payload['channel_id'] ?? null;
        $subcommand = $data['options'][0]['name'] ?? null;

        if ($subcommand === 'challenge') {
            return response()->json(['type' => 4, 'data' => $this->challenge->handle()]);
        }

        if ($subcommand === 'guide') {
            return response()->json(['type' => 4, 'data' => $this->guideSearch->handle($data)]);
        }

        if ($subcommand === 'browse') {
            $data = $this->wizard->start();
            $data['flags'] = 64;

            return response()->json(['type' => 4, 'data' => $data]);
        }

        if ($subcommand === 'comble') {
            $userId = $this->discordUserId($payload);

            $this->syncPrivateCombleFollowUp($payload, $userId);

            return response()->json(['type' => 4, 'data' => $this->comble->start($userId)]);
        }

        if ($subcommand === 'submit') {
            $data = $this->comboSubmit->start($this->discordUserId($payload));
            $data['flags'] = 64;

            return response()->json(['type' => 4, 'data' => $data]);
        }

        return response()->json([
            'type' => 4,
            'data' => $this->comboSearch->handle($data, $channelId),
        ]);
    }

    private function handleComponent(array $payload): JsonResponse
    {
        $data = $payload['data'] ?? [];
        $customId = $data['custom_id'] ?? '';
        $channelId = $payload['channel_id'] ?? null;

        // The "More filters" button opens a Modal (a popup form), which
        // requires its own interaction response type (9) instead of the
        // UPDATE_MESSAGE (7) every other wizard click uses.
        if (str_starts_with($customId, 'w:more:')) {
            return response()->json(['type' => 9, 'data' => $this->wizard->buildModal($customId)]);
        }

        if (str_starts_with($customId, 'cb:')) {
            return $this->handleCombleComponent($customId, $data['values'] ?? [], $payload);
        }

        // "Enter combo details" and "More details" both open a Modal, which
        // needs its own interaction response type (9) instead of the
        // UPDATE_MESSAGE (7) every other submit-wizard click uses.
        if (str_starts_with($customId, 'sub:details:') || str_starts_with($customId, 'sub:more:')) {
            return response()->json(['type' => 9, 'data' => $this->comboSubmit->buildModal($customId)]);
        }

        if (str_starts_with($customId, 'sub:')) {
            $userId = $this->discordUserId($payload);

            return response()->json([
                'type' => 7,
                'data' => $this->comboSubmit->handleComponent($customId, $data['values'] ?? [], $userId),
            ]);
        }

        return response()->json([
            'type' => 7,
            'data' => $this->wizard->handleComponent($customId, $data['values'] ?? [], $channelId),
        ]);
    }

    /**
     * Comble's messages are public (see DiscordCombleGame), so anyone in the
     * channel can click its dropdowns — not just the player who started the
     * game. handleComponent()/buildDamageModal() reject a click from anyone
     * else by throwing DiscordInteractionUnauthorized, caught here and
     * turned into a private (ephemeral) reply that leaves the shared,
     * publicly-visible game message untouched.
     */
    private function handleCombleComponent(string $customId, array $values, array $payload): JsonResponse
    {
        $userId = $this->discordUserId($payload);

        try {
            // The type dropdown opens a damage-guess Modal, which requires
            // its own interaction response type (9) instead of the
            // UPDATE_MESSAGE (7) every other Comble click uses.
            if (str_starts_with($customId, 'cb:type:')) {
                return response()->json(['type' => 9, 'data' => $this->comble->buildDamageModal($customId, $values[0] ?? null, $userId)]);
            }

            return response()->json(['type' => 7, 'data' => $this->comble->handleComponent($customId, $values, $userId)]);
        } catch (DiscordInteractionUnauthorized $e) {
            return $this->unauthorizedResponse($e);
        }
    }

    private function handleModalSubmit(array $payload): JsonResponse
    {
        $data = $payload['data'] ?? [];
        $customId = $data['custom_id'] ?? '';
        $channelId = $payload['channel_id'] ?? null;

        if (str_starts_with($customId, 'cb:')) {
            $userId = $this->discordUserId($payload);

            try {
                $publicData = $this->comble->handleModalSubmit($customId, $data['components'] ?? [], $userId);

                $this->syncPrivateCombleFollowUp($payload, $userId);

                return response()->json(['type' => 7, 'data' => $publicData]);
            } catch (DiscordInteractionUnauthorized $e) {
                return $this->unauthorizedResponse($e);
            }
        }

        if (str_starts_with($customId, 'sub:')) {
            return response()->json([
                'type' => 7,
                'data' => $this->comboSubmit->handleModalSubmit($customId, $data['components'] ?? []),
            ]);
        }

        return response()->json([
            'type' => 7,
            'data' => $this->wizard->handleModalSubmit($customId, $data['components'] ?? [], $channelId),
        ]);
    }

    private function unauthorizedResponse(DiscordInteractionUnauthorized $e): JsonResponse
    {
        return response()->json(['type' => 4, 'data' => ['content' => $e->getMessage(), 'flags' => 64]]);
    }

    /**
     * Keeps $userId's full, named guess breakdown (DiscordCombleGame's
     * privateSummary()) in a single private (ephemeral) message that gets
     * edited after every guess, rather than a new one being sent each time —
     * players were otherwise accumulating one hidden message per guess.
     *
     * Discord scopes a followup message to whichever interaction token
     * created it: a later interaction (each dropdown click/Modal submit gets
     * its own fresh token) can't edit a message made with an earlier one
     * directly, so the *creating* token and message id are cached (keyed by
     * user) and reused for the PATCH on every subsequent guess. That cached
     * token is only valid for Discord's normal 15-minute interaction-token
     * window; if it's gone or Discord rejects the edit (e.g. expired), this
     * falls back to posting a fresh message and re-caching it — so a stale
     * cache degrades to "a new single message" rather than silently failing.
     *
     * Deferred with afterResponse() rather than sent inline: Discord expects
     * the *initial* response within 3 seconds, and this outbound HTTP call
     * used to sit in front of it — any slowness reaching discord.com there
     * (DNS, TLS, a slow API response) delayed that ack and surfaced to
     * players as "The application did not respond" on every single
     * `/csk comble` use. afterResponse() runs this only once Discord
     * already has the real response in hand, so the follow-up can be as
     * slow as it wants without threatening the interaction itself.
     */
    private function syncPrivateCombleFollowUp(array $payload, string $userId): void
    {
        $applicationId = $payload['application_id'] ?? config('services.discord.application_id');
        $token = $payload['token'] ?? null;

        if (! $applicationId || ! $token) {
            return;
        }

        dispatch(function () use ($applicationId, $token, $userId) {
            try {
                $data = $this->comble->privateSummary($userId);
                $cached = Cache::get($this->followUpCacheKey($userId));

                if ($cached) {
                    $edit = Http::asJson()->timeout(5)->patch(
                        "https://discord.com/api/v10/webhooks/{$applicationId}/{$cached['token']}/messages/{$cached['message_id']}",
                        $data
                    );

                    if ($edit->successful()) {
                        return;
                    }
                }

                $response = Http::asJson()->timeout(5)->post(
                    "https://discord.com/api/v10/webhooks/{$applicationId}/{$token}",
                    $data
                );

                if ($response->successful() && $messageId = $response->json('id')) {
                    Cache::put($this->followUpCacheKey($userId), ['token' => $token, 'message_id' => $messageId], now()->addMinutes(15));
                }
            } catch (\Throwable $e) {
                report($e);
            }
        })->afterResponse();
    }

    private function followUpCacheKey(string $userId): string
    {
        return 'comble:discord:followup:'.$userId;
    }

    /**
     * Discord puts the invoking user under `member.user` in a guild channel
     * and under `user` directly in a DM.
     */
    private function discordUserId(array $payload): string
    {
        return $payload['member']['user']['id'] ?? $payload['user']['id'] ?? '';
    }
}
