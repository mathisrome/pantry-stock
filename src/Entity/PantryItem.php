<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PantryItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PantryItemRepository::class)]
#[ORM\Table(name: 'pantry_items')]
#[ORM\UniqueConstraint(name: 'UNIQ_pantry_items_user_product', columns: ['user_id', 'product_id'])]
class PantryItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'pantryItems')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $quantity = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user, Product $product)
    {
        $this->user = $user;
        $this->product = $product;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function increment(int $by = 1): void
    {
        if ($by <= 0) {
            throw new \InvalidArgumentException('Increment must be positive.');
        }
        $this->quantity += $by;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @return bool true if decrement happened, false if already at 0
     */
    public function decrement(int $by = 1): bool
    {
        if ($by <= 0) {
            throw new \InvalidArgumentException('Decrement must be positive.');
        }
        if ($this->quantity === 0) {
            return false;
        }
        $this->quantity = max(0, $this->quantity - $by);
        $this->updatedAt = new \DateTimeImmutable();

        return true;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
