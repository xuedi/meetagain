<?php declare(strict_types=1);

namespace App\Entity;

use App\Controller\AdminController;
use App\Controller\EventController;
use App\Controller\ManageController;
use App\Controller\MemberController;
use App\Controller\ProfileController;
use App\Controller\SecurityController;
use App\Controller\TranslationController;
use Symfony\Contracts\Translation\TranslatorInterface;

enum MenuRoutes: string
{
    case Profile = ProfileController::ROUTE_PROFILE;
    case Events = EventController::ROUTE_EVENT;
    case EventsFeatured = EventController::ROUTE_FEATURED;
    case Members = MemberController::ROUTE_MEMBER;
    case Manage = ManageController::ROUTE_MANAGE;
    case Admin = AdminController::ROUTE_ADMIN;
    case Login = SecurityController::LOGIN_ROUTE;
    case Translation = TranslationController::ROUTE_MANAGE;

    public static function getChoices(TranslatorInterface $translator): array
    {
        return array_flip(self::getTranslatedList($translator));
    }

    public static function getTranslatedList(TranslatorInterface $translator): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->value] = $translator->trans('menu_route_' . strtolower($case->name));
        }

        return $choices;
    }
}
