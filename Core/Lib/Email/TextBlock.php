<?php

namespace ERPIA\Lib\Email;

/**
 * Text block for displaying paragraph text in emails
 */
class TextBlock extends ContentBlock
{
    /** @var string */
    protected $contentText;
    
    /** @var string */
    protected $cssClass;
    
    /** @var string */
    protected $inlineStyle;
    
    public function __construct(string $text, string $css = '', string $style = '')
    {
        $this->contentText = $this->sanitizeText($text);
        $this->cssClass = $this->sanitizeCssClass($css);
        $this->inlineStyle = $this->sanitizeInlineStyle($style);
    }
    
    /**
     * Render the text block as HTML paragraph
     */
    public function render(): string
    {
        $paragraphClass = $this->getParagraphClass();
        $paragraphStyle = $this->inlineStyle;
        
        return sprintf(
            '<p class="%s" style="%s">%s</p>',
            $paragraphClass,
            $paragraphStyle,
            $this->getFormattedText()
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
     * Get the paragraph class, with default if none provided
     */
    private function getParagraphClass(): string
    {
        if (!empty($this->cssClass)) {
            return $this->cssClass;
        }
        
        return 'text-paragraph';
    }
    
    /**
     * Format text with line breaks
     */
    private function getFormattedText(): string
    {
        return nl2br($this->contentText);
    }
}