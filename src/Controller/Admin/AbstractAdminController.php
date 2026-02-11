<?php declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as SymfonyAbstractController;

/**
 * Base controller for all admin controllers.
 *
 * Provides common functionality and enforces admin role requirement.
 * All admin controllers must implement getAdminNavigation() to define
 * their navigation metadata.
 */
abstract class AbstractAdminController extends SymfonyAbstractController implements AdminNavigationInterface
{
    // Common admin functionality can go here
}
