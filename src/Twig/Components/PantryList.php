<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\PantryItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\PantryItemRepository;
use App\Repository\ProductRepository;
use App\Security\Voter\PantryItemVoter;
use App\Service\OpenFoodFactsClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class PantryList
{
    use DefaultActionTrait;

    public const string MODE_ADD = 'ADD';
    public const string MODE_REMOVE = 'REMOVE';

    #[LiveProp(writable: true)]
    public string $mode = self::MODE_ADD;

    #[LiveProp]
    public ?string $notice = null;

    #[LiveProp]
    public ?string $noticeType = null;

    #[LiveProp(writable: true)]
    public string $search = '';

    public function __construct(
        private readonly PantryItemRepository $pantryItems,
        private readonly ProductRepository $products,
        private readonly OpenFoodFactsClient $openFoodFacts,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    /** @var list<PantryItem>|null */
    private ?array $itemsCache = null;

    /**
     * @return list<PantryItem>
     */
    public function getItems(): array
    {
        return $this->itemsCache ??= $this->pantryItems->listForUser(
            $this->getCurrentUser(),
            trim($this->search) !== '' ? trim($this->search) : null,
        );
    }

    #[LiveAction]
    public function scan(#[LiveArg] string $barcode): void
    {
        $this->notice = null;
        $this->noticeType = null;
        $barcode = trim($barcode);

        if ($barcode === '') {
            return;
        }

        $user = $this->getCurrentUser();
        $product = $this->products->findOneByBarcode($barcode);

        if ($product === null) {
            $data = $this->openFoodFacts->fetchProduct($barcode);
            if ($data === null) {
                $this->setNotice('error', \sprintf('Produit inconnu (code-barres %s).', $barcode));

                return;
            }

            $product = new Product($data->barcode, $data->name);
            $product->setBrand($data->brand);
            $product->setImageUrl($data->imageUrl);
            $product->setNutriscore($data->nutriscore);
            $product->setQuantityLabel($data->quantityLabel);
            $this->em->persist($product);
        }

        $item = $this->pantryItems->findOneByUserAndProduct($user, $product);

        if ($this->mode === self::MODE_REMOVE) {
            if ($item === null || $item->getQuantity() === 0) {
                $this->setNotice('notice', \sprintf('« %s » n\'est pas (ou plus) dans votre garde-manger.', $product->getName()));

                return;
            }

            $item->decrement();
            if ($item->getQuantity() === 0) {
                $this->em->remove($item);
                $this->setNotice('notice', \sprintf('« %s » retiré du garde-manger.', $product->getName()));
            } else {
                $this->setNotice('success', \sprintf('« %s » décrémenté (%d restant).', $product->getName(), $item->getQuantity()));
            }
        } else {
            if ($item === null) {
                $item = new PantryItem($user, $product);
                $this->em->persist($item);
            }
            $item->increment();
            $this->setNotice('success', \sprintf('« %s » ajouté (%d en stock).', $product->getName(), $item->getQuantity()));
        }

        $this->em->flush();
    }

    #[LiveAction]
    public function adjust(#[LiveArg] int $itemId, #[LiveArg] int $delta): void
    {
        $this->notice = null;
        $this->noticeType = null;

        if ($delta === 0) {
            return;
        }

        $item = $this->pantryItems->find($itemId);

        if ($item === null || !$this->security->isGranted(PantryItemVoter::EDIT, $item)) {
            $this->setNotice('error', 'Article introuvable.');

            return;
        }

        $productName = $item->getProduct()->getName();

        if ($delta > 0) {
            $item->increment($delta);
            $this->setNotice('success', \sprintf('« %s » ajouté (%d en stock).', $productName, $item->getQuantity()));
        } else {
            $item->decrement(-$delta);
            if ($item->getQuantity() === 0) {
                $this->em->remove($item);
                $this->setNotice('notice', \sprintf('« %s » retiré du garde-manger.', $productName));
            } else {
                $this->setNotice('success', \sprintf('« %s » décrémenté (%d restant).', $productName, $item->getQuantity()));
            }
        }

        $this->em->flush();
    }

    #[LiveAction]
    public function clearSearch(): void
    {
        $this->search = '';
    }

    #[LiveAction]
    public function setMode(#[LiveArg] string $mode): void
    {
        $this->mode = $mode === self::MODE_REMOVE ? self::MODE_REMOVE : self::MODE_ADD;
        $this->notice = null;
        $this->noticeType = null;
    }

    private function setNotice(string $type, string $message): void
    {
        $this->noticeType = $type;
        $this->notice = $message;
    }

    private function getCurrentUser(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('PantryList requires an authenticated App\\Entity\\User.');
        }

        return $user;
    }
}
