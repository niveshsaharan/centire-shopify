<?php

namespace Centire\ShopifyApp\Console;

use Illuminate\Foundation\Console\JobMakeCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;

class WebhookJobMakeCommand extends JobMakeCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'shopify:make:webhook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new webhook job class';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__ . '/stubs/webhook-job.stub';
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the class'],
            ['topic', InputArgument::REQUIRED, 'The event/topic for the job (orders/create, products/update, etc)'],
        ];
    }

    /**
     * Execute the console command (>=5.5).
     *
     * @return void
     */
    public function handle()
    {
        // Fire parent... handle for >=5.5, fire for <5.5
        $method = method_exists($this, 'handle') ? 'handle' : 'fire';
        parent::$method();

        // Remind user to enter job into config
        $this->info("Don't forget to register the webhook in config/shopify.php. Example:");
        $this->info("
    'webhooks' => [
        [
            'topic' => '{$this->argument('topic')}',
            'address' => 'https://your-domain.com/webhook/{$this->getUrlFromName($this->getNameInput())}'
        ]
    ]
        ");
    }


    /**
     * Get the desired class name from the input.
     *
     * @return string
     */
    protected function getNameInput()
    {
        $name = trim($this->argument('name'));
        if (Str::endsWith($name, 'WebhookJob')) {
        } else {
            if (Str::endsWith($name, 'Job')) {
                $name = substr($name, 0, -3) . 'WebhookJob';
            } else {
                $name = $name . 'WebhookJob';
            }
        }

        return $name;
    }

    /**
     * Converts the job class name into a URL endpoint.
     *
     * @param string $name The name of the job
     *
     * @return string
     */
    protected function getUrlFromName(string $name)
    {
        if (Str::endsWith($name, 'WebhookJob')) {
            $name = substr($name, 0, -10);
        }

        if (Str::endsWith($name, 'Job')) {
            $name = substr($name, 0, -3);
        }

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $name));
    }
}
