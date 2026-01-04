<?php

namespace JsonRpc\Server;


use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonRpc\JsonRpc;
use Psr\Log\LoggerInterface;

class JsonRpcServer extends JsonRpc
{
    public Request $request;
    protected ?LoggerInterface $logger = null;
    protected array $config;
    protected array $map;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->request = function_exists('app') ? app('request') : Request::capture();
        $this->map = $config['map'];
    }

    public function setLogger(?LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function handler(): JsonResponse
    {
        if (!$this->request->isJson()) {
            return $this->error(self::Rpc_Error_Invalid_Request);
        }

        try {

            if ($this->request->method() == Request::METHOD_GET) {
                $method = $this->request->input('method');
                $id = $this->request->input('id');

                // Guzzle 7+ change: Use native json_decode instead of \GuzzleHttp\json_decode()
                $params = json_decode($this->request->input('params'), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \InvalidArgumentException('Invalid JSON in params');
                }
            } else {
                list($method, $params, $id) = $this->parseJson($this->request->getContent());
            }

            list($class, $function) = $this->parseMethodWithMap($method);

            if (!class_exists($class) || !method_exists($class, $function)) {
                return $this->error(self::Rpc_Error_NOT_FOUND);
            }

            // Normalize associative params to avoid PHP 8 named-parameter errors.
            $params = $this->normalizeParams($class, $function, $params);

            if (!$this->isEnoughParameter($class, $function, $params)) {
                return $this->error(self::Rpc_Error_Invalid_Params);
            }
            $this->request->attributes->add(['tunnel_method' => $method, 'tunnel_params' => $params]);
            $this->logger && $this->logger->info('server', [$id, $class, $method, $params]);
            $ret = call_user_func_array([(new $class($id, $this->request)), $function], $params);
            $this->logger && $this->logger->info('server_result', [$id, $ret]);

            return new JsonResponse($ret);

        } catch (\InvalidArgumentException $e) {
            return $this->error(self::Rpc_Error_Parse_Error);
        }
    }

    /**
     * 处理json rpc post body
     * @param string $data
     * @return array
     */
    protected function parseJson(string $data): array
    {
        // Guzzle 7+ change: Use native json_decode instead of \GuzzleHttp\json_decode()
        $data = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON');
        }

        $method = $data['method'] ?? '';
        $params = $data['params'] ?? [];
        $id = $data['id'] ?? null;

        return [$method, $params, $id];
    }

    /**
     * 根据method解析出对应的class
     */
    protected function parseMethodWithMap(string $method): array
    {
        return $this->map[$method] ?? ['', ''];
    }

    /**
     * PHP 8 treats string keys in call_user_func_array as named parameters.
     * Fall back to positional args unless all keys match method parameters.
     */
    protected function normalizeParams(string $class, string $method, array $params): array
    {
        if (!$params) {
            return $params;
        }

        $hasStringKeys = false;
        foreach (array_keys($params) as $key) {
            if (!is_int($key)) {
                $hasStringKeys = true;
                break;
            }
        }
        if (!$hasStringKeys) {
            return $params;
        }

        $ref = new \ReflectionMethod($class, $method);
        $paramNames = array_map(static fn($param) => $param->getName(), $ref->getParameters());

        $keys = array_keys($params);
        foreach ($keys as $key) {
            if (!in_array($key, $paramNames, true)) {
                return array_values($params);
            }
        }

        $normalized = [];
        foreach ($paramNames as $name) {
            if (array_key_exists($name, $params)) {
                $normalized[] = $params[$name];
            }
        }

        return $normalized;
    }

    /**
     * 检查调用方式是否足够
     */
    protected function isEnoughParameter(string $class, string $method, array $parameters): bool
    {
        $r = new \ReflectionMethod($class, $method);
        $params = $r->getParameters();
        $n = 0;
        foreach ($params as $param) {
            //$param is an instance of ReflectionParameter
            if (!$param->isOptional()) {
                $n++;
            }
        }
        return count($parameters) >= $n;
    }

    protected function error(int $code, ?string $msg = null, $id = null): JsonResponse
    {
        if ($msg === null) {
            $msg = self::ErrorMsg[$code] ?? 'undefined';
        }

        return new JsonResponse([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $msg,
            ],
            'id' => $id
        ]);
    }
}
