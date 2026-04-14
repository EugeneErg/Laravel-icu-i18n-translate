<?php

declare(strict_types = 1);

namespace EugeneErg\LaravelIcuI18nTranslate\Providers;

use EugeneErg\ICUMessageFormatParser\Parser;
use EugeneErg\IcuI18nTranslator\Formatters\FormatterInterface;
use EugeneErg\IcuI18nTranslator\Translator;
use EugeneErg\IcuI18nTranslator\Translators\Contracts\TranslatorInterface;
use EugeneErg\LaravelIcuI18nTranslate\Repositories\Read\ReadGroupRepository;
use EugeneErg\LaravelIcuI18nTranslate\Repositories\Read\ReadPathRepository;
use EugeneErg\LaravelIcuI18nTranslate\Repositories\Read\ReadTranslateRepository;
use EugeneErg\LaravelIcuI18nTranslate\Repositories\Write\WriteGroupRepository;
use EugeneErg\LaravelIcuI18nTranslate\Repositories\Write\WriteGroupTranslateRepository;
use EugeneErg\LaravelIcuI18nTranslate\Repositories\Write\WritePathRepository;
use EugeneErg\LaravelIcuI18nTranslate\Repositories\Write\WriteTranslateRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

final class ServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/Migrations');
    }

    public function register(): void
    {
        $this->app->singleton(TranslatorInterface::class . '[]', function () {
            return [];
        });
        $this->app->singleton(Translator::class, function (Container $app) {
            return new Translator(
                readGroupRepository: $app->make(ReadGroupRepository::class),
                writeGroupRepository: $app->make(WriteGroupRepository::class),
                readTranslateRepository: $app->make(ReadTranslateRepository::class),
                writeTranslateRepository: $app->make(WriteTranslateRepository::class),
                writeGroupTranslateRepository: $app->make(WriteGroupTranslateRepository::class),
                readPathRepository: $app->make(ReadPathRepository::class),
                writePathRepository: $app->make(WritePathRepository::class),
                parser: $app->make(Parser::class),
                translators: $app->make(TranslatorInterface::class . '[]'),
                formatters: $app->make(FormatterInterface::class . '[]'),
            );
        });
    }
}