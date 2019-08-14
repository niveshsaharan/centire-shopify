<?php

namespace Centire\ShopifyApp\Traits;

use App\BannedShop;
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
        return redirect(route('authenticate'));
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

        // Check if user is banned
        if(BannedShop::isBanned(ShopifyApp::sanitizeShopDomain($shopDomain)))
        {
            return redirect()->route('authenticate')->with("error", "Sorry! You're banned.");
        }

        // Save shop domain to session
        session([
            'shopify_domain' => ShopifyApp::sanitizeShopDomain($shopDomain),
            'impersonate'    => 0,
            '__referrer'     => $request->get('ref') ? $request->get('ref') : session('__referrer'),
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

                // Save referrer to cache
                $referrer = session('__referrer');

                if ($referrer && !$shop->analytics_id) {
                    \Cache::forever($shop->shopify_domain . '-referrer', $referrer);
                }

                session()->forget('__referrer');

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
     *
     * @param      $api
     * @param Shop $shop
     *
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
                                \Log::info('Cancelling charge because charge status is invalid::' . $shop->shopify_domain);
                                $shopCharge->cancel();
                            }
                        }
                    }
                } else {
                    \Log::info('Cancelling charge because details was not found or not valid::' . $shop->shopify_domain);
                    $shopCharge->cancel();
                }
            } catch (\Exception $e) {
                \Log::alert('Charge verification exception::' . $shop->shopify_domain . '::' . $e->getMessage());

                return redirect()->route('authenticate')->with('error', $e->getMessage());
            }
        }

        return redirect()->route('billing');
    }

    /**
     * Get and Save shop details
     *
     * @param Shop $shop
     *
     * @return mixed
     */
    protected function updateShopDetails(Shop $shop)
    {
        try {
            $shopifyStore = $shop->api()->graph('
query
{
    shop
    {
        id
        name
        email
        contactEmail
        primaryDomain
        {
            host
        }
        myshopifyDomain
        billingAddress
        {
            name
            company
            address1
            address2
            city
            company
            country
            countryCodeV2 
            latitude
            longitude
            province
            provinceCode
            phone
            zip
        }
        currencyCode
        currencyFormats
        {
            moneyFormat
            moneyWithCurrencyFormat
        }
        ianaTimezone
        plan
        {
            displayName
            partnerDevelopment 
            shopifyPlus
        }
    }
}       
            ')->body->shop;

            if ($shopifyStore) {
                $shop->update([
                    'gid'                       => $shopifyStore->id,
                    'name'                       => $shopifyStore->name,
                    'email'                      => $shopifyStore->email ?: $shopifyStore->contactEmail,
                    'customer_email'             => $shopifyStore->contactEmail ?: $shopifyStore->email,
                    'shop_owner'                 => $shopifyStore->billingAddress->name ?: $shopifyStore->name,
                    'domain'                     => $shopifyStore->primaryDomain->host,
                    'primary_locale'             => '',
                    'address_1'                  => isset($shopifyStore->billingAddress->address1) && $shopifyStore->billingAddress->address1 ? $shopifyStore->billingAddress->address1 : '',
                    'city'                       => isset($shopifyStore->billingAddress->city) && $shopifyStore->billingAddress->city ? $shopifyStore->billingAddress->city : '',
                    'phone'                      => isset($shopifyStore->billingAddress->phone) && $shopifyStore->billingAddress->phone ? $shopifyStore->billingAddress->phone : '',
                    'province'                   => isset($shopifyStore->billingAddress->province) && $shopifyStore->billingAddress->province ? $shopifyStore->billingAddress->province : '',
                    'province_code'              => isset($shopifyStore->billingAddress->provinceCode) && $shopifyStore->billingAddress->provinceCode? $shopifyStore->billingAddress->provinceCode: '',
                    'country'                    => isset($shopifyStore->billingAddress->country) && $shopifyStore->billingAddress->country ? $shopifyStore->billingAddress->country : '',
                    'country_name'               => isset($shopifyStore->billingAddress->country) && $shopifyStore->billingAddress->country ? $shopifyStore->billingAddress->country : '',
                    'country_code'               => isset($shopifyStore->billingAddress->countryCodeV2) && $shopifyStore->billingAddress->countryCodeV2? $shopifyStore->countryCodeV2 : '',
                    'zip'                        => isset($shopifyStore->billingAddress->zip) && $shopifyStore->billingAddress->zip ? $shopifyStore->billingAddress->zip : '',
                    'latitude'                   => isset($shopifyStore->billingAddress->latitude) && $shopifyStore->billingAddress->latitude ? $shopifyStore->billingAddress->latitude : '',
                    'longitude'                  => isset($shopifyStore->billingAddress->longitude) && $shopifyStore->billingAddress->longitude ? $shopifyStore->billingAddress->longitude : '',
                    'currency'                   => isset($shopifyStore->currencyCode) && $shopifyStore->currencyCode ? $shopifyStore->currencyCode : '',
                    'money_format'               => isset($shopifyStore->currencyFormats->moneyFormat) && $shopifyStore->currencyFormats->moneyFormat ? $shopifyStore->currencyFormats->moneyFormat : '',
                    'money_with_currency_format' => isset($shopifyStore->currencyFormats->moneyWithCurrencyFormat) && $shopifyStore->currencyFormats->moneyWithCurrencyFormat ? $shopifyStore->currencyFormats->moneyWithCurrencyFormat : '',
                    'timezone'                   => isset($shopifyStore->ianaTimezone) && $shopifyStore->ianaTimezone ? $shopifyStore->ianaTimezone : '',
                    'iana_timezone'              => isset($shopifyStore->ianaTimezone) && $shopifyStore->ianaTimezone ? $shopifyStore->ianaTimezone : '',
                    'shopify_plan_name'          => isset($shopifyStore->plan->displayName) && $shopifyStore->plan->displayName ? $shopifyStore->plan->displayName : '',
                    'shopify_plan_display_name'  => isset($shopifyStore->plan->displayName) && $shopifyStore->plan->displayName ? $shopifyStore->plan->displayName : '',
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
        dispatch( new WebhooksInstaller(ShopifyApp::shop()))->onQueue(queueName('second'));
    }

    /**
     * Installs scripttags (if any).
     *
     * @return void
     */
    protected function installScriptTags()
    {
        dispatch( new ScriptTagsInstaller(ShopifyApp::shop()) )->onQueue(queueName('second'));
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
