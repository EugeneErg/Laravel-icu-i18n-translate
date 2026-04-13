<?php

declare(strict_types = 1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icu_i18n_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('hash', 32)->unique();
            $table->text('pattern');
            $table->string('locale', '8');
            $table->text('original_pattern');
            $table->string('context');
        });
    }

    public function down(): void
    {
        Schema::drop('icu_i18n_groups');
    }
};
