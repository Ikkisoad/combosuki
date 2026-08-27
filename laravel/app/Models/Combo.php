<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Combo extends Model
{
    protected $table = 'combo';

    protected $primaryKey = 'idcombo';

    protected $fillable = [
        'combo', 'comments', 'video', 'user_iduser', 'character_idcharacter',
        'submited', 'damage', 'type', 'verified', 'verified_by_iduser', 'verified_at',
        'patch', 'author', 'password',
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

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_idcharacter');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_iduser');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class, 'combo_idcombo');
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
