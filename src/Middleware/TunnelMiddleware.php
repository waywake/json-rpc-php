<?php
namespace JsonRpc\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;

/**
 * Class TunnelMiddleware
 * @package JsonRpc\Middleware
 */
class TunnelMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Pre-Middleware Action
        $response = $next($request);

        // Post-Middleware Action

        return $response;
    }

    public function terminate($request, $response)
    {
        //过滤tool返回结果
        if ($response instanceof JsonResponse)
        {
            $content = $response->getOriginalContent();
            if (isset($content['error'])){
                app('rpc.logger')->info('rpc tunnel', [$content['error']['code']]);
            } else {
                app('rpc.logger')->info('rpc tunnel', [200]);
            }
        }
    }

}