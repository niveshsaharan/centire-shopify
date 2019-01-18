<?php

namespace Centire\ShopifyApp\Test\Middleware;

use App\Http\Middleware\Cors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CorsTest extends TestCase
{
    use RefreshDatabase;

    public function setUp()
    {
        parent::setUp();
    }

    /** @test */
    public function cors_headers_are_set()
    {
        $response = \Mockery::Mock(Response::class)
            ->shouldReceive('header')
            ->with('Access-Control-Allow-Origin', '*')
            ->shouldReceive('header')
            ->with('Access-Control-Allow-Methods', 'HEAD, GET, PUT, PATCH, POST')
            ->getMock();

        $request = Request::create('/', 'GET');

        $middleware = new Cors();

        $middleware->handle($request, function () use ($response) {
            return $response;
        });
    }
}
