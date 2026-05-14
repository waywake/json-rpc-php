<?php

namespace JsonRpc\Logging;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;

class LogstashFormatter extends NormalizerFormatter
{
    protected string $hostname;

    public function __construct()
    {
        // logstash requires a ISO 8601 format date with optional millisecond precision.
        parent::__construct('Y-m-d\TH:i:s.uP');

        $this->hostname = gethostname() ?: 'unknown';
    }

    /**
     * @param LogRecord $record
     */
    public function format(LogRecord $record): string
    {
        $record = parent::format($record);
        $message = [
            '@timestamp' => $record['datetime'],
            'host' => $this->hostname,
            'app' => $this->appName(),
            'env' => $this->environment(),
            'channel' => $record['channel'] ?? null,
            'level' => $record['level_name'] ?? null,
            'level_value' => $record['level'] ?? null,
            'message' => $record['message'] ?? null,
        ];

        $request = $this->request();
        if ($request instanceof Request) {
            $message = array_merge($message, [
                'client_app' => $request->header('X-Client-App'),
                'request_id' => $request->header('X-Request-Id'),
                'client_ip' => $request->ip(),
                'http_method' => $request->getMethod(),
                'url' => $request->fullUrl(),
            ]);
        }

        if (!empty($record['context'])) {
            $message['context'] = $record['context'];
        }

        if (!empty($record['extra'])) {
            $message['extra'] = $record['extra'];
        }

        return $this->toJson($this->withoutNullValues($message)) . "\n";
    }

    protected function appName(): ?string
    {
        $config = $this->containerValue('config');

        if ($config && method_exists($config, 'get')) {
            return $config->get('app.name');
        }

        return null;
    }

    protected function environment(): ?string
    {
        $container = Container::getInstance();

        if (method_exists($container, 'environment')) {
            return $container->environment();
        }

        return null;
    }

    protected function request(): ?Request
    {
        $request = $this->containerValue('request');

        return $request instanceof Request ? $request : null;
    }

    protected function containerValue(string $abstract): mixed
    {
        $container = Container::getInstance();

        if (!$container->bound($abstract)) {
            return null;
        }

        return $container->make($abstract);
    }

    /**
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    protected function withoutNullValues(array $message): array
    {
        return array_filter($message, static fn($value) => $value !== null);
    }
}
