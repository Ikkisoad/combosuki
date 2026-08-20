<?php

namespace App\Http\Controllers;

use App\Exceptions\DiscordInteractionUnauthorized;
use App\Services\DiscordCombleGame;
use App\Services\DiscordComboSearch;
use App\Services\DiscordComboWizard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DiscordInteractionController extends Controller
{
    public function __construct(
        private DiscordComboSearch $comboSearch,
        private DiscordComboWizard $wizard,
        private DiscordCombleGame $comble,
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

        if ($subcommand === 'browse') {
            $data = $this->wizard->start();
            $data['flags'] = 64;

            return response()->json(['type' => 4, 'data' => $data]);
        }

        if ($subcommand === 'comble') {
            $userId = $this->discordUserId($payload);
            $publicData = $this->comble->start($userId);

            $this->postPrivateCombleFollowUp($payload, $userId);

            return response()->json(['type' => 4, 'data' => $publicData]);
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

                $this->postPrivateCombleFollowUp($payload, $userId);

                return response()->json(['type' => 7, 'data' => $publicData]);
            } catch (DiscordInteractionUnauthorized $e) {
                return $this->unauthorizedResponse($e);
            }
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
     * Sends $userId's full, named guess breakdown (DiscordCombleGame's
     * privateSummary()) as a private (ephemeral) follow-up message, via the
     * interaction's own webhook — no bot token needed, unlike
     * DiscordComboSearch's video follow-up which posts as the bot into the
     * channel. Best-effort: a missing token/application id (shouldn't happen
     * on a real Discord request) just skips the follow-up rather than
     * failing the whole interaction, since the public update is more
     * important than the private detail.
     */
    private function postPrivateCombleFollowUp(array $payload, string $userId): void
    {
        $applicationId = $payload['application_id'] ?? config('services.discord.application_id');
        $token = $payload['token'] ?? null;

        if (! $applicationId || ! $token) {
            return;
        }

        Http::asJson()->post(
            "https://discord.com/api/v10/webhooks/{$applicationId}/{$token}",
            $this->comble->privateSummary($userId)
        );
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
