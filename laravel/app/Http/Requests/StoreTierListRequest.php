<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTierListRequest extends FormRequest
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
        return [
            'title' => ['required', 'string', 'max:100'],
            'game_idgame' => ['required', 'integer', 'exists:game,idgame'],
            'entries' => ['array'],
            'entries.*.character_idcharacter' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('character', 'idcharacter')->where('game_idgame', $this->input('game_idgame')),
            ],
            'entries.*.tier' => ['required', 'string', Rule::in(['S', 'A', 'B', 'C', 'D', 'F'])],
        ];
    }
}
