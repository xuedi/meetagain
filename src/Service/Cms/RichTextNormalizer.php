<?php declare(strict_types=1);

namespace App\Service\Cms;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;

readonly class RichTextNormalizer
{
    private const string BLANK_PATTERN = '/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u';

    public function toStorage(string $html): string
    {
        $document = $this->parse($html);
        if ($document === null) {
            return '';
        }

        $out = '';
        $group = [];

        foreach ($document->body->childNodes as $node) {
            if ($this->isIgnorableText($node)) {
                continue;
            }

            if (!$this->isParagraph($node)) {
                $out .= $this->flush($group) . $document->saveHtml($node);
                $group = [];
                continue;
            }

            if ($this->isBlankParagraph($node)) {
                $out .= $this->flush($group);
                $group = [];
                continue;
            }

            $lines = $this->splitOnBreaks($document, $node);

            if ($this->isAlreadyStoredParagraph($lines)) {
                $out .= $this->flush($group) . $this->flush($lines);
                $group = [];
                continue;
            }

            $group = array_merge($group, $lines);
        }

        return $out . $this->flush($group);
    }

    public function toEditor(string $html): string
    {
        $document = $this->parse($html);
        if ($document === null) {
            return '';
        }

        $out = '';
        $previousWasParagraph = false;

        foreach ($document->body->childNodes as $node) {
            if ($this->isIgnorableText($node)) {
                continue;
            }

            if (!$this->isParagraph($node)) {
                $out .= $document->saveHtml($node);
                $previousWasParagraph = false;
                continue;
            }

            if ($this->isBlankParagraph($node)) {
                continue;
            }

            if ($previousWasParagraph) {
                $out .= '<p></p>';
            }

            foreach ($this->splitOnBreaks($document, $node) as $line) {
                $out .= '<p>' . $line . '</p>';
            }

            $previousWasParagraph = true;
        }

        return $out;
    }

    public function containsBlankParagraph(string $html): bool
    {
        $document = $this->parse($html);
        if ($document === null) {
            return false;
        }

        foreach ($document->body->childNodes as $node) {
            if ($this->isParagraph($node) && $this->isBlankParagraph($node)) {
                return true;
            }
        }

        return false;
    }

    private function parse(string $html): ?HTMLDocument
    {
        if ($this->trimBlank($html) === '') {
            return null;
        }

        return HTMLDocument::createFromString(
            '<!DOCTYPE html><html><body>' . $html . '</body></html>',
            LIBXML_NOERROR,
        );
    }

    /**
     * @param array<string> $group
     */
    private function flush(array $group): string
    {
        return $group === [] ? '' : '<p>' . implode('<br>', $group) . '</p>';
    }

    /**
     * @param array<string> $lines
     */
    private function isAlreadyStoredParagraph(array $lines): bool
    {
        return count($lines) > 1;
    }

    private function isParagraph(Node $node): bool
    {
        return $node instanceof Element && $node->localName === 'p';
    }

    private function isIgnorableText(Node $node): bool
    {
        return $node instanceof Text && $this->trimBlank($node->textContent) === '';
    }

    private function isBlankParagraph(Element $paragraph): bool
    {
        if ($this->trimBlank($paragraph->textContent) !== '') {
            return false;
        }

        foreach ($paragraph->childNodes as $child) {
            if ($child instanceof Element && $child->localName !== 'br') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string>
     */
    private function splitOnBreaks(HTMLDocument $document, Element $paragraph): array
    {
        $lines = [];
        $current = '';

        foreach ($paragraph->childNodes as $child) {
            if ($child instanceof Element && $child->localName === 'br') {
                $lines[] = $current;
                $current = '';
                continue;
            }

            $current .= $document->saveHtml($child);
        }

        $lines[] = $current;

        return array_values(array_filter(
            array_map($this->trimBlank(...), $lines),
            static fn(string $line): bool => $line !== '',
        ));
    }

    private function trimBlank(string $value): string
    {
        return preg_replace(self::BLANK_PATTERN, '', $value) ?? $value;
    }
}
