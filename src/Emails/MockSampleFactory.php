<?php declare(strict_types=1);

namespace App\Emails;

use App\Service\Http\RequestHostResolver;
use DateInterval;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class MockSampleFactory
{
    private const int EVENT_ID = 12;
    private const string FALLBACK_LOCALE = 'en';
    private const string EVENT_TIME = '19:00';
    private const string PREVIOUS_EVENT_TIME = '17:30';

    /** @var array<string, array<string, string>> */
    private const array WORLDS = [
        'en' => [
            'recipientName' => 'Florence Shaw',
            'senderName' => 'Phoenix Baker',
            'attendeeNames' => 'Phoenix Baker, Olivia Rhye',
            'adminName' => 'Aysha Becker',
            'originName' => 'Wednesday Spanish Table',
            'eventTitle' => 'Wednesday Spanish Conversation Table',
            'previousLocation' => 'Bar La Esquina',
            'eventLocation' => 'Instituto Hispano Berlin',
            'announcementTitle' => 'New venue for our Wednesday table',
            'announcementBody' => '<p>From September the Wednesday conversation table moves to Instituto Hispano Berlin.</p>'
                . '<p>Same time, two streets further along - see you there.</p>',
            'messageText' => 'Could you put me on the list for Wednesday? I am bringing a friend along.',
            'responseText' => 'You are both on the list for Wednesday - no need to bring anything.',
        ],
        'de' => [
            'recipientName' => 'Eduard Franz',
            'senderName' => 'Marco Gross',
            'attendeeNames' => 'Marco Gross, Lana Steiner',
            'adminName' => 'Aysha Becker',
            'originName' => 'Filmabend Kreuzberg',
            'eventTitle' => 'Zweiwöchentlicher Filmabend - Kommend',
            'previousLocation' => 'Kreuzberg Screening Room',
            'eventLocation' => 'Kino Central',
            'announcementTitle' => 'Neuer Ort für unseren Filmabend',
            'announcementBody' => '<p>Ab September läuft der zweiwöchentliche Filmabend im Kino Central.</p>'
                . '<p>Gleiche Uhrzeit, zwei Straßen weiter - bis dann.</p>',
            'messageText' => 'Kannst du mich für Mittwoch auf die Liste setzen? Ich bringe eine Freundin mit.',
            'responseText' => 'Ihr steht beide für Mittwoch auf der Liste - mitbringen musst du nichts.',
        ],
        'zh' => [
            'recipientName' => 'Crystal Liu',
            'senderName' => 'Nicolas Wang',
            'attendeeNames' => 'Nicolas Wang, Maxwell Tan',
            'adminName' => 'Aysha Becker',
            'originName' => '中文短语交流会',
            'eventTitle' => '中文短语交流会',
            'previousLocation' => 'Kino Central',
            'eventLocation' => 'Long Jing Teahouse',
            'announcementTitle' => '交流会换了新场地',
            'announcementBody' => '<p>从九月起，中文短语交流会将在 Long Jing Teahouse 举行。</p>'
                . '<p>时间不变，欢迎大家继续参加。</p>',
            'messageText' => '可以帮我把周三的名额加上吗？我会带一位朋友一起来。',
            'responseText' => '已经帮你们两位加上周三的名额了，什么都不用带。',
        ],
        'fr' => [
            'recipientName' => 'Noah Pierre',
            'senderName' => 'Mathilde Lewis',
            'attendeeNames' => 'Mathilde Lewis, Noel Baldwin',
            'adminName' => 'Aysha Becker',
            'originName' => 'Supper Club au jardin',
            'eventTitle' => 'Supper Club au jardin - thali sud-indien',
            'previousLocation' => 'Neukölln Loft Kitchen',
            'eventLocation' => 'Schöneberg Garden House',
            'announcementTitle' => 'Nouveau lieu pour notre dîner',
            'announcementBody' => '<p>À partir de septembre, le supper club se tiendra à la Schöneberg Garden House.</p>'
                . '<p>Même heure, deux rues plus loin - à bientôt.</p>',
            'messageText' => 'Peux-tu m\'ajouter à la liste pour mercredi ? Je viens avec une amie.',
            'responseText' => 'Vous êtes toutes les deux sur la liste pour mercredi - inutile d\'apporter quoi que ce soit.',
        ],
        'es' => [
            'recipientName' => 'Sophia Perez',
            'senderName' => 'Owen Garcia',
            'attendeeNames' => 'Owen Garcia, Isla Allison',
            'adminName' => 'Aysha Becker',
            'originName' => 'Mesa de conversación de los miércoles',
            'eventTitle' => 'Mesa de conversación en español los miércoles',
            'previousLocation' => 'Instituto Hispano Berlin',
            'eventLocation' => 'Bar La Esquina',
            'announcementTitle' => 'Nuevo lugar para la mesa de los miércoles',
            'announcementBody' => '<p>Desde septiembre la mesa de conversación se traslada al Bar La Esquina.</p>'
                . '<p>Misma hora, dos calles más allá. ¡Nos vemos!</p>',
            'messageText' => '¿Puedes apuntarme a la lista del miércoles? Voy con una amiga.',
            'responseText' => 'Las dos estáis en la lista del miércoles; no hace falta que traigáis nada.',
        ],
    ];

    public function __construct(
        private RequestHostResolver $host,
        private TranslatorInterface $translator,
        private ClockInterface $clock,
    ) {}

    public function create(string $locale): MockSample
    {
        $world = self::WORLDS[$locale] ?? self::WORLDS[self::FALLBACK_LOCALE];
        $now = $this->clock->now();
        $today = $now->format('Y-m-d');

        return new MockSample(
            locale: $locale,
            host: rtrim($this->host->getSchemeAndHost(), '/'),
            url: $this->host->getHost(),
            recipientName: $world['recipientName'],
            senderName: $world['senderName'],
            attendeeNames: $world['attendeeNames'],
            adminName: $world['adminName'],
            originName: $world['originName'],
            eventId: self::EVENT_ID,
            eventTitle: $world['eventTitle'],
            eventLocation: $world['eventLocation'],
            eventDate: $today,
            eventTime: self::EVENT_TIME,
            eventStart: $today . ' ' . self::EVENT_TIME,
            announcementTitle: $world['announcementTitle'],
            announcementBody: $world['announcementBody'],
            messageText: $world['messageText'],
            responseText: $world['responseText'],
            dates: new MockSampleDates(
                createdAt: $now->sub(new DateInterval('PT2H'))->format('Y-m-d H:i:s'),
                expiresAt: $now->add(new DateInterval('P1D'))->format('Y-m-d H:i:s'),
                chargeDate: $now->add(new DateInterval('P7D'))->format('Y-m-d'),
                retryDate: $now->add(new DateInterval('P3D'))->format('Y-m-d'),
                suspendedSince: $now->sub(new DateInterval('P1M'))->format('Y-m-d'),
            ),
        );
    }

    public function changesHtml(MockSample $sample): string
    {
        $world = self::WORLDS[$sample->locale] ?? self::WORLDS[self::FALLBACK_LOCALE];

        $lines = [
            $this->translator->trans(
                'email_event_update.line_start',
                [
                    '%before%' => $sample->eventDate . ' ' . self::PREVIOUS_EVENT_TIME,
                    '%after%' => $sample->eventStart,
                ],
                'messages',
                $sample->locale,
            ),
            $this->translator->trans(
                'email_event_update.line_location',
                ['%before%' => $world['previousLocation'], '%after%' => $sample->eventLocation],
                'messages',
                $sample->locale,
            ),
        ];

        return '<ul><li>' . implode('</li><li>', $lines) . '</li></ul>';
    }

    public function removedDatesHtml(MockSample $sample): string
    {
        $now = $this->clock->now();
        $dates = [
            $now->add(new DateInterval('P7D'))->format('Y-m-d') . ' ' . $sample->eventTime,
            $now->add(new DateInterval('P14D'))->format('Y-m-d') . ' ' . $sample->eventTime,
        ];

        return '<ul><li>' . implode('</li><li>', $dates) . '</li></ul>';
    }

    public function eventsHtml(MockSample $sample): string
    {
        $url = sprintf('%s/%s/event/%d', $sample->host, $sample->locale, $sample->eventId);

        return sprintf(
            '<div class="card"><p><strong>%s</strong></p><p>%s - %s</p>'
            . '<p><a href="%s">More Info</a> &nbsp; <a href="%s#rsvp">I Want to Go</a></p></div>',
            htmlspecialchars($sample->eventTitle),
            $sample->eventStart,
            htmlspecialchars($sample->eventLocation),
            $url,
            $url,
        );
    }

    public function sectionsHtml(): string
    {
        return '<h3>Users Pending Approval</h3><ul><li>Zuzanna Burke (zuzanna.burke@example.org)</li></ul>'
            . '<h3>Reported Images</h3><ul><li>Event teaser reported by Rene Wells</li></ul>';
    }
}
