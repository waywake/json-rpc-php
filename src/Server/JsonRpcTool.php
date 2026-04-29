<?php

namespace JsonRpc\Server;

use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\View\Factory;
use JsonRpc\Exception\RpcServerException;

/**
 * Class JsonRpcTool
 * for lumen
 * @package JsonRpc\Server
 */
class JsonRpcTool
{

    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function render(): View
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
        $methods = $this->getMethods();
        $view->share('data', json_encode($this->getDocData(), JSON_UNESCAPED_UNICODE));
        $view->share('endpoint', $this->getEndpoint());
        $view->share('methods', $methods);
        $view->share('method', $method);
        $view->share('params', json_encode($params, JSON_PRETTY_PRINT));

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

    protected function getDocData(): array
    {
        $data = [];
        foreach ($this->config['map'] as $name => $item) {
            if (!is_array($item) || count($item) < 2) {
                continue;
            }

            [$class, $method] = $item;
            $data[$name] = [
                'title' => $name,
                'method' => $name,
                'param' => [],
                'return' => [],
                'code' => [],
            ];

            if (!class_exists($class) || !method_exists($class, $method)) {
                continue;
            }

            $reflection = new \ReflectionMethod($class, $method);
            $doc = $this->parseDocComment($reflection->getDocComment() ?: '');
            $data[$name] = array_merge($data[$name], $doc);

            if (empty($data[$name]['param'])) {
                foreach ($reflection->getParameters() as $parameter) {
                    $data[$name]['param'][] = [
                        'param_name' => $parameter->getName(),
                        'param_type' => $parameter->hasType() ? (string) $parameter->getType() : 'mixed',
                        'param_title' => '',
                        'param_default' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : '',
                        'param_require' => $parameter->isOptional() ? '否' : '是',
                    ];
                }
            }
        }

        return $data;
    }

    protected function parseDocComment(string $comment): array
    {
        $data = [
            'param' => [],
            'return' => [],
            'code' => [],
        ];

        foreach (preg_split('/\R/', $comment) ?: [] as $line) {
            $line = trim(preg_replace('/^\s*\*\s?/', '', $line));
            if (preg_match('/^@title\s+(.+)$/u', $line, $matches)) {
                $data['title'] = $matches[1];
                continue;
            }
            if (preg_match('/^@param\s+(\S+)\s+(\S+)(?:\s+(.+))?$/u', $line, $matches)) {
                $parts = preg_split('/\s+/u', $matches[3] ?? '', 3);
                $data['param'][] = [
                    'param_type' => $matches[1],
                    'param_name' => $matches[2],
                    'param_title' => $parts[0] ?? '',
                    'param_default' => $parts[1] ?? '',
                    'param_require' => $parts[2] ?? '',
                ];
                continue;
            }
            if (preg_match('/^@return\s+(\S+)\s+(\S+)(?:\s+(.+))?$/u', $line, $matches)) {
                $data['return'][] = [
                    'return_type' => $matches[1],
                    'return_name' => $matches[2],
                    'return_title' => $matches[3] ?? '',
                ];
                continue;
            }
            if (preg_match('/^@code\s+(\S+)(?:\s+(.+))?$/u', $line, $matches)) {
                $data['code'][] = [
                    'code' => $matches[1],
                    'content' => $matches[2] ?? '',
                ];
            }
        }

        return $data;
    }
}
