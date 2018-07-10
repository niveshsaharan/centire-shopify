<?php

namespace Centire\ShopifyApp\Models;


trait SettingTrait
{
	/**
	 * Setting belongs to many shops
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 */
	public function shops()
	{
		return $this->belongsToMany(\App\Shop::class, 'shop_setting', 'setting_id');
	}

	/**
	 * Get settings
	 *
	 * @param $shop
	 *
	 * @return array
	 */
	public static function getSettings($shop, $flat = false)
	{
		$settings = (new \App\Setting())->distinct()->leftJoin('shop_setting', function ($join) use ($shop) {
			$join->on('settings.id', '=', 'shop_setting.setting_id');
			$join->on('shop_setting.shop_id', '=', \DB::raw($shop->id));
		})->select([
			'settings.id',
			'shop_setting.id as shop_setting_id',
			'settings.name',
			'settings.value as default_value',
			'settings.label',
			'settings.placeholder',
			'settings.description',
			'settings.type',
			'shop_setting.value',
		])->get()->keyBy('name')->toArray();

		if ($flat) {
			$settingsFlat = [];

			foreach ($settings as $key => $setting) {
				$settingsFlat[$key] = app_setting(['custom' => $setting], 'custom', true);
			}

			return $settingsFlat;
		}

		return $settings;
	}
}
