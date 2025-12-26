<?php declare(strict_types=1);

/**
 * Simple Template Renderer for Installer
 *
 * Handles template loading, variable replacement, and simple loop processing.
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
     * Render a template with variables
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

        // Simple template variable replacement
        foreach ($vars as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $content = str_replace('{{ ' . $key . ' }}', htmlspecialchars((string) $value), $content);
            }
        }

        // Handle arrays for loops
        $content = $this->processLoops($content, $vars);

        // Load into layout
        $layoutPath = $this->templateDir . '/layout.html';
        if (file_exists($layoutPath)) {
            $layout = file_get_contents($layoutPath);
            $layout = str_replace('{{ content }}', $content, $layout);
            $layout = str_replace('{{ current_step }}', (string) ($vars['current_step'] ?? 1), $layout);
            return $layout;
        }

        return $content;
    }

    /**
     * Process template loops
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
                    if (is_array($item)) {
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
}
