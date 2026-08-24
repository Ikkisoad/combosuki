<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_site', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->string('url', 255);
            $table->integer('order')->nullable();
            $table->timestamps();
        });

        $sites = [
            ['title' => 'SuperCombo.gg', 'url' => 'https://supercombo.gg/'],
            ['title' => 'The Fighting Game Glossary', 'url' => 'https://glossary.infil.net/'],
            ['title' => 'Dustloop wiki', 'url' => 'http://www.dustloop.com/'],
            ['title' => 'Mizuumi wiki', 'url' => 'https://wiki.gbl.gg/'],
            ['title' => 'Dream Cancel wiki', 'url' => 'https://www.dreamcancel.com/'],
            ['title' => 'Shoryuken wiki', 'url' => 'https://srk.shib.live/w/Main_Page'],
            ['title' => 'FGCombo', 'url' => 'https://fgcombo.com/'],
            ['title' => 'Top8er', 'url' => 'https://www.top8er.com/'],
            ['title' => 'Replay Theater', 'url' => 'https://replaytheater.app/'],
        ];

        foreach ($sites as $order => $site) {
            DB::table('external_site')->insert([
                'title' => $site['title'],
                'url' => $site['url'],
                'order' => $order,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('external_site');
    }
};
