<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\PantryItem;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<self::*, PantryItem>
 */
final class PantryItemVoter extends Voter
{
    public const string EDIT = 'PANTRY_ITEM_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::EDIT && $subject instanceof PantryItem;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        \assert($subject instanceof PantryItem);

        return $subject->getUser()->getId() === $user->getId();
    }
}
