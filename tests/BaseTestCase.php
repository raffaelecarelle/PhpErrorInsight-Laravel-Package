<?php

declare(strict_types=1);

namespace PhpErrorInsightLaravel\Tests;

use Orchestra\Testbench\TestCase;
use PhpErrorInsightLaravel\PhpErrorInsightServiceProvider;

abstract class BaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /* @phpstan-ignore-next-line */
        if (method_exists($this, 'withoutMockingConsoleOutput')) {
            $this->withoutMockingConsoleOutput();
        }
    }

    protected function getPackageProviders($app)
    {
        return [
            PhpErrorInsightServiceProvider::class,
        ];
    }

    /**
     * Set up the environment.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.env', 'testing');
        $app['config']->set('app.debug', true);
        $app['config']->set('php-error-insight.enabled', true);
        $app['config']->set('php-error-insight.backend', null);
        $app['config']->set('php-error-insight.model', null);
        $app['config']->set('php-error-insight.apiKey', null);
        $app['config']->set('php-error-insight.apiUrl', null);
        $app['config']->set('php-error-insight.language', 'en');
        $app['config']->set('php-error-insight.output', 'auto');
        $app['config']->set('php-error-insight.verbose', true);
        $app['config']->set('php-error-insight.template', null);
        $app['config']->set('php-error-insight.projectRoot', __DIR__);
        $app['config']->set('php-error-insight.hostProjectRoot', null);
        $app['config']->set('php-error-insight.editorUrl', 'phpstorm://open?file=%file&line=%line');
    }
}
