<?php

declare(strict_types=1);

namespace PhpErrorInsightLaravel\Tests;

use ErrorExplainer\Config as InsightConfig;
use ErrorExplainer\Internal\Explainer;
use ErrorExplainer\Internal\Renderer;
use ErrorExplainer\Internal\StateDumper;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use PhpErrorInsightLaravel\Support\HandlerDecorator;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class HandlerDecoratorTest extends BaseTestCase
{
    private function makeApp(): Application
    {
        return $this->createApplication();
    }

    private function makeConfig(bool $enabled = true): InsightConfig
    {
        return new InsightConfig([
            'enabled' => $enabled,
            'backend' => 'none',
            'output' => InsightConfig::OUTPUT_HTML,
            'verbose' => false,
        ]);
    }

    private function makeException(): Throwable
    {
        try {
            throw new RuntimeException('boom');
        } catch (Throwable $throwable) {
            return $throwable;
        }
    }

    public function testRendersHtmlWhenEnabledDebugAndHtmlRequest(): void
    {
        $inner = new class() implements ExceptionHandler {
            /** @var array<callable> */
            public array $calls = [];

            public function report(Throwable $e): void
            {
                $this->calls[] = __FUNCTION__;
            }

            public function shouldReport(Throwable $e): bool
            {
                $this->calls[] = __FUNCTION__;

                return true;
            }

            public function render($request, Throwable $e)
            {
                $this->calls[] = __FUNCTION__;

                return new Response('INNER', 500);
            }

            public function renderForConsole($output, Throwable $e): void
            {
                $this->calls[] = __FUNCTION__;
            }
        };

        $app = $this->makeApp();
        $config = $this->makeConfig(true);
        $decorator = new HandlerDecorator($inner, $app, $config, new Explainer(), new Renderer(), new StateDumper());

        $request = new class() extends Request {
            public function expectsHtml(): bool
            {
                return true;
            }

            public function expectsJson(): bool
            {
                return false;
            }
        };

        $resp = $decorator->render($request, $this->makeException());
        $this->assertInstanceOf(Response::class, $resp);
        $this->assertSame(500, $resp->getStatusCode());
        $this->assertStringContainsString('<!doctype html>', (string) $resp->getContent());
        $this->assertStringContainsString('PHP Error Insight', (string) $resp->getContent());
    }

    public function testDelegatesToInnerForJsonRequest(): void
    {
        $inner = new class() implements ExceptionHandler {
            /** @var array<callable> */
            public array $calls = [];

            public function report(Throwable $e): void
            {
                $this->calls[] = __FUNCTION__;
            }

            public function shouldReport(Throwable $e): bool
            {
                $this->calls[] = __FUNCTION__;

                return true;
            }

            public function render($request, Throwable $e)
            {
                $this->calls[] = __FUNCTION__;

                return new Response('INNER', 500);
            }

            public function renderForConsole($output, Throwable $e): void
            {
                $this->calls[] = __FUNCTION__;
            }
        };

        $app = $this->makeApp();
        $config = $this->makeConfig();
        $decorator = new HandlerDecorator($inner, $app, $config, new Explainer(), new Renderer(), new StateDumper());

        $request = new class() extends Request {
            public function expectsJson(): bool
            {
                return true;
            }
        };

        $resp = $decorator->render($request, $this->makeException());
        $this->assertInstanceOf(Response::class, $resp);
        $this->assertSame('INNER', $resp->getContent());
    }

    public function testDelegatesWhenDisabled(): void
    {
        $inner = new class() implements ExceptionHandler {
            /** @var array<callable> */
            public array $calls = [];

            public function report(Throwable $e): void
            {
                $this->calls[] = __FUNCTION__;
            }

            public function shouldReport(Throwable $e): bool
            {
                $this->calls[] = __FUNCTION__;

                return true;
            }

            public function render($request, Throwable $e)
            {
                $this->calls[] = __FUNCTION__;

                return new Response('INNER', 500);
            }

            public function renderForConsole($output, Throwable $e): void
            {
                $this->calls[] = __FUNCTION__;
            }
        };

        $app = $this->makeApp();
        $config = $this->makeConfig(false);
        $decorator = new HandlerDecorator($inner, $app, $config, new Explainer(), new Renderer(), new StateDumper());

        $request = new class() extends Request {
            public function expectsHtml(): bool
            {
                return true;
            }
        };

        $resp = $decorator->render($request, $this->makeException());
        $this->assertInstanceOf(Response::class, $resp);
        $this->assertSame('INNER', $resp->getContent());
    }

    public function testDelegationMethods(): void
    {
        $inner = new class() implements ExceptionHandler {
            /** @var array<callable> */
            public array $calls = [];

            public function report(Throwable $e): void
            {
                $this->calls[] = __FUNCTION__;
            }

            public function shouldReport(Throwable $e): bool
            {
                $this->calls[] = __FUNCTION__;

                return false;
            }

            public function render($request, Throwable $e)
            {
                $this->calls[] = __FUNCTION__;

                return new Response('INNER', 500);
            }

            public function renderForConsole($output, Throwable $e): void
            {
                $this->calls[] = __FUNCTION__;
            }
        };

        $app = $this->makeApp();
        $config = $this->makeConfig();
        $decorator = new HandlerDecorator($inner, $app, $config, new Explainer(), new Renderer(), new StateDumper());

        $e = $this->makeException();
        $decorator->report($e);
        $this->assertContains('report', $inner->calls);
        $this->assertFalse($decorator->shouldReport($e));

        $out = fopen('php://memory', 'r+');
        $decorator->renderForConsole($out, $e);
        $this->assertContains('renderForConsole', $inner->calls);
        fclose($out);
    }
}
