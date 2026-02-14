<?php declare(strict_types=1);

namespace App\Doctrine\Query\Mysql;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

class JsonContains extends FunctionNode
{
    public Node $jsonDoc;
    public Node $value;

    public function getSql(SqlWalker $sqlWalker): string
    {
        return (
            'JSON_CONTAINS('
            . $this->jsonDoc->dispatch($sqlWalker)
            . ', '
            . $this->value->dispatch($sqlWalker)
            . ')'
        );
    }

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->jsonDoc = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->value = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
