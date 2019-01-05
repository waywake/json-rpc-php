<?php

namespace JsonRpc\Server;

use Illuminate\Http\Request;
use Illuminate\View\Factory;
use JsonRpc\Exception\RpcServerException;

/**
 * Class JsonRpcTool
 * for lumen
 * @package JsonRpc\Server
 */
class JsonRpcTool
{

    protected $config;

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

        $params = json_decode($request->input('params'), true);

        if ($request->method() == Request::METHOD_POST) {

            $method = $request->input('method');

            try {
                $result = app('rpc.auth')->call($method, $params);
                $view->share('result', json_encode($result, JSON_PRETTY_PRINT));
            } catch (RpcServerException $exception) {
                $view->share('error', ['code' => $exception->getCode(), 'message' => $exception->getMessage()]);
            }
        }

        $view->share('endpoint', $this->getEndpoint());
        $view->share('methods', $this->getMethods());
        $view->share('params', json_encode($params));

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
        return include_once $this->config['map'];
    }

}