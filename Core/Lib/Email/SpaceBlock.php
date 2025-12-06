<?php

namespace ERPIA\Lib\Email;

/**
 * Space block for creating vertical spacing in emails
 */
class SpaceBlock extends ContentBlock
{
    /** @var float */
    protected $verticalSpace;
    
    public function __construct(float $spacing = 30.0)
    {
        $this->verticalSpace = $this->validateSpacing($spacing);
    }
    
    /**
     * Render the space block as HTML
     */
    public function render(): string
    {
        $height = $this->getSafeHeightValue();
        return $this->generateSpacingHtml($height);
    }
    
    /**
     * Get the current spacing value
     */
    public function getSpacing(): float
    {
        return $this->verticalSpace;
    }
    
    /**
     * Set a new spacing value
     */
    public function setSpacing(float $spacing): self
    {
        $this->verticalSpace = $this->validateSpacing($spacing);
        return $this;
    }
    
    /**
     * Validate and sanitize spacing value
     */
    private function validateSpacing(float $spacing): float
    {
        // Ensure spacing is positive
        if ($spacing < 0) {
            return 30.0;
        }
        
        // Limit maximum spacing to prevent layout issues
        if ($spacing > 500) {
            return 500.0;
        }
        
        return $spacing;
    }
    
    /**
     * Get safe height value for CSS
     */
    private function getSafeHeightValue(): string
    {
        // Convert to integer if whole number, otherwise keep one decimal
        if (floor($this->verticalSpace) == $this->verticalSpace) {
            return (int)$this->verticalSpace . 'px';
        }
        
        return number_format($this->verticalSpace, 1, '.', '') . 'px';
    }
    
    /**
     * Generate HTML for vertical spacing
     */
    private function generateSpacingHtml(string $height): string
    {
        return sprintf(
            '<div style="width: 100%%; height: %s; clear: both;"></div>',
            htmlspecialchars($height, ENT_QUOTES, 'UTF-8')
        );
    }
}