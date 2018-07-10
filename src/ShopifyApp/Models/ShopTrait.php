<?php

namespace Centire\ShopifyApp\Models;

use Centire\ShopifyApp\Facades\ShopifyApp;

trait ShopTrait
{
	/**
	 * The API instance.
	 *
	 * @var object
	 */
	protected $api;

	/**
	 * Generate Shop Token if doesn't exists
	 * @return mixed|string
	 */
	public function apiToken($forceCreate = true)
	{
		if ($forceCreate || !$this->api_token) {
			$this->api_token = substr(bin2hex(random_bytes(7)) . md5($this->id) . bin2hex(random_bytes(7)), 0, 60);
			$this->save();
		}

		return $this->api_token;
	}

	/**
	 * Creates or returns an instance of API for the shop.
	 *
	 * @return object
	 */
	public function api()
	{
		if (!$this->api) {
			// Create new API instance
			$api = ShopifyApp::api();
			$api->setSession($this->shopify_domain, $this->shopify_token);

			$this->api = $api;
		}

		// Return existing instance
		return $this->api;
	}

	/**
	 * Check if shop has give scope
	 *
	 * @param [type] $scope
	 * @return boolean
	 */
	public function hasScope($scope)
	{
		return $this->shopify_scopes && is_array($this->shopify_scopes) && in_array($scope, $this->shopify_scopes);
	}

	/**
	 * Checks if a shop has a charge ID.
	 *
	 * @return bool
	 */
	public function isPaid()
	{
		return !is_null($this->charge_id);
	}

	/**
	 * Checks is shop is grandfathered in.
	 *
	 * @return bool
	 */
	public function isGrandfathered()
	{
		return ((bool)$this->grandfathered) === true;
	}

	/**
	 * Checks is shop is active.
	 *
	 * @return bool
	 */
	public function isActive()
	{
		return ((bool)$this->status) === true;
	}

	/**
	 * Get settings
	 * @return array
	 */
	public function getSettings($flat = false)
	{
		return \App\Setting::getSettings($this, $flat);
	}

	/**
	 * Shop may have multiple settings
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 */
	public function settings()
	{
		return $this->belongsToMany(\App\Setting::class, 'shop_setting', 'shop_id')->withPivot(['value']);
	}

	/**
	 * Shop may have many subscriptions
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 */
	public function subscriptions()
	{
		return $this->hasMany(ShopSubscription::class, 'shop_id');
	}
}
