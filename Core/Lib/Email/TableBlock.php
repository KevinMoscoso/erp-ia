<?php

namespace ERPIA\Lib\Email;

/**
 * Table block for displaying tabular data in emails
 */
class TableBlock extends ContentBlock
{
    /** @var array */
    protected $columnHeaders;
    
    /** @var array */
    protected $dataRows;
    
    /** @var string */
    protected $cssClass;
    
    /** @var string */
    protected $inlineStyle;
    
    public function __construct(array $headers, array $rows, string $css = '', string $style = '')
    {
        $this->columnHeaders = $this->validateHeaders($headers);
        $this->dataRows = $this->validateRows($rows);
        $this->cssClass = $this->sanitizeCssClass($css);
        $this->inlineStyle = $this->sanitizeInlineStyle($style);
    }
    
    /**
     * Render the table as HTML
     */
    public function render(): string
    {
        if (empty($this->columnHeaders) || empty($this->dataRows)) {
            return '';
        }
        
        $tableClass = $this->getTableClass();
        $tableStyle = $this->getTableStyle();
        
        return sprintf(
            '<table class="%s" style="%s">%s%s</table>',
            $tableClass,
            $tableStyle,
            $this->buildTableHeader(),
            $this->buildTableBody()
        );
    }
    
    /**
     * Validate and sanitize headers
     */
    private function validateHeaders(array $headers): array
    {
        $validated = [];
        foreach ($headers as $header) {
            $validated[] = htmlspecialchars((string)$header, ENT_QUOTES, 'UTF-8');
        }
        return $validated;
    }
    
    /**
     * Validate and sanitize rows
     */
    private function validateRows(array $rows): array
    {
        $validated = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            
            $validatedRow = [];
            foreach ($row as $cell) {
                $validatedRow[] = htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8');
            }
            $validated[] = $validatedRow;
        }
        return $validated;
    }
    
    /**
     * Sanitize CSS class string
     */
    private function sanitizeCssClass(string $css): string
    {
        $css = trim($css);
        // Remove any dangerous characters, allow only alphanumeric, dash, underscore, space
        $css = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $css);
        return $css;
    }
    
    /**
     * Sanitize inline style string
     */
    private function sanitizeInlineStyle(string $style): string
    {
        $style = trim($style);
        // Basic sanitization - in real implementation would need more thorough check
        $style = preg_replace('/[<>\(\)\[\]\{\}]/', '', $style);
        return $style;
    }
    
    /**
     * Get the table class, with default if none provided
     */
    private function getTableClass(): string
    {
        if (!empty($this->cssClass)) {
            return $this->cssClass;
        }
        
        return 'email-data-table spacing-3 full-width';
    }
    
    /**
     * Get the table style
     */
    private function getTableStyle(): string
    {
        return $this->inlineStyle;
    }
    
    /**
     * Build the table header HTML
     */
    private function buildTableHeader(): string
    {
        $headerCells = '';
        foreach ($this->columnHeaders as $header) {
            $headerCells .= '<th>' . $header . '</th>';
        }
        
        return '<thead><tr>' . $headerCells . '</tr></thead>';
    }
    
    /**
     * Build the table body HTML
     */
    private function buildTableBody(): string
    {
        $bodyRows = '';
        foreach ($this->dataRows as $row) {
            $bodyRows .= '<tr>';
            foreach ($row as $cell) {
                $bodyRows .= '<td>' . $cell . '</td>';
            }
            $bodyRows .= '</tr>';
        }
        
        return '<tbody>' . $bodyRows . '</tbody>';
    }
}