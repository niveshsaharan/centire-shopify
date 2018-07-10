<?php

namespace Centire\ShopifyApp\Traits;

use App\Shop;
use Centire\ShopifyApp\BasicShopifyAPI;
use Centire\ShopifyApp\Facades\ShopifyApp;
use Centire\ShopifyApp\Jobs\ScriptTagsInstaller;
use Centire\ShopifyApp\Jobs\WebhooksInstaller;
use Centire\ShopifyApp\Libraries\BillingPlan;
use Centire\ShopifyApp\Models\ShopSubscription;
use Centire\ShopifyApp\Models\Tester;

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
    public function authenticate()
    {
        // Grab the shop domain (uses session if redirected from middleware)
        $shopDomain = request('shop');

        if (!$shopDomain) {

            // Back to login, no shop
            return view('shopify-app::auth.index');
        }

        // Save shop domain to session
        session(['shopify_domain' => ShopifyApp::sanitizeShopDomain($shopDomain), 'impersonate' => 0]);

        if (!request('code')) {
            // Handle a request without a code
            return $this->authenticationWithoutCode();
        } else {
            // Handle a request with a code
            return $this->authenticationWithCode();
        }
    }

    /**
     * Impersonating a shop.
     *
     * @return \Illuminate\Http\Response
     */
    public function impersonate()
    {
        // Grab the shop domain (uses session if redirected from middleware)
        $shopDomain = request('shop');
        $code = request('code');

        if (!$shopDomain) {

            // Back to login, no shop
            return view('shopify-app::auth.index');
        }

        if ($code && $code == config('shopify.impersonate_key')) {

            // Save shop domain to session
            session(['shopify_domain' => ShopifyApp::sanitizeShopDomain($shopDomain), 'impersonate' => 1]);

            $shop = (ShopifyApp::model())::whereShopifyDomain($shopDomain)->first();

            if ($shop) {
                $shop = ShopifyApp::login($shop);

                // Create api token
                $shop->apiToken(false);
            }
        }

        // Go to homepage of app
        return redirect()->route('home');
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
        $authUrl = $api->getAuthUrl(
            config('shopify.api_scopes'),
            url(config('shopify.api_redirect'))
        );

        // Do a Full page redirect
        return view('shopify-app::auth.fullpage_redirect', [
            'authUrl' => $authUrl,
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

        /**
         * @var BasicShopifyAPI $api
         */
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
                $shop = ShopifyApp::firstOrCreate($shopDomain, true);

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

                if (!$shop->isGrandfathered()) {

                    // Check Shop charge
                    $response = $this->verifyCharge($shop);

                    if (!is_bool($response)) {
                        return $response;
                    }
                }

                // Go to homepage of app
                return redirect()->route('home');
            } else {
                return redirect()->route('authenticate')->with(
                    'error',
                    "Unable to get access token. Please try again."
                );
            }
        } catch (\Exception $e) {
            return redirect()->route('authenticate')->with(
                'error',
                "Unable to get access token. Please try again. " . $e->getMessage()
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
        $api = $shop->api();
        $chargeId = $shop->charge_id;

        if ($chargeId) {

            /**
             * @var ShopSubscription $shopSubscription
             */
            $shopSubscription = $shop->subscriptions()->whereChargeId($chargeId)->first();

            if ($shopSubscription) {
                $subscription = $shopSubscription->subscription;
                $subscription = $subscription && $subscription->is_active == true && $subscription->plan && $subscription->plan->is_active == true ? $subscription : null;

                if ($subscription) {
                    $chargeType = $subscription->billing_type;

                    try {
                        $billingPlan = new BillingPlan($shop, $chargeType);
                        $billingPlan->setChargeId($chargeId);
                        $charge = $billingPlan->getCharge();

                        if ($charge
                            && (
                                $charge->test == 0
                                || Tester::whereShopifyDomain($shop->shopify_domain)->whereIsActive(true)->count()
                            )
                        ) {

                            // Accepted: Redirect to activate
                            if ($charge->status === "accepted") {
                                return redirect()->route('billing.process', ['charge_id' => $chargeId]);
                            } else {
                                if ($charge->status === "active") {
                                    return true;
                                } else {
                                    if ($charge->status === "pending") {
                                        return redirect($charge->confirmation_url);
                                    } else {
                                        $shop->charge_id = null;
                                        $shop->save();
                                        return true;
                                    }
                                }
                            }
                        } else {
                            $shop->charge_id = null;
                            $shop->save();
                            return true;
                        }
                    } catch (\Exception $e) {
                        $shop->charge_id = null;
                        $shop->save();
                        return true;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Get and Save shop details
     * @param $shop
     * @return mixed
     */
    protected function updateShopDetails($shop)
    {
        /**
         * @var BasicShopifyAPI $api
         */
        $api = $shop->api();

        $shopifyStore = $api->rest(
            'GET',
            '/admin/shop.json'
        );

        /**
         * @var \Shopify_Store_Data $shopifyStore
         */
        $shopifyStore = $shopifyStore && isset($shopifyStore->body) && isset($shopifyStore->body->shop) && $shopifyStore->body->shop ? $shopifyStore->body->shop : null;

        if ($shopifyStore) {
            $shop->update([
                "shopify_id" => $shopifyStore->id,
                "name" => $shopifyStore->name,
                "email" => $shopifyStore->email ? $shopifyStore->email : $shopifyStore->customer_email,
                "customer_email" => $shopifyStore->customer_email ? $shopifyStore->customer_email : '',
                "shop_owner" => isset($shopifyStore->shop_owner) && $shopifyStore->shop_owner ? $shopifyStore->shop_owner : '',
                "domain" => $shopifyStore->domain,
                "primary_locale" => isset($shopifyStore->primary_locale) && $shopifyStore->primary_locale ? $shopifyStore->primary_locale : '',
                "address_1" => isset($shopifyStore->address1) && $shopifyStore->address1 ? $shopifyStore->address1 : '',
                "city" => isset($shopifyStore->city) && $shopifyStore->city ? $shopifyStore->city : '',
                "phone" => isset($shopifyStore->phone) && $shopifyStore->phone ? $shopifyStore->phone : '',
                "province" => isset($shopifyStore->province) && $shopifyStore->province ? $shopifyStore->province : '',
                "province_code" => isset($shopifyStore->province_code) && $shopifyStore->province_code ? $shopifyStore->province_code : '',
                "country" => isset($shopifyStore->country) && $shopifyStore->country ? $shopifyStore->country : '',
                "country_name" => isset($shopifyStore->country_name) && $shopifyStore->country_name ? $shopifyStore->country_name : '',
                "country_code" => isset($shopifyStore->country_code) && $shopifyStore->country_code ? $shopifyStore->country_code : '',
                "zip" => isset($shopifyStore->zip) && $shopifyStore->zip ? $shopifyStore->zip : '',
                'latitude' => isset($shopifyStore->latitude) && $shopifyStore->latitude ? $shopifyStore->latitude : '',
                'longitude' => isset($shopifyStore->longitude) && $shopifyStore->longitude ? $shopifyStore->longitude : '',
                "currency" => isset($shopifyStore->currency) && $shopifyStore->currency ? $shopifyStore->currency : '',
                "money_format" => isset($shopifyStore->money_format) && $shopifyStore->money_format ? $shopifyStore->money_format : '',
                "money_with_currency_format" => isset($shopifyStore->money_with_currency_format) && $shopifyStore->money_with_currency_format ? $shopifyStore->money_with_currency_format : '',
                "timezone" => isset($shopifyStore->timezone) && $shopifyStore->timezone ? $shopifyStore->timezone : '',
                "iana_timezone" => isset($shopifyStore->iana_timezone) && $shopifyStore->iana_timezone ? $shopifyStore->iana_timezone : '',
                "shopify_plan_name" => isset($shopifyStore->plan_name) && $shopifyStore->plan_name ? $shopifyStore->plan_name : '',
                "shopify_plan_display_name" => isset($shopifyStore->plan_display_name) && $shopifyStore->plan_display_name ? $shopifyStore->plan_display_name : '',
                'status' => 1
            ]);
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
