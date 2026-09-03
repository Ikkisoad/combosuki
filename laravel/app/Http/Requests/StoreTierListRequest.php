<?php

namespace App\Http\Requests;

use App\Models\GameResource;
use App\Models\TierListEntry;
use Illuminate\Contracts\Validation\Validator;
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
        $tierListResource = $this->filled('game_idgame')
            ? GameResource::where('game_idgame', $this->input('game_idgame'))
                ->where('include_in_tier_lists', true)
                ->first()
            : null;

        $rules = [
            'title' => ['required', 'string', 'max:100'],
            'game_idgame' => ['required', 'integer', 'exists:game,idgame'],
            'entries' => ['array'],
            'entries.*.character_idcharacter' => [
                'required',
                'integer',
                Rule::exists('character', 'idcharacter')->where('game_idgame', $this->input('game_idgame')),
            ],
            'entries.*.resources_values_idResources_values' => $tierListResource
                ? [
                    'required',
                    'integer',
                    Rule::exists('resources_values', 'idResources_values')
                        ->where('game_resources_idgame_resources', $tierListResource->idgame_resources),
                ]
                : ['prohibited'],
            'entries.*.tier' => ['required', 'string', Rule::in(TierListEntry::TIERS)],
        ];

        if ($this->user()?->is_admin) {
            $rules['created_at'] = ['nullable', 'date', 'before_or_equal:now'];
            $rules['user_iduser'] = ['nullable', 'integer', 'exists:user,iduser'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $seen = [];

            foreach ($this->input('entries', []) as $entry) {
                $key = ($entry['character_idcharacter'] ?? '').'-'.($entry['resources_values_idResources_values'] ?? '');

                if (isset($seen[$key])) {
                    $validator->errors()->add('entries', 'Each character (and resource value, if applicable) can only appear once.');

                    return;
                }

                $seen[$key] = true;
            }
        });
    }
}
