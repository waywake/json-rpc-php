<?php

namespace Tests\Unit;

use DateTimeImmutable;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use JsonRpc\Logging\LogstashFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class LogstashFormatterTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }

    public function testItFormatsLogRecordsAsSingleLineJson(): void
    {
        $app = new Application(dirname(__DIR__, 2));
        $app->detectEnvironment(static fn() => 'testing');
        $app->instance('config', new Repository([
            'app' => [
                'name' => 'demo-app',
            ],
        ]));
        $app->instance('request', Request::create('/', 'GET', [], [], [], [
            'HTTP_X_CLIENT_APP' => 'client-app',
            'HTTP_X_REQUEST_ID' => 'request-1',
        ]));
        Container::setInstance($app);

        $formatter = new LogstashFormatter();
        $line = $formatter->format(new LogRecord(
            datetime: new DateTimeImmutable('2026-04-29 09:00:00 UTC'),
            channel: 'rpc',
            level: Level::Info,
            message: 'client_request',
            context: ['method' => 'user.info'],
            extra: []
        ));

        $this->assertStringEndsWith("\n", $line);

        $data = json_decode($line, true);
        $this->assertSame('demo-app', $data['app']);
        $this->assertSame('testing', $data['env']);
        $this->assertSame('client-app', $data['client_app']);
        $this->assertSame('request-1', $data['request_id']);
        $this->assertSame('INFO', $data['level']);
        $this->assertSame('client_request', $data['message']);
        $this->assertSame(['method' => 'user.info'], json_decode($data['context'], true));
        $this->assertArrayHasKey('@timestamp', $data);
        $this->assertArrayHasKey('host', $data);
    }

    public function testItOmitsRequestIdWhenHeaderIsMissing(): void
    {
        $app = new Application(dirname(__DIR__, 2));
        $app->detectEnvironment(static fn() => 'testing');
        $app->instance('config', new Repository([
            'app' => [
                'name' => 'demo-app',
            ],
        ]));
        $app->instance('request', Request::create('/'));
        Container::setInstance($app);

        $line = (new LogstashFormatter())->format(new LogRecord(
            datetime: new DateTimeImmutable('2026-04-29 09:00:00 UTC'),
            channel: 'rpc',
            level: Level::Debug,
            message: 'server_result',
            context: [],
            extra: []
        ));

        $this->assertArrayNotHasKey('request_id', json_decode($line, true));
    }
}
