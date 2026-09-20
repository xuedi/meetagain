<?php declare(strict_types=1);

namespace Plugin\Glossary\Service;

use DateInterval;
use DateTimeImmutable;
use Override;
use Plugin\Glossary\Entity\TrainerCard;
use Plugin\Glossary\Enum\CardState;
use Plugin\Glossary\Enum\Grade;

final readonly class Sm2Scheduler implements SchedulerInterface
{
    private const int EASE_FLOOR = 1300;

    #[Override]
    public function schedule(TrainerCard $card, Grade $grade, DateTimeImmutable $now): void
    {
        $repetitions = $card->getRepetitions();
        $wasLearned = $repetitions > 0;

        if ($grade->isPass()) {
            $interval = match ($repetitions) {
                0 => 1,
                1 => 6,
                default => (int) round(($card->getIntervalDays() * $card->getEasePermille()) / 1000),
            };
            $card->setRepetitions($repetitions + 1)->setState(CardState::Review);
        } else {
            $interval = 1;
            $card->setRepetitions(0)->setState($wasLearned ? CardState::Relearning : CardState::Learning);
            if ($wasLearned) {
                $card->setLapses($card->getLapses() + 1);
            }
        }

        $miss = 5 - $this->quality($grade);
        $easeDelta = 100 - ($miss * (80 + ($miss * 20)));

        $card
            ->setEasePermille(max(self::EASE_FLOOR, $card->getEasePermille() + $easeDelta))
            ->setIntervalDays($interval)
            ->setDueAt($now->add(new DateInterval(sprintf('P%dD', $interval))));
    }

    private function quality(Grade $grade): int
    {
        return match ($grade) {
            Grade::Again => 2,
            Grade::Hard => 3,
            Grade::Good => 4,
            Grade::Easy => 5,
        };
    }
}
