<?php

namespace Centire\ShopifyApp\Models;

trait ShopSettingTrait
{
	/**
	 * Belongs to a setting
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function setting()
	{
		return $this->belongsTo(\App\Setting::class, 'setting_id');
	}

	/**
	 * Belongs to a shop
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function shop()
	{
		return $this->belongsTo(\App\Shop::class, 'shop_id');
	}
}
