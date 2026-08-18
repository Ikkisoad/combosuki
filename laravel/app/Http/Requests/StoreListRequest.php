<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A `0` game_idgame means "no game" (cross-game list), matching legacy's
     * `$_POST['gameid'] == 0` sentinel — normalize it to null before validation.
     */
    protected function prepareForValidation(): void
    {
        if ((int) $this->input('game_idgame') === 0) {
            $this->merge(['game_idgame' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'list_name' => ['required', 'string', 'max:100'],
            'game_idgame' => ['nullable', 'integer', 'exists:game,idgame'],
        ];
    }
}
