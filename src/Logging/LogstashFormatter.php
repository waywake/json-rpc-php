<?php
/**
 * Created by PhpStorm.
 * User: dongwei
 * Date: 2019/1/10
 * Time: 3:18 PM
 */
namespace JsonRpc\Logging;

use Monolog\Formatter\NormalizerFormatter;

class LogstashFormatter extends NormalizerFormatter
{
    protected $hostname;

    public function __construct()
    {
        // logstash requires a ISO 8601 format date with optional millisecond precision.
        parent::__construct('Y-m-d\TH:i:s.uP');

        $this->hostname = gethostname();
    }

    public function format(array $record)
    {
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

        if (isset($record['x-client-app'])) {
            $message['client_app'] = $record['x_client_app'];
        }
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
