<?php

namespace Tests\Fixtures;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

class ConfigMutatingFormatter implements FormatterInterface
{
    public function __construct()
    {
        config(['rpc' => 'invalid']);
    }

    public function format(LogRecord $record): string
    {
        return $record->message;
    }

    public function formatBatch(array $records): array
    {
        return array_map($this->format(...), $records);
    }
}
