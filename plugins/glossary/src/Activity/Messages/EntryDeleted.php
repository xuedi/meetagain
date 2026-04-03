<?php declare(strict_types=1);

namespace Plugin\Glossary\Activity\Messages;

use App\Activity\MessageAbstract;

class EntryDeleted extends MessageAbstract
{
    public const string TYPE = 'glossary.entry_deleted';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('glossary_id');
        $this->ensureIsNumeric('glossary_id');
        $this->ensureHasKey('term');

        return $this;
    }

    protected function renderText(): string
    {
        return sprintf('Deleted glossary entry: %s', $this->meta['term']);
    }

    protected function renderHtml(): string
    {
        return sprintf('Deleted glossary entry: <strong>%s</strong>', $this->escapeHtml($this->meta['term']));
    }
}
