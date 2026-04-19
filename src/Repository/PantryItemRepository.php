<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PantryItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PantryItem>
 */
final class PantryItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PantryItem::class);
    }

    public function findOneByUserAndProduct(User $user, Product $product): ?PantryItem
    {
        return $this->findOneBy(['user' => $user, 'product' => $product]);
    }

    /**
     * @return list<PantryItem>
     */
    public function listForUser(User $user, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('pi')
            ->addSelect('p')
            ->innerJoin('pi.product', 'p')
            ->where('pi.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.name', 'ASC');

        if ($search !== null && $search !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :search OR LOWER(p.brand) LIKE :search OR p.barcode LIKE :search')
                ->setParameter('search', '%'.strtolower($search).'%');
        }

        /** @var list<PantryItem> */
        return $qb->getQuery()->getResult();
    }
}
