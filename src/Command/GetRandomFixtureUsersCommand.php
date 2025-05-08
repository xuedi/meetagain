<?php declare(strict_types=1);

namespace App\Command;

use App\DataFixtures\UserFixture;
use Random\RandomException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:fixtures:generateUsers', description: 'outputs random user figures as array items',)]
class GetRandomFixtureUsersCommand extends Command
{
    public function __construct(private readonly UserFixture $userFixture)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = __DIR__ . '/../DataFixtures/UserFixture.php';
        $search = "[]";

        $content = file_get_contents($file);
        $pos = strpos($content, $search);
        while ($pos !== false) {
            $content = $this->strReplaceFirst($search, "[" . $this->getRandomUserList() . "]", $content);
            $pos = strpos($content, $search);
        }
        file_put_contents($file, $content);

        return Command::SUCCESS;
    }

    private function strReplaceFirst($search, $replace, $subject): string
    {
        $search = '/' . preg_quote($search, '/') . '/';
        return preg_replace($search, $replace, $subject, 1);
    }

    /**
     * @throws RandomException
     */
    private function getRandomUserList(): string
    {
        $number = random_int(3 + random_int(1, 3), 8 + random_int(1, 10));
        $allUsers = $this->userFixture->getUsernames();
        $randomUsers = array_rand($allUsers, $number);

        $elements = [];
        foreach ($randomUsers as $randomUser) {
            $elements[] = "'" . $allUsers[$randomUser] . "'";
        }

        // special users
        $specialUser = ['xuedi', 'Adem Lane', 'Crystal Liu'];
        foreach ($specialUser as $user) {
            if (random_int(1, 11) > 7) {
                $elements[] = "'" . $user . "'";
            }
        }

        return implode(',', $elements);
    }
}
