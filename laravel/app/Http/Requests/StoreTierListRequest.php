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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'created_at' => $this->filled('created_at') ? $this->input('created_at') : null,
            'user_iduser' => $this->filled('user_iduser') ? $this->input('user_iduser') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
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

        if ($this->user()?->is_admin) {
            $rules['created_at'] = ['nullable', 'date', 'before_or_equal:now'];
            $rules['user_iduser'] = ['nullable', 'integer', 'exists:user,iduser'];
        }

        return $rules;
    }
}
