<?php declare(strict_types=1);

namespace App\Command;

use Override;
use SimpleXMLElement;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:badge:generate', description: 'Generate coverage badge SVG from coverage report')]
class BadgeGenerateCommand extends Command
{
    public function __construct(
        private readonly string $kernelProjectDir,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cloverXml = $this->kernelProjectDir . '/tests/reports/clover.xml';
        $template = $this->kernelProjectDir . '/tests/badge/coverage_template.svg';
        $badge = $this->kernelProjectDir . '/tests/badge/coverage.svg';

        if (!file_exists($cloverXml)) {
            $output->writeln('<error>No coverage file found: tests/reports/clover.xml</error>');
            $output->writeln('Run tests with coverage first: just test');
            return Command::FAILURE;
        }

        if (!file_exists($template)) {
            $output->writeln('<error>Badge template not found: tests/badge/coverage_template.svg</error>');
            return Command::FAILURE;
        }

        $xml = new SimpleXMLElement(file_get_contents($cloverXml));
        $metrics = $xml->xpath('//metrics');
        $totalElements = 0;
        $checkedElements = 0;

        foreach ($metrics as $metric) {
            $totalElements += (int) $metric['elements'];
            $checkedElements += (int) $metric['coveredelements'];
        }

        if ($totalElements === 0) {
            $output->writeln('<error>No coverage data found in clover.xml</error>');
            return Command::FAILURE;
        }

        $coverage = (int) round(($checkedElements / $totalElements) * 100);

        $color = match (true) {
            $coverage >= 80 => '00CC00',
            $coverage >= 70 => '33CC00',
            $coverage >= 60 => '66CC00',
            $coverage >= 50 => '99CC00',
            $coverage >= 45 => 'AAAA00',
            $coverage >= 40 => 'BBBB00',
            $coverage >= 35 => 'CC9900',
            $coverage >= 30 => 'DD7700',
            $coverage >= 25 => 'EE5500',
            $coverage >= 20 => 'FF3300',
            default => 'FF0000',
        };

        $darkeningFactor = 1.2;
        $R = sprintf('%02X', (int) floor(hexdec(substr($color, 0, 2)) / $darkeningFactor));
        $G = sprintf('%02X', (int) floor(hexdec(substr($color, 2, 2)) / $darkeningFactor));
        $B = sprintf('%02X', (int) floor(hexdec(substr($color, 4, 2)) / $darkeningFactor));

        $values = [
            'coverage' => $coverage,
            'colorStart' => $color,
            'colorStop' => $R . $G . $B,
        ];

        $svg = file_get_contents($template);
        foreach ($values as $search => $replace) {
            $search = '{{' . $search . '}}';
            $svg = str_replace($search, (string) $replace, $svg);
        }
        file_put_contents($badge, $svg);

        $output->writeln("<info>Coverage badge generated: {$coverage}%</info>");
        $output->writeln('Badge saved to: tests/badge/coverage.svg');

        return Command::SUCCESS;
    }
}
