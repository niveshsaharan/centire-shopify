<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Partners URL
    |--------------------------------------------------------------------------
    |
    | Shopify partners URL
    |
    */
    'partner_url' => env('SHOPIFY_PARTNER_URL'),

    /*
    |--------------------------------------------------------------------------
    | Shop Model
    |--------------------------------------------------------------------------
    |
    | This option is for overriding the shop model with your own.
    |
    */

    'shop_model' => env('SHOPIFY_SHOP_MODEL', '\App\Shop'),

    /*
    |--------------------------------------------------------------------------
    | ESDK Mode
    |--------------------------------------------------------------------------
    |
    | ESDK (embedded apps) are enabled by default. Set to false to use legacy
    | mode and host the app inside your own container.
    |
    */

    'easdk_enabled' => (bool)env('SHOPIFY_EASDK_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Shopify App Name
    |--------------------------------------------------------------------------
    |
    | This option simply lets you display your app's name.
    |
    */

    'app_name' => env('SHOPIFY_APP_NAME', 'Shopify App'),

    /*
    |--------------------------------------------------------------------------
    | Shopify App Slug
    |--------------------------------------------------------------------------
    |
    | This option simply lets you display your app's slug.
    |
    */

    'app_slug' => env('SHOPIFY_APP_SLUG', ''),

    /*
    |--------------------------------------------------------------------------
    | Shopify API Key
    |--------------------------------------------------------------------------
    |
    | This option is for the app's API key.
    |
    */

    'api_key' => env('SHOPIFY_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Shopify API Secret
    |--------------------------------------------------------------------------
    |
    | This option is for the app's API secret.
    |
    */

    'api_secret' => env('SHOPIFY_API_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Shopify API Scopes
    |--------------------------------------------------------------------------
    |
    | This option is for the scopes your application needs in the API.
    |
    */

    'api_scopes' => env('SHOPIFY_API_SCOPES', 'read_products,write_products'),

    /*
    |--------------------------------------------------------------------------
    | Shopify API Redirect
    |--------------------------------------------------------------------------
    |
    | This option is for the redirect after authentication.
    |
    */

    'api_redirect' => env('SHOPIFY_API_REDIRECT', '/auth'),

    /*
    |--------------------------------------------------------------------------
    | Shopify API class
    |--------------------------------------------------------------------------
    |
    | This option option allows you to change out the default API class
    | which is OhMyBrew\BasicShopifyAPI. This option is mainly used for
    | testing and does not need to be changed unless required.
    |
    */

    'api_class' => env('SHOPIFY_API_CLASS', \Centire\ShopifyApp\BasicShopifyAPI::class),

    /*
    |--------------------------------------------------------------------------
    | Shopify "MyShopify" domain
    |--------------------------------------------------------------------------
    |
    | The internal URL used by shops. This will not change but in the future
    | it may.
    |
    */

    'myshopify_domain' => 'myshopify.com',

    /*
    |--------------------------------------------------------------------------
    | Enable Billing
    |--------------------------------------------------------------------------
    |
    | Enable billing component to the package.
    |
    */

    'billing_enabled' => (bool)env('SHOPIFY_BILLING_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Billing Redirect
    |--------------------------------------------------------------------------
    |
    | Required redirection URL for billing when
    | a customer accepts or declines the charge presented.
    |
    */

    'billing_redirect' => env('SHOPIFY_BILLING_REDIRECT', '/billing/process'),

    /*
    |--------------------------------------------------------------------------
    | Shopify Webhooks
    |--------------------------------------------------------------------------
    |
    | This option is for defining webhooks.
    | Key is for the Shopify webhook event
    | Value is for the endpoint to call
    |
    */

    'webhooks' => [
        /*[
            'topic' => 'app/uninstalled',
            'address' => str_replace('http://', 'https://', env('APP_URL') . '/webhook/app-uninstalled')
        ],
        [
            'topic' => 'shop/update',
            'address' => str_replace('http://', 'https://', env('APP_URL') . '/webhook/shop-update')
        ],*/
    ],

    /*
    |--------------------------------------------------------------------------
    | Shopify ScriptTags
    |--------------------------------------------------------------------------
    |
    | This option is for defining scripttags.
    |
    */

    'script_tags' => [
        /*[
            'src' => str_replace('http://', 'https://', env('APP_URL') . '/js/app.js'),
            'event' => 'onload',
            'display_scope' => 'online_store'
        ],*/
    ],

    /*
    |--------------------------------------------------------------------------
    | After Authenticate Job
    |--------------------------------------------------------------------------
    |
    | This option is for firing a job after a shop has been authenticated.
    | This, like webhooks and scripttag jobs, will fire every time a shop
    | authenticates, not just once.
    |
    */

    'after_authenticate_job' => [
        'jobs' => [
            // \App\Jobs\JobClass::class => 'queueName'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Impersonate Key
    |--------------------------------------------------------------------------
    |
    */
    'impersonate_key' => env('SHOPIFY_IMPERSONATE_KEY'),
];
