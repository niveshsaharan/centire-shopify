<?php

namespace Centire\ShopifyApp\Test\Middleware;

use App\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Input;
use Centire\ShopifyApp\Middleware\AuthShop;
use Tests\TestCase;

class AuthShopMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function shop_has_no_access_should_abort()
    {
        $called = false;
        $result = (new AuthShop())->handle(request(), function ($request) use (&$called) {
            // Should never be called
            $called = true;
        });

        $this->assertFalse($called);
        $this->assertEquals(true, strpos($result, 'Redirecting to ' . config('app.url') . '/auth') !== false);
    }

    /** @test */
    public function shop_has_access_should_pass_middleware()
    {
        $shop = factory(Shop::class)->create([
            'shopify_domain' => 'example.myshopify.com',
            'shopify_token'  => 'my-valid-token',
            'status'         => 1,
        ]);

        $this->actingAs($shop, 'shop.web');
        $called = false;

        (new AuthShop())->handle(request(), function ($request) use (&$called) {
            // Should be called
            $called = true;
        });

        $this->assertEquals(true, $called);
    }

    /** @test */
    public function shop_without_shopify_token_should_not_pass()
    {
        $shop = factory(Shop::class)->create([
            'shopify_domain' => 'example.myshopify.com',
            'shopify_token'  => null,
            'status'         => 1,
        ]);

        $this->actingAs($shop, 'shop.web');

        $called = false;
        $result = (new AuthShop())->handle(request(), function ($request) use (&$called) {
            // Shouldn never be called
            $called = true;
        });

        $this->assertFalse($called);
        $this->assertEquals(true, strpos($result, 'Redirecting to ' . config('app.url') . '/auth') !== false);
    }

    /** @test */
    public function trashed_shop_should_not_pass()
    {
        $shop = factory(Shop::class)->create([
            'shopify_domain' => 'example.myshopify.com',
            'shopify_token'  => 'some-token',
            'status'         => 1,
        ]);

        $shop->delete();

        $this->actingAs($shop, 'shop.web');

        $called = false;
        $result = (new AuthShop())->handle(request(), function ($request) use (&$called) {
            // Shouldn never be called
            $called = true;
        });

        $this->assertFalse($called);
        $this->assertEquals(true, strpos($result, 'Redirecting to ' . config('app.url') . '/auth') !== false);
    }

    /** @test */
    public function shop_which_do_not_match_shop_param_should_logout_and_re_authenticate()
    {
        $shop = factory(Shop::class)->create([
            'shopify_domain' => 'example.myshopify.com',
            'shopify_token'  => 'some-token',
            'status'         => 1,
        ]);

        $this->actingAs($shop, 'shop.web');

        Input::merge(['shop' => 'example-different-shop.myshopify.com']);

        $called = false;
        (new AuthShop())->handle(request(), function ($request) use (&$called) {
            // Should never be called
            $called = true;
        });

        $this->assertFalse($called);
        $this->assertEquals('example-different-shop.myshopify.com', request('shop'));
        $this->assertFalse($this->isAuthenticated('shop.web'));
    }

    /** @test */
    public function headers_for_easdk_enabled_should_be_adjusted()
    {
        $shop = factory(Shop::class)->create([
            'shopify_domain' => 'example.myshopify.com',
            'shopify_token'  => 'some-token',
            'status'         => 1,
        ]);

        // Set a shop
        $this->actingAs($shop, 'shop.web');

        config(['shopify.easdk_enabled' => true]);
        $response = (new AuthShop())->handle(
            request(),
            function ($request) use (&$called) {
                // Nothing to do here...
            }
        );

        $this->assertEquals('CP="Not used"', $response->headers->get('p3p'));
        $this->assertNull($response->headers->get('x-frame-options'));

        config(['shopify.easdk_enabled' => false]);
        $response = (new AuthShop())->handle(
            request(),
            function ($request) use (&$called) {
                // Nothing to do here...
            }
        );

        $this->assertNotEquals('CP="Not used"', $response->headers->get('p3p'));
    }

    /** @test */
    public function headers_for_easdk_disabled_should_be_adjusted()
    {
        $shop = factory(Shop::class)->create([
            'shopify_domain' => 'example.myshopify.com',
            'shopify_token'  => 'some-token',
            'status'         => 1,
        ]);

        // Set a shop
        $this->actingAs($shop, 'shop.web');

        config(['shopify.easdk_enabled' => false]);

        $response = (new AuthShop())->handle(
            request(),
            function ($request) use (&$called) {
                // Nothing to do here...
            }
        );

        $this->assertNull($response->headers->get('p3p'));
        $this->assertNull($response->headers->get('x-frame-options'));
    }
}
