# Testing

How to write unit and functional tests for plugin code.

---

## Test directory structure

```
plugins/your-plugin/
  tests/
    Unit/
      Service/
        YourServiceTest.php
      Filter/
        YourFilterTest.php
      Authorization/
        YourAuthorizationTest.php
    Functional/
      Controller/
        YourControllerTest.php
```

Mirror the `src/` structure inside `tests/Unit/` and `tests/Functional/` for easy navigation.

---

## Unit testing a service

Use PHPUnit with the Arrange / Act / Assert pattern and mock dependencies.
Add AAA comments — they make the test intent immediately clear.

```php
namespace Plugin\YourPlugin\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Plugin\YourPlugin\Repository\YourRepository;
use Plugin\YourPlugin\Service\YourService;
use Twig\Environment;

class YourServiceTest extends TestCase
{
    public function testGetEventTileReturnsNullWhenNoData(): void
    {
        // Arrange
        $repository = $this->createMock(YourRepository::class);
        $repository->method('findByEventId')->willReturn(null);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        $sut = new YourService($repository, $twig);

        // Act
        $result = $sut->getEventTile(42);

        // Assert
        $this->assertNull($result);
    }

    public function testGetEventTileRendersTemplateWhenDataExists(): void
    {
        // Arrange
        $data = new YourEntity();
        $repository = $this->createMock(YourRepository::class);
        $repository->method('findByEventId')->with(42)->willReturn($data);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@YourPlugin/tile.html.twig', ['data' => $data])
            ->willReturn('<div>tile</div>');

        $sut = new YourService($repository, $twig);

        // Act
        $result = $sut->getEventTile(42);

        // Assert
        $this->assertSame('<div>tile</div>', $result);
    }
}
```

---

## Testing an EventFilterInterface

Mock the user context and assert the filter returns the correct event ID set:

```php
namespace Plugin\YourPlugin\Tests\Unit\Filter;

use PHPUnit\Framework\TestCase;
use Plugin\YourPlugin\Filter\PrivateEventFilter;
use Plugin\YourPlugin\Repository\AccessRepository;

class PrivateEventFilterTest extends TestCase
{
    public function testGetEventIdFilterReturnsOnlyAccessibleIds(): void
    {
        // Arrange
        $repository = $this->createMock(AccessRepository::class);
        $repository->method('getAccessibleEventIds')->willReturn([1, 3, 5]);

        $filter = new PrivateEventFilter($repository);

        // Act
        $result = $filter->getEventIdFilter();

        // Assert
        $this->assertSame([1, 3, 5], $result);
    }

    public function testGetEventIdFilterReturnsEmptyWhenNoRestrictions(): void
    {
        // Arrange
        $repository = $this->createMock(AccessRepository::class);
        $repository->method('getAccessibleEventIds')->willReturn([]);

        $filter = new PrivateEventFilter($repository);

        // Act
        $result = $filter->getEventIdFilter();

        // Assert
        $this->assertEmpty($result);
    }
}
```

---

## Testing an EventActionGuardInterface

Assert that the provider returns `true`, `false`, or `null` for each action type:

```php
namespace Plugin\YourPlugin\Tests\Unit\Authorization;

use PHPUnit\Framework\TestCase;
use Plugin\YourPlugin\Authorization\MembershipAuthorizationProvider;
use Plugin\YourPlugin\Repository\MembershipRepository;

class MembershipAuthorizationProviderTest extends TestCase
{
    public function testRsvpIsAllowedForActiveMember(): void
    {
        // Arrange
        $repository = $this->createMock(MembershipRepository::class);
        $repository->method('isActiveMember')->willReturn(true);

        $provider = new MembershipAuthorizationProvider($repository);
        $user = $this->createMock(User::class);

        // Act
        $result = $provider->canPerformAction('event.rsvp', 1, $user);

        // Assert
        $this->assertTrue($result);
    }

    public function testRsvpIsDeniedForNonMember(): void
    {
        // Arrange
        $repository = $this->createMock(MembershipRepository::class);
        $repository->method('isActiveMember')->willReturn(false);

        $provider = new MembershipAuthorizationProvider($repository);

        // Act
        $result = $provider->canPerformAction('event.rsvp', 1, null);

        // Assert
        $this->assertFalse($result);
    }

    public function testUnknownActionReturnsNull(): void
    {
        // Arrange
        $repository = $this->createMock(MembershipRepository::class);
        $provider = new MembershipAuthorizationProvider($repository);

        // Act
        $result = $provider->canPerformAction('some.unknown.action', 1, null);

        // Assert
        $this->assertNull($result);
    }
}
```

---

## Running tests

```bash
# Run all tests for a specific plugin
just testUnit plugins/your-plugin/tests/

# Run a single test class
just testUnit plugins/your-plugin/tests/Unit/Service/YourServiceTest.php

# Run functional tests for a plugin
just testFunctional plugins/your-plugin/tests/Functional/
```

---

## Reference

- [Core testing guide](../core-development/testing.md) — PHPUnit patterns, AAA, mocks vs stubs, coverage
- [Fixtures](../core-development/fixtures.md) — how to load test data in functional tests
