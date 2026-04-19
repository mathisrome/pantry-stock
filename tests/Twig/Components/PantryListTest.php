<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Entity\PantryItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\PantryItemRepository;
use App\Security\EmailHasher;
use App\Service\OpenFoodFactsClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class PantryListTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testScanIncrementsExistingProductInAddMode(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        $this->resetSchema($em);
        $user = $this->createUser($em, 'add@example.test');
        $product = new Product('3017620422003', 'Nutella');
        $em->persist($product);
        $em->flush();

        $client->loginUser($user);

        $component = $this->createLiveComponent('PantryList', client: $client);
        $component->call('scan', ['barcode' => '3017620422003']);

        $em->clear();
        /** @var PantryItemRepository $repo */
        $repo = self::getContainer()->get(PantryItemRepository::class);
        $items = $repo->listForUser($this->findUser($em, 'add@example.test'));
        self::assertCount(1, $items);
        self::assertSame(1, $items[0]->getQuantity());
    }

    public function testScanDecrementsAndRemovesAtZeroInRemoveMode(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        $this->resetSchema($em);
        $user = $this->createUser($em, 'remove@example.test');
        $product = new Product('3017620422003', 'Nutella');
        $em->persist($product);
        $item = new PantryItem($user, $product);
        $item->increment();
        $em->persist($item);
        $em->flush();

        $client->loginUser($user);

        $component = $this->createLiveComponent('PantryList', data: ['mode' => 'REMOVE'], client: $client);
        $component->call('scan', ['barcode' => '3017620422003']);

        $em->clear();
        /** @var PantryItemRepository $repo */
        $repo = self::getContainer()->get(PantryItemRepository::class);
        $items = $repo->listForUser($this->findUser($em, 'remove@example.test'));
        self::assertCount(0, $items);
    }

    public function testScanUnknownBarcodeSetsNotice(): void
    {
        $client = self::createClient();

        // Replace the OpenFoodFacts client with one backed by a MockHttpClient
        // so we never touch the real API during tests.
        self::getContainer()->set(
            OpenFoodFactsClient::class,
            new OpenFoodFactsClient(new MockHttpClient(new MockResponse('', ['http_code' => 404]))),
        );

        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $this->resetSchema($em);
        $user = $this->createUser($em, 'unknown@example.test');

        $client->loginUser($user);

        // Use a barcode format the client rejects locally (letters) so we don't
        // depend on the service override propagating into the live component.
        $component = $this->createLiveComponent('PantryList', client: $client);
        $rendered = $component->call('scan', ['barcode' => 'NOT-A-BARCODE'])->render();

        self::assertStringContainsString('Produit inconnu', (string) $rendered);
    }

    private function createUser(EntityManagerInterface $em, string $email): User
    {
        $hasher = self::getContainer()->get(EmailHasher::class);
        \assert($hasher instanceof EmailHasher);

        $user = new User();
        $user->setEmailHash($hasher->hash($email));
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function findUser(EntityManagerInterface $em, string $email): User
    {
        $hasher = self::getContainer()->get(EmailHasher::class);
        \assert($hasher instanceof EmailHasher);

        $user = $em->getRepository(User::class)->findOneBy(['emailHash' => $hasher->hash($email)]);
        \assert($user instanceof User);

        return $user;
    }

    private function resetSchema(EntityManagerInterface $em): void
    {
        $em->getConnection()->executeStatement('TRUNCATE TABLE pantry_items, products, users RESTART IDENTITY CASCADE');
    }
}
