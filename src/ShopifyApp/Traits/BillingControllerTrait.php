<?php

namespace Centire\ShopifyApp\Traits;

use App\Charge;
use App\Plan;
use Carbon\Carbon;
use Centire\ShopifyApp\Events\ShopifyChargeActivated;
use Centire\ShopifyApp\Facades\ShopifyApp;
use Centire\ShopifyApp\Libraries\BillingPlan;

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

		return redirect()->route('authenticate')->with('error', 'Login is required to access the billing page.');
	}

	/**
	 * Processes the response from the customer
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function process()
	{
		$shop = ShopifyApp::shop();

		if ($shop) {
			$chargeId = request('charge_id');

			$planDetails = $this->planDetails();

			// Setup the plan and get the charge
			$plan = new BillingPlan($shop, $planDetails['charge_type']);
			$plan->setChargeId($chargeId);

			try {
				// Check the customer's answer to the billing
				$chargeStatus = $plan->getCharge()->status;

				// Create a charge (regardless of the status)
				$charge = new Charge();
				$charge->type = $planDetails['charge_type'] === 'recurring' ? Charge::CHARGE_RECURRING : Charge::CHARGE_ONETIME;
				$charge->charge_id = $chargeId;
				$charge->status = $chargeStatus;
				$charge->name = $planDetails['name'];
				$charge->price = $planDetails['price'];
				$charge->trial_days = $planDetails['trial_days'];
				$charge->discount_amount = $planDetails['discount_amount'];
				$charge->plan_id = $planDetails['plan_id'];
				$charge->test = $planDetails['test'];
				$charge->terms = isset($planDetails['terms']) ? $planDetails['terms'] : null;
				$charge->capped_amount = isset($planDetails['capped_amount']) ? $planDetails['capped_amount'] : null;

				if ($chargeStatus == 'accepted') {
					$response = $plan->activate();

					$charge->status = $response->status;
					$charge->billing_on = $response->billing_on;
					$charge->trial_ends_on = $response->trial_ends_on;
					$charge->activated_on = $response->activated_on;
				} else {
					// Customer declined the charge
                    $charge->status = 'declined';
					$charge->cancelled_on = Carbon::today()->format('Y-m-d');
				}

				// Save and link to the shop
				$shop->charges()->save($charge);

				if ($chargeStatus == 'declined') {
					// Customer declined the charge, abort
					return redirect()->route('authenticate')->with(
						'error',
						'It seems you have declined the billing charge for this application.'
					);
				}
				elseif ($charge->status == 'active')
                {
                    // [Event] Shopify Charge Activated
                    event(new ShopifyChargeActivated( $shop,[
                            'charge' => $charge,
                        ]
                    ));
                }
			} catch (\Exception $e) {
				\Log::alert('Charge activation exception::' . $shop->shopify_domain . '::' . $e->getMessage());

				return redirect()->route('authenticate')->with('error', 'An error has occurred while activating the charge.');
			}

			return redirect()->route('home');
		} else {
			return redirect()->route('authenticate')->with('error', 'Login is required to access the billing page.');
		}
	}

	/**
	 * Base plan to use for billing.
	 * Setup as a function so its patchable.
	 *
	 * @return array|null
	 */
	protected function planDetails()
	{
		$shop = ShopifyApp::shop();

		$plan = Plan::orderBy('priority', 'ASC')->first();

		if ($plan) {
			// Price
			$discountAmount = $plan->discount($shop);
			$planPrice = $plan->price - $discountAmount;

			$planDetails = [
				'name'            => $plan->name,
				'price'           => $planPrice,
				'return_url'      => url(config('shopify.billing_redirect')),
				'trial_days'      => $plan->trial_days,
				'charge_type'     => $plan->plan_type,
				'discount_amount' => $discountAmount,
				'plan_id'         => $plan->id,
				'test'            => $planPrice <= 0 || $shop->isTester(),
			];

			// Handle capped amounts for UsageCharge API
			if (isset($plan->metadata['capped_amount'])) {
			    $cappedAmount = (int) $plan->metadata['capped_amount'];
				$planDetails['capped_amount'] = $cappedAmount ?: 1;
				$planDetails['terms'] = $plan->terms;
			}

			// Grab the last charge for the shop (if any) to determine if this shop
			// reinstalled the app so we can issue new trial days based on result
			$lastCharge = $shop->charges()
				->whereIn('type', [Charge::CHARGE_RECURRING, Charge::CHARGE_ONETIME])
				->orderBy('created_at', 'desc')
				->first();

			if ($lastCharge && $lastCharge->isCancelled()) {
				// Return the new trial days, could result in 0
				$planDetails['trial_days'] = $lastCharge->remainingTrialDaysFromCancel();
			}

			return $planDetails;
		}

		return null;
	}
}
