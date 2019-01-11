<?php

namespace JsonRpc;

use GuzzleHttp\Exception\ServerException;
use JsonRpc\Exception\RpcServerException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Client
{
    /**
     * all configuration information
     * @var array
     */
    protected $config;

    /**
     * request id
     * @var string
     */
    protected $id;

    /**
     * logger
     * @var Logger
     */
    protected $logger;
    /**
     * @var \GuzzleHttp\Client
     */
    protected $http;

    /**
     * which server rpc call choose
     * @var array
     */
    protected $server_config;

    public function __construct($config)
    {
        $default = [
            'app' => '***',
            'log_path'=> "/logs/rpc_monitor_".date("Ymd").".log",
            'log_formatter' => \JsonRpc\Logging\LogstashFormatter::class,
        ];
        $this->config = array_merge($default, $config);
        $stream = new StreamHandler(app()->storagePath().$this->config['log_path']);
        $stream->setFormatter(new $this->config['log_formatter']());
        $logger = new Logger('RPC.LOGGER');
        $logger->pushHandler($stream);
        $this->id = app('request')->header('X-Request-Id')?:"no-x-request-id";
        $this->logger = $logger;
    }

    /**
     *
     * @param $k
     * @return $this
     */
    public function endpoint($k)
    {
        $this->server_config = $this->config['client'][$k];

        $default = [
            'app' => $k,
            'timeout' => 3,
            'allow_redirects' => false,
        ];

        $this->http = new \GuzzleHttp\Client(array_merge($default, $this->server_config));
        return $this;
    }

    /**
     * @param $name
     * @param $arguments
     * @throws RpcServerException
     * @return array
     */
    public function call($name, $arguments)
    {
        $payload = [
            'jsonrpc' => '2.0',
            'method' => $name,
            'params' => $arguments,
            'id' => $this->id(),
        ];
        return $this->post($payload);
    }

    /**
     * @param $name
     * @param $arguments
     * @return array
     * @throws RpcServerException
     */
    public function __call($name, $arguments)
    {
        return $this->call($name, $arguments);
    }

    /**
     * @param $payload
     * @throws RpcServerException
     * @return array
     */
    protected function post($payload)
    {
        try {
            $headers = [
                'client_app' => $this->config['app'],
            ];
            $resp = $this->http->request('POST', 'rpc/json-rpc-v2.json', [
                'headers' => $headers,
                'json' => $payload,
            ]);
        } catch (ServerException $e) {
            throw new RpcServerException($e->getMessage(), $e->getCode());
        }

        try {
            $body = \GuzzleHttp\json_decode($resp->getBody(), true);
            if (isset($body['error']) && isset($body['error']['code']) && isset($body['error']['message'])) {
                throw new RpcServerException($body['error']['message'], $body['error']['code']);
            }
            $this->logger->info('MONITOR',compact("payload", "body", "headers"));
            return $body['result'];

        } catch (\InvalidArgumentException $e) {
            throw new RpcServerException('json decode error', -32700);
        }
    }

    /**
     * request id
     * @return int
     */
    protected function id()
    {
        return  $this->id.'-'.time();
    }

}