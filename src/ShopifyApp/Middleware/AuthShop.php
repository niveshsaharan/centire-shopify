<?php

namespace Centire\ShopifyApp\Middleware;

use Closure;
use Illuminate\Http\Request;
use Centire\ShopifyApp\Facades\ShopifyApp;
use Symfony\Component\HttpFoundation\Response;

class AuthShop
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $shop = ShopifyApp::shop();

        $shopParam = ShopifyApp::sanitizeShopDomain(request('shop'));

        // Check if shop has a session, also check the shops to ensure a match
        if (
            $shop === null ||
            ($shopParam && $shopParam !== $shop->shopify_domain) === true ||
            $shop->shopify_token === null || $shop->trashed()
        ) {
            ShopifyApp::logout();

            $routeParams = [];
            if ($shopParam) {
                $routeParams['shop'] = $shopParam;
            }

            return $request->expectsJson() ? abort(401, 'Authentication Required.') : redirect()->route('authenticate', $routeParams);
        }

        if (!$shop->isActive()) {
            return $request->expectsJson() ? abort(401, 'Authentication Required.') : redirect()->route('authenticate', ['shop' => $shop->shopify_domain]);
        }

        if($shop->isPrivateApp())
        {
            config()->set('shopify.api_key', $shop->api_key);
            config()->set('shopify.api_secret', $shop->api_secret);
            config()->set('shopify.api_password', $shop->shopify_token);
            config()->set('shopify.easdk_enabled', false);
        }

        // Shop is OK, move on...
        $response = $next($request);
        if (!$response instanceof Response) {
            // We need a response object to modify headers
            $response = new Response($response);
        }

        if (shopify_easdk_enabled()) {
            // Headers applicable to EASDK only
            $response->headers->set('P3P', 'CP="Not used"');
            $response->headers->remove('X-Frame-Options');
        }

        return $response;
    }
}
