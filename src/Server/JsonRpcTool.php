<?php

namespace JsonRpc\Server;

use Illuminate\Http\Request;
use Illuminate\View\Factory;
use itxq\apidoc\BootstrapApiDoc;
use JsonRpc\Exception\RpcServerException;

/**
 * Class JsonRpcTool
 * for lumen
 * @package JsonRpc\Server
 */
class JsonRpcTool
{

    protected array $config;
    protected array $classes = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function render(): string
    {
        /**
         * @var $request Request
         */
        $request = app('request');

        /**
         * @var $view Factory
         */
        $view = view();

        $params = json_decode($request->input('params', "[\r\n]"), true) ?? [];
        $method = $request->input('method');
        if ($request->method() == Request::METHOD_POST) {
            try {
                $result = app('rpc.' . $this->config['name'])->call($method, $params);
                $view->share('result', json_encode($result, JSON_PRETTY_PRINT));
            } catch (RpcServerException $exception) {
                $resp = $exception->getResponse();
                $view->share('error', [
                        'code' => $exception->getCode(),
                        'message' => $exception->getMessage(),
                        'resp' => $resp]
                );
            }
        }
        $methods = [];
        foreach ($this->config['map'] as $key => $item) {
            if (!in_array($item[0], $methods)) {
                $methods[] = $item[0];
            }
        }
        $config = [
            'class' => $methods,
            'filter_method' => [],
        ];

        $api = new BootstrapApiDoc($config);
        $data = $api->getApiDocTmp();
        $methods = $this->getMethods();
        $view->share('data',json_encode($data));
        $view->share('endpoint', $this->getEndpoint());
        $view->share('methods', $methods);
        $view->share('method', $method);
        $view->share('params', json_encode($params, JSON_PRETTY_PRINT));

        foreach ($methods as $name => $class) {
            $desc[$name] = $this->desc($class[0], $class[1]);
        }
        return $view->exists('tool') ?
            $view->make('tool') :
            $view->file(__DIR__ . '/../views/tool.blade.php');
    }

    public function getEndpoint(): string
    {

        /**
         * @var $request Request
         */
        $request = app('request');
        return $request->getSchemeAndHttpHost() . '/rpc/json-rpc-v2.json';
    }

    public function getMethods(): array
    {
        return $this->config['map'];
    }

    protected function desc(string $class, string $method): string
    {
        if (!isset($this->classes[$class])) {
            $reflector = new \ReflectionClass($class);
            $this->classes[$class] = $reflector;
        } else {
            $reflector = $this->classes[$class];
        }

        $comment = $reflector->getMethod($method)->getDocComment();
        return str_replace("/**\n", '', $comment ?: '');
    }
}
