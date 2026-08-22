<?php declare(strict_types=1);

namespace App\Emails;

final readonly class MockSample
{
    public function __construct(
        public string $locale,
        public string $host,
        public string $url,
        public string $recipientName,
        public string $senderName,
        public string $attendeeNames,
        public string $adminName,
        public string $originName,
        public int $eventId,
        public string $eventTitle,
        public string $eventLocation,
        public string $eventDate,
        public string $eventTime,
        public string $eventStart,
        public string $announcementTitle,
        public string $announcementBody,
        public string $messageText,
        public string $responseText,
        public MockSampleDates $dates,
    ) {}
}
