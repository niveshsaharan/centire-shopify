<?php

namespace Centire\ShopifyApp\Test\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Centire\ShopifyApp\Middleware\AuthWebhook;
use Tests\TestCase;

//require_once __DIR__ . '/../../Stubs/OrdersCreateJobStub.php';

class AuthWebhookMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function denys_for_missing_shop_headers()
    {
        request()->header('x-shopify-hmac-sha256', '1234');

        try {
            (new AuthWebhook())->handle(request(), function ($request) {
                // ...
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals('Invalid webhook signature', $e->getMessage());

            return;
        }

        $this->fail('Test passed with invalid webhook signature');
    }

    /** @test */
    public function denys_for_missing_hmac_headers()
    {
        try {
            request()->header('x-shopify-shop-domain', 'example.myshopify.com');
            (new AuthWebhook())->handle(request(), function ($request) {
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals('Invalid webhook signature', $e->getMessage());

            return;
        }

        $this->fail('Test passed with missing hmac signature');
    }

    private function hmac($data)
    {
        return  base64_encode(hash_hmac('sha256', $data, config('shopify.api_secret'), true));
    }

    /** @test */
    public function passes_middleware_with_valid_details()
    {
        Queue::fake();
        $data = file_get_contents(__DIR__ . '/../Fixtures/webhook.json');

        $response = $this->call(
            'post',
            '/webhook/shop-update',
            [],
            [],
            [],
            [
                'HTTP_CONTENT_TYPE'          => 'application/json',
                'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'example.myshopify.com',
                'HTTP_X_SHOPIFY_HMAC_SHA256' => $this->hmac($data), // Matches fixture data and API secret
            ],
            $data
        );
        $response->assertStatus(201);
    }

    /** @test */
    public function denys_for_invalid_hmac_headers()
    {
        Queue::fake();

        $response = $this->call(
            'post',
            '/webhook/orders-create',
            [],
            [],
            [],
            [
                'HTTP_CONTENT_TYPE'          => 'application/json',
                'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'example.myshopify.com',
                'HTTP_X_SHOPIFY_HMAC_SHA256' => 'hDJhTqHOY7d5WRlbDl4ehGm/t4kOQKtR+5w6wm+LBQw=', // Matches fixture data and API secret
            ],
            file_get_contents(__DIR__ . '/../Fixtures/webhook.json') . 'invalid'
        );
        $response->assertStatus(401);
    }
}
