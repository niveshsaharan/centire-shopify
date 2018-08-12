<?php

namespace Centire\ShopifyApp\Test\Middleware;

use App\Charge;
use App\Plan;
use App\Shop;
use Centire\ShopifyApp\Middleware\Billable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BillableMiddlewareTest extends TestCase
{
	use RefreshDatabase;

	/** @test */
	public function unpaid_shop_redirects_to_billing_page()
	{
		// Enable billing and set a shop
		config(['shopify.billing_enabled' => true]);

		$shop = factory(Shop::class)->create([
			'shopify_domain' => 'example.myshopify.com',
			'shopify_token'  => 'my-valid-token',
			'status'         => 1,
		]);

		$this->actingAs($shop, 'shop.web');

		$called = false;

		try {
			$result = (new Billable())->handle(request(), function ($request) use (&$called) {
				// Should never be called
				$called = true;
			});
		} catch (HttpException $e) {
			$this->assertFalse($called);
			$this->assertEquals(402, $e->getStatusCode());

			return;
		}

		$this->fail('Should throw exception.');
	}

	/** @test */
	public function paid_shop_passes_middleware()
	{
		// Enable billing and set a shop
		config(['shopify.billing_enabled' => true]);

		$shop = factory(Shop::class)->create([
			'shopify_domain' => 'example.myshopify.com',
			'shopify_token'  => 'my-valid-token',
			'status'         => 1,
		]);

		$this->actingAs($shop, 'shop.web');

		$plan = factory(Plan::class)->create([
			'name'         => 'my plan',
			'duration'     => 30,
			'trial_days'   => 14,
			'price'        => 2000,
			'plan_type'    => 'recurring',
		]);

		$charge = factory(Charge::class)->create([
			'type'          => Charge::CHARGE_RECURRING,
			'charge_id'     => 455696195,
			'shop_id'       => $shop->id,
			'plan_id'       => $plan->id,
			'status'        => 'active',
			'trial_days'    => 14,
		]);

		$called = false;
		$result = (new Billable())->handle(request(), function ($request) use (&$called) {
			// Should be called
			$called = true;
		});

		$this->assertTrue($called);
	}

	/** @test */
	public function grandfathered_shop_passes_middleware()
	{
		// Enable billing and set a shop
		config(['shopify.billing_enabled' => true]);

		$shop = factory(Shop::class)->create([
			'shopify_domain' => 'example.myshopify.com',
			'shopify_token'  => 'my-valid-token',
			'status'         => 1,
			'grandfathered'  => 1,
		]);

		$this->actingAs($shop, 'shop.web');

		$called = false;
		$result = (new Billable())->handle(request(), function ($request) use (&$called) {
			// Should be called
			$called = true;
		});

		$this->assertTrue($called);
	}

	/** @test */
	public function disabled_billing_should_pass_middleware()
	{
		// Ensure billing is disabled and set a shop
		config(['shopify.billing_enabled' => false]);

		$shop = factory(Shop::class)->create([
			'shopify_domain' => 'example.myshopify.com',
			'shopify_token'  => 'my-valid-token',
			'status'         => 1,
		]);

		$this->actingAs($shop, 'shop.web');

		$called = false;
		$result = (new Billable())->handle(request(), function ($request) use (&$called) {
			// Should be called
			$called = true;
		});

		$this->assertTrue($called);
	}
}
