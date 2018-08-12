<?php

namespace Centire\ShopifyApp\Test\Models;

use App\Charge;
use App\Plan;
use App\Shop;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargeModelTest extends TestCase
{
	use RefreshDatabase;

	/**
	 * @var Shop $shop
	 */
	protected $shop;

	/**
	 * @var Charge $charge
	 */
	protected $charge;

	/**
	 * @var Plan $plan
	 */
	protected $plan;

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

		$this->plan = factory(Plan::class)->create([
			'name'         => 'my plan',
			'duration'     => 30,
			'trial_days'   => 14,
			'price'        => 2000,
			'plan_type'    => 'recurring',
		]);

		$this->charge = $this->createCharge();
	}

	protected function createCharge($data = [])
	{
		return factory(Charge::class)->create(array_merge([
			'type'          => Charge::CHARGE_RECURRING,
			'charge_id'     => rand(1000, 5000000),
			'shop_id'       => $this->shop->id,
			'plan_id'       => $this->plan->id,
			'status'        => 'active',
			'trial_days'    => 14,
		], $data));
	}

	/** @test */
	public function belongs_to_shop()
	{
		$this->assertInstanceOf(
			Shop::class,
			$this->charge->shop
		);
	}

	/** @test */
	public function charge_implements_type()
	{
		$this->assertEquals(
			Charge::CHARGE_RECURRING,
			$this->charge->type
		);
	}

	/** @test */
	public function is_test()
	{
		$charge = $this->createCharge([
			'test' => true,
		]);

		$this->assertEquals(true, $charge->isTest());
	}

	/** @test */
	public function is_type()
	{
		$this->assertTrue($this->charge->isType(Charge::CHARGE_RECURRING));
	}

	/** @test */
	public function is_trial()
	{
		$charge = $this->createCharge([
			'trial_ends_on' => null,
		]);

		$charge2 = $this->createCharge([
			'trial_days'    => 10,
			'trial_ends_on' => Carbon::today()->addDay(5),
		]);

		$this->assertFalse($charge->isTrial());
		$this->assertTrue($charge2->isTrial());
	}

	/** @test */
	public function is_active_trial()
	{
		$charge = $this->createCharge([
			'trial_ends_on' => Carbon::today()->addDay(3),
		]);

		$charge2 = $this->createCharge([
			'trial_ends_on' => Carbon::today()->subDay(3),
		]);

		$this->assertTrue($charge->isActiveTrial());
		$this->assertFalse($charge2->isActiveTrial());
	}

	/** @test */
	public function remaining_trial_days()
	{
		$charge = $this->createCharge([
			'trial_days'   => 3,
		'trial_ends_on' => Carbon::today()->addDay(3),
	]);

		$charge2 = $this->createCharge([
			'trial_days'    => 3,
			'trial_ends_on' => Carbon::today()->subDay(3),
		]);

		$this->assertEquals(3, $charge->remainingTrialDays());
		$this->assertEquals(0, $charge2->remainingTrialDays());
	}

	/** @test */
	public function used_trial_days()
	{
		$charge = $this->createCharge([
			'trial_days'    => 10,
			'trial_ends_on' => Carbon::today()->addDay(3),
		]);

		$charge2 = $this->createCharge([
			'trial_days'    => 10,
			'trial_ends_on' => Carbon::today()->addDay(5),
		]);

		$this->assertEquals(7, $charge->usedTrialDays());
		$this->assertEquals(5, $charge2->usedTrialDays());
	}

	/** @test */
	public function accepted_and_declined()
	{
		$charge = $this->createCharge([
			'status' => 'accepted',
		]);

		$this->assertTrue($charge->isAccepted());
		$this->assertFalse($charge->isDeclined());
	}

	/** @test */
	public function active()
	{
		$charge2 = $this->createCharge([
			'trial_days'    => 10,
			'trial_ends_on' => Carbon::today()->addDay(5),
			'status'        => 'cancelled',
		]);

		$this->assertTrue($this->charge->isActive());
		$this->assertFalse($charge2->isActive());
	}

	/** @test */
	public function ongoing()
	{
		$charge2 = $this->createCharge([
			'trial_days'    => 10,
			'trial_ends_on' => Carbon::today()->addDay(5),
			'status'        => 'cancelled',
		]);

		$charge3 = $this->createCharge([
			'trial_days'    => 10,
			'trial_ends_on' => Carbon::today()->addDay(5),
			'status'        => 'active',
		]);

		$this->assertFalse($charge2->isOngoing());
		$this->assertTrue($charge3->isOngoing());
	}

	/** @test */
	public function cancelled()
	{
		$charge2 = $this->createCharge([
			'trial_days'    => 10,
			'trial_ends_on' => Carbon::today()->addDay(5),
			'status'        => 'cancelled',
		]);

		$charge3 = $this->createCharge([
			'trial_days'    => 10,
			'trial_ends_on' => Carbon::today()->addDay(5),
			'status'        => 'active',
		]);

		$this->assertFalse($charge3->isCancelled());
		$this->assertTrue($charge2->isCancelled());
	}

	/** @test */
	public function remaining_trial_days_from_cancel()
	{
		$charge2 = $this->createCharge([
			'trial_days'    => 10,
			'trial_ends_on' => Carbon::today(),
			'status'        => 'cancelled',
		]);

		$charge3 = $this->createCharge([
			'trial_days'    => 10,
			'trial_ends_on' => Carbon::today()->addDay(6),
			'status'        => 'cancelled',
		]);

		$this->assertEquals(5, $charge3->remainingTrialDaysFromCancel());
		$this->assertEquals(0, $charge2->remainingTrialDaysFromCancel());
	}
}
