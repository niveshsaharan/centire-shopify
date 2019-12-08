<?php

namespace Centire\ShopifyApp\Middleware;

use Closure;
use Illuminate\Http\Request;
use Centire\ShopifyApp\Facades\ShopifyApp;

class Billable
{
    /**
     * Checks if a shop has paid for access.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (config('shopify.billing_enabled') === true && config('shopify.billing_free_plan_enabled') !== true) {
            $shop = ShopifyApp::shop();

            if (!$shop) {
                abort(401, 'Authentication required.');
            }

            if (!$shop->isPaid() && !$shop->isGrandfathered()) {
                // They're not grandfathered in, and there is no charge or charge was declined... redirect to billing
                abort(402, 'Payment required.');
            }
        }

        // Move on, everything's fine
        return $next($request);
    }
}
