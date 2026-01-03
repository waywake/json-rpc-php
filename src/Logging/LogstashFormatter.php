<?php
/**
 * Created by PhpStorm.
 * User: dongwei
 * Date: 2019/1/10
 * Time: 3:18 PM
 */
namespace JsonRpc\Logging;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;

class LogstashFormatter extends NormalizerFormatter
{
    protected string $hostname;

    public function __construct()
    {
        // logstash requires a ISO 8601 format date with optional millisecond precision.
        parent::__construct('Y-m-d\TH:i:s.uP');

        $this->hostname = gethostname();
    }

    /**
     * @param array|LogRecord $record
     */
    public function format(array|LogRecord $record): string
    {
        // Handle Monolog 3.x LogRecord object
        if ($record instanceof LogRecord) {
            $record = $record->toArray();
        }

        $record = parent::format($record);
        $message = array(
            '@timestamp' => $record['datetime'],
            'host' => $this->hostname,
            'app' => env('APP_NAME'),
            'env' => app()->environment(),
            'client_app' => app('request')->header('X-Client-App'),
        );

        $request_id = app('request')->header('X-Request-Id');
        if ($request_id) {
            $message['request_id'] = $request_id;
        }

        // if (isset($record['channel'])) {
        //     $message['channel'] = $record['channel'];
        // }
        if (isset($record['level_name'])) {
            $message['level'] = $record['level_name'];
        }
        if (isset($record['message'])) {
            $message['message'] = $record['message'];
        }

        if (!empty($record['context'])) {
            $message['context'] = $this->toJson($record['context']);
        }

        return $this->toJson($message) . "\n";
    }
}
