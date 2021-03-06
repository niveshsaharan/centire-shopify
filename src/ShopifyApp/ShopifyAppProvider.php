<?php

namespace Centire\ShopifyApp;

use Illuminate\Support\ServiceProvider;

class ShopifyAppProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Routes
        $this->loadRoutesFrom(__DIR__ . '/resources/routes.php');

        // Views
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'shopify-app');

        // Config publish
        $this->publishes([
            __DIR__ . '/resources/config/shopify.php' => config_path('shopify.php'),
        ]);

        // Database migrations
        $this->loadMigrationsFrom(__DIR__ . '/resources/database/migrations');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        // Merge options with published config
        $this->mergeConfigFrom(__DIR__ . '/resources/config/shopify.php', 'shopify');

        // ShopifyApp facade
        $this->app->bind('shopifyapp', function ($app) {
            return new ShopifyApp($app);
        });

        // Commands
        $this->commands([
            \Centire\ShopifyApp\Console\WebhookJobMakeCommand::class,
        ]);

        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('ShopifyApp', '\Centire\ShopifyApp\Facades\ShopifyApp');

        // Shop auth via session
        \Auth::extend('shop.web', function ($app, $name, array $config) {
            return new \Centire\ShopifyApp\Auth\ShopifyWebGuard(
                $name,
                \Auth::createUserProvider($config['provider']),
                $app->make('request'),
                $app->session
            );
        });
    }
}
