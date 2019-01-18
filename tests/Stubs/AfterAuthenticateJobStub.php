<?php

namespace App\Jobs;

use App\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AfterAuthenticateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Shop $shop
     */
    public $shop;

    public function __construct($shop)
    {
        $this->shop = $shop;
    }

    public function handle()
    {
        return true;
    }
}
