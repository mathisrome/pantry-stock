<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'UNIQ_users_email_hash', columns: ['email_hash'])]
#[UniqueEntity(fields: ['emailHash'], message: 'Un compte existe déjà pour cet email.')]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'email_hash', type: Types::STRING, length: 64)]
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 64)]
    private string $emailHash = '';

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    /** @var Collection<int, PantryItem> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: PantryItem::class, orphanRemoval: true)]
    private Collection $pantryItems;

    public function __construct()
    {
        $this->pantryItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmailHash(): string
    {
        return $this->emailHash;
    }

    public function setEmailHash(string $emailHash): self
    {
        $this->emailHash = $emailHash;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->emailHash;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    /** @return Collection<int, PantryItem> */
    public function getPantryItems(): Collection
    {
        return $this->pantryItems;
    }
}
