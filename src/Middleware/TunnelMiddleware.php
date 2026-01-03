<?php

namespace JsonRpc\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class TunnelMiddleware
 * @package JsonRpc\Middleware
 */
class TunnelMiddleware
{
	/**
	 * Handle an incoming request.
	 *
	 * @param  Request  $request
	 * @param  Closure  $next
	 * @return mixed
	 */
	public function handle(Request $request, Closure $next): mixed
	{
		// Pre-Middleware Action
		$response = $next($request);

		// Post-Middleware Action

		return $response;
	}

	/**
	 * @param Request $request
	 * @param JsonResponse $response
	 */
	public function terminate(Request $request, $response): void
	{
		// Filter tool return results
		if ($response instanceof JsonResponse) {
			if (app()->environment('develop', 'production') && env('RPC_MONITOR_SWITCH') == 1) {
				$content = $response->getOriginalContent();
				$status = isset($content['error']) ? $content['error']['code'] : 200;

				// Gracefully handle InfluxDB if available
				$this->writeToInfluxDB($status);
			}

		}
	}

	protected function writeToInfluxDB(int $status): void
	{
		// Check if InfluxDB client is available
		if (!class_exists(\InfluxDB\Client::class)) {
			return;
		}

		try {
			$client = new \InfluxDB\Client("influxdb-svc", 8086, '', '', false, false, 1, 1);
			$database = $client->selectDB('rpc_monitor');
			$points = array(
				new \InfluxDB\Point(
					'monitor',
					1,
					['app' => env('APP_NAME'), 'status' => $status, 'env' => app()->environment()],
					['status_value' => $status == 200 ? $status : -$status]
				)
			);
			$database->writePoints($points, \InfluxDB\Database::PRECISION_SECONDS);
		} catch (\Exception $exception) {
			app('log')->error('influxdb-write-wrong', ['code' => $exception->getCode(),'message' => $exception->getMessage()]);
		}
	}

}
