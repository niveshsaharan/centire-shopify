<?php

namespace Centire\ShopifyApp\Jobs;

use App\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScriptTagsInstaller implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	/**
	 * The shop.
	 *
	 * @var Shop $shop
	 */
	protected $shop;

	/**
	 * Script Tags list.
	 *
	 * @var array
	 */
	protected $scriptTags;

	/**
	 * Create a new job instance.
	 *
	 * @param $shop - The shop object
	 * @param array $scriptTags The scriptTag list
	 *
	 * @return void
	 */
	public function __construct($shop, array $scriptTags)
	{
		$this->shop = $shop;
		$this->scriptTags = $scriptTags;
	}

	/**
	 * Get the tags that should be assigned to the job.
	 *
	 * @return array
	 */
	public function tags()
	{
		return [
			'script_tag_installer',
			'shop:' . $this->shop->shopify_domain,
		];
	}

	/**
	 * Execute the job.
	 *
	 * @return array
	 */
	public function handle()
	{
		// Keep track of whats created
		$created = [];

		// Get the current scriptTags installed on the shop
		$api = $this->shop->api();

		$shopScriptTags = $api->rest(
			'GET',
			'/admin/script_tags.json',
			['limit' => 250, 'fields' => 'id,src']
		)->body->script_tags;

		foreach ($this->scriptTags as $scriptTag) {
			// Check if the required scriptTag exists on the shop
			if (!$this->scriptTagExists($shopScriptTags, $scriptTag)) {
				// It does not... create the scriptTag
				$api->rest('POST', '/admin/script_tags.json', [
					'script_tag' => array_only(
						$scriptTag,
						['src', 'event', 'display_scope']
					),
				]);

				$created[] = $scriptTag;
			}
		}

		return $created;
	}

	/**
	 * Check if scriptTag is in the list.
	 *
	 * @param array $shopScriptTags The scriptTags installed on the shop
	 * @param array $scriptTag The scriptTag
	 *
	 * @return bool
	 */
	protected function scriptTagExists(array $shopScriptTags, array $scriptTag)
	{
		foreach ($shopScriptTags as $shopScriptTag) {
			if ($shopScriptTag->src === $scriptTag['src']) {
				// Found the scriptTag in our list
				return true;
			}
		}

		return false;
	}
}
