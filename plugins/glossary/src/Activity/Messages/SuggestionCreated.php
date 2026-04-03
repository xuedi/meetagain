<?php declare(strict_types=1);

namespace Plugin\Glossary\Activity\Messages;

use App\Activity\MessageAbstract;

class SuggestionCreated extends MessageAbstract
{
    public const string TYPE = 'glossary.suggestion_created';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('glossary_id');
        $this->ensureIsNumeric('glossary_id');
        $this->ensureHasKey('term');
        $this->ensureHasKey('field');

        return $this;
    }

    protected function renderText(): string
    {
        return sprintf('Suggested change to %s for glossary entry: %s', $this->meta['field'], $this->meta['term']);
    }

    protected function renderHtml(): string
    {
        return sprintf(
            'Suggested change to <em>%s</em> for glossary entry: <strong>%s</strong>',
            $this->escapeHtml($this->meta['field']),
            $this->escapeHtml($this->meta['term']),
        );
    }
}
