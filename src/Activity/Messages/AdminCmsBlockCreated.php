<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class AdminCmsBlockCreated extends MessageAbstract
{
    public const string TYPE = 'core.admin_cms_block_created';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('cms_id');
        $this->ensureIsNumeric('cms_id');
        $this->ensureHasKey('block_id');
        $this->ensureIsNumeric('block_id');
        $this->ensureHasKey('block_type');

        return $this;
    }

    protected function renderText(): string
    {
        return $this->translator->trans('profile_social.activity_admin_cms_block_created', [
            '%type%' => $this->meta['block_type'],
            '%slug%' => $this->meta['cms_slug'] ?? $this->meta['cms_id'],
        ]);
    }

    protected function renderHtml(): string
    {
        return $this->renderText();
    }
}
