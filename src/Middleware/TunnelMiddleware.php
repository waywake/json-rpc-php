<?php

namespace JsonRpc\Middleware;

use Closure;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use InfluxDB\Database;
use InfluxDB\Point;

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
	
	/**
	 * @param \Illuminate\Http\Request $request
	 * @param \Closure $response
	 */
	public function terminate($request, $response)
	{
		//过滤tool返回结果
		if ($response instanceof JsonResponse) {
			if (app()->environment('develop')) {
				$content = $response->getOriginalContent();
				$status = isset($content['error']) ? $content['error']['code'] : 200;
				$client = new \InfluxDB\Client("influxdb-svc", 8086, '', '', false, false, 1, 1);
				$database = $client->selectDB('rpc_monitor');
				$points = array(
					new Point(
						'monitor',
						1,
						['app' => env('APP_NAME'), 'status' => $status, 'env' => app()->environment()],
						['status_value' => $status == 200 ? $status : -$status]
					)
				);
			}
			
		}
	}
	
}