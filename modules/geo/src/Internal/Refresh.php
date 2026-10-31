<?php declare(strict_types=1);

namespace Module\Geo\Internal;

use Module\Geo\Internal\Import\Dataset;
use Module\Geo\Internal\Import\Loader;
use Module\Geo\Internal\Repository\CountryRepository;
use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use Override;

readonly class Refresh implements TaskInterface
{
    public function __construct(
        private CountryRepository $countries,
        private Loader $loader,
        private ImportAction $action,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return 'geo-refresh';
    }

    #[Override]
    public function getCadence(): Cadence
    {
        return new Cadence('20 4 7 * *', jitterSeconds: 3600, offloaded: true);
    }

    #[Override]
    public function getDescription(): string
    {
        return 'Keeps the imported countries and their states in step with GeoNames. Once a month it downloads both lists again and updates names, codes and populations; nothing is ever deleted. Cities, districts and translations are not touched - they are imported by hand. While no country has been imported it does nothing and never reaches the network.';
    }

    #[Override]
    public function run(): Result
    {
        if (!$this->countries->hasAny()) {
            return new Result(Status::ok, 'No country imported, nothing to refresh');
        }

        $options = $this->action->options();
        $countries = $this->loader->import(Dataset::Countries, $options);
        $states = $this->loader->import(Dataset::States, $options);

        return new Result(Status::ok, sprintf(
            '%d countries and %d states updated, %d and %d new',
            $countries->updated,
            $states->updated,
            $countries->created,
            $states->created,
        ));
    }
}
