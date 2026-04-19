<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\PantryItem;
use App\Entity\Product;
use App\Entity\User;
use App\Security\Voter\PantryItemVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class PantryItemVoterTest extends TestCase
{
    public function testOwnerCanEdit(): void
    {
        $owner = $this->makeUser(1);
        $item = new PantryItem($owner, new Product('3017620422003', 'Nutella'));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->vote($this->makeToken($owner), $item, PantryItemVoter::EDIT),
        );
    }

    public function testOtherUserCannotEdit(): void
    {
        $owner = $this->makeUser(1);
        $other = $this->makeUser(2);
        $item = new PantryItem($owner, new Product('3017620422003', 'Nutella'));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote($this->makeToken($other), $item, PantryItemVoter::EDIT),
        );
    }

    public function testAnonymousCannotEdit(): void
    {
        $owner = $this->makeUser(1);
        $item = new PantryItem($owner, new Product('3017620422003', 'Nutella'));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote($this->makeToken(null), $item, PantryItemVoter::EDIT),
        );
    }

    public function testAbstainsOnUnknownAttribute(): void
    {
        $owner = $this->makeUser(1);
        $item = new PantryItem($owner, new Product('3017620422003', 'Nutella'));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->vote($this->makeToken($owner), $item, 'SOMETHING_ELSE'),
        );
    }

    private function vote(TokenInterface $token, mixed $subject, string $attribute): int
    {
        return (new PantryItemVoter())->vote($token, $subject, [$attribute]);
    }

    private function makeUser(int $id): User
    {
        $user = new User();
        $user->setEmailHash(str_pad((string) $id, 64, '0', STR_PAD_LEFT));
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);

        return $user;
    }

    private function makeToken(?User $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
