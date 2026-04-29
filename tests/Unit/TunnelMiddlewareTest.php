<?php

namespace Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonRpc\JsonRpc;
use JsonRpc\Middleware\TunnelMiddleware;
use PHPUnit\Framework\TestCase;

class TunnelMiddlewareTest extends TestCase
{
    private array $loggedErrors = [];

    protected function tearDown(): void
    {
        if (class_exists(\InfluxDB\Client::class, false)) {
            \InfluxDB\Client::$throwOnSelect = false;
            \InfluxDB\Client::$lastDatabase = null;
        }
        Container::setInstance(null);
        parent::tearDown();
    }

    public function testHandleReturnsNextResponse(): void
    {
        $response = (new TunnelMiddleware())->handle(Request::create('/'), function () {
            return 'next-response';
        });

        $this->assertSame('next-response', $response);
    }

    public function testTerminateIgnoresNonJsonResponses(): void
    {
        $this->bindApplication('production', true);

        (new TunnelMiddleware())->terminate(Request::create('/'), 'plain-response');

        $this->assertTrue(true);
    }

    public function testTerminateSkipsMonitorWhenDisabled(): void
    {
        $this->bindApplication('production', false);

        (new TunnelMiddleware())->terminate(Request::create('/'), new JsonResponse([
            'jsonrpc' => '2.0',
            'result' => 'ok',
            'id' => 1,
        ]));

        $this->assertTrue(true);
    }

    public function testTerminateSkipsMonitorOutsideDevelopAndProduction(): void
    {
        $this->bindApplication('testing', true);

        (new TunnelMiddleware())->terminate(Request::create('/'), new JsonResponse([
            'jsonrpc' => '2.0',
            'result' => 'ok',
            'id' => 1,
        ]));

        $this->assertTrue(true);
    }

    public function testTerminateSkipsMonitorWhenInfluxClientIsMissing(): void
    {
        $this->bindApplication('production', true);

        (new TunnelMiddleware())->terminate(Request::create('/'), new JsonResponse([
            'jsonrpc' => '2.0',
            'result' => 'ok',
            'id' => 1,
        ]));

        $this->assertTrue(true);
    }

    public function testTerminateWritesSuccessAndErrorStatusesToInfluxClient(): void
    {
        $this->loadInfluxStubs();
        $this->bindApplication('production', true);

        $middleware = new TunnelMiddleware();
        $middleware->terminate(Request::create('/'), new JsonResponse([
            'jsonrpc' => '2.0',
            'result' => 'ok',
            'id' => 1,
        ]));
        $this->assertSame('rpc_monitor', \InfluxDB\Client::$lastDatabase->name);
        $this->assertSame('s', \InfluxDB\Client::$lastDatabase->precision);
        $this->assertSame(200, \InfluxDB\Client::$lastDatabase->points[0]->tags['status']);
        $this->assertSame(200, \InfluxDB\Client::$lastDatabase->points[0]->fields['status_value']);

        $middleware->terminate(Request::create('/'), new JsonResponse([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => JsonRpc::Rpc_Error_System_Error,
                'message' => 'System error',
            ],
            'id' => 1,
        ]));

        $this->assertSame(JsonRpc::Rpc_Error_System_Error, \InfluxDB\Client::$lastDatabase->points[0]->tags['status']);
        $this->assertSame(-JsonRpc::Rpc_Error_System_Error, \InfluxDB\Client::$lastDatabase->points[0]->fields['status_value']);
    }

    public function testTerminateLogsInfluxClientFailures(): void
    {
        $this->loadInfluxStubs();
        $this->bindApplication('production', true);
        \InfluxDB\Client::$throwOnSelect = true;

        (new TunnelMiddleware())->terminate(Request::create('/'), new JsonResponse([
            'jsonrpc' => '2.0',
            'result' => 'ok',
            'id' => 1,
        ]));

        $this->assertSame('influxdb-write-wrong', $this->loggedErrors[0][0]);
        $this->assertSame([
            'code' => 503,
            'message' => 'influx unavailable',
        ], $this->loggedErrors[0][1]);
    }

    private function loadInfluxStubs(): void
    {
        require_once dirname(__DIR__) . '/Fixtures/InfluxDbStubs.php';
        \InfluxDB\Client::$throwOnSelect = false;
        \InfluxDB\Client::$lastDatabase = null;
    }

    private function bindApplication(string $environment, bool $monitorEnabled): void
    {
        $app = new Application(dirname(__DIR__, 2));
        $app->detectEnvironment(static fn() => $environment);
        $app->instance('config', new Repository([
            'app' => [
                'name' => 'demo-app',
            ],
            'rpc' => [
                'monitor' => [
                    'enabled' => $monitorEnabled,
                ],
            ],
        ]));
        $app->instance('log', new class($this->loggedErrors) {
            public function __construct(private array &$errors)
            {
            }

            public function error(string $message, array $context): void
            {
                $this->errors[] = [$message, $context];
            }
        });
        Container::setInstance($app);
    }
}
