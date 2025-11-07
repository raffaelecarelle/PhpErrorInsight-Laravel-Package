<?php

declare(strict_types=1);

namespace PhpErrorInsightLaravel;

use Illuminate\Contracts\Debug\ExceptionHandler as LaravelExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use PhpErrorInsight\Config as InsightConfig;
use PhpErrorInsight\Internal\Explainer as InternalExplainer;
use PhpErrorInsight\Internal\Renderer as InternalRenderer;

final class PhpErrorInsightServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/php-error-insight.php', 'php-error-insight');

        // Bind the library Config using Laravel config + env
        $this->app->singleton(InsightConfig::class, function (Application $app): InsightConfig {
            $cfg = (array) $app['config']->get('php-error-insight', []);

            return InsightConfig::fromEnvAndArray($cfg);
        });

        // Decorate the Laravel exception handler to render our HTML when appropriate
        $this->app->extend(LaravelExceptionHandler::class, fn ($handler, Application $app): Support\HandlerDecorator => new Support\HandlerDecorator($handler, $app, $app->make(InsightConfig::class), new InternalExplainer(), new InternalRenderer()));
    }

    public function boot(): void
    {
        // Publish config for customization
        $this->publishes([
            __DIR__ . '/../config/php-error-insight.php' => $this->app->configPath('php-error-insight.php'),
        ], 'php-error-insight-config');
    }
}
