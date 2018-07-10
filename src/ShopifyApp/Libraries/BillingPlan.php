<?php

namespace Centire\ShopifyApp\Libraries;

use Carbon\Carbon;
use Centire\ShopifyApp\Events\ShopifyChargeCreated;
use Exception;

class BillingPlan
{
    /**
     * The shop to target billing.
     *
     * @var \App\Shop
     */
    protected $shop;

    /**
     * The plan details for Shopify.
     *
     * @var array
     */
    protected $details;

    /**
     * The charge ID.
     *
     * @var int
     */
    protected $chargeId;

    /**
     * The charge type.
     *
     * @var string
     */
    protected $chargeType;

    /**
     * Constructor for billing plan class.
     *
     * @param $shop - The shop to target for billing.
     * @param string $chargeType The type of charge for the plan (single or recurring).
     *
     * @return $this
     */
    public function __construct($shop, string $chargeType = 'recurring')
    {
        $this->shop = $shop;
        $this->chargeType = $chargeType === 'single' ? 'application_charge' : 'recurring_application_charge';

        return $this;
    }

    /**
     * Sets the plan.
     *
     * @param array $plan The plan details.
     *                    $plan = [
     *                    'name'         => (string) Plan name.
     *                    'price'        => (float) Plan price. Required.
     *                    'test'         => (boolean) Test mode or not.
     *                    'trial_days'   => (int) Plan trial period in days.
     *                    'return_url'   => (string) URL to handle response for acceptance or decline or billing. Required.
     *                    ]
     *
     * @return $this
     */
    public function setDetails(array $details)
    {
        $this->details = $details;

        return $this;
    }

    /**
     * Sets the charge ID.
     *
     * @param int $chargeId The charge ID to use
     *
     * @return $this
     */
    public function setChargeId(int $chargeId)
    {
        $this->chargeId = $chargeId;

        return $this;
    }

    /**
     * Gets the charge information for a previously inited charge.
     *
     * @return object
     */
    public function getCharge()
    {
        // Check if we have a charge ID to use
        if (!$this->chargeId) {
            throw new Exception('Can not get charge information without charge ID.');
        }

        // Run API to grab details
        $charge = $this->shop->api()->rest(
            'GET',
            "/admin/{$this->chargeType}s/{$this->chargeId}.json"
        )->body;

        // Check if charge exists
        if (isset($charge->{$this->chargeType})) {
            $charge = $charge->{$this->chargeType};

            return $charge;
        }

        throw new Exception('Charge was not found.');
    }

    /**
     * Activates a plan to the shop.
     *
     * Example usage:
     * (new BillingPlan([shop], 'recurring'))->setChargeId(request('charge_id'))->activate();
     *
     * @return object|bool
     */
    public function activate()
    {
        // Check if we have a charge ID to use
        if (!$this->chargeId) {
            throw new Exception('Can not activate plan without a charge ID.');
        }

        // Activate and return the API response
        $charge = $this->shop->api()->rest(
            'POST',
            "/admin/{$this->chargeType}s/{$this->chargeId}/activate.json"
        )->body;

        // Check if charge is activated
        if (isset($charge->{$this->chargeType})) {
            $charge = $charge->{$this->chargeType};

            return $charge;
        }

        throw new Exception('Charge could not be activated.');
    }

    /**
     * Gets the confirmation URL to redirect the customer to.
     * This URL sends them to Shopify's billing page.
     *
     * Example usage:
     * (new BillingPlan([shop], 'recurring'))->setDetails($plan)->getConfirmationUrl();
     * @param $shop
     * @return string
     */
    public function getConfirmationUrl()
    {
        $charge = $this->createCharge();

        if ($charge) {
            return $charge->confirmation_url;
        }

        throw new Exception('Charge was not found.');
    }

    /**
     * Create charge
     * @return mixed
     * @throws Exception
     */
    public function createCharge()
    {
        // Check if we have plan details
        if (!is_array($this->details)) {
            throw new Exception('Plan details are missing for confirmation URL request.');
        }

        // Begin the charge request
        $charge = $this->shop->api()->rest(
            'POST',
            "/admin/{$this->chargeType}s.json",
            [
                "{$this->chargeType}" => [
                    'test' => isset($this->details['test']) ? $this->details['test'] : false,
                    'trial_days' => isset($this->details['trial_days']) ? $this->details['trial_days'] : 0,
                    'name' => $this->details['name'],
                    'price' => $this->details['price'],
                    'return_url' => $this->details['return_url'],
                ],
            ]
        )->body;

        // Check if charge is created
        if (isset($charge->{$this->chargeType})) {
            $charge = $charge->{$this->chargeType};

            // End existing subscriptions
            $this->shop->subscriptions()->where('charge_id', '!=', $charge->id)->update([
                'cancelled_on' => Carbon::now(),
            ]);

            // Create new subscription
            $shopSubscription = $this->shop->subscriptions()->create([
                'shop_id' => $this->shop->id,
                'subscription_id' => $this->details['subscription_id'],
                'trial_days' => (int)$charge->trial_days,
                'charge_id' => $charge->id,
                'discount' => $this->details['discount_amount'],
                'billing_on' => $charge->billing_on ? Carbon::parse($charge->billing_on)->setTimezone('UTC') : null,
                'trial_ends_on' => $charge->trial_ends_on ? Carbon::parse($charge->trial_ends_on)->setTimezone('UTC') : null,
                'activated_on' => $charge->activated_on ? Carbon::parse($charge->activated_on)->setTimezone('UTC') : null,
                'created_at' => Carbon::parse($charge->created_at)->setTimezone('UTC'),
                'updated_at' => Carbon::parse($charge->updated_at)->setTimezone('UTC'),
                'is_active' => false,
            ]);

            // [Event] Shopify Charge Activated
            event(new ShopifyChargeCreated($this->shop, ['charge' => $charge, 'subscription' => $shopSubscription]));

            return $charge;
        }

        throw new Exception('Charge could not be created.');
    }
}
