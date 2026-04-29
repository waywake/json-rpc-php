<?php

namespace Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonRpc\Middleware\Security;
use PHPUnit\Framework\TestCase;

class MiddlewareSecurityTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }

    public function testItAllowsAllClientsInLocalEnvironment(): void
    {
        $this->bindApplication('local');

        $response = (new Security())->handle($this->requestFromIp('8.8.8.8'), function () {
            return 'next';
        });

        $this->assertSame('next', $response);
    }

    public function testItAllowsPrivateNetworkClientsOutsideLocalEnvironment(): void
    {
        $this->bindApplication('production');

        $response = (new Security())->handle($this->requestFromIp('10.0.1.5'), function () {
            return 'next';
        });

        $this->assertSame('next', $response);
    }

    public function testItRejectsPublicClientsOutsideLocalEnvironment(): void
    {
        $this->bindApplication('production');

        $response = (new Security())->handle($this->requestFromIp('8.8.8.8'), function () {
            return 'next';
        });

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('8.8.8.8 is forbidden', $response->getData());
    }

    private function bindApplication(string $environment): void
    {
        $app = new Application(dirname(__DIR__, 2));
        $app->detectEnvironment(static fn() => $environment);
        Container::setInstance($app);
    }

    private function requestFromIp(string $ip): Request
    {
        return Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => $ip,
        ]);
    }
}
