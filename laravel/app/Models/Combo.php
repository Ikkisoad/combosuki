<?php

namespace App\Models;

use App\Models\Concerns\HasEditHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Combo extends Model
{
    use HasEditHistory;

    protected $table = 'combo';

    protected $primaryKey = 'idcombo';

    protected $fillable = [
        'combo', 'comments', 'video', 'user_iduser', 'character_idcharacter',
        'submited', 'damage', 'type', 'verified', 'verified_by_iduser', 'verified_at',
        'patch_idgame_patch', 'author', 'password',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'submited' => 'datetime',
            'verified_at' => 'datetime',
            'views' => 'integer',
        ];
    }

    /**
     * Keeps combo_damage_histories in sync with damage/patch changes so the
     * combo page can show what a combo's damage was in each patch (e.g. a
     * character nerf). One row per (combo, patch): editing damage without
     * changing the patch corrects that patch's row in place; editing damage
     * together with bumping the patch dropdown records a new row and leaves
     * the previous patch's value untouched. Also fires on create: unlike
     * updates, a fresh insert never populates wasChanged(), so that's
     * checked via wasRecentlyCreated instead.
     */
    protected static function booted(): void
    {
        static::saved(function (Combo $combo) {
            if ($combo->damage === null || $combo->patch_idgame_patch === null) {
                return;
            }

            if (! $combo->wasRecentlyCreated && ! $combo->wasChanged(['damage', 'patch_idgame_patch'])) {
                return;
            }

            ComboDamageHistory::updateOrCreate(
                ['combo_idcombo' => $combo->idcombo, 'patch_idgame_patch' => $combo->patch_idgame_patch],
                ['damage' => $combo->damage]
            );
        });
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_idcharacter');
    }

    public function patch(): BelongsTo
    {
        return $this->belongsTo(GamePatch::class, 'patch_idgame_patch');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_iduser');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class, 'combo_idcombo');
    }

    public function damageHistories(): HasMany
    {
        return $this->hasMany(ComboDamageHistory::class, 'combo_idcombo');
    }

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(ListModel::class, 'combo_listing', 'idcombo', 'idlist')
            ->withPivot(['comment', 'list_category_idlist_category']);
    }

    public function listingType(): BelongsTo
    {
        return $this->belongsTo(GameEntry::class, 'type', 'entryid');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_iduser');
    }

    public function markVerifiedBy(User $user): void
    {
        $this->update([
            'verified' => 1,
            'verified_by_iduser' => $user->iduser,
            'verified_at' => now(),
        ]);
    }

    /**
     * Restricts to combos $viewer is allowed to see in public listings: guest
     * submissions (no account to judge trust on), already-verified combos,
     * combos by an author who has proven themselves with another verified
     * combo elsewhere, and the viewer's own combos. Staff (isTrusted()) see
     * everything unfiltered. This never blocks direct access to a combo's
     * own show page — only listing/search/stats queries should apply it.
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        if ($viewer?->isTrusted()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($viewer) {
            $q->whereNull('user_iduser')
                ->orWhere('verified', 1)
                ->orWhereHas('user.combos', fn (Builder $q2) => $q2->where('verified', 1));

            if ($viewer !== null) {
                $q->orWhere('user_iduser', $viewer->iduser);
            }
        });
    }
}
