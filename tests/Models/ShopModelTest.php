<?php

namespace Centire\ShopifyApp\Test\Models;

use App\Charge;
use App\Plan;
use App\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopModelTest extends TestCase
{
	use RefreshDatabase;

	/**
	 * @var $shop Shop
	 */
	protected $shop;

	/**
	 * Setup test
	 * @return void
	 */
	public function setUp()
	{
		parent::setUp();

		$this->shop = factory(Shop::class)->create([
			'shopify_domain' => 'example.myshopify.com',
			'shopify_token'  => 'my-valid-token',
			'status'         => 1,
		]);
	}

	/** @test */
	public function shop_returns_api()
	{
		// First run should store the api object to api var
		$run1 = $this->shop->api();

		// Second run should retrive api var
		$run2 = $this->shop->api();

		$this->assertEquals($run1, $run2);
	}

	/**
	 * @test
	 */
	public function shop_should_not_save_without_domain()
	{
		try {
			$shop = new Shop();
			$shop->shopify_token = '1234';
			$shop->save();
		} catch (\Illuminate\Database\QueryException $e) {
			$this->assertTrue(true);

			return;
		}

		$this->fail('Shop saved without shopify domain');
	}

	/** @test */
	public function shop_should_save_and_allow_for_mass_assignment()
	{
		$shop = new Shop();
		$shop->shopify_domain = 'first.myshopify.com';
		$shop->shopify_token = '1234';
		$shop->shopify_id = 3432432;
		$shop->name = 'shop name';
		$shop->email = 'someemail@example.com';
		$shop->customer_email = 'customer@shop.com' ;
		$shop->shop_owner = 'owner';
		$shop->domain = 'domain.com';
		$shop->primary_locale = 'en';
		$shop->address_1 = 'address1';
		$shop->city = 'city';
		$shop->phone = 'phone';
		$shop->province = 'province';
		$shop->province_code = 'province_code';
		$shop->country = 'country';
		$shop->country_name = 'country_name';
		$shop->country_code = 'country_code';
		$shop->zip = 'zip';
		$shop->latitude = 'latitude';
		$shop->longitude = 'longitude';
		$shop->currency = 'currency' ;
		$shop->money_format = 'money_format';
		$shop->money_with_currency_format = 'money_with_currency_format';
		$shop->timezone = 'timezone';
		$shop->iana_timezone = 'iana_timezone';
		$shop->shopify_plan_name = 'plan_name';
		$shop->shopify_plan_display_name = 'plan_display_name';
		$shop->status = 1;
		$shop->save();

		$shop2 = Shop::create(
			[
			'shopify_domain'            => 'hello.myshopify.com',
			'shopify_token'             => '1234',
			'shopify_id'                => 23423,
			'analytics_id'              => 12,
			'name'                      => 'shop name',
			'email'                     => 'someemail@example.com',
			'customer_email'            => 'customer@shop.com',
			'shop_owner'                => 'owner',
			'domain'                    => 'domain.com',
			'primary_locale'            => 'en',
			'address_1'                 => 'address1',
			'city'                      => 'city',
			'phone'                     => 'phone',
			'province'                  => 'province',
			'province_code'             => 'province_code',
			'country'                   => 'country',
			'country_name'              => 'country_name',
			'country_code'              => 'country_code',
			'zip'                       => 'zip',
			'latitude'                  => 'latitude',
			'longitude'                 => 'longitude',
			'currency'                  => 'currency',
			'money_format'              => 'money_format',
			'money_with_currency_format'=> 'money_with_currency_format',
			'timezone'                  => 'timezone',
			'iana_timezone'             => 'iana_timezone',
			'shopify_plan_name'         => 'plan_name',
			'shopify_plan_display_name' => 'plan_display_name',
			'status'                    => 1,
			]
		);

		$this->assertEquals('first.myshopify.com', $shop->shopify_domain);
	}

	/** @test */
	public function shop_should_return_grandfathered_state()
	{
		$shop = factory(Shop::class)->create([
			'grandfathered'         => 1,
		]);

		$shop2 = factory(Shop::class)->create([
			'grandfathered'         => 0,
		]);

		$this->assertEquals(true, $shop->isGrandfathered());
		$this->assertEquals(false, $shop2->isGrandfathered());
	}

	/** @test */
	public function shop_can_be_soft_deleted_and_can_be_restored()
	{
		$this->shop->delete();

		// Test soft delete
		$this->assertTrue($this->shop->trashed());
		$this->assertSoftDeleted('shops', [
			'id'             => $this->shop->id,
			'shopify_domain' => $this->shop->shopify_domain,
		]);

		// Test restore
		$this->shop->restore();
		$this->assertFalse($this->shop->trashed());
	}

	/** @test */
	public function should_return_bool_for_has_charges()
	{
		$shop = factory(Shop::class)->create();

		$shop2 = factory(Shop::class)->create();

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

		$this->assertEquals(true, $shop->hasCharges());
		$this->assertEquals(false, $shop2->hasCharges());
	}
}
