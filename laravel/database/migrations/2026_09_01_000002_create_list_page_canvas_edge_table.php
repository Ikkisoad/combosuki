<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_page_canvas_edge', function (Blueprint $table) {
            $table->id('idCanvasEdge');
            $table->foreignId('idFromNode')->constrained('list_page_canvas_node', 'idCanvasNode')->cascadeOnDelete();
            $table->foreignId('idToNode')->constrained('list_page_canvas_node', 'idCanvasNode')->cascadeOnDelete();
            $table->string('label', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_page_canvas_edge');
    }
};
