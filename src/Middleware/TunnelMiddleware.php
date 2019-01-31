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
			app('rpc.logger')->info('record to influxdb', [app()->environment('dev', 'production')]);
			if (app()->environment('dev', 'production')) {
				app('rpc.logger')->info('record to influxdb');
				$content = $response->getOriginalContent();
				$status = isset($content['error']) ? $content['error']['code'] : 200;
				$client = new \InfluxDB\Client("10.0.1.67");
				$database = $client->selectDB('rpc_monitor');
				$points = array(
					new Point(
						'monitor',
						null,
						['app' => env('APP_NAME'), 'status' => $status, 'env' => app()->environment()],
						['content' => $request->getContent()]
					)
				);
				app('rpc.logger')->info('record to influxdb', ['rs' => $database->writePoints($points, Database::PRECISION_SECONDS)]);
			}
			
		}
	}
	
}