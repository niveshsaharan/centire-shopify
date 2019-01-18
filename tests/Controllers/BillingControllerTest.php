<?php

namespace Centire\ShopifyApp\Test\Controllers;

use App\Charge;
use App\Discount;
use App\Plan;
use App\Shop;
use Carbon\Carbon;
use Centire\ShopifyApp\Controllers\BillingController;
use Centire\ShopifyApp\Events\ShopifyChargeActivated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Centire\ShopifyApp\Test\Stubs\ApiStub;
use Tests\TestCase;

class BillingControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var Shop $shop
     */
    protected $shop;

    /**
     * Setup test
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        // Stub in our API class
        config(['shopify.api_class' => new ApiStub()]);
        config(['shopify.billing_enabled' => true]);

        $this->shop = factory(Shop::class)->create([
            'shopify_domain' => 'example.myshopify.com',
            'status'         => 1,
            'grandfathered'  => 0,
        ]);

        // Login as
        $this->be($this->shop, 'shop.web');
    }

    /**
     * @test
     */
    public function unauthenticated_shop_is_redirected_to_auth_page()
    {
        auth('shop.web')->logout();
        $plan = factory(Plan::class)->create();

        $response = $this->get('/billing');
        $response->assertStatus(302)
            ->assertRedirect('/auth')
        ->assertSessionHas('error', 'Login is required to access the billing page.');
    }

    /**
     * @test
     */
    public function app_must_have_an_active_plan()
    {
        $response = $this->get('/billing');

        $response->assertStatus(302)
            ->assertRedirect('/auth')
            ->assertSessionHas('error', 'No active plan was found.');
    }

    /** @test */
    public function can_get_plan_details()
    {
        $plan = factory(Plan::class)->create([
            'name'         => 'my plan',
            'duration'     => 30,
            'trial_days'   => 5,
            'price'        => 2345,
            'plan_type'    => 'recurring',
        ]);

        $controller = new BillingController();
        $method = new \ReflectionMethod(BillingController::class, 'planDetails');
        $method->setAccessible(true);

        // Based on default config
        $this->assertEquals(
            [
                'name'            => 'my plan',
                'price'           => 2345,
                'return_url'      => url(config('shopify.billing_redirect')),
                'trial_days'      => 5,
                'charge_type'     => 'recurring',
                'discount_amount' => 0,
                'plan_id'         => $plan->id,
                'test'            => false,
            ],

            $method->invoke($controller, $this->shop)
        );
    }

    /** @test */
    public function can_get_flat_discounted_plan_details()
    {
        $plan = factory(Plan::class)->create([
            'name'         => 'my plan',
            'duration'     => 30,
            'trial_days'   => 5,
            'price'        => 2345,
            'plan_type'    => 'recurring',
        ]);

        $discount = factory(Discount::class)->create([
            'shopify_domain'        => $this->shop->shopify_domain,
            'plan_id'               => $plan->id,
            'discount_type'         => 'FLAT',
            'amount'                => 123,
        ]);

        $controller = new BillingController();
        $method = new \ReflectionMethod(BillingController::class, 'planDetails');
        $method->setAccessible(true);

        // Based on default config
        $this->assertEquals(
            [
                'name'            => 'my plan',
                'price'           => 2345 - 123,
                'return_url'      => url(config('shopify.billing_redirect')),
                'trial_days'      => 5,
                'charge_type'     => 'recurring',
                'discount_amount' => 123,
                'plan_id'         => $plan->id,
                'test'            => false,
            ],

            $method->invoke($controller, $this->shop)
        );
    }

    /** @test */
    public function can_get_percentage_discounted_plan_details()
    {
        $plan = factory(Plan::class)->create([
            'name'         => 'my plan',
            'duration'     => 30,
            'trial_days'   => 5,
            'price'        => 2000,
            'plan_type'    => 'recurring',
        ]);

        $discount = factory(Discount::class)->create([
            'shopify_domain'        => $this->shop->shopify_domain,
            'plan_id'               => $plan->id,
            'discount_type'         => 'PERCENTAGE',
            'amount'                => 20,
        ]);

        $controller = new BillingController();
        $method = new \ReflectionMethod(BillingController::class, 'planDetails');
        $method->setAccessible(true);

        // Based on default config
        $this->assertEquals(
            [
                'name'            => 'my plan',
                'price'           => 1600,
                'return_url'      => url(config('shopify.billing_redirect')),
                'trial_days'      => 5,
                'charge_type'     => 'recurring',
                'discount_amount' => 400,
                'plan_id'         => $plan->id,
                'test'            => false,
            ],

            $method->invoke($controller, $this->shop)
        );
    }

    /** @test */
    public function can_get_plan_details_with_usage()
    {
        $plan = factory(Plan::class)->create([
            'name'         => 'my plan',
            'duration'     => 30,
            'trial_days'   => 5,
            'price'        => 2000,
            'plan_type'    => 'recurring',
            'metadata'     => [
                'capped_amount' => 100,
            ],
            'terms' => '$1 for 100 requests',
        ]);

        $controller = new BillingController();
        $method = new \ReflectionMethod(BillingController::class, 'planDetails');
        $method->setAccessible(true);

        // Based on default config
        $this->assertEquals(
            [
                'name'            => 'my plan',
                'price'           => 2000,
                'return_url'      => url(config('shopify.billing_redirect')),
                'trial_days'      => 5,
                'charge_type'     => 'recurring',
                'discount_amount' => 0,
                'capped_amount'   => 100,
                'terms'           => '$1 for 100 requests',
                'plan_id'         => $plan->id,
                'test'            => false,
            ],

            $method->invoke($controller, $this->shop)
        );
    }

    /** @test */
    public function returns_changed_plan_details_and_trial_days_for_cancelled_charge()
    {
        $plan = factory(Plan::class)->create([
            'name'         => 'my plan',
            'duration'     => 30,
            'trial_days'   => 14,
            'price'        => 2000,
            'plan_type'    => 'recurring',
        ]);

        $charge = factory(Charge::class)->create([
            'shop_id'       => $this->shop->id,
            'plan_id'       => $plan->id,
            'status'        => 'cancelled',
            'trial_days'    => 14,
            'trial_ends_on' => Carbon::today()->addDays(14)->format('Y-m-d'),
            'cancelled_on'  => Carbon::today()->addDays(3)->format('Y-m-d'),
        ]);

        $controller = new BillingController();
        $method = new \ReflectionMethod(BillingController::class, 'planDetails');
        $method->setAccessible(true);

        // Based on default config
        $this->assertEquals(
            [
                'name'            => 'my plan',
                'price'           => 2000,
                'return_url'      => url(config('shopify.billing_redirect')),
                'trial_days'      => 11,
                'charge_type'     => 'recurring',
                'discount_amount' => 0,
                'plan_id'         => $plan->id,
                'test'            => false,
            ],

            $method->invoke($controller, $this->shop)
        );
    }

    /**
     * @test
     */
    public function shop_is_redirected_to_shopify_billing_page()
    {
        $this->withoutExceptionHandling();
        $plan = factory(Plan::class)->create();
        $response = $this->get('/billing');

        $response->assertViewHas(
            'url',
            'https://' . $this->shop->shopify_domain . '/admin/charges/1029266947/confirm_recurring_application_charge?signature=BAhpBANeWT0%3D--64de8739eb1e63a8f848382bb757b20343eb414f'
        );
    }

    /** @test */
    public function shop_can_process_charge()
    {
        $this->withoutExceptionHandling();
        $plan = factory(Plan::class)->create();

        $response = $this->call('get', '/billing/process', ['charge_id' => 1029266947]);
        $response->assertStatus(302);
        $this->assertEquals(1029266947, $this->shop->charges()->get()->last()->charge_id);
    }

    /** @test */
    public function processing_charge_dispatches_events()
    {
        \Event::fake();
        $plan = factory(Plan::class)->create();
        $response = $this->call('get', '/billing/process', ['charge_id' => 1029266947]);
        \Event::assertDispatched(ShopifyChargeActivated::class, function ($e) {
            return $e->metadata['charge']->charge_id === 1029266947 && $e->shop->id === $this->shop->id;
        });
    }
}
