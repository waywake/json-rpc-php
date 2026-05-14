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
        $this->assertSame('127.0.0.1', $data['client_ip']);
        $this->assertSame('GET', $data['http_method']);
        $this->assertSame('http://localhost', $data['url']);
        $this->assertSame('rpc', $data['channel']);
        $this->assertSame('INFO', $data['level']);
        $this->assertSame(200, $data['level_value']);
        $this->assertSame('client_request', $data['message']);
        $this->assertSame(['method' => 'user.info'], $data['context']);
        $this->assertArrayHasKey('@timestamp', $data);
        $this->assertArrayHasKey('host', $data);
    }

    public function testItOmitsEmptyRequestFieldsWhenHeadersAreMissing(): void
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

        $data = json_decode($line, true);

        $this->assertArrayNotHasKey('client_app', $data);
        $this->assertArrayNotHasKey('request_id', $data);
        $this->assertSame('server_result', $data['message']);
    }

    public function testItFormatsWithoutLaravelApplicationContext(): void
    {
        Container::setInstance(new Container());

        $line = (new LogstashFormatter())->format(new LogRecord(
            datetime: new DateTimeImmutable('2026-04-29 09:00:00 UTC'),
            channel: 'rpc',
            level: Level::Warning,
            message: 'cli_message',
            context: ['safe' => true],
            extra: ['worker' => 'queue']
        ));

        $data = json_decode($line, true);

        $this->assertSame('WARNING', $data['level']);
        $this->assertSame(['safe' => true], $data['context']);
        $this->assertSame(['worker' => 'queue'], $data['extra']);
        $this->assertArrayNotHasKey('app', $data);
        $this->assertArrayNotHasKey('env', $data);
        $this->assertArrayNotHasKey('request_id', $data);
    }
}
