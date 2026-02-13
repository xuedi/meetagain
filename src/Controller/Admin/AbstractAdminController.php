<?php declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as SymfonyAbstractController;

abstract class AbstractAdminController extends SymfonyAbstractController implements AdminNavigationInterface
{
    // Common admin functionality can go here
}
