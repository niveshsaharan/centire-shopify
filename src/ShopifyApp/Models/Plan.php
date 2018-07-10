<?php

namespace Centire\ShopifyApp\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    /**
     * Table name
     * @var string
     */
    protected $table = 'plans';

    /**
     * Fillable columns
     * @var array
     */
    protected $fillable = [
        'name',
        'display_name',
        'identifier',
        'description',
        'priority',
        'is_active'
    ];

    /**
     * Plan may have multiple subscriptions
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function options()
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }
}
