<?php

namespace Centire\ShopifyApp\Middleware;

use Centire\ShopifyApp\Facades\ShopifyApp;
use Closure;
use Illuminate\Http\Request;

class AuthWebhook
{
    /**
     * Handle an incoming request to ensure webhook is valid.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $hmac = request()->header('x-shopify-hmac-sha256') ?: '';
        $shopDomain = request()->header('x-shopify-shop-domain');

        $apiSecret = config('shopify.api_secret');

        // Find shop details
        $shop = ShopifyApp::firstOrCreate($shopDomain, false);

        if($shop && $shop->isPrivateApp()){
            $apiSecret = $shop->api_secret;
        }

        $data = request()->getContent();

        // From https://help.shopify.com/api/getting-started/webhooks#verify-webhook
        $hmacLocal = base64_encode(hash_hmac('sha256', $data, $apiSecret, true));
        if (!hash_equals($hmac, $hmacLocal) || empty($shopDomain)) {

            // Just return with 201
            return response('', 201);

            // Issue with HMAC or missing shop header
            abort(401, 'Invalid webhook signature');
        }

        // All good, process webhook
        return $next($request);
    }
}
