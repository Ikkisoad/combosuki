<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalSite extends Model
{
    protected $table = 'external_site';

    protected $fillable = ['title', 'url', 'order'];
}
