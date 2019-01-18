<?php

namespace Centire\ShopifyApp\Test\Console;

use Centire\ShopifyApp\Console\WebhookJobMakeCommand;
use Tests\TestCase;
use ReflectionMethod;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Tester\CommandTester;

class WebhookJobMakeCommandTest extends TestCase
{
    /** @test */
    public function can_run_successfully()
    {
        $application = new ConsoleApplication();

        $testedCommand = $this->app->make(WebhookJobMakeCommand::class);
        $testedCommand->setLaravel($this->app);
        $application->add($testedCommand);

        $command = $application->find('shopify:make:webhook');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'command' => $command->getName(),
            'name'    => 'TestordersCreateJob',
            'topic'   => 'testorders/create',
        ]);

        $output = $commandTester->getDisplay();

        $this->assertContains("Don't forget to register the webhook in config/shopify.php", $output);
        $this->assertContains("'address' => 'https://your-domain.com/webhook/testorders-create'", $output);
        $this->assertContains("'topic' => 'testorders/create',", $output);

        \File::delete(app_path('/Jobs/TestordersCreateWebhookJob.php'));
    }

    /** @test */
    public function can_generate_url_from_name()
    {
        $application = new ConsoleApplication();
        $testedCommand = $this->app->make(WebhookJobMakeCommand::class);
        $testedCommand->setLaravel($this->app);
        $application->add($testedCommand);

        $command = $application->find('shopify:make:webhook');

        $method = new ReflectionMethod($command, 'getUrlFromName');
        $method->setAccessible(true);

        $result = $method->invoke($command, 'OrdersCreateWebhookJob');
        $result2 = $method->invoke($command, 'OrdersCreate');
        $result3 = $method->invoke($command, 'OrdersCreateCustomJob');

        $this->assertEquals($result, 'orders-create');
        $this->assertEquals($result2, 'orders-create');
        $this->assertEquals($result3, 'orders-create-custom');
    }

    /** @test */
    public function returns_stub()
    {
        $application = new ConsoleApplication();
        $testedCommand = $this->app->make(WebhookJobMakeCommand::class);
        $testedCommand->setLaravel($this->app);
        $application->add($testedCommand);

        $command = $application->find('shopify:make:webhook');

        $method = new ReflectionMethod($command, 'getStub');
        $method->setAccessible(true);

        $result = $method->invoke($command);

        $this->assertContains('/stubs/webhook-job.stub', $result);
    }
}
