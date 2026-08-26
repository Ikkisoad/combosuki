<?php

namespace App\Http\Requests;

use App\Models\GameResource;
use Illuminate\Foundation\Http\FormRequest;

class UpdateComboRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('combo'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $game = $this->route('combo')->character->game;

        $rules = [
            'character_idcharacter' => ['required', 'integer', 'exists:character,idcharacter,game_idgame,'.$game->idgame],
            'listingtype' => ['required', 'integer', 'exists:game_entry,entryid,gameid,'.$game->idgame],
            'combo' => ['required', 'string'],
            'damage' => ['nullable', 'numeric', 'min:0'],
            'patch' => ['nullable', 'string', 'max:10'],
            'comments' => ['nullable', 'string'],
            'video' => ['nullable', 'string', 'max:255'],
            'resources' => ['array'],
            'resources.*' => ['nullable'],
            'resources.*.*' => ['nullable'],
        ];

        // The inline quick-edit form on the combo page doesn't send a
        // `resources` field at all (see ComboController::update), so only
        // the advanced edit form (which does, and never leaves a primary
        // resource blank) is held to the required-primary-resources rule.
        if ($this->has('resources')) {
            $this->addPrimaryResourceRules($rules, $game);
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    protected function addPrimaryResourceRules(array &$rules, $game): void
    {
        $primaryResources = GameResource::where('game_idgame', $game->idgame)
            ->where('primaryORsecundary', 1)
            ->whereIn('type', [1, 2, 3])
            ->get();

        foreach ($primaryResources as $resource) {
            if ($resource->type === 2) {
                $rules['resources.'.$resource->idgame_resources] = ['required', 'numeric'];

                continue;
            }

            $exists = 'exists:resources_values,idResources_values,game_resources_idgame_resources,'.$resource->idgame_resources;

            if ($resource->type === 3) {
                $rules['resources.'.$resource->idgame_resources.'.0'] = ['required', 'integer', $exists];
                $rules['resources.'.$resource->idgame_resources.'.1'] = ['required', 'integer', $exists];

                continue;
            }

            $rules['resources.'.$resource->idgame_resources] = ['required', 'integer', $exists];
        }
    }
}
