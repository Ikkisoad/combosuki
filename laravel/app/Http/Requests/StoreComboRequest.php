<?php

namespace App\Http\Requests;

use App\Models\Game;
use App\Models\GameResource;
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
        return self::rulesFor($this->route('game'));
    }

    /**
     * The same rules rules() builds from the route, exposed statically so a
     * caller with no HTTP route to pull {game} from (e.g. the Discord
     * `/csk submit` wizard) can validate against the same source of truth
     * rather than hand-rolling a second, driftable copy.
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(Game $game): array
    {
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

        self::addPrimaryResourceRules($rules, $game);

        return $rules;
    }

    /**
     * Primary resources are always shown on the form with no blank option
     * (List/Duplicated default to their first value by admin-configured
     * order, Number defaults to 0), so submitting one empty only happens by
     * bypassing the form entirely — reject that rather than silently
     * dropping the resource.
     *
     * @param  array<string, mixed>  $rules
     */
    protected static function addPrimaryResourceRules(array &$rules, Game $game): void
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
