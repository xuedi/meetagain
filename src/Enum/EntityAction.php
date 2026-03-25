<?php declare(strict_types=1);

namespace App\Enum;

enum EntityAction: string
{
    case CreateCms = 'create_cms';
    case UpdateCms = 'update_cms';
    case DeleteCms = 'delete_cms';
    case CreateEvent = 'create_event';
    case UpdateEvent = 'update_event';
    case DeleteEvent = 'delete_event';
    case CreateLocation = 'create_location';
    case UpdateLocation = 'update_location';
    case DeleteLocation = 'delete_location';
    case CreateAnnouncement = 'create_announcement';
    case UpdateAnnouncement = 'update_announcement';
    case DeleteAnnouncement = 'delete_announcement';
    case CreateUser = 'create_user';
    case DeleteUser = 'delete_user';
    case CreateImage = 'create_image';
    case CreateBookSuggestion = 'create_book_suggestion';
    case DeleteBookSuggestion = 'delete_book_suggestion';
    case CreateBook = 'create_book';
    case DeleteBook = 'delete_book';
    case CreateHost = 'create_host';
    case DeleteHost = 'delete_host';
}
