<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\BlockType\FieldDefinition;
use App\Entity\CmsBlock;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Repository\CmsBlockRepository;
use App\Service\Cms\RichTextNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:cms:block-normalize', description: 'Rewrite rich-text CMS blocks into the stored line model')]
class CmsBlockNormalizeCommand extends Command
{
    public function __construct(
        private readonly CmsBlockRepository $blockRepository,
        private readonly RichTextNormalizer $normalizer,
        private readonly EntityManagerInterface $em,
        private readonly EntityActionDispatcher $entityActionDispatcher,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change without writing');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $scanned = 0;
        $rows = [];
        $touchedPageIds = [];

        foreach ($this->blockRepository->findAll() as $block) {
            $scanned++;
            $changes = $this->normalizeBlock($block);
            if ($changes === []) {
                continue;
            }

            $rows[] = [
                $block->getId(),
                $block->getPage()->getId(),
                $block->getLanguage(),
                $block->getType()->value,
                implode(', ', $changes),
            ];

            $pageId = $block->getPage()->getId();
            if ($pageId !== null) {
                $touchedPageIds[$pageId] = true;
            }
        }

        if ($rows === []) {
            $io->success(sprintf('Scanned %d blocks, all already normalized.', $scanned));

            return Command::SUCCESS;
        }

        $io->table(['Block', 'Page', 'Locale', 'Type', 'Fields'], $rows);

        if ($dryRun) {
            $this->em->clear();
            $io->note(sprintf('Dry run: %d of %d blocks would change. Nothing was written.', count($rows), $scanned));

            return Command::SUCCESS;
        }

        $this->em->flush();
        foreach (array_keys($touchedPageIds) as $pageId) {
            $this->entityActionDispatcher->dispatch(EntityAction::UpdateCmsBlock, $pageId);
        }

        $io->success(sprintf('Normalized %d of %d blocks.', count($rows), $scanned));

        return Command::SUCCESS;
    }

    /**
     * @return array<string> names of the fields whose value actually changed
     */
    private function normalizeBlock(CmsBlock $block): array
    {
        $type = $block->getType();
        if ($type === null) {
            return [];
        }

        $json = $block->getJson();
        $changed = [];

        foreach ($type->getBlockClass()::getFieldDefinitions() as $field) {
            if (!$this->isNormalizable($field, $json)) {
                continue;
            }

            $normalized = $this->normalizer->toStorage($json[$field->name]);
            if ($normalized === $json[$field->name]) {
                continue;
            }

            $json[$field->name] = $normalized;
            $changed[] = $field->name;
        }

        if ($changed !== []) {
            $block->setJson($json);
            $this->em->persist($block);
        }

        return $changed;
    }

    /**
     * @param array<string, mixed> $json
     */
    private function isNormalizable(FieldDefinition $field, array $json): bool
    {
        if (!$field->richText || !isset($json[$field->name]) || !is_string($json[$field->name])) {
            return false;
        }

        return $this->isFlattenedByTheEditor($json[$field->name]);
    }

    private function isFlattenedByTheEditor(string $html): bool
    {
        return $this->normalizer->containsBlankParagraph($html);
    }
}
