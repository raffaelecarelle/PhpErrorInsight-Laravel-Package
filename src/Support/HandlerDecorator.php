<?php

declare(strict_types=1);

namespace PhpErrorInsightLaravel\Support;

use ErrorExplainer\Config;
use ErrorExplainer\Config as InsightConfig;
use ErrorExplainer\Internal\Explainer as InternalExplainer;
use ErrorExplainer\Internal\Renderer as InternalRenderer;
use ErrorExplainer\Internal\StateDumper as InternalStateDumper;
use Illuminate\Contracts\Debug\ExceptionHandler as LaravelExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class HandlerDecorator implements LaravelExceptionHandler
{
    public function __construct(
        private LaravelExceptionHandler $inner,
        private Application $app,
        private InsightConfig $config,
        private InternalExplainer $explainer,
        private InternalRenderer $renderer,
        private InternalStateDumper $state
    ) {
    }

    public function report(Throwable $e): void
    {
        $this->inner->report($e);
    }

    public function shouldReport(Throwable $e): bool
    {
        if (method_exists($this->inner, 'shouldReport')) {
            return $this->inner->shouldReport($e);
        }

        return true; // sensible default
    }

    public function render($request, Throwable $e)
    {
        // Try to use our viewer only for HTML web requests when enabled and app is in debug.
        if ($this->shouldUseInsight($request)) {
            $exp = $this->buildExp($e);
            $cfg = $this->buildConfig();

            ob_start();
            try {
                $this->renderer->render($exp, $cfg, 'exception', false);
            } finally {
                $content = (string) ob_get_clean();
            }

            return new Response($content, 500, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        return $this->inner->render($request, $e);
    }

    public function renderForConsole($output, Throwable $e): void
    {
        $exp = $this->buildExp($e);
        $cfg = $this->buildConfig();

        $this->renderer->render($exp, $cfg, 'exception', false);
    }

    private function shouldUseInsight(Request $request): bool
    {
        // Enabled via config and app.debug
        $enabled = $this->config->enabled;

        // Detect debug flag across versions (Laravel 11+: hasDebugModeEnabled)
        if (method_exists($this->app, 'hasDebugModeEnabled')) {
            $debug = $this->app->hasDebugModeEnabled();
        } else {
            $debug = (bool) ($this->app['config']->get('app.debug') ?? false);
        }

        // Check that the request wants HTML (compat across versions)
        $wantsHtml = false;
        // First, if the request expects JSON, we must not render HTML
        if (method_exists($request, 'expectsJson') && (bool) $request->expectsJson()) {
            $wantsHtml = false;
        } elseif (method_exists($request, 'expectsHtml')) {
            $wantsHtml = (bool) $request->expectsHtml();
        } elseif (method_exists($request, 'acceptsHtml')) {
            $wantsHtml = (bool) $request->acceptsHtml();
        } elseif (method_exists($request, 'wantsHtml')) {
            $wantsHtml = (bool) $request->wantsHtml();
        } elseif (method_exists($request, 'expectsJson')) {
            // As a last resort, if we can detect JSON but it's false, assume HTML
            $wantsHtml = !$request->expectsJson();
        }

        return $enabled && $debug && $wantsHtml;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildExp(Throwable $e): array
    {
        // Build explanation and render capturing the echo output from the renderer
        $exp = $this->explainer->explain('exception', $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTrace(), null, $this->config);
        $exp['state'] = $this->state->collectState($e->getTrace());

        return $exp;
    }

    private function buildConfig(): Config
    {
        return InsightConfig::fromEnvAndArray([
            'enabled' => $this->config->enabled,
            'backend' => $this->config->backend,
            'model' => $this->config->model,
            'apiKey' => $this->config->apiKey,
            'apiUrl' => $this->config->apiUrl,
            'language' => $this->config->language,
            'output' => 'auto',
            'verbose' => $this->config->verbose,
            'template' => $this->config->template,
            'projectRoot' => $this->config->projectRoot,
            'hostProjectRoot' => $this->config->hostProjectRoot,
            'editorUrl' => $this->config->editorUrl,
        ]);
    }
}
