<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ProductData;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin wrapper over the OpenFoodFacts product API.
 *
 * @see https://wiki.openfoodfacts.org/API
 */
final class OpenFoodFactsClient
{
    public function __construct(
        #[Autowire(service: 'open_food_facts.client')]
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function fetchProduct(string $barcode): ?ProductData
    {
        $barcode = trim($barcode);
        if ($barcode === '' || !preg_match('/^\d{6,32}$/', $barcode)) {
            return null;
        }

        try {
            $response = $this->client->request('GET', \sprintf('/api/v2/product/%s.json', $barcode));
            $status = $response->getStatusCode();
            if ($status === 404) {
                return null;
            }
            if ($status >= 400) {
                $this->logger->warning('OpenFoodFacts unexpected status', ['barcode' => $barcode, 'status' => $status]);

                return null;
            }

            /** @var array<string, mixed> $payload */
            $payload = $response->toArray(false);
        } catch (ExceptionInterface $e) {
            $this->logger->warning('OpenFoodFacts request failed', ['barcode' => $barcode, 'error' => $e->getMessage()]);

            return null;
        } catch (\JsonException $e) {
            $this->logger->warning('OpenFoodFacts invalid JSON', ['barcode' => $barcode, 'error' => $e->getMessage()]);

            return null;
        }

        $apiStatus = $payload['status'] ?? 0;
        if ($apiStatus !== 1 && $apiStatus !== '1') {
            return null;
        }

        /** @var array<string, mixed> $product */
        $product = \is_array($payload['product'] ?? null) ? $payload['product'] : [];

        $name = $this->firstNonEmptyString($product, ['product_name', 'generic_name', 'product_name_fr']);
        if ($name === null) {
            return null;
        }

        return new ProductData(
            barcode: $barcode,
            name: $name,
            brand: $this->firstNonEmptyString($product, ['brands']),
            imageUrl: $this->firstNonEmptyString($product, ['image_front_small_url', 'image_front_url', 'image_url']),
            nutriscore: $this->firstNonEmptyString($product, ['nutriscore_grade']),
            quantityLabel: $this->firstNonEmptyString($product, ['quantity']),
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string>         $keys
     */
    private function firstNonEmptyString(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (\is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
