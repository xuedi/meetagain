<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Activity;
use App\Entity\Event;
use App\Entity\User;
use App\Entity\ActivityType;
use App\Repository\ActivityRepository;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\ActivityService;
use App\Service\GlobalService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ActivityServiceTest extends TestCase
{
    private MockObject|EntityManagerInterface $emMock;
    private MockObject|GlobalService $globServiceMock;
    private MockObject|ActivityRepository $activityRepoMock;
    private MockObject|User $userMock;
    private ActivityService $subject;

    protected function setUp(): void
    {
        $this->userMock = $this->createMock(User::class);
        $this->emMock = $this->createMock(EntityManagerInterface::class);
        $this->globServiceMock = $this->createMock(GlobalService::class);
        $this->activityRepoMock = $this->createMock(ActivityRepository::class);
        $this->subject = new ActivityService($this->globServiceMock, $this->emMock, $this->activityRepoMock);
    }

    public function testSimpleLoggingActivity(): void
    {
        $expectedMessage = '';
        $expectedUserMock = $this->userMock;
        $expectedUserActivity = ActivityType::Login;

        $this->emMock->expects($this->once())->method('flush');
        $this->emMock->expects($this->once())->method('persist')
            ->with($this->callback(function (Activity $actual) use ($expectedUserActivity, $expectedUserMock, $expectedMessage) {
                $this->assertSame($actual->getType(), $expectedUserActivity, 'Failed property match: Type');
                $this->assertSame($actual->getUser(), $expectedUserMock, 'Failed property match: User');
                $this->assertSame($actual->getMessage(), $expectedMessage, 'Failed property match: Message');

                return true;
            }));

        $this->subject->log($expectedUserActivity, $expectedUserMock, []);
    }

    #[DataProvider('getAllLoggingActivityCases')]
    public function testAllLoggingActivity(ActivityType $expectedUserActivity): void
    {
        $expectedUserMock = $this->userMock;

        $this->emMock->expects($this->once())->method('flush');
        $this->emMock->expects($this->once())->method('persist')
            ->with($this->callback(function (Activity $actual) use ($expectedUserActivity, $expectedUserMock) {
                $this->assertSame($actual->getType(), $expectedUserActivity, 'Failed property match: Type');
                $this->assertSame($actual->getUser(), $expectedUserMock, 'Failed property match: User');

                return true;
            }));

        $validMetaData = ['old' => 'x', 'new' => 'x', 'event_id' => 1, 'user_id' => 2];

        $this->subject->log($expectedUserActivity, $expectedUserMock, $validMetaData);
    }

    public static function getAllLoggingActivityCases(): array
    {
        return [
            [ActivityType::ChangedUsername],
            [ActivityType::Login],
            [ActivityType::RsvpYes],
            [ActivityType::RsvpNo],
            [ActivityType::Registered],
            [ActivityType::FollowedUser],
            [ActivityType::UnFollowedUser],
        ];
    }

    #[DataProvider('getAllActivityPreparationCases')]
    public function testActivityPreparation(ActivityType $userActivity, string $expectedMessage, array $metaData): void
    {
        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock->method('getUserNameList')->willReturn([1 => 'UserNumberOne']);

        $eventRepoMock = $this->createMock(EventRepository::class);
        $eventRepoMock->method('getEventNameList')->willReturn([1 => 'EventNumberOne', 2 => '#2']);


        $repoList = [
            User::class => $userRepoMock,
            Event::class => $eventRepoMock,
        ];
        $this->emMock
            ->method('getRepository')
            ->willReturnCallback(function ($arg) use ($repoList) {
                return $repoList[$arg];
            });


        $activity = new Activity();
        $activity->setType($userActivity);
        $activity->setMeta($metaData);
        $activity = $this->subject->prepareActivity($activity);

        $this->assertEquals($activity->getMessage(), $expectedMessage);
    }

    public static function getAllActivityPreparationCases(): array
    {
        return [
            [ActivityType::ChangedUsername, 'Changed username from oldUsername to newUsername', [
                'old' => 'oldUsername',
                'new' => 'newUsername'
            ]],
            [ActivityType::FollowedUser, 'Started following: UserNumberOne', [
                'user_id' => 1,
            ]],
            [ActivityType::RsvpYes, 'Going to event: EventNumberOne', [
                'event_id' => 1,
            ]],
            [ActivityType::RsvpNo, 'Is skipping event: #2', [
                'event_id' => 2,
            ]],
            [ActivityType::UnFollowedUser, '', []],
            [ActivityType::Registered, 'User registered', []],
            [ActivityType::Login, 'User logged in', []],
        ];
    }
}
