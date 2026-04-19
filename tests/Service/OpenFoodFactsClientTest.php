<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\OpenFoodFactsClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OpenFoodFactsClientTest extends TestCase
{
    public function testFetchesKnownProduct(): void
    {
        $payload = [
            'status' => 1,
            'product' => [
                'product_name' => 'Nutella',
                'brands' => 'Ferrero',
                'image_front_small_url' => 'https://example.test/img.jpg',
                'nutriscore_grade' => 'e',
                'quantity' => '400 g',
            ],
        ];
        $client = new OpenFoodFactsClient(new MockHttpClient(new MockResponse(
            (string) json_encode($payload, JSON_THROW_ON_ERROR),
            ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']],
        )));

        $data = $client->fetchProduct('3017620422003');

        self::assertNotNull($data);
        self::assertSame('3017620422003', $data->barcode);
        self::assertSame('Nutella', $data->name);
        self::assertSame('Ferrero', $data->brand);
        self::assertSame('https://example.test/img.jpg', $data->imageUrl);
        self::assertSame('e', $data->nutriscore);
        self::assertSame('400 g', $data->quantityLabel);
    }

    public function testReturnsNullWhenApiReportsUnknown(): void
    {
        $client = new OpenFoodFactsClient(new MockHttpClient(new MockResponse(
            (string) json_encode(['status' => 0], JSON_THROW_ON_ERROR),
            ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']],
        )));

        self::assertNull($client->fetchProduct('0000000000000'));
    }

    public function testReturnsNullOn404(): void
    {
        $client = new OpenFoodFactsClient(new MockHttpClient(new MockResponse('', ['http_code' => 404])));

        self::assertNull($client->fetchProduct('0000000000000'));
    }

    public function testReturnsNullOnNetworkError(): void
    {
        $client = new OpenFoodFactsClient(new MockHttpClient(static function (): MockResponse {
            return new MockResponse([new \RuntimeException('boom')]);
        }));

        self::assertNull($client->fetchProduct('3017620422003'));
    }

    public function testRejectsInvalidBarcodeWithoutCallingApi(): void
    {
        $called = 0;
        $client = new OpenFoodFactsClient(new MockHttpClient(static function () use (&$called): MockResponse {
            ++$called;

            return new MockResponse('{}');
        }));

        self::assertNull($client->fetchProduct('abc'));
        self::assertNull($client->fetchProduct(''));
        self::assertSame(0, $called);
    }
}
