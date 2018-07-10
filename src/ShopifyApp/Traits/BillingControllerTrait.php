<?php

namespace Centire\ShopifyApp\Traits;

use Carbon\Carbon;
use Centire\ShopifyApp\Events\ShopifyChargeActivated;
use Centire\ShopifyApp\Facades\ShopifyApp;
use Centire\ShopifyApp\Libraries\BillingPlan;
use Centire\ShopifyApp\Models\ShopSubscription;
use Centire\ShopifyApp\Models\Subscription;
use Centire\ShopifyApp\Models\Tester;

trait BillingControllerTrait
{
    /**
     * Redirects to billing screen for Shopify.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if ($shop = ShopifyApp::shop()) {
            $planDetails = $this->planDetails();

            if ($planDetails) {

                // Get the confirmation URL
                $plan = new BillingPlan($shop, $planDetails['charge_type']);

                $plan->setDetails($planDetails);

                try {
                    $planConfirmationUrl = $plan->getConfirmationUrl();

                    // Do a Full Page redirect
                    return view('shopify-app::billing.fullpage_redirect', [
                        'url' => $planConfirmationUrl,
                    ]);
                } catch (\Exception $e) {
                    return redirect()->route('authenticate')->with(
                        'error',
                        'Exception: ' . $e->getMessage()
                    );
                }
            } else {
                return redirect()->route('authenticate')->with(
                    'error',
                    'No active plan was found.'
                );
            }
        }

        return redirect()->route('authenticate');
    }

    /**
     * Processes the response from the customer
     * @return \Illuminate\Http\RedirectResponse
     */
    public function process()
    {
        /**
         * @var $shop
         */
        $shop = ShopifyApp::shop();

        if ($shop) {
            $chargeId = request('charge_id');

            /**
             * @var ShopSubscription $shopSubscription
             */
            $shopSubscription = $shop->subscriptions()->whereChargeId($chargeId)->whereIsActive(false)->first();

            if ($shopSubscription) {
                $subscription = $shopSubscription->subscription;
                $subscription = $subscription && $subscription->is_active == true && $subscription->plan && $subscription->plan->is_active == true ? $subscription : null;

                if ($subscription) {
                    try {

                        // Setup the plan and get the charge
                        $plan = new BillingPlan($shop, $subscription->billing_type);
                        $plan->setChargeId($chargeId);

                        // Check the customer's answer to the billing
                        $charge = $plan->getCharge();

                        if ($charge->status == 'accepted') {

                            // Customer accepted, activate the charge
                            $charge = $plan->activate();

                            // Save the charge ID to the shop
                            $shop->status = 1;
                            $shop->charge_id = $chargeId;
                            $shop->save();

                            // Create subscription
                            $shopSubscription->update([
                                'billing_on' => $charge->billing_on ? Carbon::parse($charge->billing_on)->setTimezone('UTC') : null,
                                'trial_ends_on' => $charge->trial_ends_on ? Carbon::parse($charge->trial_ends_on)->setTimezone('UTC') : null,
                                'activated_on' => $charge->activated_on ? Carbon::parse($charge->activated_on)->setTimezone('UTC') : null,
                                'updated_at' => Carbon::parse($charge->updated_at)->setTimezone('UTC'),
                                'is_active' => true,
                            ]);


                            // [Event] Shopify Charge Activated
                            event(new ShopifyChargeActivated(
                                $shop,
                                [
                                    'charge' => $charge,
                                    'subscription' => $shopSubscription
                                ]
                            ));

                            // Go to homepage of app
                            return redirect()->route('home');
                        } else {

                            // Customer declined the charge, abort
                            return redirect()->route('authenticate')->with(
                                'error',
                                'It seems you have declined the billing charge for this application'
                            );
                        }
                    } catch (\Exception $e) {
                        return redirect()->route('authenticate')->with(
                            'error',
                            'Exception: ' . $e->getMessage()
                        );
                    }
                } else {
                    return redirect()->route('authenticate')->with(
                        'error',
                        "The selected plan is not exist or no longer active."
                    );
                }
            } else {
                return redirect()->route('authenticate')->with(
                    'error',
                    'No such subscription was found.'
                );
            }
        } else {
            return redirect()->route('authenticate');
        }
    }

    /**
     * Base plan to use for billing.
     * Setup as a function so its patchable.
     *
     * @return array
     */
    protected function planDetails()
    {
        $shop = ShopifyApp::shop();

        /**
         * @var Subscription $subscription
         */
        $subscription = Subscription::whereIsActive(true)->orderBy('priority', 'ASC')->first();

        $subscription = $subscription && $subscription->plan && $subscription->plan->is_active == true ? $subscription : null;


        if ($subscription) {

            // Price
            $discountAmount = $this->discount($shop, $subscription);
            $subscriptionPrice = $subscription->price - $discountAmount;

            // Check if this is a test charge
            $isTestCharge = $subscriptionPrice <= 0 || Tester::whereShopifyDomain($shop->shopify_domain)->whereIsActive(true)->count() ? true : false;

            $chargeType = $subscription->billing_type;

            $planDetails =  [
                "name" => $subscription->display_name,
                "price" => $subscriptionPrice > 0 ? number_format($subscriptionPrice, 2, '.', '') : 0,
                "return_url" => url(config('shopify.billing_redirect')),
                "trial_days" => (int)$subscription->trial_days,
                'charge_type' => $chargeType,
                'discount_amount' => $discountAmount,
                'subscription_id' => $subscription->id,
                "test" => $isTestCharge,
            ];

            // Handle capped amounts for UsageCharge API
            if ($chargeType == 'usage') {
                $planDetails['capped_amount'] = $subscription->metadata['capped_amount'];
                $planDetails['terms'] = $subscription->metadata['billing_terms'];
            }

            return $planDetails;
        }

        return null;
    }

    /**
     * @param $shop
     * @param Subscription $subscription
     * @return mixed
     */
    protected function discount($shop, $subscription)
    {
        // Discount
        $discount = $subscription->discounts()
            ->whereIsActive(true)
            ->where('shopify_domain', $shop->shopify_domain)
            ->orderBy('created_at', 'DESC')
            ->get()
            ->first();

        $discountAmount = $discount && isset($discount->discount) && $discount->discount ? $discount->discount : 0;
        $discountType = $discountAmount ? $discount->discount_type : null;
        $discountAmount = $discountType == 'FLAT' ? $discountAmount : ($discountType == 'PERCENTAGE' && $discountAmount <= 100 ? ($subscription->price * $discountAmount) / 100 : 0);

        $discountAmount = number_format($discountAmount, 2);
        $discountAmount = $subscription->price > $discountAmount ? $discountAmount : $subscription->price;

        return $discountAmount;
    }
}
