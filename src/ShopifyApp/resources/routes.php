<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| All the routes for the Shopify App setup.
|
*/

Route::group(['middleware' => ['web']], function () {
	/*
	|--------------------------------------------------------------------------
	| Login Route
	|--------------------------------------------------------------------------
	|
	| Allows a shop to login/install.
	|
	*/

	Route::get('/login', 'Centire\ShopifyApp\Controllers\AuthController@index')
		->name('login');

	/*
	|--------------------------------------------------------------------------
	| Authenticate Method
	|--------------------------------------------------------------------------
	|
	| Authenticates a shop.
	|
	*/

	Route::match(
		['get', 'post'],
		'/auth',
		'Centire\ShopifyApp\Controllers\AuthController@authenticate'
	)
		->name('authenticate');

	/*
	|--------------------------------------------------------------------------
	| Impersonate Method
	|--------------------------------------------------------------------------
	|
	| Impersonate a shop.
	|
	*/

	Route::match(['get', 'post'], '/impersonate', 'Centire\ShopifyApp\Controllers\AuthController@impersonate')
		->name('impersonate');

	/*
	|--------------------------------------------------------------------------
	| Billing Handler
	|--------------------------------------------------------------------------
	|
	| Billing handler. Sends to billing screen for Shopify.
	|
	*/

	Route::get('/billing', 'Centire\ShopifyApp\Controllers\BillingController@index')
		->name('billing');

	/*
	|--------------------------------------------------------------------------
	| Billing Processor
	|--------------------------------------------------------------------------
	|
	| Processes the customer's response to the billing screen.
	|
	*/

	Route::get('/billing/process', 'Centire\ShopifyApp\Controllers\BillingController@process')
		->name('billing.process');
});

Route::group(['middleware' => ['api']], function () {
	/*
	|--------------------------------------------------------------------------
	| Webhook Handler
	|--------------------------------------------------------------------------
	|
	| Handles incoming webhooks.
	|
	*/

	Route::post('/webhook/{type}', 'Centire\ShopifyApp\Controllers\WebhookController@handle')
		->middleware('auth.webhook')
		->name('webhook');
});
