<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\Game;
use App\Models\GameResource;
use App\Models\Resource;

/**
 * Creates a Combo and syncs its resource values, shared by the website's
 * ComboController::store() and the Discord `/csk submit` wizard
 * (DiscordComboSubmit) so there's exactly one place that knows how to turn a
 * set of attributes + resource picks into database rows.
 *
 * Does no validation of its own — callers are responsible for validating
 * that character_idcharacter/type belong to $game and that every required
 * primary resource has a valid value before calling. The one exception is
 * syncResources()'s pre-existing defensive skip of resources that don't
 * belong to $game or carry an empty value, which is data-integrity hygiene
 * rather than business validation.
 */
class ComboSubmissionService
{
    /**
     * @param  array<string, mixed>  $attributes  Combo model field names: combo, comments, video, character_idcharacter, damage, type, patch_idgame_patch.
     * @param  array<int, mixed>  $resources  Keyed by GameResource id, same shape StoreComboRequest's `resources` input uses.
     */
    public function create(Game $game, array $attributes, array $resources, ?int $userId): Combo
    {
        // A genuinely absent key (the Discord wizard, which has no picker
        // step) auto-selects the game's current patch; an explicitly null
        // value (the web form's blank "— none —" option) is honored as "no
        // patch" rather than silently overridden.
        $patchId = array_key_exists('patch_idgame_patch', $attributes)
            ? $attributes['patch_idgame_patch']
            : $game->currentPatch?->idgame_patch;

        $combo = Combo::create([
            'combo' => $attributes['combo'],
            'comments' => $attributes['comments'] ?? null,
            'video' => $attributes['video'] ?? null,
            'character_idcharacter' => $attributes['character_idcharacter'],
            'submited' => now(),
            'damage' => $attributes['damage'] ?? null,
            'type' => $attributes['type'],
            'patch_idgame_patch' => $patchId,
            'user_iduser' => $userId,
        ]);

        $this->syncResources($combo, $game, $resources);

        return $combo;
    }

    public function syncResources(Combo $combo, Game $game, array $resources): void
    {
        $combo->resources()->delete();

        foreach ($resources as $idGameResources => $value) {
            if ($value === null || $value === '' || $value === '-') {
                continue;
            }

            $gameResource = GameResource::find($idGameResources);

            if (! $gameResource || $gameResource->game_idgame !== $game->idgame) {
                continue;
            }

            if ($gameResource->type === 1) {
                Resource::create([
                    'combo_idcombo' => $combo->idcombo,
                    'Resources_values_idResources_values' => (int) $value,
                    'number_value' => null,
                ]);
            } elseif ($gameResource->type === 2) {
                foreach ($gameResource->values as $resourceValue) {
                    Resource::create([
                        'combo_idcombo' => $combo->idcombo,
                        'Resources_values_idResources_values' => $resourceValue->idResources_values,
                        'number_value' => (float) $value,
                    ]);
                }
            } elseif ($gameResource->type === 3) {
                foreach ((array) $value as $valueId) {
                    if ($valueId === null || $valueId === '' || $valueId === '-') {
                        continue;
                    }

                    Resource::create([
                        'combo_idcombo' => $combo->idcombo,
                        'Resources_values_idResources_values' => (int) $valueId,
                        'number_value' => null,
                    ]);
                }
            }
        }
    }
}
