<?php

namespace Centire\ShopifyApp\Test\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Input;
use Centire\ShopifyApp\Middleware\AuthProxy;
use Tests\TestCase;

class AuthProxyMiddlewareTest extends TestCase
{
	use RefreshDatabase;

	/**
	 * @var array $queryParams
	 */
	protected $queryParams;

	public function setUp()
	{
		parent::setUp();

		// From Shopify's docs
		$this->queryParams = [
			'extra'       => ['1', '2'],
			'shop'        => 'shop-name.myshopify.com',
			'path_prefix' => '/apps/awesome_reviews',
			'timestamp'   => '1317327555',
			'signature'   => 'a9718877bea71c2484f91608a7eaea1532bdf71f5c56825065fa4ccabe549ef3',
		];

		// Set the app secret to match Shopify's docs
		config(['shopify.api_secret' => 'hush']);
	}

	/**
	 * @test
	 */
	public function missing_shop_throws_exception()
	{
		$query = $this->queryParams;
		unset($query['shop']);
		Input::merge($query);

		$called = false;
		try {
			(new AuthProxy())->handle(request(), function ($request) use (&$called) {
				// Should never be called
				$called = true;
			});
		} catch (\Exception $e) {
			$this->assertEquals('Invalid proxy signature', $e->getMessage());
			$this->assertEquals(false, $called);

			return;
		}

		$this->fail('Test passed without shop.');
	}

	/** @test */
	public function valid_request_passed()
	{
		Input::merge($this->queryParams);

		// Confirm no shop
		$this->assertEquals(null, session('shopify_domain'));

		$called = false;
		(new AuthProxy())->handle(request(), function ($request) use (&$called) {
			// Should be called
			$called = true;

			// Session should be set by now
			$this->assertEquals($this->queryParams['shop'], session('shopify_domain'));
		});

		// Confirm full run
		$this->assertEquals(true, $called);
	}

	/** @test */
	public function invalid_signature_should_return_error()
	{
		$query = $this->queryParams;
		$query['oops'] = 'i-did-it-again';
		Input::merge($query);

		$called = false;

		try {
			(new AuthProxy())->handle(request(), function ($request) use (&$called) {
				// Should never be called
				$called = true;
			});
		} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
			$this->assertEquals('Invalid proxy signature', $e->getMessage());
			$this->assertEquals(false, $called);

			return;
		}

		$this->fail('Test passed with invalid signature.');
	}
}
