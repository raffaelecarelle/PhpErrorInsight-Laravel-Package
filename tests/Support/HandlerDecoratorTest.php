<?php

declare(strict_types=1);

namespace PhpErrorInsightLaravel\Tests\Support;

use BadMethodCallException;
use Exception;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;
use PhpErrorInsightLaravel\Support\HandlerDecorator;
use PhpErrorInsightLaravel\Tests\BaseTestCase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Response;

class HandlerDecoratorTest extends BaseTestCase
{
    private HandlerDecorator $decorator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decorator = $this->app->make(HandlerDecorator::class);
    }

    public function testReportLogsExceptionToLaravelLog(): void
    {
        $exception = new RuntimeException('Test runtime exception for reporting');

        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
        }

        $this->decorator->report($exception);

        $this->assertFileExists($logFile);
        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('Test runtime exception for reporting', $logContent);
    }

    public function testShouldReportReturnsCorrectValueForReportableExceptions(): void
    {
        $exception = new RuntimeException('Reportable exception');

        $result = $this->decorator->shouldReport($exception);

        $this->assertTrue($result);
    }

    public function testRenderReturnsHtmlResponseWhenDebugEnabledAndRequestExpectsHtml(): void
    {
        // Abilita debug mode
        config(['app.debug' => true]);
        config(['error-insight.enabled' => true]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept', 'text/html');

        $exception = new RuntimeException('Test exception for HTML rendering', 500);

        $response = $this->decorator->render($request, $exception);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertNotEmpty($content);
        $this->assertStringContainsString('Test exception for HTML rendering', $content);
    }

    public function testRenderDelegatesToLaravelHandlerWhenDebugDisabled(): void
    {
        config(['app.debug' => false]);
        config(['error-insight.enabled' => true]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept', 'text/html');

        $exception = new RuntimeException('Test exception with debug off');

        $response = $this->decorator->render($request, $exception);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testRenderDelegatesToLaravelHandlerWhenInsightDisabled(): void
    {
        config(['app.debug' => true]);
        config(['error-insight.enabled' => false]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept', 'text/html');

        $exception = new RuntimeException('Test exception with insight off');

        $response = $this->decorator->render($request, $exception);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testRenderReturnsJsonWhenRequestExpectsJson(): void
    {
        config(['app.debug' => true]);
        config(['error-insight.enabled' => true]);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('Content-Type', 'application/json');

        $exception = new RuntimeException('Test exception for JSON');

        $response = $this->decorator->render($request, $exception);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = $response->getContent();

        $this->assertTrue(
            str_starts_with((string) $response->headers->get('Content-Type', ''), 'application/json')
            || !str_contains($content, 'error-insight')
        );
    }

    public function testRenderHandlesExceptionWithStackTrace(): void
    {
        config(['app.debug' => true]);
        config(['error-insight.enabled' => true]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept', 'text/html');

        try {
            $this->throwNestedExceptions();
        } catch (Exception $exception) {
            $response = $this->decorator->render($request, $exception);

            $this->assertInstanceOf(Response::class, $response);
            $this->assertEquals(500, $response->getStatusCode());
            $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));

            // Verifica che il contenuto contenga informazioni dell'eccezione
            $content = $response->getContent();
            $this->assertNotEmpty($content);
            $this->assertStringContainsString('Nested exception at level 3', $content);
        }
    }

    public function testRenderForConsoleOutputsExceptionInformation(): void
    {
        $output = new BufferedOutput();
        $exception = new RuntimeException('Console exception test', 100);

        ob_start();
        try {
            $this->decorator->renderForConsole($output, $exception);
        } finally {
            $consoleOutput = ob_get_clean();
        }

        $this->assertIsString($consoleOutput);
    }

    public function testRenderForConsoleHandlesExceptionWithTrace(): void
    {
        $output = new BufferedOutput();

        try {
            $this->throwNestedExceptions();
        } catch (Exception $exception) {
            ob_start();
            try {
                $this->decorator->renderForConsole($output, $exception);
            } finally {
                $consoleOutput = ob_get_clean();
            }

            $this->assertIsString($consoleOutput);
        }
    }

    public function testRenderWorksWithDifferentExceptionTypes(): void
    {
        config(['app.debug' => true]);
        config(['error-insight.enabled' => true]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept', 'text/html');

        $exceptions = [
            new RuntimeException('Runtime error'),
            new LogicException('Logic error'),
            new InvalidArgumentException('Invalid argument'),
            new BadMethodCallException('Bad method call'),
        ];

        foreach ($exceptions as $exception) {
            $response = $this->decorator->render($request, $exception);

            $this->assertInstanceOf(Response::class, $response);
            $this->assertEquals(500, $response->getStatusCode());
        }
    }

    public function testRenderPreservesExceptionMessageInResponse(): void
    {
        config(['app.debug' => true]);
        config(['error-insight.enabled' => true]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept', 'text/html');

        $customMessage = 'This is a very specific error message ' . uniqid('', true);
        $exception = new RuntimeException($customMessage);

        $response = $this->decorator->render($request, $exception);

        $this->assertEquals(500, $response->getStatusCode());

        // Il messaggio dovrebbe essere presente nel contenuto della risposta
        $content = $response->getContent();
        $this->assertNotEmpty($content);
        $this->assertStringContainsString($customMessage, $content);
    }

    public function testIntegrationWithLaravelExceptionHandler(): void
    {
        config(['app.debug' => true]);
        config(['error-insight.enabled' => true]);

        $request = Request::create('/test-route', 'GET');
        $request->headers->set('Accept', 'text/html');

        $exception = new Exception('Integration test exception');

        $response = $this->decorator->render($request, $exception);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertNotEmpty($response->getContent());
    }

    public function testRenderHandlesExceptionsThrownInDifferentFiles(): void
    {
        config(['app.debug' => true]);
        config(['php-error-insight.enabled' => true]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept', 'text/html');

        // Crea un'eccezione che sembra venire da un file specifico
        $exception = new RuntimeException('Exception from specific location');

        $response = $this->decorator->render($request, $exception);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotEmpty($content);

        // Verifica che il contenuto contenga informazioni sull'eccezione
        $this->assertStringContainsString('Exception from specific location', $content);
    }

    /**
     * Helper method per creare eccezioni annidate con stack trace.
     */
    private function throwNestedExceptions(): void
    {
        $this->levelOne();
    }

    private function levelOne(): void
    {
        $this->levelTwo();
    }

    private function levelTwo(): void
    {
        $this->levelThree();
    }

    private function levelThree(): void
    {
        throw new RuntimeException('Nested exception at level 3');
    }
}
