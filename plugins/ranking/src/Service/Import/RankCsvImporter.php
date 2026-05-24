<?php declare(strict_types=1);

namespace Plugin\Ranking\Service\Import;

use App\Activity\ActivityService;
use App\Entity\User;
use App\Repository\UserRepository;
use Plugin\Ranking\Activity\Messages\BulkImported;
use Plugin\Ranking\Entity\RankingConfig;
use Plugin\Ranking\Enum\RankChangeReason;
use Plugin\Ranking\Repository\RankDefinitionRepository;
use Plugin\Ranking\Service\RankAssignmentService;
use Plugin\Ranking\ValueObject\RankImportReport;
use Plugin\Ranking\ValueObject\RankImportRowError;

readonly class RankCsvImporter
{
    public function __construct(
        private RankAssignmentService $assignmentService,
        private RankDefinitionRepository $definitionRepository,
        private UserRepository $userRepository,
        private ActivityService $activityService,
    ) {}

    public function import(string $csvPath, RankingConfig $config, User $actor): RankImportReport
    {
        $errors = [];
        $imported = 0;

        $handle = @fopen($csvPath, 'rb');
        if ($handle === false) {
            $errors[] = new RankImportRowError(0, 'Cannot open CSV');

            return new RankImportReport(0, $errors);
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false || $header === null) {
                return new RankImportReport(0, [new RankImportRowError(0, 'Empty CSV')]);
            }
            $header = array_map(static fn($h): string => strtolower(trim((string) $h)), $header);
            $emailIdx = array_search('member_email', $header, true);
            $rankIdx = array_search('rank', $header, true);
            $ratingIdx = array_search('rating', $header, true);

            if ($emailIdx === false) {
                return new RankImportReport(0, [new RankImportRowError(0, 'Missing member_email column')]);
            }

            $definitionsByLabel = [];
            foreach ($this->definitionRepository->findByConfig($config) as $d) {
                $definitionsByLabel[strtolower($d->getLabel())] = $d;
            }

            $rowNumber = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                $email = isset($row[$emailIdx]) ? trim((string) $row[$emailIdx]) : '';
                if ($email === '') {
                    $errors[] = new RankImportRowError($rowNumber, 'Missing email');
                    continue;
                }

                $user = $this->userRepository->findOneBy(['email' => strtolower($email)]);
                if ($user === null) {
                    $errors[] = new RankImportRowError($rowNumber, sprintf('User not found: %s', $email));
                    continue;
                }

                if ($config->getArchetype()->isNumeric()) {
                    $rawValue = $ratingIdx !== false && isset($row[$ratingIdx]) ? trim((string) $row[$ratingIdx]) : null;
                    if ($rawValue === null || $rawValue === '' || !is_numeric($rawValue)) {
                        $errors[] = new RankImportRowError($rowNumber, sprintf('Invalid rating for %s', $email));
                        continue;
                    }
                    $this->assignmentService->assignNumeric($config, (int) $user->getId(), $actor, (int) $rawValue, RankChangeReason::Import);
                    $imported++;
                    continue;
                }

                $rawRank = $rankIdx !== false && isset($row[$rankIdx]) ? trim((string) $row[$rankIdx]) : null;
                if ($rawRank === null || $rawRank === '') {
                    $errors[] = new RankImportRowError($rowNumber, sprintf('Missing rank for %s', $email));
                    continue;
                }
                $definition = $definitionsByLabel[strtolower($rawRank)] ?? null;
                if ($definition === null) {
                    $errors[] = new RankImportRowError($rowNumber, sprintf('Unknown rank "%s" for %s', $rawRank, $email));
                    continue;
                }
                $this->assignmentService->assignDefinition($config, (int) $user->getId(), $actor, $definition, RankChangeReason::Import);
                $imported++;
            }
        } finally {
            fclose($handle);
        }

        if ($imported > 0) {
            $this->activityService->log(BulkImported::TYPE, $actor, [
                'group_id' => $config->getGroupId(),
                'count' => $imported,
            ]);
        }

        return new RankImportReport($imported, $errors);
    }
}
