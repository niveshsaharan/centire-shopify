<?php

namespace Centire\ShopifyApp\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{

    /**
     * table name
     * @var string
     */
    protected $table = 'subscriptions';

    /**
     * Cast columns
     * @var array
     */
    public $casts = [
        'metadata' => 'json',
    ];

    /**
     * Fillable columns
     * @var array
     */
    protected $fillable = [

    ];

    /**
     * A subscription may be subscribed by multiple users
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscribers()
    {
        return $this->hasMany(ShopSubscription::class, 'subscription_id');
    }


    /**
     * Each subscription belongs to a plan
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }


    /**
     * Store discounts
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function discounts()
    {
        return $this->hasMany(Discount::class, 'subscription_id');
    }
}
