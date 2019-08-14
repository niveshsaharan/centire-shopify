<?php

namespace Centire\ShopifyApp\Jobs;

use App\Shop;
use Centire\ShopifyApp\Services\ScriptTagManager;
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
     * Create a new job instance.
     *
     * @param $shop - The shop object
     *
     * @return void
     */
    public function __construct($shop)
    {
        $this->shop = $shop;
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
        return (new ScriptTagManager($this->shop))->createScriptTags();
    }

}
