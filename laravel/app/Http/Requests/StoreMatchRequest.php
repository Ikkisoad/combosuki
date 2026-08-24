<?php

namespace App\Http\Requests;

use App\Models\GameResource;
use Illuminate\Foundation\Http\FormRequest;

class StoreMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $game = $this->route('game');

        $rules = [
            'player_one' => ['required', 'string', 'max:100'],
            'player_one_user_iduser' => ['nullable', 'integer', 'exists:user,iduser'],
            'player_one_character_idcharacter' => ['required', 'integer', 'exists:character,idcharacter,game_idgame,'.$game->idgame],
            'player_two' => ['required', 'string', 'max:100'],
            'player_two_user_iduser' => ['nullable', 'integer', 'exists:user,iduser'],
            'player_two_character_idcharacter' => ['required', 'integer', 'exists:character,idcharacter,game_idgame,'.$game->idgame],
            'video' => ['required', 'string', 'max:255'],
            'played_at' => ['required', 'date'],
        ];

        $matchResources = GameResource::where('game_idgame', $game->idgame)
            ->where('primaryORsecundary', 1)
            ->where('include_in_matches', true)
            ->get();

        foreach ($matchResources as $resource) {
            $exists = 'exists:resources_values,idResources_values,game_resources_idgame_resources,'.$resource->idgame_resources;

            $rules['player_one_resources.'.$resource->idgame_resources] = ['nullable', 'integer', $exists];
            $rules['player_two_resources.'.$resource->idgame_resources] = ['nullable', 'integer', $exists];
        }

        return $rules;
    }
}
