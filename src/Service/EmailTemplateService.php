<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\EmailTemplate;
use App\Repository\EmailTemplateRepository;

readonly class EmailTemplateService
{
    public function __construct(private EmailTemplateRepository $repo)
    {
    }

    public function getTemplate(string $identifier): ?EmailTemplate
    {
        return $this->repo->findByIdentifier($identifier);
    }

    public function renderContent(string $content, array $context): string
    {
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $content = str_replace('{{' . $key . '}}', (string) $value, $content);
            }
        }

        return $content;
    }

    public function getDefaultTemplates(): array
    {
        return [
            'verification_request' => [
                'subject' => 'Please Confirm your Email',
                'body' => '<h1>Welcome {{username}}!</h1>

<p>
    You signed up as for {{url}} please confirm your email address by clicking the link below.
</p>

<p>
    <a href="{{host}}/{{lang}}/register/verify/{{token}}">Confirm your email</a>
</p>

<p>
    Cheers,<br>
    xuedi & yimu
</p>',
                'variables' => ['username', 'token', 'host', 'url', 'lang'],
            ],
            'welcome' => [
                'subject' => 'Welcome!',
                'body' => '<h1>Welcome to {{url}}</h1>

<p>
    Your account just got approved, welcome to the community
</p>

<p>
    Feel free to <a href="{{host}}/{{lang}}/profile/">Login</a>
</p>

<p>
    Cheers,<br>
    xuedi & yimu
</p>',
                'variables' => ['host', 'url', 'lang'],
            ],
            'password_reset_request' => [
                'subject' => 'Password reset request',
                'body' => '<h1>Hello {{username}}!</h1>

<p>
    Someone (hopefully you) requested to reset your password.
</p>
<p>
    If you did not request this, you can safely ignore this email.
</p>
<p>
    If you did, please click the link below to continue.
</p>

<p>
    <a href="{{host}}/{{lang}}/reset/verify/{{token}}">Reset your password</a>
</p>

<p>
    Cheers,<br>
    xuedi & yimu
</p>',
                'variables' => ['username', 'token', 'host', 'lang'],
            ],
            'notification_message' => [
                'subject' => 'You received a message from {{sender}}',
                'body' => '<h1>Hello {{username}}!</h1>

<p>
    <b>{{sender}}</b> just has sent you a message <br>
</p>
<p>
    You can read the message <a href="{{host}}/{{lang}}/profile/messages/{{senderId}}">here</a>.
</p>
<p>
    If you don\'t like to receive this notification, you can<br>
    switch them off in your <a href="{{host}}/{{lang}}/profile/config/">settings</a>
</p>

<p>
    Cheers,<br>
    xuedi & yimu
</p>',
                'variables' => ['username', 'sender', 'senderId', 'host', 'lang'],
            ],
            'notification_rsvp' => [
                'subject' => 'A user you follow plans to attend an event',
                'body' => '<h1>Hello {{username}}!</h1>

<p>
    <b>{{followedUserName}}</b> plans to attend the <b>{{eventTitle}}</b> event<br>
     at the <b>{{eventLocation}}</b> on the <b>{{eventDate}}</b>.
</p>
<p>
    You can have a look at the Event details <a href="{{host}}/{{lang}}/event/{{eventId}}">here</a>.
</p>
<p>
    If you don\'t like to receive this notification, you can<br>
    switch them off in your <a href="{{host}}/{{lang}}/profile/config/">settings</a>
</p>

<p>
    Cheers,<br>
    xuedi & yimu
</p>',
                'variables' => ['username', 'followedUserName', 'eventLocation', 'eventDate', 'eventId', 'eventTitle', 'host', 'lang'],
            ],
            'notification_event_canceled' => [
                'subject' => 'Event canceled: {{eventTitle}}',
                'body' => '<h1>Hello {{username}}!</h1>

<p>
    We\'re sorry to inform you that the <b>{{eventTitle}}</b> event<br>
    at the <b>{{eventLocation}}</b> on the <b>{{eventDate}}</b> has been <b>canceled</b>.
</p>
<p>
    You can still view the event details <a href="{{host}}/{{lang}}/event/{{eventId}}">here</a>.
</p>
<p>
    If you don\'t like to receive this notification, you can<br>
    switch them off in your <a href="{{host}}/{{lang}}/profile/config/">settings</a>
</p>

<p>
    Cheers,<br>
    xuedi & yimu
</p>',
                'variables' => ['username', 'eventLocation', 'eventDate', 'eventId', 'eventTitle', 'host', 'lang'],
            ],
        ];
    }
}
