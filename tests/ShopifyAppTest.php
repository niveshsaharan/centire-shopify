<?php

namespace Centire\ShopifyApp\Tests;

use App\Shop;
use Centire\ShopifyApp\BasicShopifyAPI;
use Centire\ShopifyApp\ShopifyApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopifyAppTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var ShopifyApp $shopifyApp
     */
    protected $shopifyApp;

    public function setUp()
    {
        parent::setUp();

        $this->shopifyApp = new ShopifyApp($this->app);
    }

    /** @test */
    public function shop_without_session()
    {
        // No session, no API instance, thus no shop
        $this->assertNull($this->shopifyApp->shop());
    }

    /** @test */
    public function shop_with_session()
    {
        $shop = factory(Shop::class)->create([
            'shopify_domain' => 'example.myshopify.com',
        ]);

        $this->actingAs($shop, 'shop.web');

        // First run should store the shop object to shop var
        $run1 = $this->shopifyApp->shop();

        // Second run should retrive shop var
        $run2 = $this->shopifyApp->shop();

        $this->assertEquals($run1, $run2);
    }

    /** @test */
    public function creates_new_shop_with_session_if_it_does_not_exist()
    {
        $this->assertEquals(null, Shop::where('shopify_domain', 'example-nonexistant.myshopify.com')->first());

        $this->shopifyApp->firstOrCreate('example-nonexistant.myshopify.com');

        $this->assertNotNull(Shop::where('shopify_domain', 'example-nonexistant.myshopify.com')->first());
    }

    /** @test */
    public function returns_api_instance()
    {
        $this->assertEquals(BasicShopifyAPI::class, get_class($this->shopifyApp->api()));
    }

    /** @test */
    public function shop_sanitize()
    {
        $domains = ['my-shop', 'my-shop.myshopify.com', 'https://my-shop.myshopify.com', 'http://my-shop.myshopify.com'];
        $domains_2 = ['my-shop', 'my-shop.myshopify.io', 'https://my-shop.myshopify.io', 'http://my-shop.myshopify.io'];
        $domains_3 = ['', false, null];

        // Test for standard myshopify.com
        foreach ($domains as $domain) {
            $this->assertEquals('my-shop.myshopify.com', $this->shopifyApp->sanitizeShopDomain($domain));
        }

        // Test if someone changed the domain
        config(['shopify.myshopify_domain' => 'myshopify.io']);
        foreach ($domains_2 as $domain) {
            $this->assertEquals('my-shop.myshopify.io', $this->shopifyApp->sanitizeShopDomain($domain));
        }

        // Test for empty shops
        foreach ($domains_3 as $domain) {
            $this->assertEquals(null, $this->shopifyApp->sanitizeShopDomain($domain));
        }
    }

    /** @test */
    public function should_use_default_model()
    {
        $shop = factory(Shop::class)->create([
            'shopify_domain' => 'example.myshopify.com',
        ]);

        $this->actingAs($shop, 'shop.web');

        $shop = $this->shopifyApp->shop();
        $this->assertEquals('App\Shop', get_class($shop));
    }
}
