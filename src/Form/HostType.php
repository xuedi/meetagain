<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Host;
use App\Entity\User;
use App\Filter\Admin\Member\AdminMemberListFilterService;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Override;
use SortDirection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class HostType extends AbstractType
{
    public function __construct(
        private readonly AdminMemberListFilterService $memberFilterService,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'admin_host.form_label_name',
            'constraints' => [
                new NotBlank(),
                new Length(max: 16),
            ],
        ])->add('user', EntityType::class, [
            'label' => 'admin_host.form_label_user',
            'class' => User::class,
            'choice_label' => 'name',
            'required' => false,
            'query_builder' => fn(EntityRepository $repository): QueryBuilder => $this->userChoices($repository, $builder->getData()?->getUser()?->getId()),
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Host::class,
        ]);
    }

    private function userChoices(EntityRepository $repository, ?int $linkedUserId): QueryBuilder
    {
        $query = $repository->createQueryBuilder('u')->orderBy('u.name', SortDirection::Ascending);
        $userIds = $this->memberFilterService->getUserIdFilter()->getUserIds();
        if ($userIds === null) {
            return $query;
        }

        if ($linkedUserId !== null) {
            $userIds[] = $linkedUserId;
        }

        return $userIds === [] ? $query->andWhere('1 = 0') : $query->andWhere('u.id IN (:ids)')->setParameter('ids', $userIds);
    }
}
