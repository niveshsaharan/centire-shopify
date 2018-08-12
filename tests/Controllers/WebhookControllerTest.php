<?php

namespace Centire\ShopifyApp\Test\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Centire\ShopifyApp\Controllers\WebhookController;
use ReflectionMethod;
use Centire\ShopifyApp\Test\Stubs\ApiStub;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
	use RefreshDatabase;

	/**
	 * @var $headers
	 */
	protected $headers;

	public function setUp()
	{
		parent::setUp();

		$this->headers = [
			'HTTP_CONTENT_TYPE'          => 'application/json',
			'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'example.myshopify.com',
			'HTTP_X_SHOPIFY_HMAC_SHA256' => 'hDJhTqHOY7d5WRlbDl4ehGm/t4kOQKtR+5w6wm+LBQw=', // Matches fixture data and API secret
		];

		// Stub in our API class
		config(['shopify.api_class' => new ApiStub()]);
	}

	private function hmac($data)
	{
		return  base64_encode(hash_hmac('sha256', $data, config('shopify.api_secret'), true));
	}

	/** @test */
	public function valid_request_pushes_webhook_job_to_queue()
	{
		Queue::fake();
		$data = file_get_contents(__DIR__ . '/../Fixtures/webhook.json');
		$this->headers['HTTP_X_SHOPIFY_HMAC_SHA256'] = $this->hmac($data);
		$response = $this->call(
			'post',
			'/webhook/shop-update',
			[],
			[],
			[],
			$this->headers,
			$data
		);
		$response->assertStatus(201);

		Queue::assertPushed(\App\Jobs\ShopUpdateWebhookJob::class);
	}

	/** @test */
	public function missing_webhook_job_returns_error()
	{
		$data = file_get_contents(__DIR__ . '/../Fixtures/webhook.json');
		$this->headers['HTTP_X_SHOPIFY_HMAC_SHA256'] = $this->hmac($data);
		$response = $this->call(
			'post',
			'/webhook/products-create',
			[],
			[],
			[],
			$this->headers,
			$data
		);
		$response->assertStatus(500);

		$this->assertEquals('Missing webhook job for: products-create', $response->exception->getMessage());
	}

	/** @test */
	public function returns_webhook_job_class_name_for_webook_type()
	{
		$controller = new WebhookController();
		$method = new ReflectionMethod(WebhookController::class, 'getJobClassFromType');
		$method->setAccessible(true);

		$types = [
			'shop-update'       => 'ShopUpdateWebhookJob',
		];

		foreach ($types as $type => $className) {
			$this->assertEquals("\\App\\Jobs\\$className", $method->invoke($controller, $type));
		}
	}

	/** @test */
	public function valid_request_pushes_job_to_queu_with_data()
	{
		Queue::fake();
		$data = file_get_contents(__DIR__ . '/../Fixtures/webhook.json');
		$this->headers['HTTP_X_SHOPIFY_HMAC_SHA256'] = $this->hmac($data);
		$response = $this->call(
			'post',
			'/webhook/shop-update',
			[],
			[],
			[],
			$this->headers,
			$data
		);
		$response->assertStatus(201);

		Queue::assertPushed(\App\Jobs\ShopUpdateWebhookJob::class, function ($job) {
			return $job->shopDomain === 'example.myshopify.com'
				   && $job->data instanceof \stdClass
				   && $job->data->email === 'jon@doe.ca';
		});
	}
}
