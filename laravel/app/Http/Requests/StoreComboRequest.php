<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreComboRequest extends FormRequest
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
            'character_idcharacter' => ['required', 'integer', 'exists:character,idcharacter,game_idgame,'.$game->idgame],
            'combo' => ['required', 'string'],
            'damage' => ['nullable', 'numeric', 'min:0'],
            'patch' => ['nullable', 'string', 'max:10'],
            'comments' => ['nullable', 'string'],
            'video' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:16'],
            'resources' => ['array'],
            'resources.*' => ['nullable'],
        ];
    }
}
