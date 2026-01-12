<?php declare(strict_types=1);

namespace Plugin\Dishes\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Dishes\Entity\Dish;
use Plugin\Dishes\Entity\DishSuggestion;
use Plugin\Dishes\Entity\DishSuggestionField;
use Plugin\Dishes\Entity\DishTranslation;
use Plugin\Dishes\Repository\DishRepository;
use RuntimeException;

readonly class DishService
{
    public function __construct(
        private EntityManagerInterface $em,
        private DishRepository $repo,
    ) {
    }

    public function createDish(
        string $name,
        string $language,
        int $userId,
        bool $isManager,
        ?string $phonetic = null,
        ?string $description = null,
        ?string $recipe = null,
        ?string $origin = null,
    ): Dish {
        $dish = new Dish();
        $dish->setCreatedBy($userId);
        $dish->setCreatedAt(new DateTimeImmutable());
        $dish->setApproved($isManager);
        $dish->setOriginLang($language);
        $dish->setOrigin($origin);

        $translation = new DishTranslation();
        $translation->setLanguage($language);
        $translation->setName($name);
        $translation->setPhonetic($phonetic);
        $translation->setDescription($description ?? '');
        $translation->setRecipe($recipe);
        $dish->addTranslation($translation);

        $this->em->persist($dish);
        $this->em->flush();

        return $dish;
    }

    public function getDish(int $id): ?Dish
    {
        return $this->repo->find($id);
    }

    public function getApprovedDishes(): array
    {
        return $this->repo->findApproved();
    }

    public function getAllDishes(): array
    {
        return $this->repo->findBy([], ['createdAt' => 'DESC']);
    }

    public function getPendingDishes(): array
    {
        return $this->repo->findPending();
    }

    public function getDishesWithSuggestions(): array
    {
        return $this->repo->findWithSuggestions();
    }

    public function approveDish(int $id): void
    {
        $dish = $this->repo->find($id);
        if ($dish === null) {
            throw new RuntimeException('Dish not found');
        }

        $dish->setApproved(true);
        $this->em->persist($dish);
        $this->em->flush();
    }

    public function rejectDish(int $id): void
    {
        $dish = $this->repo->find($id);
        if ($dish === null) {
            throw new RuntimeException('Dish not found');
        }

        if ($dish->getApproved()) {
            throw new RuntimeException('Cannot reject approved dish');
        }

        $this->em->remove($dish);
        $this->em->flush();
    }

    public function addSuggestion(
        int $dishId,
        int $userId,
        DishSuggestionField $field,
        string $language,
        string $value,
    ): void {
        $dish = $this->repo->find($dishId);
        if ($dish === null) {
            throw new RuntimeException('Dish not found');
        }

        $suggestion = DishSuggestion::create(
            createdBy: $userId,
            field: $field,
            language: $language,
            value: $value,
        );

        $dish->addSuggestion($suggestion);
        $this->em->persist($dish);
        $this->em->flush();
    }

    public function applySuggestion(int $dishId, string $hash): int
    {
        $dish = $this->repo->find($dishId);
        if ($dish === null) {
            throw new RuntimeException('Dish not found');
        }

        $suggestion = $dish->findSuggestionByHash($hash);
        if ($suggestion === null) {
            throw new RuntimeException('Suggestion not found');
        }

        if ($suggestion->field === DishSuggestionField::Origin) {
            $dish->setOrigin($suggestion->value);
        } else {
            $translation = $dish->findTranslation($suggestion->language);
            if ($translation === null) {
                $translation = new DishTranslation();
                $translation->setLanguage($suggestion->language);
                $translation->setName('');
                $translation->setDescription('');
                $dish->addTranslation($translation);
            }

            /** @var DishSuggestionField::Name|DishSuggestionField::Phonetic|DishSuggestionField::Description|DishSuggestionField::Recipe $field */
            $field = $suggestion->field;
            match ($field) {
                DishSuggestionField::Name => $translation->setName($suggestion->value),
                DishSuggestionField::Phonetic => $translation->setPhonetic($suggestion->value),
                DishSuggestionField::Description => $translation->setDescription($suggestion->value),
                DishSuggestionField::Recipe => $translation->setRecipe($suggestion->value),
            };
        }

        $dish->removeSuggestionByHash($hash);
        $this->em->persist($dish);
        $this->em->flush();

        return count($dish->getSuggestionObjects());
    }

    public function denySuggestion(int $dishId, string $hash): int
    {
        $dish = $this->repo->find($dishId);
        if ($dish === null) {
            throw new RuntimeException('Dish not found');
        }

        $dish->removeSuggestionByHash($hash);
        $this->em->persist($dish);
        $this->em->flush();

        return count($dish->getSuggestionObjects());
    }

    public function incrementLike(int $dishId): int
    {
        $dish = $this->repo->find($dishId);
        if ($dish === null) {
            throw new RuntimeException('Dish not found');
        }

        $dish->incrementLikes();
        $this->em->persist($dish);
        $this->em->flush();

        return $dish->getLikes();
    }

    public function updateTranslation(
        int $dishId,
        string $language,
        int $userId,
        bool $isManager,
        string $name,
        ?string $phonetic,
        ?string $description,
        ?string $recipe,
    ): void {
        $dish = $this->repo->find($dishId);
        if ($dish === null) {
            throw new RuntimeException('Dish not found');
        }

        $translation = $dish->findTranslation($language);
        $isNewTranslation = $translation === null;

        if ($isManager) {
            if ($isNewTranslation) {
                $translation = new DishTranslation();
                $translation->setLanguage($language);
                $dish->addTranslation($translation);
            }

            $translation->setName($name);
            $translation->setPhonetic($phonetic);
            $translation->setDescription($description ?? '');
            $translation->setRecipe($recipe);

            $this->em->persist($dish);
            $this->em->flush();

            return;
        }

        if ($isNewTranslation || $translation->getName() !== $name) {
            $dish->addSuggestion(DishSuggestion::create(
                createdBy: $userId,
                field: DishSuggestionField::Name,
                language: $language,
                value: $name,
            ));
        }

        if ($isNewTranslation || $translation->getPhonetic() !== $phonetic) {
            if ($phonetic !== null && $phonetic !== '') {
                $dish->addSuggestion(DishSuggestion::create(
                    createdBy: $userId,
                    field: DishSuggestionField::Phonetic,
                    language: $language,
                    value: $phonetic,
                ));
            }
        }

        $currentDescription = $isNewTranslation ? '' : $translation->getDescription();
        if ($currentDescription !== ($description ?? '')) {
            $dish->addSuggestion(DishSuggestion::create(
                createdBy: $userId,
                field: DishSuggestionField::Description,
                language: $language,
                value: $description ?? '',
            ));
        }

        $currentRecipe = $isNewTranslation ? null : $translation->getRecipe();
        if ($currentRecipe !== $recipe && ($recipe !== null && $recipe !== '')) {
            $dish->addSuggestion(DishSuggestion::create(
                createdBy: $userId,
                field: DishSuggestionField::Recipe,
                language: $language,
                value: $recipe,
            ));
        }

        $this->em->persist($dish);
        $this->em->flush();
    }

    public function updateOrigin(int $dishId, int $userId, bool $isManager, ?string $origin): void
    {
        $dish = $this->repo->find($dishId);
        if ($dish === null) {
            throw new RuntimeException('Dish not found');
        }

        if ($isManager) {
            $dish->setOrigin($origin);
            $this->em->persist($dish);
            $this->em->flush();

            return;
        }

        if ($dish->getOrigin() !== $origin && $origin !== null && $origin !== '') {
            $dish->addSuggestion(DishSuggestion::create(
                createdBy: $userId,
                field: DishSuggestionField::Origin,
                language: '',
                value: $origin,
            ));

            $this->em->persist($dish);
            $this->em->flush();
        }
    }

    public function detach(Dish $dish): void
    {
        $this->em->detach($dish);
    }
}
