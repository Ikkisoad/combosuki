<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Logs extends Model
{
    protected $table = 'logs';

    protected $primaryKey = 'idlog';

    protected $fillable = ['description', 'date'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
