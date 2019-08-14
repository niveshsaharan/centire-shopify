<?php

namespace Centire\ShopifyApp\Services;

use Centire\ShopifyApp\BasicShopifyAPI;
use Illuminate\Support\Facades\Config;
use App\Shop;

/**
 * Responsible for managing webhooks.
 */
class WebhookManager
{
    /**
     * The shop.
     *
     * @var Shop
     */
    protected $shop;

    /**
     * The shop API.
     *
     * @var BasicShopifyAPI
     */
    protected $api;

    /**
     * Cached shop webhooks result.
     *
     * @var array
     */
    protected $shopWebhooks;

    /**
     * Create a new instance.
     *
     * @param object $shop The shop object
     *
     * @return void
     */
    public function __construct($shop)
    {
        $this->shop = $shop;
        $this->api = $this->shop->api();
    }

    /**
     * Gets the webhooks present in the shop.
     *
     * @return array
     */
    public function shopWebhooks()
    {
        if (!$this->shopWebhooks) {
            $this->shopWebhooks = $this->api->graph(<<<Query
query
{
    webhookSubscriptions(first: 250) 
    {
        edges 
        {
            node 
            {
                id
                callbackUrl
            }
        }
    }
}
Query
            )->body->webhookSubscriptions->edges;
        }

        return $this->shopWebhooks;
    }

    /**
     * Gets the webhooks present in the configuration.
     *
     * @return array
     */
    public function configWebhooks()
    {
        return config('shopify.webhooks');
    }

    /**
     * Check if webhook is in the shop (by address).
     *
     * @param array $webhook The webhook
     *
     * @return bool
     */
    public function webhookExists(array $webhook)
    {
        $shopWebhooks = $this->shopWebhooks();
        foreach ($shopWebhooks as $shopWebhook) {
            if ($shopWebhook->node->callbackUrl === $webhook['address']) {
                // Found the webhook in our list
                return true;
            }
        }
        return false;
    }

    /**
     * Creates webhooks (if they do not exist).
     *
     * @return array
     */
    public function createWebhooks()
    {
        $configWebhooks = $this->configWebhooks();

        // Create if it does not exist
        $created = [];

        $mutation = [];
        $mutationArgs = [];
        $mutationVariables = [];
        foreach ($configWebhooks as $i => $webhook) {
            // Check if the required webhook exists on the shop
            if (!$this->webhookExists($webhook)) {
                $mutationArgs[] = '$topic_' . $i . ': WebhookSubscriptionTopic!';
                $mutationArgs[] = '$webhookSubscription_' . $i . ': WebhookSubscriptionInput!';
                $mutation['WEBHOOK_' . $i] = '
WEBHOOK_' . $i . ': webhookSubscriptionCreate(topic: $topic_' . $i . ', webhookSubscription: $webhookSubscription_' . $i . ') 
{
    userErrors 
    {
        field
        message
    }
    webhookSubscription 
    {
        id
        callbackUrl
    }
}';

                $mutationVariables['topic_' . $i] = strtoupper(str_replace('/', '_', $webhook['topic']));
                $mutationVariables['webhookSubscription_' . $i] = [
                    'callbackUrl' => $webhook['address'],
                ];
            }
        }

        if ($mutation) {
            $webhooks = $this->api->graph('mutation webhookSubscriptionCreate(' . implode(',', $mutationArgs) . '){' . implode('', $mutation) . '}', $mutationVariables)->body;
        }

        foreach ($mutation as $key => $value) {
            if (isset($webhooks->$key) && $webhooks->$key->webhookSubscription->id) {
                $created[] = $webhooks->$key->webhookSubscription;
            }
        }

        return $created;
    }

    /**
     * Deletes webhooks in the shop tied to the app.
     *
     * @return array
     */
    public function deleteWebhooks()
    {
        $shopWebhooks = $this->shopWebhooks();
        $deleted = [];
        $mutation = [];
        $mutationArgs = [];
        $mutationVariables = [];

        foreach ($shopWebhooks as $i => $shopWebhook) {
            $mutationArgs[] = '$id_' . $i . ': ID!';
            $mutation['WEBHOOK_' . $i] = '
WEBHOOK_' . $i . ': webhookSubscriptionDelete(id: $id_' . $i . ') 
{
    deletedWebhookSubscriptionId
    userErrors {
        field
        message
    }
}';
            $mutationVariables['id_' . $i] = $shopWebhook->node->id;
        }

        if ($mutation) {
            $response = $this->api->graph('mutation webhookSubscriptionDelete(' . implode(',', $mutationArgs) . '){' . implode('', $mutation) . '}', $mutationVariables)->body;

            foreach ($mutation as $key => $value) {
                if (isset($response->$key) && $response->$key->deletedWebhookSubscriptionId) {
                    $deleted[] = $response->$key->deletedWebhookSubscriptionId;
                }
            }
        }

        // Reset
        $this->shopWebhooks = null;
        return $deleted;
    }

    /**
     * Recreates the webhooks.
     *
     * @return void
     */
    public function recreateWebhooks()
    {
        $this->deleteWebhooks();
        $this->createWebhooks();
    }
}