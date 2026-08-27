<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A third-party account (currently only Discord) attached to a Combo好き
 * account. Deliberately stores no OAuth token and no email address: the
 * linking flow reads the provider profile once and throws both away, so a
 * database leak here exposes nothing but a public username and snowflake.
 */
class UserConnectedAccount extends Model
{
    protected $table = 'user_connected_account';

    protected $primaryKey = 'idconnection';

    protected $fillable = ['provider', 'provider_user_id', 'provider_nickname', 'user_iduser'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_iduser');
    }
}
