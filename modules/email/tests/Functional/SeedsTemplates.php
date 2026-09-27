<?php declare(strict_types=1);

namespace Module\Email\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;

trait SeedsTemplates
{
    private static function seedTemplates(KernelInterface $kernel): void
    {
        new Application($kernel)->find('app:email-templates:seed')->run(new ArrayInput([]), new NullOutput());
        $kernel->getContainer()->get('doctrine')->getManager()->clear();
    }
}
