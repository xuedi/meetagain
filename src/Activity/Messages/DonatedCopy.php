<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class DonatedCopy extends MessageAbstract
{
    public const string TYPE = 'core.circulation_donated_copy';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('item_type');
        $this->ensureHasKey('item_id');
        $this->ensureIsNumeric('item_id');

        return $this;
    }

    protected function renderText(): string
    {
        return $this->translator->trans('profile_social.activity_circulation_donated', [
            '%circulation%' => $this->translator->trans('circulation.link_label'),
        ]);
    }

    protected function renderHtml(): string
    {
        $url = $this->router->generate('app_circulation_dashboard', ['itemType' => (string) $this->meta['item_type']]);
        $link = sprintf('<a href="%s">%s</a>', $url, $this->escapeHtml($this->translator->trans('circulation.link_label')));

        return $this->translator->trans('profile_social.activity_circulation_donated', ['%circulation%' => $link]);
    }
}
