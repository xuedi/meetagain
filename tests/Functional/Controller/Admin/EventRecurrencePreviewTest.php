<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventRecurrencePreviewTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';
    private const string ENDPOINT = '/en/admin/events/recurrence/preview';

    public function testReturnsCandidatesForAWeekdayRule(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', self::ENDPOINT, [
            'mode' => 'weekday',
            'period' => 'month',
            'ordinal' => '1',
            'weekday' => 'SU',
            'after' => '2030-01-01',
        ]);

        // Assert
        $this->assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        static::assertCount(6, $payload['candidates']);
        static::assertSame('2030-01-06', $payload['candidates'][0]['date']);
        static::assertSame('FREQ=MONTHLY;BYDAY=1SU', $payload['candidates'][0]['spec']);
        static::assertSame('Every first Sunday of the month', $payload['candidates'][0]['summary']);
        foreach ($payload['candidates'] as $candidate) {
            static::assertSame('Sunday', date('l', (int) strtotime($candidate['date'])));
        }
    }

    public function testReturnsCandidatesForADayOfMonthRule(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', self::ENDPOINT, [
            'mode' => 'day_of_month',
            'period' => 'quarter',
            'day' => ['15'],
            'after' => '2030-01-01',
        ]);

        // Assert
        $this->assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        static::assertSame('2030-01-15', $payload['candidates'][0]['date']);
        static::assertSame('FREQ=MONTHLY;INTERVAL=3;BYMONTHDAY=15', $payload['candidates'][0]['spec']);
        static::assertSame('Every 15th of every quarter', $payload['candidates'][0]['summary']);
    }

    public function testYearlyCandidatesCarryTheMonthOfTheirOwnDate(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', self::ENDPOINT, [
            'mode' => 'weekday',
            'period' => 'year',
            'ordinal' => '1',
            'weekday' => 'SU',
            'after' => '2030-01-01',
        ]);

        // Assert: one candidate per month, each anchoring the yearly rule to its own month
        $this->assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        static::assertCount(12, $payload['candidates']);
        static::assertSame('FREQ=YEARLY;BYMONTH=1;BYDAY=1SU', $payload['candidates'][0]['spec']);
        static::assertSame('FREQ=YEARLY;BYMONTH=2;BYDAY=1SU', $payload['candidates'][1]['spec']);
    }

    public function testReturnsCandidatesForSeveralWeekdaysInAWeek(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', self::ENDPOINT, [
            'mode' => 'weekday',
            'period' => 'week',
            'weekday' => ['WE', 'MO'],
            'after' => '2030-01-01',
        ]);

        // Assert
        $this->assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        static::assertSame('FREQ=WEEKLY;BYDAY=MO,WE', $payload['candidates'][0]['spec']);
        static::assertSame('Every Monday and Wednesday', $payload['candidates'][0]['summary']);
        static::assertSame('2030-01-02', $payload['candidates'][0]['date']);
    }

    public function testReturnsCandidatesForSeveralOrdinalsInAMonth(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', self::ENDPOINT, [
            'mode' => 'weekday',
            'period' => 'month',
            'ordinal' => ['3', '1'],
            'weekday' => ['FR'],
            'after' => '2030-01-01',
        ]);

        // Assert
        $this->assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        static::assertSame('FREQ=MONTHLY;BYDAY=1FR,3FR', $payload['candidates'][0]['spec']);
        static::assertSame('Every first and third Friday of the month', $payload['candidates'][0]['summary']);
    }

    public function testSeveralWeekdaysCombinedWithAnOrdinalAreNarrowedToOneWeekday(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', self::ENDPOINT, [
            'mode' => 'weekday',
            'period' => 'month',
            'ordinal' => ['1'],
            'weekday' => ['FR', 'MO'],
            'after' => '2030-01-01',
        ]);

        // Assert: a monthly rule carries one weekday, so the selection is corrected rather than refused
        $this->assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        static::assertSame(['MO'], $payload['selection']['weekday']);
        static::assertSame('FREQ=MONTHLY;BYDAY=1MO', $payload['candidates'][0]['spec']);
        static::assertFalse($payload['controls']['weekdayMultiple']);
    }

    public function testADayOfMonthRuleOnAWeeklyPeriodIsMovedToMonthly(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', self::ENDPOINT, [
            'mode' => 'day_of_month',
            'period' => 'week',
            'day' => ['15'],
            'after' => '2030-01-01',
        ]);

        // Assert
        $this->assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        static::assertSame('month', $payload['selection']['period']);
        static::assertSame([15], $payload['selection']['day']);
        static::assertSame('FREQ=MONTHLY;BYMONTHDAY=15', $payload['candidates'][0]['spec']);
        static::assertSame(['month', 'two_months', 'quarter', 'year'], $payload['controls']['periods']);
    }

    public function testAWeeklyPeriodReportsTheOrdinalAsNotApplicable(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', self::ENDPOINT, [
            'mode' => 'weekday',
            'period' => 'two_weeks',
            'ordinal' => ['1'],
            'weekday' => ['SU'],
            'after' => '2030-01-01',
        ]);

        // Assert: a week holds one of each weekday, so the ordinal is dropped, not greyed out
        $this->assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        static::assertSame([], $payload['selection']['ordinal']);
        static::assertFalse($payload['controls']['ordinal']);
        static::assertTrue($payload['controls']['weekdayMultiple']);
        static::assertSame('FREQ=WEEKLY;INTERVAL=2;BYDAY=SU', $payload['candidates'][0]['spec']);
    }

    public function testReturnsCandidatesForSeveralDaysOfTheMonth(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', self::ENDPOINT, [
            'mode' => 'day_of_month',
            'period' => 'month',
            'day' => ['15', '1'],
            'after' => '2030-01-02',
        ]);

        // Assert
        $this->assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        static::assertSame([1, 15], $payload['selection']['day']);
        static::assertSame('FREQ=MONTHLY;BYMONTHDAY=1,15', $payload['candidates'][0]['spec']);
        static::assertSame('Every 1st and 15th of the month', $payload['candidates'][0]['summary']);
        static::assertSame('2030-01-15', $payload['candidates'][0]['date']);
        static::assertSame('2030-02-01', $payload['candidates'][1]['date']);
    }

    public function testTheLastDayCannotBeCombinedWithNumberedDays(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', self::ENDPOINT, [
            'mode' => 'day_of_month',
            'period' => 'month',
            'day' => ['1', '-1'],
            'after' => '2030-01-02',
        ]);

        // Assert: "last day" is its own sentence, so it wins and the numbered days drop out
        $this->assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        static::assertSame([-1], $payload['selection']['day']);
        static::assertSame('FREQ=MONTHLY;BYMONTHDAY=-1', $payload['candidates'][0]['spec']);
    }

    public function testRejectsAnUnknownPeriod(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', self::ENDPOINT, ['mode' => 'weekday', 'period' => 'fortnight']);

        // Assert
        $this->assertResponseStatusCodeSame(400);
    }

    public function testIsNotReachableAnonymously(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', self::ENDPOINT, ['mode' => 'weekday', 'period' => 'month']);

        // Assert
        static::assertResponseStatusCodeSame(302);
    }

    private function loginAsAdmin(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler
            ->selectButton('Login')
            ->form([
                '_username' => self::ADMIN_EMAIL,
                '_password' => self::ADMIN_PASSWORD,
            ]);
        $client->submit($form);
        $client->followRedirect();
    }
}
