<?php

namespace Centire\ShopifyApp\Test\Console;

use App\Console\Commands\UpdateShopifyReviewProgress;
use App\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Tester\CommandTester;

class UpdateShopifyReviewProgressTest extends TestCase
{
	use RefreshDatabase;

	/** @test */
	public function can_run_successfully()
	{
		config(['shopify.app_slug' => 'app-reviews']);
		config(['filesystems.disks.local.global' => app_path('../tests/fixtures')]);

		$shop = factory(Shop::class)->create([
			'shopify_domain' => 'example.myshopify.com',
		]);

		$shop2 = factory(Shop::class)->create([
			'shopify_domain' => 'example1.myshopify.com',
		]);

		$application = new ConsoleApplication();

		$testedCommand = $this->app->make(UpdateShopifyReviewProgress::class);
		$testedCommand->setLaravel($this->app);
		$application->add($testedCommand);

		$command = $application->find('progress:update:reviews');
		$commandTester = new CommandTester($command);

		$commandTester->execute([
			'command' => $command->getName(),
		]);

		$output = $commandTester->getDisplay();

		$this->assertContains('1 reviews found.', $output);
		$this->assertDatabaseHas('shop_setting', [
		   'shop_id'    => $shop->id,
		   'setting_id' => 100030,
		   'value'      => 5,
		]);
		$this->assertDatabaseMissing('shop_setting', [
		   'shop_id'    => $shop2->id,
		   'setting_id' => 100030,
		   'value'      => 5,
		]);
	}
}
