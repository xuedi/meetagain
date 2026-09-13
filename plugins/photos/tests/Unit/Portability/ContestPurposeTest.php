<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Unit\Portability;

use App\Entity\User;
use App\Portability\ImageImporter;
use App\Portability\ImportContext;
use App\Portability\Scope;
use Module\Ballot\Contract\BallotSubject;
use PHPUnit\Framework\TestCase;
use Plugin\Photos\Portability\ContestPurpose;
use Plugin\Photos\Service\ContestService;

final class ContestPurposeTest extends TestCase
{
    public function testItClaimsTheContestOnly(): void
    {
        // Arrange
        $purpose = new ContestPurpose();

        // Act
        $claims = [$purpose->supports(ContestService::PURPOSE), $purpose->supports('event.item.photo')];

        // Assert
        static::assertSame([true, false], $claims);
    }

    public function testAContestBelongsToAScopeHoldingOneOfItsEntrants(): void
    {
        // Arrange
        $purpose = new ContestPurpose();
        $scope = new Scope(itemIds: ['photo' => [12]]);

        // Act
        $inScope = [
            $purpose->inScope(ContestService::PURPOSE, null, ['11', '12'], $scope),
            $purpose->inScope(ContestService::PURPOSE, null, ['11', '13'], $scope),
            $purpose->inScope(ContestService::PURPOSE, new BallotSubject('event', 9), ['12'], $scope),
        ];

        // Assert
        static::assertSame([true, false, false], $inScope);
    }

    public function testEntrantsAreRekeyedToTheImportedPhotos(): void
    {
        // Arrange
        $context = new ImportContext($this->createStub(ImageImporter::class), '/archive', new User());
        $context->mapItems('photo', [12 => 120]);
        $purpose = new ContestPurpose();

        // Act
        $keys = [$purpose->importKey(ContestService::PURPOSE, '12', $context), $purpose->importKey(ContestService::PURPOSE, '13', $context)];

        // Assert
        static::assertSame(['120', null], $keys);
    }
}
