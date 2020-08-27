<?php

namespace Centire\ShopifyApp\Jobs;

use App\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use \Illuminate\Support\Str;

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

        // Create dynamic URL for cloud
        foreach ($scriptTags as $key => $scriptTag) {
            if (!Str::startsWith($scriptTag['src'], 'http')) {
                if (config('shopify.shopify_assets_source') === 'cloud' && config('filesystems.cloud')) {
                    $scriptTags[$key]['src'] = \Storage::cloud()->url('/assets/' . $scriptTag['src']);
                } else {
                    unset($scriptTags[$key]);
                }
            }
        }

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

        if (!$this->shop->hasScope(['read_script_tags', 'write_script_tags'])) {
            return [];
        }

        // Get the current scriptTags installed on the shop
        $api = $this->shop->api();

        $shopScriptTags = $api->rest(
            'GET',
            '/script_tags.json',
            ['limit' => 250, 'fields' => 'id,src']
        )->body->script_tags;

        $validScriptTags = [];

        foreach ($this->scriptTags as $scriptTag) {
            // Check if the required scriptTag exists on the shop
            if (!$this->scriptTagExists($shopScriptTags, $scriptTag)) {
                // It does not... create the scriptTag
                $api->rest('POST', '/script_tags.json', [
                    'script_tag' => array_only(
                        $scriptTag,
                        ['src', 'event', 'display_scope']
                    ),
                ]);

                $created[] = $scriptTag;
            }

            $validScriptTags[] = $scriptTag['src'];
        }

        // Delete
        foreach ($shopScriptTags as $scriptTag) {
            if (!in_array($scriptTag->src, $validScriptTags)) {
                $api->rest('DELETE', '/script_tags/' . $scriptTag->id . '.json', []);
            }
        }

        if ($this->shop->hasScope(['read_themes', 'write_themes'])) {
            \Artisan::call('theme:scripts:replace', ['shop' => $this->shop->shopify_domain, 'delay' => 1]);
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
