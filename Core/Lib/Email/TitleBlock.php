<?php

namespace ERPIA\Lib\Email;

/**
 * Title block for displaying headings in emails
 */
class TitleBlock extends ContentBlock
{
    /** @var string */
    protected $headingText;
    
    /** @var string */
    protected $headingType;
    
    /** @var string */
    protected $cssClass;
    
    /** @var string */
    protected $inlineStyle;
    
    public function __construct(string $text, string $type = 'h2', string $css = '', string $style = '')
    {
        $this->headingText = $this->sanitizeText($text);
        $this->headingType = $this->validateHeadingType($type);
        $this->cssClass = $this->sanitizeCssClass($css);
        $this->inlineStyle = $this->sanitizeInlineStyle($style);
    }
    
    /**
     * Render the title block as HTML heading
     */
    public function render(): string
    {
        $element = $this->headingType;
        $class = $this->getHeadingClass();
        $style = $this->inlineStyle;
        
        return sprintf(
            '<%s class="%s" style="%s">%s</%s>',
            $element,
            $class,
            $style,
            $this->headingText,
            $element
        );
    }
    
    /**
     * Sanitize text content for HTML output
     */
    private function sanitizeText(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate heading type is one of h1-h6
     */
    private function validateHeadingType(string $type): string
    {
        $allowedTypes = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
        $type = strtolower($type);
        
        if (in_array($type, $allowedTypes)) {
            return $type;
        }
        
        return 'h2';
    }
    
    /**
     * Sanitize CSS class string
     */
    private function sanitizeCssClass(string $css): string
    {
        $css = trim($css);
        // Allow only safe characters for CSS classes
        return preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $css);
    }
    
    /**
     * Sanitize inline style string
     */
    private function sanitizeInlineStyle(string $style): string
    {
        $style = trim($style);
        // Basic sanitization for inline styles
        return preg_replace('/[<>\(\)\[\]\{\}]/', '', $style);
    }
    
    /**
     * Get the heading class, with default if none provided
     */
    private function getHeadingClass(): string
    {
        if (!empty($this->cssClass)) {
            return $this->cssClass;
        }
        
        return 'email-heading';
    }
}