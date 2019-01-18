<?php

namespace Centire\ShopifyApp\Tests;

use Centire\ShopifyApp\BasicShopifyAPI;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use ReflectionClass;
use Tests\TestCase;
use TypeError;
use Exception;

class BasicShopifyAPITest extends TestCase
{
    /**
     * @test
     *
     * Should set API to private mode
     */
    public function it_should_set_api_to_private_mode()
    {
        $api = new BasicShopifyAPI(true);
        $this->assertEquals(true, $api->isPrivate());
        $this->assertEquals(false, $api->isPublic());
    }

    /**
     * @test
     *
     * Should set API to public mode
     */
    public function it_should_set_api_to_public_mode()
    {
        $api = new BasicShopifyAPI();
        $this->assertEquals(false, $api->isPrivate());
        $this->assertEquals(true, $api->isPublic());
    }

    /**
     * @test
     *
     * Should set shop
     */
    public function it_should_set_shop()
    {
        $api = new BasicShopifyAPI();
        $api->setShop('example.myshopify.com');
        $this->assertEquals('example.myshopify.com', $api->getShop());
    }

    /**
     * @test
     *
     * Should set access token
     */
    public function it_should_set_access_token()
    {
        $api = new BasicShopifyAPI();
        $api->setAccessToken('123');
        $this->assertEquals('123', $api->getAccessToken());
    }

    /**
     * @test
     *
     * Should set API key and API password and API shared secret
     */
    public function it_should_set_api_key_and_password()
    {
        $api = new BasicShopifyAPI();
        $api->setApiKey('123');
        $api->setApiPassword('abc');
        $api->setApiSecret('!@#');
        $reflected = new ReflectionClass($api);
        $apiKeyProperty = $reflected->getProperty('apiKey');
        $apiKeyProperty->setAccessible(true);
        $apiPasswordProperty = $reflected->getProperty('apiPassword');
        $apiPasswordProperty->setAccessible(true);
        $apiSecretProperty = $reflected->getProperty('apiSecret');
        $apiSecretProperty->setAccessible(true);
        $this->assertEquals('123', $apiKeyProperty->getValue($api));
        $this->assertEquals('abc', $apiPasswordProperty->getValue($api));
        $this->assertEquals('!@#', $apiSecretProperty->getValue($api));
    }

    /**
     * @test
     *
     * Should allow for own client injection
     */
    public function it_should_allow_for_own_client()
    {
        $api = new BasicShopifyAPI();
        $api->setClient(new Client(['handler' => new MockHandler()]));
        $reflected = new ReflectionClass($api);
        $clientProperty = $reflected->getProperty('client');
        $clientProperty->setAccessible(true);
        $value = $clientProperty->getValue($api);
        $this->assertEquals('GuzzleHttp\Handler\MockHandler', get_class($value->getConfig('handler')));
    }

    /**
     * @test
     *
     * Check verify with no params
     */
    public function it_should_fail_request_verify_with_no_params()
    {
        $api = new BasicShopifyAPI();
        $api->setApiSecret('hush');
        $this->assertEquals(false, $api->verifyRequest([]));
    }

    /**
     * @test
     *
     * @expectedException TypeError
     *
     * Check verify with no params
     */
    public function it_should_fail_request_verify_with_no_params_again()
    {
        $api = new BasicShopifyAPI();
        $api->setApiSecret('hush');
        $this->assertEquals(false, $api->verifyRequest(null));
    }

    /**
     * @test
     *
     * @expectedException Exception
     * @expectedExceptionMessage API secret is missing
     *
     * Check verify without api secret
     */
    public function it_should_throw_error_on_verify_without_api_secret()
    {
        $api = new BasicShopifyAPI();
        $api->verifyRequest([]);
    }

    /**
     * @test
     *
     * Check verify with params
     */
    public function it_should_pass_request_verify_with_params()
    {
        $hmac = '4712bf92ffc2917d15a2f5a273e39f0116667419aa4b6ac0b3baaf26fa3c4d20';
        $params = [
            'code'      => '0907a61c0c8d55e99db179b68161bc00',
            'hmac'      => $hmac,
            'shop'      => 'some-shop.myshopify.com',
            'timestamp' => '1337178173',
        ];
        $api = new BasicShopifyAPI();
        $api->setApiSecret('hush');
        $this->assertEquals(true, $api->verifyRequest($params));
    }

    /**
     * @test
     *
     * Check verify with bad params
     */
    public function it_should_pass_request_verify_with_bad_params()
    {
        $hmac = '4712bf92ffc2917d15a2f5a273e39f0116667419aa4b6ac0b3baaf26fa3c4d20';
        $params = [
            'code' => '0907a61c0c8d55e99db179b68161bc00-OOPS',
            'hmac' => $hmac,
            'shop' => 'some-shop.myshopify.com',
        ];
        $api = new BasicShopifyAPI();
        $api->setApiSecret('hush');
        $this->assertEquals(false, $api->verifyRequest($params));
    }

    /**
     * @test
     *
     * Should set shop and access tokeb via quick method
     */
    public function it_should_set_session()
    {
        $api = new BasicShopifyAPI();
        $api->setSession('example.myshopify.com', '1234');
        $this->assertEquals('example.myshopify.com', $api->getShop());
        $this->assertEquals('1234', $api->getAccessToken());
    }

    /**
     * @test
     *
     * Should isolate API session
     */
    public function it_should_with_session()
    {
        $self = $this;
        $api = new BasicShopifyAPI();
        // Isolated for a shop
        $api->withSession('example.myshopify.com', '1234', function () use (&$self) {
            $self->assertEquals('example.myshopify.com', $this->getShop());
            $self->assertEquals('1234', $this->getAccessToken());
            $self->assertInstanceOf(BasicShopifyAPI::class, $this);
        });
        // Isolated for a shop
        $api->withSession('example2.myshopify.com', '12345', function () use (&$self) {
            $self->assertEquals('example2.myshopify.com', $this->getShop());
            $self->assertEquals('12345', $this->getAccessToken());
            $self->assertInstanceOf(BasicShopifyAPI::class, $this);
        });
        // Isolated for a shop and returns a value
        $valueReturn = $api->withSession('example2.myshopify.com', '12345', function () use (&$self) {
            return $this->getAccessToken();
        });
        $this->assertEquals($valueReturn, '12345');
        // Should remain untouched
        $this->assertEquals($api->getShop(), null);
        $this->assertEquals($api->getAccessToken(), null);
    }

    /**
     * @test
     * @expectedException TypeError
     *
     * Ensure a closure is passed to withSession
     */
    public function it_should_throw_exception_for_session_with_no_closure()
    {
        $api = new BasicShopifyAPI();
        $api->withSession('example.myshopify.com', '1234', null);
    }

    /**
     * @test
     *
     * Should get access token from Shopify
     */
    public function it_should_get_access_token_from_shopify()
    {
        $response = new Response(
            200,
            [],
            file_get_contents(__DIR__ . '/Fixtures/admin__oauth__access_token.json')
        );
        $mock = new MockHandler([$response]);
        $client = new Client(['handler' => $mock]);
        $api = new BasicShopifyAPI();
        $api->setShop('example.myshopify.com');
        $api->setApiKey('123');
        $api->setApiSecret('abc');
        $api->setClient($client);
        $code = '!@#';
        $token = $api->requestAccessToken($code);
        $data = json_decode($mock->getLastRequest()->getBody());
        $this->assertEquals('f85632530bf277ec9ac6f649fc327f17', $token);
        $this->assertEquals('abc', $data->client_secret);
        $this->assertEquals('123', $data->client_id);
        $this->assertEquals($code, $data->code);
    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage Shopify domain missing for API calls
     *
     * Ensure Shopify domain is there for grabbing the access tokens
     */
    public function it_should_throw_exception_for_missing_shop_on_access_token_request()
    {
        $api = new BasicShopifyAPI(true);
        $api->requestAccessToken('123');
    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage API key or secret is missing
     *
     * Ensure Shopify API secret is there for grabbing the access tokens
     */
    public function it_should_throw_exception_for_missing_api_secret_on_access_token_request()
    {
        $api = new BasicShopifyAPI(true);
        $api->setShop('example.myshopify.com');
        $api->requestAccessToken('123');
    }

    /**
     * @test
     *
     * Should get auth URL
     */
    public function it_should_return_auth_url()
    {
        $api = new BasicShopifyAPI();
        $api->setShop('example.myshopify.com');
        $api->setApiKey('123');
        $this->assertEquals(
            'https://example.myshopify.com/admin/oauth/authorize?client_id=123&scope=read_products,write_products&redirect_uri=https://localapp.local/',
            $api->getAuthUrl(['read_products', 'write_products'], 'https://localapp.local/')
        );
    }

    /**
     * @test
     *
     * @expectedException Exception
     * @expectedExceptionMessage Shopify domain missing for API calls
     *
     * Should throw error for missing shop on auth call
     */
    public function it_should_throw_error_for_missing_shop_domain_on_auth_call()
    {
        $api = new BasicShopifyAPI();
        $api->getAuthUrl(['read_products', 'write_products'], 'https://localapp.local/');
    }

    /**
     * @test
     *
     * @expectedException Exception
     * @expectedExceptionMessage API key is missing
     *
     * Should throw error for missing API key on auth call
     */
    public function it_should_throw_error_for_missing_api_key_on_auth_call()
    {
        $api = new BasicShopifyAPI();
        $api->setShop('example.myshopify.com');
        $api->getAuthUrl(['read_products', 'write_products'], 'https://localapp.local/');
    }

    /**
     * @test
     *
     * Checking base URL for API calls on private
     */
    public function it_should_return_private_base_url()
    {
        $response = new Response(
            200,
            ['http_x_shopify_shop_api_call_limit' => '2/80'],
            file_get_contents(__DIR__ . '/Fixtures/admin_shop.json')
        );
        $mock = new MockHandler([$response]);
        $client = new Client(['handler' => $mock]);
        $api = new BasicShopifyAPI(true);
        $api->setClient($client);
        $api->setShop('example.myshopify.com');
        $api->setApiKey('123');
        $api->setApiPassword('abc');
        $api->rest('GET', '/admin/shop.json');
        $lastRequest = $mock->getLastRequest()->getUri();
        $this->assertEquals('https', $lastRequest->getScheme());
        $this->assertEquals('example.myshopify.com', $lastRequest->getHost());
        $this->assertEquals('123:abc', $lastRequest->getUserInfo());
        $this->assertEquals('/admin/shop.json', $lastRequest->getPath());
    }

    /**
     * @test
     *
     * Checking base URL for API calls on public
     */
    public function it_should_return_public_base_url()
    {
        $response = new Response(
            200,
            ['http_x_shopify_shop_api_call_limit' => '2/80'],
            file_get_contents(__DIR__ . '/Fixtures/admin_shop.json')
        );
        $mock = new MockHandler([$response]);
        $client = new Client(['handler' => $mock]);
        $api = new BasicShopifyAPI();
        $api->setClient($client);
        $api->setShop('example.myshopify.com');
        $api->rest('GET', '/admin/shop.json');
        $lastRequest = $mock->getLastRequest()->getUri();
        $this->assertEquals('https', $lastRequest->getScheme());
        $this->assertEquals('example.myshopify.com', $lastRequest->getHost());
        $this->assertEquals(null, $lastRequest->getUserInfo());
        $this->assertEquals('/admin/shop.json', $lastRequest->getPath());
    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage Shopify domain missing for API calls
     *
     * Ensure Shopify domain is there for baseURL
     */
    public function it_should_throw_exception_for_missing_domain()
    {
        $api = new BasicShopifyAPI();
        $api->rest('GET', '/admin/shop.json');
    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage API key and password required for private Shopify REST calls
     *
     * Ensure Shopify API details is passsed for private API calls
     */
    public function itShouldThrowExceptionForMissingApiDetails()
    {
        $api = new BasicShopifyAPI(true);
        $api->setShop('example.myshopify.com');
        $api->rest('GET', '/admin/shop.json');
    }

    /**
     * @test
     *
     * Should get Guzzle response and JSON body
     */
    public function itShouldReturnGuzzleResponseAndJsonBody()
    {
        $response = new Response(
            200,
            ['http_x_shopify_shop_api_call_limit' => '2/80'],
            file_get_contents(__DIR__ . '/Fixtures/admin_shop.json')
        );
        $mock = new MockHandler([$response]);
        $client = new Client(['handler' => $mock]);
        $api = new BasicShopifyAPI();
        $api->setClient($client);
        $api->setShop('example.myshopify.com');
        $api->setApiKey('123');
        $api->setAccessToken('!@#');
        // Fake param just to test it receives it
        $request = $api->rest('GET', '/admin/shop.json', ['limit' => 1, 'page' => 1]);
        $data = $mock->getLastRequest()->getUri()->getQuery();
        $token_header = $mock->getLastRequest()->getHeader('X-Shopify-Access-Token')[0];
        $this->assertEquals(true, is_object($request));
        $this->assertInstanceOf('GuzzleHttp\Psr7\Response', $request->response);
        $this->assertEquals(200, $request->response->getStatusCode());
        $this->assertEquals(true, is_object($request->body));
        $this->assertEquals('Apple Computers', $request->body->shop->name);
        $this->assertEquals('limit=1&page=1', $data);
        $this->assertEquals('!@#', $token_header);
    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage Invalid API call limit key. Valid keys are: left, made, limit
     *
     * Ensure we pass a valid key to the API calls
     */
    public function itShouldThrowExceptionForInvalidApiCallsKey()
    {
        $api = new BasicShopifyAPI();
        $api->getApiCalls('rest', 'oops');
    }

    /**
     * @test
     *
     * Should get API call limits
     */
    public function itShouldReturnApiCallLimits()
    {
        $response = new Response(200, ['http_x_shopify_shop_api_call_limit' => '2/80'], '{}');
        $mock = new MockHandler([$response]);
        $client = new Client(['handler' => $mock]);
        $api = new BasicShopifyAPI();
        $api->setClient($client);
        $api->setShop('example.myshopify.com');
        $api->setApiKey('123');
        $api->setAccessToken('!@#');
        $api->rest('GET', '/admin/shop.json');
        $this->assertEquals(2, $api->getApiCalls('rest', 'made'));
        $this->assertEquals(80, $api->getApiCalls('rest', 'limit'));
        $this->assertEquals(80 - 2, $api->getApiCalls('rest', 'left'));
        $this->assertEquals(['left' => 80 - 2, 'made' => 2, 'limit' => 80], $api->getApiCalls('rest'));
    }

    /**
     * @test
     *
     * Should use query for GET requests
     */
    public function itShouldUseQueryForGetMethod()
    {
        $response = new Response(200, ['http_x_shopify_shop_api_call_limit' => '2/80'], '{}');
        $mock = new MockHandler([$response]);
        $client = new Client(['handler' => $mock]);
        $api = new BasicShopifyAPI();
        $api->setClient($client);
        $api->setShop('example.myshopify.com');
        $api->setApiKey('123');
        $api->setAccessToken('!@#');
        $api->rest('GET', '/admin/shop.json', ['limit' => 1, 'page' => 1]);
        $this->assertEquals('limit=1&page=1', $mock->getLastRequest()->getUri()->getQuery());
        $this->assertNull(json_decode($mock->getLastRequest()->getBody()));
    }

    /**
     * @test
     *
     * Should use JSON for non-GET methods
     */
    public function itShouldUseJsonForNonGetMethods()
    {
        $response = new Response(200, ['http_x_shopify_shop_api_call_limit' => '2/80'], '{}');
        $mock = new MockHandler([$response]);
        $client = new Client(['handler' => $mock]);
        $api = new BasicShopifyAPI();
        $api->setClient($client);
        $api->setShop('example.myshopify.com');
        $api->setApiKey('123');
        $api->setAccessToken('!@#');
        $api->rest('POST', '/admin/gift_cards.json', ['gift_cards' => ['initial_value' => 25.00]]);
        $this->assertEquals('', $mock->getLastRequest()->getUri()->getQuery());
        $this->assertNotNull(json_decode($mock->getLastRequest()->getBody()));
    }

    /**
     * @test
     *
     * Should alias request to REST method
     */
    public function itShouldAliasRequestToRestMethod()
    {
        $response = new Response(
            200,
            ['http_x_shopify_shop_api_call_limit' => '2/80'],
            file_get_contents(__DIR__ . '/Fixtures/admin_shop.json')
        );
        $mock = new MockHandler([$response]);
        $client = new Client(['handler' => $mock]);
        $api = new BasicShopifyAPI();
        $api->setClient($client);
        $api->setShop('example.myshopify.com');
        $request = $api->request('GET', '/admin/shop.json');
        $this->assertEquals(true, is_object($request->body));
        $this->assertEquals('Apple Computers', $request->body->shop->name);
    }
}
