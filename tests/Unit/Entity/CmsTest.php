<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Cms;
use App\Entity\CmsMenuLocation;
use App\Entity\MenuLocation;
use PHPUnit\Framework\TestCase;

class CmsTest extends TestCase
{
    public function testAddMenuLocationAddsLocation(): void
    {
        // Arrange
        $cms = new Cms();
        $menuLocation = new CmsMenuLocation();
        $menuLocation->setLocation(MenuLocation::TopBar);

        // Act
        $cms->addMenuLocation($menuLocation);

        // Assert
        $this->assertCount(1, $cms->getMenuLocations());
        $this->assertTrue($cms->getMenuLocations()->contains($menuLocation));
    }

    public function testAddMenuLocationSetsCmsReference(): void
    {
        // Arrange
        $cms = new Cms();
        $menuLocation = new CmsMenuLocation();
        $menuLocation->setLocation(MenuLocation::TopBar);

        // Act
        $cms->addMenuLocation($menuLocation);

        // Assert
        $this->assertSame($cms, $menuLocation->getCms());
    }

    public function testAddMenuLocationPreventsDuplicates(): void
    {
        // Arrange
        $cms = new Cms();
        $menuLocation = new CmsMenuLocation();
        $menuLocation->setLocation(MenuLocation::TopBar);

        // Act
        $cms->addMenuLocation($menuLocation);
        $cms->addMenuLocation($menuLocation);

        // Assert
        $this->assertCount(1, $cms->getMenuLocations());
    }

    public function testRemoveMenuLocationRemovesLocation(): void
    {
        // Arrange
        $cms = new Cms();
        $menuLocation = new CmsMenuLocation();
        $menuLocation->setLocation(MenuLocation::TopBar);
        $cms->addMenuLocation($menuLocation);

        // Act
        $cms->removeMenuLocation($menuLocation);

        // Assert
        $this->assertCount(0, $cms->getMenuLocations());
        $this->assertFalse($cms->getMenuLocations()->contains($menuLocation));
    }

    public function testRemoveMenuLocationClearsCmsReference(): void
    {
        // Arrange
        $cms = new Cms();
        $menuLocation = new CmsMenuLocation();
        $menuLocation->setLocation(MenuLocation::TopBar);
        $cms->addMenuLocation($menuLocation);

        // Act
        $cms->removeMenuLocation($menuLocation);

        // Assert
        $this->assertNull($menuLocation->getCms());
    }

    public function testGetMenuLocationsReturnsEmptyCollectionByDefault(): void
    {
        // Arrange
        $cms = new Cms();

        // Act
        $locations = $cms->getMenuLocations();

        // Assert
        $this->assertCount(0, $locations);
    }
}
