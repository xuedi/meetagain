<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use Plugin\Photos\Form\ConfigType;
use Plugin\Photos\ValueObject\Config;
use Symfony\Component\Form\Forms;

class ConfigTypeTest extends TestCase
{
    public function testEverySettingOnTheValueObjectIsReachableFromTheForm(): void
    {
        // Arrange
        $form = Forms::createFormFactory()->create(ConfigType::class, new Config()->setMemberStreams(false));

        // Act
        $fields = array_keys($form->all());

        // Assert
        static::assertSame(['memberUploads', 'showCameraMeta', 'memberStreams', 'eventBox', 'contest', 'contestSubmissionsPerMember'], $fields);
        static::assertFalse($form->get('memberStreams')->getData());
    }
}
