<?php declare(strict_types=1);

namespace Plugin\Glossary\Entity;

enum SuggestionField: string
{
    case Phrase = 'phrase';
    case Pinyin = 'pinyin';
    case Category = 'category';
    case Explanation = 'explanation';
}
