<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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

    public function testRegisterPageIsPublic(): void
    {
        $client = self::createClient();
        $client->request('GET', '/register');
        self::assertResponseIsSuccessful();
    }

    public function testAuthenticatedUserSeesPantry(): void
    {
        $client = self::createClient();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $this->resetSchema($em);

        $user = new User();
        $user->setEmail('test@example.test');
        $user->setPassword($hasher->hashPassword($user, 'password123'));
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
