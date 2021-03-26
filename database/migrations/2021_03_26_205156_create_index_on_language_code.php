<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIndexOnLanguageCode extends Migration
{
    public function up(): void
    {
        Schema::connection(config('translation.database.connection'))
            ->table(config('translation.database.languages_table'), function (Blueprint $table) {
                $table->unique(['language']);
            });
    }

    public function down(): void
    {
        Schema::connection(config('translation.database.connection'))
            ->table(config('translation.database.languages_table'), function (Blueprint $table) {
                $table->dropUnique(['language']);
            });
    }
}
