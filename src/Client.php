<?php

namespace JsonRpc;

use GuzzleHttp\Exception\ServerException;
use JsonRpc\Exception\RpcServerException;

class Client
{
    protected $config;

    protected $id;
    /**
     * @var \GuzzleHttp\Client
     */
    protected $http;

    public function __construct($config)
    {
        $default = [
            'app' => '***',
        ];

        $this->config = array_merge($default, $config);
        $this->id = 0;
    }

    public function endpoint($k)
    {
        $config = $this->config[$k];

        $default = [
            'timeout' => 3,
            'allow_redirects' => false,
        ];

        $this->http = new \GuzzleHttp\Client(array_merge($default, $config));
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
            $resp = $this->http->request('POST', 'rpc/json-rpc-v2.json', [
                'headers' => [
                    'hwmc_app' => $this->config['app'],
                ],
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
        $this->id++;
        return $this->id;
    }

}