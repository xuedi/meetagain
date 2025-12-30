<?php declare(strict_types=1);

/**
 * Simple Template Renderer for Installer.
 *
 * Handles template loading, variable replacement, conditionals, and loop processing.
 * Supports: {{ var }}, {% if condition %}, {% for item in items %}
 * Designed to work without dependencies before composer install.
 */
class TemplateRenderer
{
    private string $templateDir;

    public function __construct(string $templateDir)
    {
        $this->templateDir = $templateDir;
    }

    /**
     * Render a template with variables.
     *
     * @param array<string, mixed> $vars
     */
    public function render(string $template, array $vars = []): string
    {
        $templatePath = $this->templateDir . '/' . $template . '.html';

        if (!file_exists($templatePath)) {
            return "Template not found: $template";
        }

        $content = file_get_contents($templatePath);

        // Handle arrays for loops first (conditionals inside loops will be processed there)
        $content = $this->processLoops($content, $vars);

        // Process remaining conditionals (outside loops)
        $content = $this->processConditionals($content, $vars);

        // Simple template variable replacement
        foreach ($vars as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $content = str_replace('{{ ' . $key . ' }}', htmlspecialchars((string) $value), $content);
            }
        }

        // Load into layout
        $layoutPath = $this->templateDir . '/layout.html';
        if (file_exists($layoutPath)) {
            $layout = file_get_contents($layoutPath);
            $layout = str_replace('{{ content }}', $content, $layout);
            $layout = str_replace('{{ current_step }}', (string) ($vars['current_step'] ?? 1), $layout);

            // Process conditionals in layout too
            $layout = $this->processConditionals($layout, $vars);

            return $layout;
        }

        return $content;
    }

    /**
     * Process template loops.
     *
     * Handles simple loop syntax: {% for item in items %}...{% endfor %}
     * Supports both scalar and associative array iteration.
     *
     * @param array<string, mixed> $vars
     */
    private function processLoops(string $content, array $vars): string
    {
        $pattern = '/{%\s*for\s+(\w+)\s+in\s+(\w+)\s*%}(.*?){%\s*endfor\s*%}/s';

        return preg_replace_callback($pattern, function ($matches) use ($vars) {
            $itemVar = $matches[1];
            $arrayVar = $matches[2];
            $loopContent = $matches[3];
            $output = '';

            if (isset($vars[$arrayVar]) && is_array($vars[$arrayVar])) {
                foreach ($vars[$arrayVar] as $item) {
                    $itemContent = $loopContent;

                    // Create a context for this loop iteration
                    $loopVars = $vars;
                    if (is_array($item)) {
                        $loopVars[$itemVar] = $item;

                        // Process conditionals within the loop with loop variables
                        $itemContent = $this->processConditionals($itemContent, $loopVars);

                        // Replace variables
                        foreach ($item as $key => $value) {
                            $itemContent = str_replace(
                                '{{ ' . $itemVar . '.' . $key . ' }}',
                                htmlspecialchars((string) $value),
                                $itemContent
                            );
                        }
                    } else {
                        $itemContent = str_replace('{{ ' . $itemVar . ' }}', htmlspecialchars((string) $item), $itemContent);
                    }
                    $output .= $itemContent;
                }
            }

            return $output;
        }, $content) ?? $content;
    }

    /**
     * Process template conditionals.
     *
     * Handles conditional syntax: {% if condition %}...{% endif %}
     * Supports: {% if var %}, {% if var == value %}, {% if var >= value %}
     * Also supports {% else %} blocks
     *
     * @param array<string, mixed> $vars
     */
    private function processConditionals(string $content, array $vars): string
    {
        // Pattern for if/else/endif blocks
        $pattern = '/{%\s*if\s+([^%]+?)\s*%}(.*?)(?:{%\s*else\s*%}(.*?))?{%\s*endif\s*%}/s';

        return preg_replace_callback($pattern, function ($matches) use ($vars) {
            $condition = trim($matches[1]);
            $ifContent = $matches[2];
            $elseContent = $matches[3] ?? '';

            $result = $this->evaluateCondition($condition, $vars);

            return $result ? $ifContent : $elseContent;
        }, $content) ?? $content;
    }

    /**
     * Evaluate a conditional expression.
     *
     * @param array<string, mixed> $vars
     */
    private function evaluateCondition(string $condition, array $vars): bool
    {
        // Handle comparison operators
        if (preg_match('/^(\w+(?:\.\w+)?)\s*(==|!=|>=|<=|>|<)\s*(.+)$/', $condition, $matches)) {
            $left = $this->getVarValue($matches[1], $vars);
            $operator = $matches[2];
            $right = trim($matches[3], '\'" ');

            // Try to convert to number if both are numeric
            if (is_numeric($left) && is_numeric($right)) {
                $left = (float) $left;
                $right = (float) $right;
            }

            return match ($operator) {
                '==' => $left == $right,
                '!=' => $left != $right,
                '>=' => $left >= $right,
                '<=' => $left <= $right,
                '>' => $left > $right,
                '<' => $left < $right,
                default => false,
            };
        }

        // Simple variable existence/truthiness check
        $value = $this->getVarValue($condition, $vars);

        // Check if value is truthy
        if (is_array($value)) {
            return count($value) > 0;
        }

        return !empty($value);
    }

    /**
     * Get variable value from vars array, supporting dot notation.
     *
     * @param array<string, mixed> $vars
     */
    private function getVarValue(string $varName, array $vars): mixed
    {
        // Handle dot notation (e.g., req.passed)
        if (str_contains($varName, '.')) {
            $parts = explode('.', $varName, 2);
            if (isset($vars[$parts[0]]) && is_array($vars[$parts[0]])) {
                return $vars[$parts[0]][$parts[1]] ?? null;
            }

            return null;
        }

        return $vars[$varName] ?? null;
    }
}
