<?php

namespace Centire\ShopifyApp;

use Illuminate\Foundation\Application;

class ShopifyApp
{
	/**
	 * Laravel application.
	 *
	 * @var \Illuminate\Foundation\Application
	 */
	public $app;

	/**
	 * The current shop.
	 *
	 * @var \App\Shop
	 */
	public $shop;

	/**
	 * Create a new confide instance.
	 *
	 * @param \Illuminate\Foundation\Application $app
	 *
	 * @return void
	 */
	public function __construct(Application $app)
	{
		$this->app = $app;
	}

	/**
	 *  Gets/sets the current shop.
	 * @return \App\Shop|\Illuminate\Contracts\Auth\Authenticatable|null
	 */
	public function shop()
	{
		if (!$this->shop) {
			$guard = request()->route()->getAction('guard') ?: 'shop.web';

			$shop = auth($guard)->user();

			// Update shop instance
			$this->shop = $shop;
		}

		return $this->shop;
	}

	/**
	 * @return \Illuminate\Config\Repository|mixed
	 */
	public function model()
	{
		$shopModel = config('shopify.shop_model');

		return class_exists($shopModel) ? $shopModel : \App\Shop::class;
	}

	/**
	 * First or create
	 * @param $shopifyDomain
	 * @return mixed
	 */
	public function firstOrCreate($shopifyDomain, $shouldLogin = false)
	{
		$shopModel = $this->model();
		$shop = $shopModel::firstOrCreate(['shopify_domain' => $shopifyDomain]);

		if ($shouldLogin) {
			$shop = $this->login($shop);
		}

		return $shop;
	}

	/**
	 * Login
	 * @param $shop
	 */
	public function login($shop)
	{
		$guard = request()->route()->getAction('guard') ?: 'shop.web';

		// Login to shop
		auth($guard)->login($shop);

		$shop = auth($guard)->user();

		$this->shop = $shop;

		return $this->shop;
	}

	/**
	 * Logout
	 */
	public function logout()
	{
		if ($this->shop) {
			$guard = request()->route()->getAction('guard') ?: 'shop.web';

			auth($guard)->logout();

			// Update shop instance
			$this->shop = null;
		}

		session()->forget('shopify_domain');
	}

	/**
	 * Gets an API instance.
	 *
	 * @return BasicShopifyAPI
	 */
	public function api()
	{
		/**
		 * @var BasicShopifyAPI $api
		 */
		$api = new BasicShopifyAPI();
		$api->setApiKey(config('shopify.api_key'));
		$api->setApiSecret(config('shopify.api_secret'));

		return $api;
	}

	/**
	 * Ensures shop domain meets the specs.
	 *
	 * @param string $domain The shopify domain
	 *
	 * @return string|null|mixed
	 */
	public function sanitizeShopDomain($domain)
	{
		if (empty($domain)) {
			return null;
		}

		$configEndDomain = config('shopify.myshopify_domain');
		$domain = preg_replace('/https?:\/\//i', '', trim($domain));

		if (strpos($domain, $configEndDomain) === false && strpos($domain, '.') === false) {
			// No myshopify.com ($configEndDomain) in shop's name
			$domain .= ".{$configEndDomain}";
		}

		// Return the host after cleaned up
		return parse_url("http://{$domain}", PHP_URL_HOST);
	}
}
