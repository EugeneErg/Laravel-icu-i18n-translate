<?php

declare(strict_types = 1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icu_i18n_paths', function (Blueprint $table): void {
            $table->id();
            $table->string('value');
            $table->foreignId('parent_id')->nullable()->references('id')->on('icu_i18n_paths')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->index()->references('id')->on('icu_i18n_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::drop('icu_i18n_paths');
    }
};
