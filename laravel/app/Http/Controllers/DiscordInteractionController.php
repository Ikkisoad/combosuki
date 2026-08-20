<?php

namespace App\Http\Controllers;

use App\Services\DiscordCombleGame;
use App\Services\DiscordComboSearch;
use App\Services\DiscordComboWizard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            return response()->json(['type' => 4, 'data' => $this->comble->start($this->discordUserId($payload))]);
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

        // Comble's type dropdown opens a damage-guess Modal the same way.
        if (str_starts_with($customId, 'cb:type:')) {
            $data = $this->comble->buildDamageModal($customId, $data['values'][0] ?? null, $this->discordUserId($payload));

            return response()->json(['type' => 9, 'data' => $data]);
        }

        if (str_starts_with($customId, 'cb:')) {
            return response()->json([
                'type' => 7,
                'data' => $this->comble->handleComponent($customId, $data['values'] ?? [], $this->discordUserId($payload)),
            ]);
        }

        return response()->json([
            'type' => 7,
            'data' => $this->wizard->handleComponent($customId, $data['values'] ?? [], $channelId),
        ]);
    }

    private function handleModalSubmit(array $payload): JsonResponse
    {
        $data = $payload['data'] ?? [];
        $customId = $data['custom_id'] ?? '';
        $channelId = $payload['channel_id'] ?? null;

        if (str_starts_with($customId, 'cb:')) {
            return response()->json([
                'type' => 7,
                'data' => $this->comble->handleModalSubmit($customId, $data['components'] ?? [], $this->discordUserId($payload)),
            ]);
        }

        return response()->json([
            'type' => 7,
            'data' => $this->wizard->handleModalSubmit($customId, $data['components'] ?? [], $channelId),
        ]);
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
