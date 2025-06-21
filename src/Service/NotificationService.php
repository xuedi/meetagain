<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use Doctrine\ORM\EntityManagerInterface;

readonly class NotificationService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function notify(Activity $activity): void
    {
        // get user
        // get all people following this user
        // decide what kind of notifications
        dump($activity);
    }
}
