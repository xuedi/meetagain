<?php declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as SymfonyAbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Base controller for all admin controllers.
 *
 * Provides common functionality and enforces admin role requirement.
 * All admin controllers must implement getAdminNavigation() to define
 * their navigation metadata.
 */
#[IsGranted('ROLE_ADMIN')]
abstract class AbstractAdminController extends SymfonyAbstractController implements AdminNavigationInterface
{
    // Common admin functionality can go here
}
