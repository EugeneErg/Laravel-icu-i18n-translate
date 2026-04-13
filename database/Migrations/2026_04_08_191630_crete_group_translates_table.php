<?php

declare(strict_types = 1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icu_i18n_group_translates', function (Blueprint $table): void {
            $table->foreignId('group_id')->references('id')->on('icu_i18n_groups')->cascadeOnDelete();
            $table->foreignId('translate_id')->references('id')->on('icu_i18n_translates')->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->references('id')->on('icu_i18n_translates')->nullOnDelete();
            $table->string('key');
            $table->index(['group_id', 'translate_id']);
            $table->index(['group_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::drop('icu_i18n_group_translates');
    }
};
