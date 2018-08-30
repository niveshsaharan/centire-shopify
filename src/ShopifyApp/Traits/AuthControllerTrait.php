<?php

namespace Centire\ShopifyApp\Traits;

use App\Charge;
use App\Http\Utilities\GlobalDataHelper;
use App\Shop;
use Centire\ShopifyApp\Facades\ShopifyApp;
use Centire\ShopifyApp\Jobs\ScriptTagsInstaller;
use Centire\ShopifyApp\Jobs\WebhooksInstaller;
use Centire\ShopifyApp\Libraries\BillingPlan;
use Illuminate\Http\Request;

trait AuthControllerTrait
{
	/**
	 * Index route which displays the login page.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index()
	{
		return view('shopify-app::auth.index');
	}

	/**
	 * Authenticate a shop
	 * @return bool|\Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Routing\Redirector|\Illuminate\View\View
	 */
	public function authenticate(Request $request)
	{
		// Grab the shop domain (uses session if redirected from middleware)
		$shopDomain = $request->get('shop');

		if (!$shopDomain) {

		    // Get shopify app details
		    $shopifyApp = (new GlobalDataHelper())->apps(config('shopify.app_slug'));

			// Back to login, no shop
			return view('shopify-app::auth.index', compact('shopifyApp'));
		}

		// Save shop domain to session
		session([
			'shopify_domain' => ShopifyApp::sanitizeShopDomain($shopDomain),
			'impersonate'    => 0,
			]);

		if ($request->get('code')) {
			// Handle a request with a code
			return $this->authenticationWithCode();
		} else {
			// Handle a request without a code
			return $this->authenticationWithoutCode();
		}
	}

	/**
	 * Impersonating a shop.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function impersonate(Request $request)
	{
		$shopDomain = $request->get('shop');
		$code = $request->get('code');

		if (!$shopDomain) {
			// Back to login, no shop
			return redirect()->route('authenticate');
		}

		if ($code && $code == config('shopify.impersonate_key')) {
			// Save shop domain to session
			session([
				'shopify_domain' => ShopifyApp::sanitizeShopDomain($shopDomain),
				'impersonate'    => 1,
			]);

			$shop = Shop::whereShopifyDomain($shopDomain)->first();

			if ($shop) {
				$shop = ShopifyApp::login($shop);

				// Create api token
				$shop->apiToken(false);
			}

			return redirect()->route('home');
		}

		// Go to homepage of app
		return redirect()->route('authenticate');
	}

	/**
	 * Fires when there is no code on authentication.
	 *
	 * @return \Illuminate\Http\Response
	 */
	protected function authenticationWithoutCode()
	{
		// Setup an API instance
		$shopDomain = session('shopify_domain');
		$api = ShopifyApp::api();
		$api->setShop($shopDomain);

		// Grab the authentication URL
		try {
			$authUrl = $api->getAuthUrl(
				config('shopify.api_scopes'),
				url(config('shopify.api_redirect'))
			);
		} catch (\Exception $e) {
		}

		// Do a Full page redirect
		return view('shopify-app::auth.fullpage_redirect', [
			'authUrl'    => $authUrl,
			'shopDomain' => $shopDomain,
		]);
	}

	/**
	 * Fires when there is a code on authentication.
	 *
	 * @return bool|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
	 */
	protected function authenticationWithCode()
	{
		// Setup an API instance
		$shopDomain = session('shopify_domain');

		$api = ShopifyApp::api();
		$api->setShop($shopDomain);

		// Check if request is verified
		if (!$api->verifyRequest(request()->all())) {
			// Not valid, redirect to login and show the errors
			return redirect()->route('authenticate')->with('error', 'Shopify Auth: Cannot verify request.');
		}

		// Access token
		try {
			$accessToken = $api->requestAccessToken(request('code'));

			if ($accessToken) {
				// Find or create shop
				$shop = ShopifyApp::firstOrCreate($shopDomain, true);

				// If trashed, restore it and restores it's charges
				if ($shop->trashed()) {
					$shop->restore();
					$shop->charges()->restore();
				}

				// Create api token
				$shop->apiToken();

				// Save token to shop
				$shop->shopify_token = $accessToken;
				$shop->shopify_scopes = array_filter(array_map('trim', explode(',', config('shopify.api_scopes'))));
				$shop->save();

				// Install webhooks and scripts
				$this->installWebhooks();
				$this->installScriptTags();

				// If app is not active
				if ($shop->status != 1) {
					$shop = $this->updateShopDetails($shop);

					// Run after authenticate job
					$this->afterAuthenticateJob();
				}

				// Check Shop charge
				$isChargeValid = $this->verifyCharge($shop);

				if ($isChargeValid !== true) {
					return $isChargeValid;
				}

				// Go to homepage of app
				return redirect()->route('home');
			} else {
				return redirect()->route('authenticate')->with(
					'error',
					'Unable to get access token. Please try again.'
				);
			}
		} catch (\Exception $e) {
			return redirect()->route('authenticate')->with(
				'error',
				'Unable to get access token. Please try again. ' . $e->getMessage() . $e->getFile()
			);
		}
	}

	/**
	 * Verify shop charge
	 * @param $api
	 * @param Shop $shop
	 * @return bool|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
	 */
	protected function verifyCharge($shop)
	{
		if ($shop->isGrandfathered() || !config('shopify.billing_enabled')) {
			return true;
		}

		$shopCharge = $shop->charges()
			->whereIn('type', [Charge::CHARGE_RECURRING, Charge::CHARGE_ONETIME])
			->whereNull('cancelled_on')
			->orderBy('created_at', 'desc')
			->first();

		if ($shopCharge) {
			try {
				$chargeId = $shopCharge->charge_id;
				$chargeType = $shopCharge->type == Charge::CHARGE_ONETIME ? 'single' : 'recurring';
				$billingPlan = new BillingPlan($shop, $chargeType);
				$billingPlan->setChargeId($chargeId);
				$charge = $billingPlan->getCharge();

				if ($charge
							&& (
								$charge->test == 0
								|| $shop->isTester()
							)
						) {
					// Accepted: Redirect to activate
					if ($charge->status === 'accepted') {
						return redirect()->route('billing.process', ['charge_id' => $chargeId]);
					} else {
						if ($charge->status === 'active') {
							return true;
						} else {
							if ($charge->status === 'pending') {
								return redirect($charge->confirmation_url);
							} else {
								$shopCharge->cancel();
							}
						}
					}
				} else {
					$shopCharge->cancel();
				}
			} catch (\Exception $e) {
				$shopCharge->cancel();

				return redirect()->route('billing')->with('error', $e->getMessage());
			}
		}

		return redirect()->route('billing');
	}

	/**
	 * Get and Save shop details
	 * @param Shop $shop
	 * @return mixed
	 */
	protected function updateShopDetails(Shop $shop)
	{
		try {
			$shopifyStore = $shop->api()->rest(
				'GET',
				'/admin/shop.json'
			);

			/**
			 * @var \Shopify_Store_Data $shopifyStore
			 */
			$shopifyStore = $shopifyStore && isset($shopifyStore->body) && isset($shopifyStore->body->shop) && $shopifyStore->body->shop ? $shopifyStore->body->shop : null;

			if ($shopifyStore) {
				$shop->update([
					'shopify_id'                 => $shopifyStore->id,
					'name'                       => $shopifyStore->name,
					'email'                      => $shopifyStore->email ? $shopifyStore->email : $shopifyStore->customer_email,
					'customer_email'             => $shopifyStore->customer_email ? $shopifyStore->customer_email : '',
					'shop_owner'                 => isset($shopifyStore->shop_owner) && $shopifyStore->shop_owner ? $shopifyStore->shop_owner : '',
					'domain'                     => $shopifyStore->domain,
					'primary_locale'             => isset($shopifyStore->primary_locale) && $shopifyStore->primary_locale ? $shopifyStore->primary_locale : '',
					'address_1'                  => isset($shopifyStore->address1) && $shopifyStore->address1 ? $shopifyStore->address1 : '',
					'city'                       => isset($shopifyStore->city) && $shopifyStore->city ? $shopifyStore->city : '',
					'phone'                      => isset($shopifyStore->phone) && $shopifyStore->phone ? $shopifyStore->phone : '',
					'province'                   => isset($shopifyStore->province) && $shopifyStore->province ? $shopifyStore->province : '',
					'province_code'              => isset($shopifyStore->province_code) && $shopifyStore->province_code ? $shopifyStore->province_code : '',
					'country'                    => isset($shopifyStore->country) && $shopifyStore->country ? $shopifyStore->country : '',
					'country_name'               => isset($shopifyStore->country_name) && $shopifyStore->country_name ? $shopifyStore->country_name : '',
					'country_code'               => isset($shopifyStore->country_code) && $shopifyStore->country_code ? $shopifyStore->country_code : '',
					'zip'                        => isset($shopifyStore->zip) && $shopifyStore->zip ? $shopifyStore->zip : '',
					'latitude'                   => isset($shopifyStore->latitude) && $shopifyStore->latitude ? $shopifyStore->latitude : '',
					'longitude'                  => isset($shopifyStore->longitude) && $shopifyStore->longitude ? $shopifyStore->longitude : '',
					'currency'                   => isset($shopifyStore->currency) && $shopifyStore->currency ? $shopifyStore->currency : '',
					'money_format'               => isset($shopifyStore->money_format) && $shopifyStore->money_format ? $shopifyStore->money_format : '',
					'money_with_currency_format' => isset($shopifyStore->money_with_currency_format) && $shopifyStore->money_with_currency_format ? $shopifyStore->money_with_currency_format : '',
					'timezone'                   => isset($shopifyStore->timezone) && $shopifyStore->timezone ? $shopifyStore->timezone : '',
					'iana_timezone'              => isset($shopifyStore->iana_timezone) && $shopifyStore->iana_timezone ? $shopifyStore->iana_timezone : '',
					'shopify_plan_name'          => isset($shopifyStore->plan_name) && $shopifyStore->plan_name ? $shopifyStore->plan_name : '',
					'shopify_plan_display_name'  => isset($shopifyStore->plan_display_name) && $shopifyStore->plan_display_name ? $shopifyStore->plan_display_name : '',
					'status'                     => 1,
				]);
			}
		} catch (\Exception $e) {
		}

		return $shop;
	}

	/**
	 * Installs webhooks (if any).
	 *
	 * @return void
	 */
	protected function installWebhooks()
	{
		$webhooks = config('shopify.webhooks');
		if (count($webhooks) > 0) {
			dispatch(
				new WebhooksInstaller(ShopifyApp::shop(), $webhooks)
			)->onQueue(queueName('second'));
		}
	}

	/**
	 * Installs scripttags (if any).
	 *
	 * @return void
	 */
	protected function installScriptTags()
	{
		$scriptTags = config('shopify.script_tags');
		if (count($scriptTags) > 0) {
			dispatch(
				new ScriptTagsInstaller(ShopifyApp::shop(), $scriptTags)
			)->onQueue(queueName('second'));
		}
	}

	/**
	 * Runs a job after authentication if provided.
	 *
	 * @return bool
	 */
	protected function afterAuthenticateJob()
	{
		$jobConfig = config('shopify.after_authenticate_job');

		if (empty($jobConfig) || !isset($jobConfig['jobs']) || !$jobConfig['jobs']) {
			// Empty config or no job assigned
			return false;
		}

		if (is_array($jobConfig['jobs']) && $jobConfig['jobs']) {
			foreach ($jobConfig['jobs'] as $jobClass => $queueName) {
				$job = new $jobClass(ShopifyApp::shop());

				if ($queueName == 'inline') {
					$job->handle();
				} else {
					dispatch($job)->onQueue(queueName($queueName));
				}
			}
		}

		return true;
	}
}
