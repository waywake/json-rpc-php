<?php

namespace JsonRpc\Server;

use Illuminate\Http\Request;
use Illuminate\View\Factory;
use JsonRpc\Exception\RpcServerException;
use Monolog\Logger;

/**
 * Class JsonRpcTool
 * for lumen
 * @package JsonRpc\Server
 */
class JsonRpcTool
{

    protected $config;
    protected $classes;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function render()
    {
        /**
         * @var $request Request
         */
        $request = app('request');

        /**
         * @var $view Factory
         */
        $view = view();

        $params = json_decode($request->input('params', "[\r\n]"), true);
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
        $methods = $this->getMethods();
        $view->share('method', $method);
        $view->share('endpoint', $this->getEndpoint());
        $view->share('methods', $methods);
        $view->share('params', json_encode($params, JSON_PRETTY_PRINT));

        foreach ($methods as $name => $class) {
            $desc[$name] = $this->desc($class[0], $class[1]);
        }
        return $view->exists('tool') ?
            $view->make('tool') :
            $view->file(__DIR__ . '/../views/tool.blade.php');
    }

    public function getEndpoint()
    {

        /**
         * @var $request Request
         */
        $request = app('request');
        return $request->getSchemeAndHttpHost() . '/rpc/json-rpc-v2.json';
    }

    public function getMethods()
    {
        return $this->config['map'];
    }

    protected function desc($class, $method)
    {
        if (!isset($this->classes[$class])) {
            $reflector = new \ReflectionClass($class);
            $this->classes[$class] = $reflector;
        } else {
            $reflector = $this->classes[$class];
        }

        return str_replace("/**\n", '', $reflector->getMethod($method)->getDocComment());
    }
}