<?php

namespace App\Http\Requests;

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

        return [
            'character_idcharacter' => ['required', 'integer', 'exists:character,idcharacter,game_idgame,'.$game->idgame],
            'listingtype' => ['required', 'integer', 'exists:game_entry,entryid,gameid,'.$game->idgame],
            'combo' => ['required', 'string'],
            'damage' => ['nullable', 'numeric', 'min:0'],
            'patch' => ['nullable', 'string', 'max:10'],
            'comments' => ['nullable', 'string'],
            'video' => ['nullable', 'string', 'max:255'],
            'resources' => ['array'],
            'resources.*' => ['nullable'],
        ];
    }
}
