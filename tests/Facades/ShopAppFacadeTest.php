<?php

namespace Centire\ShopifyApp\Test\Facades;

use Centire\ShopifyApp\Facades\ShopifyApp;
use Tests\TestCase;
use ReflectionMethod;

class ShopAppFacadeTest extends TestCase
{
    /** @test */
    public function can_run()
    {
        $method = new ReflectionMethod(ShopifyApp::class, 'getFacadeAccessor');
        $method->setAccessible(true);

        $this->assertEquals('shopifyapp', $method->invoke(null));
    }
}
