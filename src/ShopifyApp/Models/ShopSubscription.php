<?php

namespace Centire\ShopifyApp\Models;

use Illuminate\Database\Eloquent\Model;

class ShopSubscription extends Model
{

    /**
     * Table name
     * @var string
     */
	protected $table = 'shop_subscription';

    /**
     * Fillable columns
     * @var array
     */
	protected $fillable = [
		'shop_id',
		'subscription_id',
		'trial_days',
		'charge_id',
		'discount',
		'billing_on',
		'trial_ends_on',
		'activated_on',
		'cancelled_on',
		'created_at',
		'updated_at',
		'is_active',
	];

    /**
     * timestamp columns
     * @var array
     */
	public $dates = [
		'billing_on',
		'cancelled_on',
		'trial_ends_on',
		'activated_on',
	];

	/**
	 * Belongs to a subscription
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function subscription()
	{
		return $this->belongsTo(Subscription::class, 'subscription_id');
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
