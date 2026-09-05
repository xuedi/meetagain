<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service;

use Plugin\Boardgames\Entity\Game;
use Plugin\Boardgames\Enum\PlayerFit;

readonly class FitCalculator
{
    public function forGame(Game $game, int $headcount): PlayerFit
    {
        $min = $game->getMinPlayers();
        $max = $game->getMaxPlayers();

        if ($min === null && $max === null) {
            return PlayerFit::Unknown;
        }

        if ($headcount <= 0) {
            return PlayerFit::Unknown;
        }

        if ($min !== null && $headcount < $min) {
            return PlayerFit::TooFew;
        }

        if ($max !== null && $headcount > $max) {
            return PlayerFit::TooMany;
        }

        return PlayerFit::Fits;
    }
}
