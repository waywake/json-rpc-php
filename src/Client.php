<?php

namespace JsonRpc;

use GuzzleHttp\Exception\ServerException;
use JsonRpc\Exception\RpcServerException;

class Client
{
    protected $id;

    protected $http;

    public function __construct()
    {
        $this->id = 0;
        $this->http = new \GuzzleHttp\Client([
            'base_uri' => 'http://auth.lo.haowumc.com',
            'timeout' => 3,
            'allow_redirects' => false,
//            'proxy' => '192.168.16.1:10'
        ]);
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
            $resp = $this->http->request('POST', 'rpc/gateway.json', [
                'json' => $payload
            ]);
        } catch (ServerException $e) {
            throw new RpcServerException($e->getMessage(),$e->getCode());
        }

        try {
            $body = \GuzzleHttp\json_decode($resp->getBody(), true);

            if (isset($body['error']) && isset($body['error']['code']) && isset($body['error']['message'])) {
                throw new RpcServerException($body['error']['message'], $body['error']['code']);
            }

            return $body['result'];

        } catch (\InvalidArgumentException $e) {
            throw new RpcServerException('json decode error');
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