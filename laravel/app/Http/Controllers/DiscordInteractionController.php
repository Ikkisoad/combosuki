<?php

namespace App\Http\Controllers;

use App\Services\DiscordComboSearch;
use App\Services\DiscordComboWizard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscordInteractionController extends Controller
{
    public function __construct(
        private DiscordComboSearch $comboSearch,
        private DiscordComboWizard $wizard,
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

        return response()->json([
            'type' => 7,
            'data' => $this->wizard->handleComponent($customId, $data['values'] ?? [], $channelId),
        ]);
    }

    private function handleModalSubmit(array $payload): JsonResponse
    {
        $data = $payload['data'] ?? [];
        $channelId = $payload['channel_id'] ?? null;

        return response()->json([
            'type' => 7,
            'data' => $this->wizard->handleModalSubmit($data['custom_id'] ?? '', $data['components'] ?? [], $channelId),
        ]);
    }
}
