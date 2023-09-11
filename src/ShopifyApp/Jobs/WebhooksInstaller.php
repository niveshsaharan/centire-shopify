<?php

namespace Centire\ShopifyApp\Jobs;

use App\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WebhooksInstaller implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The shop.
     *
     * @var Shop $shop
     */
    protected $shop;

    /**
     * Webhooks list.
     *
     * @var array
     */
    protected $webhooks;

    /**
     * Create a new job instance.
     *
     * @param $shop - The shop object
     * @param array $webhooks The webhook list
     *
     * @return void
     */
    public function __construct($shop, array $webhooks)
    {
        $this->shop = $shop;
        $this->webhooks = $webhooks;
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags()
    {
        return [
            'webhooks_installer',
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

        // Get the current webhooks installed on the shop
        $api = $this->shop->api();

        $shopWebhooks = $api->rest(
            'GET',
            '/webhooks.json',
            ['limit' => 250, 'fields' => 'id,address']
        )->body->webhooks;

        $installedWebhooks = [];

        foreach ($shopWebhooks as $shopWebhook) {
            $installedWebhooks[$shopWebhook->address] = $shopWebhook->id;
        }

        $deleteWebhooks = $installedWebhooks;
        foreach ($this->webhooks as $webhook) {
            // Check if the required webhook exists on the shop
            if(! isset($installedWebhooks[$webhook['address']])){
                $api->rest('POST', '/webhooks.json', [
                    'webhook' => array_only($webhook, [
                        'topic',
                        'address',
                    ]),
                ]);

                $created[] = $webhook;
            }

            if(isset($installedWebhooks[$webhook['address']])){
                unset($deleteWebhooks[$webhook['address']]);
            }
        }

        if($deleteWebhooks){
            foreach ($deleteWebhooks as $deleteWebhook){
                $api->rest('DELETE', '/webhooks/' . $deleteWebhook . '.json', []);
            }
        }

        return $created;
    }

    /**
     * Check if webhook is in the list.
     *
     * @param array $shopWebhooks The webhooks installed on the shop
     * @param array $webhook The webhook
     *
     * @return bool
     */
    protected function webhookExists(array $shopWebhooks, array $webhook)
    {
        foreach ($shopWebhooks as $shopWebhook) {
            if ($shopWebhook->address === $webhook['address']) {
                // Found the webhook in our list
                return true;
            }
        }

        return false;
    }
}
