<?php

namespace JsonRpc;

use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Client as GuzzleClient;
use JsonRpc\Exception\RpcServerException;
use Psr\Log\LoggerInterface;

class Client extends JsonRpc
{
    /**
     * all configuration information
     * @var array
     */
    protected array $config;

    /**
     * request id
     * @var int
     */
    protected int $id;

    /**
     * @var \GuzzleHttp\Client
     */
    protected ?GuzzleClient $http = null;

    /**
     * @var LoggerInterface
     */
    protected ?LoggerInterface $logger = null;

    /**
     * which server rpc call choose
     * @var array
     */
    protected ?array $server_config = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->id = 1;
    }

    public function setLogger(?LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     *
     * @param string $k
     * @return $this
     */
    public function endpoint(string $k): self
    {
        $this->server_config = $this->config['client'][$k];

        $default = [
            'timeout' => 10,
            'allow_redirects' => false,
        ];

        $this->http = new GuzzleClient(array_merge($default, $this->server_config));
        return $this;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @param array $options
     * @return array
     * @throws RpcServerException
     */
    public function call(string $name, array $arguments, array $options = []): mixed
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
     * @param string $name
     * @param array $arguments
     * @return mixed
     * @throws RpcServerException
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->call($name, $arguments);
    }

    /**
     * @param array $payload
     * @param array $options
     * @return array
     * @throws RpcServerException
     */
    protected function post(array $payload, array $options = []): mixed
    {
        $uri = 'rpc/json-rpc-v2.json?app=' . $this->config['app'];

        $requestId = isset($_SERVER['HTTP_X_REQUEST_ID']) ? $_SERVER['HTTP_X_REQUEST_ID'] : 'nginx-config-err';

        try {
            $headers = [
                'X-Client-App' => $this->config['app'],
                'X-Request-Id' => $requestId,
            ];
            $this->logger && $this->logger->info("client_request", array_merge($this->server_config ?? [], $payload));
            $resp = $this->http->request('POST', $uri, array_merge([
                'headers' => $headers,
                'json' => $payload,
            ], $options));
        } catch (ServerException $e) {
            $ex = new RpcServerException(
                self::ErrorMsg[JsonRpc::Rpc_Error_Internal_Error] ?? 'Internal error',
                JsonRpc::Rpc_Error_Internal_Error
            );
            if ($this->isDebug()) {
                $ex->setResponse($e->getResponse());
            }
            throw $ex;
        }

        try {
            // GUZZLE 7+ CHANGE: Use native json_decode() instead of \GuzzleHttp\json_decode()
            $body = json_decode($resp->getBody()->getContents(), true);
            $this->logger && $this->logger->debug("client_response", $body);
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
            $this->logger && $this->logger->error('client_decode_error', array_merge($this->server_config ?? [], $payload));
            $ex = new RpcServerException($e->getMessage(), JsonRpc::Rpc_Error_Parse_Error);
            if ($this->isDebug()) {
                $ex->setResponse($resp);
            }
            throw $ex;
        }
    }

    protected function isDebug(): bool
    {
        return function_exists('config') && (bool) config('app.debug', false);
    }

    /**
     * request id
     * @return int
     */
    protected function id(): int
    {
        return $this->id++;
    }

}
