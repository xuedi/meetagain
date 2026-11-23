<?php declare(strict_types=1);

namespace Plugin\Books;

use App\Anonymization\AnonymizerInterface;
use App\Anonymization\Claim;
use App\Anonymization\Context;
use Override;

readonly class Anonymizer implements AnonymizerInterface
{
    #[Override]
    public function getOrder(): int
    {
        return 40;
    }

    #[Override]
    public function getClaims(): array
    {
        return [
            Claim::columns('plg_books_book', kept: ['isbn', 'title', 'author', 'description']),
        ];
    }

    #[Override]
    public function anonymize(Context $context): void {}
}
