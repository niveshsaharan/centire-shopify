<?php

namespace Centire\ShopifyApp\Test\Console;

use App\Console\Commands\UpdateAppScript;
use Tests\TestCase;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Tester\CommandTester;

class UpdateAppScriptTest extends TestCase
{
    /** @test */
    public function can_run_successfully()
    {
        \File::delete(resource_path('assets/shopify/app.js'));

        $application = new ConsoleApplication();

        $testedCommand = $this->app->make(UpdateAppScript::class);
        $testedCommand->setLaravel($this->app);
        $application->add($testedCommand);

        $command = $application->find('scripts:bootstrap:update');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $output = $commandTester->getDisplay();

        $this->assertContains('Compiling Bootstrap script...', $output);
        $this->assertContains('Bootstrap Script is compiled successfully', $output);
        $this->assertFileExists(resource_path('assets/shopify/app.js'));
    }
}
