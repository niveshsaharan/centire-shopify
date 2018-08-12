<?php

namespace Centire\ShopifyApp\Traits;

trait WebhookControllerTrait
{
	/**
	 * Handles an incoming webhook.
	 *
	 * @param string $type The type of webhook
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function handle($type)
	{
		$classPath = $this->getJobClassFromType($type);

		if (!$classPath) {
			\Log::error("Missing webhook job for: {$type}");

			// Can not find a job for this webhook type
			abort(500, "Missing webhook job for: {$type}");
		}

		// Dispatch
		$shopDomain = request()->header('x-shopify-shop-domain');
		$data = json_decode(request()->getContent());
		dispatch(new $classPath($shopDomain, $data))->onQueue(queueName('second'));

		return response('', 201);
	}

	/**
	 * Converts type into a class string.
	 *
	 * @param string $type The type of webhook
	 *
	 * @return string|null
	 */
	protected function getJobClassFromType($type)
	{
		$jobClassPath = '\\App\\Jobs\\' . str_replace('_', '', ucwords(str_replace('-', '', ucwords($type, '-')), '_')) . 'WebhookJob';

		if (class_exists($jobClassPath)) {
			return $jobClassPath;
		}

		$jobClassPath = '\\Centire\\ShopifyApp\\Jobs\\' . str_replace('_', '', ucwords(str_replace('-', '', ucwords($type, '-')), '_')) . 'WebhookJob';

		if (class_exists($jobClassPath)) {
			return $jobClassPath;
		}

		return null;
	}
}
