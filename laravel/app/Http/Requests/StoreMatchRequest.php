<?php

namespace App\Http\Requests;

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

        return [
            'player_one' => ['required', 'string', 'max:100'],
            'player_one_user_iduser' => ['nullable', 'integer', 'exists:user,iduser'],
            'player_one_character_idcharacter' => ['required', 'integer', 'exists:character,idcharacter,game_idgame,'.$game->idgame],
            'player_two' => ['required', 'string', 'max:100'],
            'player_two_user_iduser' => ['nullable', 'integer', 'exists:user,iduser'],
            'player_two_character_idcharacter' => ['required', 'integer', 'exists:character,idcharacter,game_idgame,'.$game->idgame],
            'video' => ['required', 'string', 'max:255'],
            'played_at' => ['required', 'date'],
        ];
    }
}
