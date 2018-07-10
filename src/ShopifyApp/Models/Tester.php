<?php

namespace Centire\ShopifyApp\Models;

use Illuminate\Database\Eloquent\Model;

class Tester extends Model
{

    /**
     * Table name
     * @var string
     */
	protected $table = 'testers';

    /**
     * Fillable columns
     * @var array
     */
	protected $fillable = [
		'shopify_domain',
		'is_active',
	];
}
