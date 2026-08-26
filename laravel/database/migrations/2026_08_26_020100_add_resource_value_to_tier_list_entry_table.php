<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The local WAMP dev DB has a legacy `int` PK on resources_values
        // that predates the bigint-unsigned convention `$table->id()`
        // produces on real production — branch so this migration works
        // against both. See project_mysql_legacy_int_columns memory.
        $resourceValueIdIsLegacyInt = Schema::getColumnType('resources_values', 'idResources_values') === 'int';

        Schema::table('tier_list_entry', function (Blueprint $table) use ($resourceValueIdIsLegacyInt) {
            if ($resourceValueIdIsLegacyInt) {
                $table->integer('resources_values_idResources_values')->nullable()->after('character_idcharacter');
                $table->foreign('resources_values_idResources_values')
                    ->references('idResources_values')->on('resources_values')
                    ->nullOnDelete();
            } else {
                $table->foreignId('resources_values_idResources_values')
                    ->nullable()
                    ->after('character_idcharacter')
                    ->constrained('resources_values', 'idResources_values')
                    ->nullOnDelete();
            }

            // Add the new composite unique index before dropping the old one:
            // both share the (tier_list_idtier_list, character_idcharacter)
            // prefix that supports the tier_list_idtier_list foreign key, and
            // MySQL refuses to drop an index still needed by an FK.
            $table->unique(
                ['tier_list_idtier_list', 'character_idcharacter', 'resources_values_idResources_values'],
                'tier_list_entry_unique_with_resource_value'
            );

            $table->dropUnique('tier_list_entry_unique');
        });

        Schema::table('tier_list_entry', function (Blueprint $table) {
            $table->renameIndex('tier_list_entry_unique_with_resource_value', 'tier_list_entry_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tier_list_entry', function (Blueprint $table) {
            $table->unique(['tier_list_idtier_list', 'character_idcharacter'], 'tier_list_entry_unique_plain');
            $table->dropUnique('tier_list_entry_unique');
        });

        Schema::table('tier_list_entry', function (Blueprint $table) {
            $table->renameIndex('tier_list_entry_unique_plain', 'tier_list_entry_unique');
            $table->dropForeign(['resources_values_idResources_values']);
            $table->dropColumn('resources_values_idResources_values');
        });
    }
};
