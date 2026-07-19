<?php declare(strict_types=1);

namespace App\Enum;

enum EntityAction: string
{
    case CreateCms = 'create_cms';
    case UpdateCms = 'update_cms';
    case DeleteCms = 'delete_cms';
    case UpdateCmsBlock = 'update_cms_block';
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
    case CreateHost = 'create_host';
    case DeleteHost = 'delete_host';
    case CreateWallPost = 'create_wall_post';
    case DeleteWallPost = 'delete_wall_post';
    case CreateGlossary = 'create_glossary';
    case DeleteGlossary = 'delete_glossary';
    case CreateEventItemAssociation = 'create_event_item_association';
    case DeleteEventItemAssociation = 'delete_event_item_association';
}
