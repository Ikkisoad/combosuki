<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_page_canvas_node', function (Blueprint $table) {
            $table->id('idCanvasNode');
            $table->foreignId('idListPage')->constrained('list_page', 'idListPage')->cascadeOnDelete();
            $table->string('node_type', 20);
            $table->string('title', 255)->nullable();
            $table->text('body')->nullable();
            $table->foreignId('idCombo')->nullable()->constrained('combo', 'idcombo')->nullOnDelete();
            $table->float('pos_x')->default(0);
            $table->float('pos_y')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_page_canvas_node');
    }
};
