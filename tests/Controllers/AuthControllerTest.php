<?php

namespace Centire\ShopifyApp\Test\Controllers;

use App\Charge;
use App\Plan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Centire\ShopifyApp\Controllers\AuthController;
use Centire\ShopifyApp\Jobs\ScriptTagsInstaller;
use Centire\ShopifyApp\Jobs\WebhooksInstaller;
use App\Shop;
use Centire\ShopifyApp\Test\Stubs\ApiStub;
use Tests\TestCase;
use ReflectionMethod;

require_once __DIR__ . '/../Stubs/AfterAuthenticateJobStub.php';

class AuthControllerTest extends TestCase
{
	use RefreshDatabase;

	/**
	 * Normal shop params
	 * @var $hmacParams
	 */
	protected $hmacParams;

	/**
	 * Trashed shop params
	 * @var
	 */
	protected $hmacTrashedParams;

	/**
	 * Setup test
	 * @return void
	 */
	public function setUp()
	{
		parent::setUp();

		// Normal shop
		$this->hmacParams = [
			'code'      => '1234678',
			'shop'      => 'example.myshopify.com',
			'timestamp' => '1337178173',
		];

		$this->hmacParams['hmac'] = hash_hmac('sha256', urldecode(http_build_query($this->hmacParams)), config('shopify.api_secret'));

		// Trashed shop
		$this->hmacTrashedParams = [
			'code'      => '1234678910',
			'shop'      => 'trashed-shop.myshopify.com',
			'timestamp' => '1337178173',
		];

		$this->hmacTrashedParams['hmac'] = hash_hmac('sha256', urldecode(http_build_query($this->hmacTrashedParams)), config('shopify.api_secret'));

		// Stub in our API class
		config(['shopify.api_class' => new ApiStub()]);
	}

	/** @test */
	public function login_page_is_correct()
	{
		$response = $this->get('/login');
		$response->assertStatus(200)
			->assertViewIs('shopify-app::auth.index')
			->assertSeeText('Enter your shop domain to log in or install this app.');
	}

	/**
	 * @test
	 */
	public function auth_page_is_correct()
	{
		$response = $this->get('/auth');

		$response->assertStatus(200)
			->assertViewIs('shopify-app::auth.index')
			->assertSee('Enter your shop domain to log in or install this app.');
	}

	/**
	 * @test
	 */
	public function current_page_is_auth_when_no_shop()
	{
		$response = $this->post('/auth', [
			'shop' => '',
		]);

		$response->assertStatus(200)
			->assertViewIs('shopify-app::auth.index')
			->assertSee('Enter your shop domain to log in or install this app.');
	}

	/**
	 * @test
	 */
	public function session_has_shop_domain_while_authenticating()
	{
		$response = $this->post('/auth', [
			'shop' => 'example.myshopify.com',
		]);

		$response->assertSessionHas('shopify_domain', 'example.myshopify.com')
			->assertSessionHas('impersonate', 0);
	}

	/** @test */
	public function no_code_in_url_redirects_to_shopify_auth_screen()
	{
		$response = $this->post('/auth', ['shop' => 'example.myshopify.com']);
		$response->assertSessionHas('shopify_domain', 'example.myshopify.com')
			->assertViewIs('shopify-app::auth.fullpage_redirect')
			->assertViewHas('shopDomain', 'example.myshopify.com')
			->assertViewHas(
			'authUrl',
			'https://example.myshopify.com/admin/oauth/authorize?client_id=' . config('shopify.api_key') . '&scope=' . config('shopify.api_scopes') . '&redirect_uri=' . config('app.url') . '/auth'
		);
	}

	/** @test */
	public function valid_auth_updates_shopify_token_and_logs_in()
	{
		$response = $this->call('get', '/auth', $this->hmacParams);

		$response->assertSessionHas('shopify_domain', $this->hmacParams['shop']);

		$shop = Shop::whereShopifyDomain($this->hmacParams['shop'])->first();

		$this->assertNotNull($shop);
		$this->assertNotNull($shop->shopify_token);
		$this->assertNotNull($shop->api_token);
		$this->assertEquals($shop->shopify_scopes, array_filter(array_map('trim', explode(',', config('shopify.api_scopes')))));
		$this->assertAuthenticated('shop.web')
			->assertAuthenticatedAs($shop, 'shop.web');
		$this->assertEquals('12345678', $shop->shopify_token);
		$this->assertEquals(1, $shop->status);
	}

	/** @test */
	public function valid_auth_restores_trashed_shop()
	{
		$shop = factory(Shop::class)->create([
			'shopify_domain' => $this->hmacTrashedParams['shop'],
		]);

		// Trash shop
		$shop->delete();

		$response = $this->call('get', '/auth', $this->hmacTrashedParams);

		$shop = $shop->fresh();
		$this->assertFalse($shop->trashed());
	}

	/** @test */
	public function valid_auth_redirects_to_home()
	{
		$shop = factory(Shop::class)->create([
			'shopify_domain' => $this->hmacParams['shop'],
			'api_token'      => 'some-token',
		]);

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

		$response = $this->call('get', '/auth', $this->hmacParams);
		$response->assertStatus(302);
		$this->assertEquals(config('app.url'), $response->headers->get('location'));
	}

	/** @test */
	public function invalid_auth_request_redirects_to_auth_page()
	{
		$params = $this->hmacParams;
		$params['hmac'] = 'some-invalid-code';

		$response = $this->call('get', '/auth', $params);
		$response->assertSessionHas('error', 'Shopify Auth: Cannot verify request.');
		$response->assertStatus(302);
		$this->assertEquals(config('app.url') . '/auth', $response->headers->get('location'));
	}

	/** @test */
	public function no_config_doesnt_fire_install_webhooks_job()
	{
		Queue::fake();

		config(['shopify.webhooks' => []]);

		$this->call('get', '/auth', $this->hmacParams);

		Queue::assertNotPushed(WebhooksInstaller::class);
	}

	/** @test */
	public function no_config_doesnt_fire_install_scripts_job()
	{
		Queue::fake();

		config(['shopify.script_tags' => []]);

		$this->call('get', '/auth', $this->hmacParams);

		Queue::assertNotPushed(ScriptTagsInstaller::class);
	}

	/** @test */
	public function valid_auth_fires_install_webhooks_job()
	{
		Queue::fake();

		config(['shopify.webhooks' => [
			[
				'topic'   => 'orders/create',
				'address' => config('app.url') . '/webhooks/orders-create',
			],
		]]);

		$this->call('get', '/auth', $this->hmacParams);

		Queue::assertPushed(WebhooksInstaller::class);
	}

	/** @test */
	public function valid_auth_fires_install_scripts_job()
	{
		Queue::fake();

		config(['shopify.script_tags' => [
			[
				'src' => config('app.url') . '/js/app.js',
			],
		]]);

		$this->call('get', '/auth', $this->hmacParams);

		Queue::assertPushed(ScriptTagsInstaller::class);
	}

	/** @test */
	public function no_config_doesnt_fire_after_authentication_job()
	{
		Queue::fake();

		$jobClass = \App\Jobs\AfterAuthenticateJob::class;
		config(['shopify.after_authenticate_job' => []]);

		$method = new ReflectionMethod(AuthController::class, 'afterAuthenticateJob');
		$method->setAccessible(true);
		$result = $method->invoke(new AuthController());

		$this->assertEquals(false, $result);
		Queue::assertNotPushed($jobClass);
	}

	/** @test */
	public function inline_after_authentication_job_is_fired_without_queue()
	{
		Queue::fake();

		$jobClass = \App\Jobs\AfterAuthenticateJob::class;
		config(['shopify.after_authenticate_job' => [
			'jobs'    => [
				$jobClass => 'inline',
				],
		]]);

		$method = new ReflectionMethod(AuthController::class, 'afterAuthenticateJob');
		$method->setAccessible(true);
		$result = $method->invoke(new AuthController());

		$this->assertEquals(true, $result);
		Queue::assertNotPushed($jobClass); // since inline == true
	}

	/** @test */
	public function non_inline_after_authentication_job_is_dispatched_to_queue()
	{
		Queue::fake();

		$jobClass = \App\Jobs\AfterAuthenticateJob::class;
		config(['shopify.after_authenticate_job' => [
			'jobs'    => [
				$jobClass => 'first',
			],
		]]);

		$method = new ReflectionMethod(AuthController::class, 'afterAuthenticateJob');
		$method->setAccessible(true);
		$result = $method->invoke(new AuthController());

		$this->assertEquals(true, $result);
		Queue::assertPushed($jobClass); // since inline == false
	}

	/**
	 * @test
	 */
	public function impersonate_with_valid_details()
	{
		$shop = factory(Shop::class)->create([
			'shopify_domain' => $this->hmacParams['shop'],
			'api_token'      => 'some-token',
		]);

		$response = $this->call('GET', '/impersonate', [
			'shop' => $this->hmacParams['shop'],
			'code' => config('shopify.impersonate_key'),
		]);

		$response->assertSessionHas('shopify_domain', $this->hmacParams['shop'])
			->assertSessionHas('impersonate', 1)
		->assertRedirect('/');

		$this->assertAuthenticatedAs($shop, 'shop.web')
		->assertEquals($shop->api_token, $shop->fresh()->api_token);
	}

	/**
	 * @test
	 */
	public function impersonate_with_invalid_details_redirects_to_auth()
	{
		$shop = factory(Shop::class)->create([
			'shopify_domain' => $this->hmacParams['shop'],
			'api_token'      => 'some-token',
		]);

		$response = $this->call('GET', '/impersonate', [
			'shop' => $this->hmacParams['shop'],
			'code' => 'some-code',
		]);

		$response->assertSessionMissing('shopify_domain')
			->assertSessionMissing('impersonate')
		->assertRedirect('/auth');
	}

	/**
	 * @test
	 */
	public function verify_charge_no_charge_redirects_to_billing()
	{
		$shop = factory(Shop::class)->create([
			'shopify_domain' => $this->hmacParams['shop'],
		]);

		$controller = new AuthController();
		$method = new \ReflectionMethod(AuthController::class, 'verifyCharge');
		$method->setAccessible(true);

		$response = $method->invoke($controller, $shop);

		$this->assertEquals(url('/billing'), $response->headers->get('location'));
	}

	/**
	 * @test
	 */
	public function verify_charge_returns_true_for_grandfathered_shops()
	{
		$shop = factory(Shop::class)->create([
			'shopify_domain' => $this->hmacParams['shop'],
			'grandfathered'  => 1,
		]);

		$controller = new AuthController();
		$method = new \ReflectionMethod(AuthController::class, 'verifyCharge');
		$method->setAccessible(true);

		$response = $method->invoke($controller, $shop);

		$this->assertTrue($response);
	}

	/**
	 * @test
	 */
	public function verify_charge_returns_true_if_billing_disabled()
	{
		$shop = factory(Shop::class)->create([
			'shopify_domain' => $this->hmacParams['shop'],
		]);
		config(['shopify.billing_enabled' => false]);
		$controller = new AuthController();
		$method = new \ReflectionMethod(AuthController::class, 'verifyCharge');
		$method->setAccessible(true);

		$response = $method->invoke($controller, $shop);

		$this->assertTrue($response);
	}

	/**
	 * @test
	 */
	public function verify_charge_redirects_to_billing_for_cancelled_charge()
	{
		$shop = factory(Shop::class)->create([
			'shopify_domain' => $this->hmacParams['shop'],
		]);

		$plan = factory(Plan::class)->create([
			'name'         => 'my plan',
			'duration'     => 30,
			'trial_days'   => 14,
			'price'        => 2000,
			'plan_type'    => 'recurring',
		]);

		$charge = factory(Charge::class)->create([
			'shop_id'       => $shop->id,
			'plan_id'       => $plan->id,
			'status'        => 'cancelled',
			'trial_days'    => 14,
			'trial_ends_on' => Carbon::today()->addDays(14)->format('Y-m-d'),
			'cancelled_on'  => Carbon::today()->addDays(3)->format('Y-m-d'),
		]);

		$controller = new AuthController();
		$method = new \ReflectionMethod(AuthController::class, 'verifyCharge');
		$method->setAccessible(true);

		$response = $method->invoke($controller, $shop);

		$this->assertEquals(url('/billing'), $response->headers->get('location'));
	}

	/**
	 * @test
	 */
	public function verify_charge_redirects_to_billing_process_for_accepted_charge()
	{
		$shop = factory(Shop::class)->create([
			'shopify_domain' => $this->hmacParams['shop'],
		]);

		$plan = factory(Plan::class)->create([
			'name'         => 'my plan',
			'duration'     => 30,
			'trial_days'   => 14,
			'price'        => 2000,
			'plan_type'    => 'recurring',
		]);

		$charge = factory(Charge::class)->create([
			'charge_id'     => 1029266947,
			'shop_id'       => $shop->id,
			'plan_id'       => $plan->id,
			'status'        => 'accepted',
			'trial_days'    => 14,
		]);

		$controller = new AuthController();
		$method = new \ReflectionMethod(AuthController::class, 'verifyCharge');
		$method->setAccessible(true);

		$response = $method->invoke($controller, $shop);

		$this->assertEquals(url('/billing/process?charge_id=' . 1029266947), $response->headers->get('location'));
	}

	/**
	 * @test
	 */
	public function verify_charge_returns_true_for_active_charge()
	{
		$shop = factory(Shop::class)->create([
			'shopify_domain' => $this->hmacParams['shop'],
		]);

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

		$controller = new AuthController();
		$method = new \ReflectionMethod(AuthController::class, 'verifyCharge');
		$method->setAccessible(true);

		$response = $method->invoke($controller, $shop);

		$this->assertTrue($response);
	}

	/**
	 * @test
	 */
	public function verify_charge_redirects_to_shopify_charge_confirmation_page_for_pending_charge()
	{
		$shop = factory(Shop::class)->create([
			'shopify_domain' => $this->hmacParams['shop'],
		]);

		$plan = factory(Plan::class)->create([
			'name'         => 'my plan',
			'duration'     => 30,
			'trial_days'   => 14,
			'price'        => 2000,
			'plan_type'    => 'recurring',
		]);

		$charge = factory(Charge::class)->create([
			'type'          => Charge::CHARGE_RECURRING,
			'charge_id'     => 1029266948,
			'shop_id'       => $shop->id,
			'plan_id'       => $plan->id,
			'status'        => 'pending',
			'trial_days'    => 14,
		]);

		$controller = new AuthController();
		$method = new \ReflectionMethod(AuthController::class, 'verifyCharge');
		$method->setAccessible(true);

		$response = $method->invoke($controller, $shop);

		$this->assertEquals('https://example.myshopify.com/admin/charges/1029266948/confirm_recurring_application_charge?signature=BAhpBANeWT0%3D--64de8739eb1e63a8f848382bb757b20343eb414f', $response->headers->get('location'));
	}
}
