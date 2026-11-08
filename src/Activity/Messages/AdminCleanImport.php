<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class AdminCleanImport extends MessageAbstract
{
    public const string TYPE = 'core.admin_clean_import';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('archive');

        return $this;
    }

    protected function renderText(): string
    {
        return $this->translator->trans('profile_social.activity_admin_clean_import', [
            '%archive%' => $this->meta['archive'],
        ]);
    }
}
