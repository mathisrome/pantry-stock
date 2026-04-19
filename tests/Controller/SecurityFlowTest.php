<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Security\EmailHasher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityFlowTest extends WebTestCase
{
    public function testAnonymousIsRedirectedToLogin(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testLoginPageIsPublic(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');
        self::assertResponseIsSuccessful();
    }

    public function testAuthenticatedUserSeesPantry(): void
    {
        $client = self::createClient();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var EmailHasher $hasher */
        $hasher = self::getContainer()->get(EmailHasher::class);

        $this->resetSchema($em);

        $user = new User();
        $user->setEmailHash($hasher->hash('test@example.test'));
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'garde-manger');
    }

    private function resetSchema(EntityManagerInterface $em): void
    {
        $conn = $em->getConnection();
        $conn->executeStatement('TRUNCATE TABLE pantry_items, products, users RESTART IDENTITY CASCADE');
    }
}
