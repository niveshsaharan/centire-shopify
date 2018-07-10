<?php

namespace Centire\ShopifyApp\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    /**
     * Table name
     * @var string
     */
    protected $table = 'discounts';

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
        'shopify_domain',
        'coupon_code',
        'discount_type',
        'discount',
        'subscription_id',
        'metadata',
        'is_active',
    ];
}
