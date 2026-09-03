<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordCommandUsage extends Model
{
    protected $table = 'discord_command_usages';

    protected $primaryKey = 'idcommand_usage';

    public $timestamps = false;

    protected $fillable = ['command', 'uses'];

    protected function casts(): array
    {
        return [
            'uses' => 'integer',
        ];
    }

    public static function recordUsage(string $command): void
    {
        static::query()->firstOrCreate(['command' => $command], ['uses' => 0])->increment('uses');
    }
}
