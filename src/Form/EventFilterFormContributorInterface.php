<?php declare(strict_types=1);

namespace App\Form;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\FormBuilderInterface;

#[AutoconfigureTag]
interface EventFilterFormContributorInterface
{
    public function addFields(FormBuilderInterface $builder): void;
}
