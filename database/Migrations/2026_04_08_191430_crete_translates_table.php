<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('icu_i18n_translates', static function (Blueprint $table): void {
            $table->id();
            $table->string('hash', 32);
            $table->text('pattern');
            $table->string('locale', '8');
            $table->unique(['hash', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::drop('icu_i18n_translates');
    }
};
