<?php

namespace JsonRpc;

use GuzzleHttp\Exception\ServerException;
use JsonRpc\Exception\RpcServerException;
use JsonRpc\Server\JsonRpcBase;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Client extends JsonRpc
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
        $this->config = $config;
        $this->id = 1;
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
    public function call($name, $arguments, $options = [])
    {
        $payload = [
            'jsonrpc' => '2.0',
            'method' => $name,
            'params' => $arguments,
            'id' => $this->id(),
        ];
        return $this->post($payload, $options);
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
    protected function post($payload, $options = [])
    {
        $uri = 'rpc/json-rpc-v2.json?app='.$this->config['app'];
        try {
            $headers = [
                'X-Client-App' => $this->config['app'],
	            'X-Request-Id' => app('request')->header('X-Request-Id')
            ];
            app('rpc.logger')->info("client_request", array_merge($this->server_config, $payload));
            $resp = $this->http->request('POST', $uri, array_merge([
                'headers' => $headers,
                'json' => $payload,
            ], $options));
        } catch (ServerException $e) {
            $ex = new RpcServerException(self::ErrorMsg[JsonRpc::Rpc_Error_Internal_Error], JsonRpc::Rpc_Error_Internal_Error);
            if (env("APP_DEBUG") == true) {
                $resp = $e->getResponse();
                $ex->setResponse($e->getResponse());
            }
            throw $ex;
        }

        try {
            $body = \GuzzleHttp\json_decode($resp->getBody(), true);
            app('rpc.logger')->info("client_response", $body);
            if (empty($body)) {
                throw new RpcServerException('http response empty', JsonRpc::Rpc_Error_System_Error);
            }
            if (isset($body['error']) && isset($body['error']['code']) && isset($body['error']['message'])) {
                $message = is_array($body['error']['message']) ? json_encode($body['error']['message']) : $body['error']['message'];
                $e = new RpcServerException($message, $body['error']['code']);
                throw $e;
            }

            return $body['result'];

        } catch (\InvalidArgumentException $e) {
            app('rpc.logger')->error('client_decode_error', array_merge($this->server_config, $payload));
            $ex = new RpcServerException($e->getMessage(), JsonRpc::Rpc_Error_Parse_Error);
            if (env("APP_DEBUG") == true) {
                $ex->setResponse($resp);
            }
            throw $ex;
        }
    }

    /**
     * request id
     * @return int
     */
    protected function id()
    {
        return $this->id++;
    }

}