<?php
/**
 * Template Helper Class
 * Provides a clean API for including template components with parameters
 */

class Template
{
    private static string $basePath = __DIR__ . '/templates/';

    /**
     * Render a template with the given parameters
     */
    public static function render(string $template, array $params = []): void
    {
        // Closure scope prevents $params keys from colliding with or leaking into outer variables
        (static function(string $__path, array $__vars): void {
            extract($__vars, EXTR_SKIP);
            include $__path;
        })(self::$basePath . $template . '.php', $params);
    }

    /**
     * Render page head (doctype + head section)
     */
    public static function pageHead(string $title = '', array $css = [], string $themeColor = '#2d5016', string $basePath = ''): void
    {
        $manifestPath = $basePath . 'manifest.json';
        $cssBasePath = $basePath;
        $pageTitle = $title;
        include self::$basePath . 'page_head.php';
    }

    /**
     * Render compact header with back button
     */
    public static function header(string $title, string $backHref = '', string $rightHtml = '<span></span>'): void
    {
        $compact = true;
        include self::$basePath . 'header.php';
    }

    /**
     * Render flash message if present
     */
    public static function flash(?array $flash): void
    {
        include self::$basePath . 'flash.php';
    }

    /**
     * Render empty state
     */
    public static function emptyState(string $message, string $actionHref = '', string $actionText = '', string $extraClass = ''): void
    {
        include self::$basePath . 'empty_state.php';
    }

    /**
     * Render form error div (hidden)
     */
    public static function formError(string $id = 'formError'): void
    {
        include self::$basePath . 'form_error.php';
    }

    /**
     * Render form message div (hidden)
     */
    public static function formMessage(string $id = 'formMessage'): void
    {
        include self::$basePath . 'form_message.php';
    }
}
