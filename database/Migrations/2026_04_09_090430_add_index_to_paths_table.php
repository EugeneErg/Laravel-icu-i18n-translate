<?php

declare(strict_types = 1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE UNIQUE INDEX icu_i18n_paths_parent_id_value_unique
            ON icu_i18n_paths (COALESCE(parent_id, 0), value)
        ");
    }

    public function down(): void
    {
        Schema::table('icu_i18n_paths', function (Blueprint $table): void {
            $table->dropUnique(['parent_id', 'value']);
        });
    }
};
