<?php

namespace Centire\ShopifyApp\Test\Libraries;

use App\Plan;
use Centire\ShopifyApp\Libraries\BillingPlan;
use App\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Centire\ShopifyApp\Test\Stubs\ApiStub;
use Tests\TestCase;

class BillingPlanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var $shop Shop
     */
    protected $shop;

    /**
     * @var array $plan
     */
    protected $plan;

    public function setUp()
    {
        parent::setUp();

        // Stub in our API class
        config(['shopify.api_class' => new ApiStub()]);

        // Base shop and plan
        $this->shop = factory(Shop::class)->create([
            'shopify_domain' => 'example.myshopify.com',
            'status'         => 1,
            'grandfathered'  => 0,
        ]);

        $plan = factory(Plan::class)->create([
            'name'         => 'my plan',
            'duration'     => 30,
            'trial_days'   => 5,
            'price'        => 2345,
            'plan_type'    => 'recurring',
        ]);

        $this->plan = [
            'name'            => $plan->name,
            'price'           => $plan->price,
            'return_url'      => url(config('shopify.billing_redirect')),
            'trial_days'      => $plan->trial_days,
            'charge_type'     => $plan->plan_type,
            'discount_amount' => 0,
            'plan_id'         => 1,
            'test'            => false,
        ];
    }

    /** @test */
    public function returns_confirmation_url()
    {
        $url = (new BillingPlan($this->shop))->setDetails($this->plan)->getConfirmationUrl();

        $this->assertEquals(
            'https://example.myshopify.com/admin/charges/1029266947/confirm_recurring_application_charge?signature=BAhpBANeWT0%3D--64de8739eb1e63a8f848382bb757b20343eb414f',
            $url
        );
    }

    /** @test */
    public function returns_confirmation_url_with_usage()
    {
        $plan = array_merge($this->plan, [
            'capped_amount' => 100.00,
            'terms'         => '$1 for 500 emails',
        ]);
        $url = (new BillingPlan($this->shop))->setDetails($plan)->getConfirmationUrl();

        $this->assertEquals(
            'https://example.myshopify.com/admin/charges/1029266947/confirm_recurring_application_charge?signature=BAhpBANeWT0%3D--64de8739eb1e63a8f848382bb757b20343eb414f',
            $url
        );
    }

    /** @test */
    public function throws_exception_when_no_plan_details_are_passed()
    {
        try {
            (new BillingPlan($this->shop))->getConfirmationUrl();
        } catch (\Exception $e) {
            $this->assertEquals('Plan details are missing for confirmation URL request.', $e->getMessage());

            return;
        }

        $this->fail('Test passed but was supposed to throw exception');
    }

    /** @test */
    public function activate_plan()
    {
        $response = (new BillingPlan($this->shop))->setChargeId(1029266947)->activate();

        $this->assertEquals(true, is_object($response));
        $this->assertEquals('active', $response->status);
    }

    /** @test */
    public function throws_exception_while_activation_plan_without_charge_id()
    {
        try {
            (new BillingPlan($this->shop))->activate();
        } catch (\Exception $e) {
            $this->assertEquals('Can not activate plan without a charge ID.', $e->getMessage());

            return;
        }

        $this->fail('Test passed but was supposed to throw exception');
    }

    /** @test */
    public function returns_charge_details_for_charge_id()
    {
        $response = (new BillingPlan($this->shop))->setChargeId(1029266947)->getCharge();

        $this->assertEquals(true, is_object($response));
        $this->assertEquals('accepted', $response->status);
    }

    /** @test */
    public function throws_exception_when_getting_charge_details_without_charge_id()
    {
        try {
            (new BillingPlan($this->shop))->getCharge();
        } catch (\Exception $e) {
            $this->assertEquals('Can not get charge information without charge ID.', $e->getMessage());

            return;
        }

        $this->fail('Test passed but was supposed to throw exception');
    }
}
