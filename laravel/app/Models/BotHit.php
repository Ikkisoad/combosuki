<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotHit extends Model
{
    protected $table = 'bot_hits';

    protected $primaryKey = 'idbot_hit';

    public $timestamps = false;

    protected $fillable = ['path', 'ip_address', 'user_agent', 'created_at'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
