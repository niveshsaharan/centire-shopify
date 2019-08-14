<?php

namespace Centire\ShopifyApp\Services;

use Centire\ShopifyApp\BasicShopifyAPI;
use Illuminate\Support\Facades\Config;
use App\Shop;

/**
 * Responsible for managing stronfront tokens.
 */
class StoreFrontAccessTokenManager
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
     * Cached shop tokens result.
     *
     * @var array
     */
    protected $shopTokens;

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
     * Gets the tokens present in the shop.
     *
     * @return array
     */
    public function shopTokens()
    {
        if (!$this->shopTokens) {
            $this->shopTokens = $this->api->graph(<<<Query
query
{
    shop 
    {
        storefrontAccessTokens(first:250)
        {
            edges
            {
                node
                {
                    id
                    accessScopes
                    {
                        handle
                    }
                    accessToken
                }
            }
        }
    }
}
Query
            )->body->shop->storefrontAccessTokens->edges;
        }

        return $this->shopTokens;
    }

    /**
     * Check if token is in the shop (by address).
     *
     * @param array $scopes The scopes
     *
     * @return bool
     */
    public function scopesExists(array $scopes)
    {
        $shopTokens = $this->shopTokens();
        $accessScopes = [];
        foreach ($shopTokens as $shopToken) {
            foreach($shopToken->node->accessScopes as $accessScope){
                $accessScopes[] = $accessScope->handle;
            }
        }

        return collect($scopes)->every(function($scope)use ($accessScopes) {
            return in_array($scope, $accessScopes);
        });
    }

    /**
     * Creates tokens (if they do not exist).
     *
     * @return array
     */
    public function createTokens()
    {
        // Create if it does not exist
        $created = [];

        $scopes = collect(array_filter(array_map('trim', explode(',', config('shopify.api_scopes')))))->filter(function($scope){
            return starts_with($scope, 'unauthenticated_');
        })->toArray();

        if($scopes){
            $mutation = [];
            $mutationArgs = [];
            $mutationVariables = [];

            $i = 0;
            // Check if the required scopes exists in any of the token
            if (!$this->scopesExists($scopes)) {
                $mutationArgs[] = '$input_' . $i . ': StorefrontAccessTokenInput!';
                $mutation['STOREFRONTTOKEN_' . $i] = '
STOREFRONTTOKEN_' . $i . ': storefrontAccessTokenCreate(input: $input_' . $i . ') 
{
    shop
    {
        id
    }
    storefrontAccessToken 
    {
        id
        accessToken
    }
    userErrors 
    {
        field
        message
    }
}';

                $mutationVariables['input_' . $i] = ['title' => config("shopify.app_slug")];
            }

            if ($mutation) {
                $tokens = $this->api->graph('mutation storefrontAccessTokenCreate(' . implode(',', $mutationArgs) . '){' . implode('', $mutation) . '}', $mutationVariables)->body;
            }

            foreach ($mutation as $key => $value) {
                if (isset($tokens->$key) && $tokens->$key->storefrontAccessToken->id) {
                    $created[] = $tokens->$key->storefrontAccessToken;

                    // Update token in shop object
                    $this->shop->update([
                        'shopify_storefront_token' => $tokens->$key->storefrontAccessToken->accessToken
                    ]);
                }
            }
        }


        return $created;
    }

    /**
     * Deletes tokens in the shop tied to the app.
     *
     * @return array
     */
    public function deleteTokens()
    {
        $shopTokens = $this->shopTokens();
        $deleted = [];
        $mutation = [];
        $mutationArgs = [];
        $mutationVariables = [];

        foreach ($shopTokens as $i => $shopToken) {
            $mutationArgs[] = '$input_' . $i . ': StorefrontAccessTokenDeleteInput!';
            $mutation['STOREFRONTTOKEN_' . $i] = '
STOREFRONTTOKEN_' . $i . ': storefrontAccessTokenDelete(input: $input_' . $i . ') 
{
    deletedStorefrontAccessTokenId
    userErrors {
        field
        message
    }
}';
            $mutationVariables['input_' . $i] = ['id' => $shopToken->node->id];
        }

        if ($mutation) {
            $response = $this->api->graph('mutation storefrontAccessTokenDelete(' . implode(',', $mutationArgs) . '){' . implode('', $mutation) . '}', $mutationVariables)->body;

            foreach ($mutation as $key => $value) {
                if (isset($response->$key) && $response->$key->deletedStorefrontAccessTokenId) {
                    $deleted[] = $response->$key->deletedStorefrontAccessTokenId;

                    // Update token in shop object
                    $this->shop->update([
                        'shopify_storefront_token' => null
                    ]);
                }
            }
        }

        // Reset
        $this->shopTokens = null;
        return $deleted;
    }

    /**
     * Recreates the tokens.
     *
     * @return void
     */
    public function recreateTokens()
    {
        $this->deleteTokens();
        $this->createTokens();
    }
}