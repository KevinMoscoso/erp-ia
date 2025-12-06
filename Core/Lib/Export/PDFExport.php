<?php

namespace ERPIA\Lib\Export;

use ERPIA\Core\DatabaseWhere;
use ERPIA\Models\Base\BusinessDocument;
use ERPIA\Models\Base\ModelClass;
use ERPIA\Core\HttpResponse;
use ERPIA\Core\NumberFormatter;
use ERPIA\Core\Internationalization;
use ERPIA\Models\FormatoDocumento;

/**
 * PDF export implementation for ERPIA
 */
class PDFExport extends PDFDocument
{
    const BATCH_LIMIT = 500;
    
    /** @var object|null */
    protected $documentFormat = null;
    
    /** @var object|null */
    protected $pdfGenerator = null;
    
    /**
     * Export a business document to PDF
     */
    public function exportBusinessDocument(BusinessDocument $model): bool
    {
        if ($this->documentFormat === null) {
            $this->documentFormat = $this->getDocumentFormatting($model);
        }
        
        if ($this->pdfGenerator === null) {
            $this->createNewPage();
        } else {
            $this->pdfGenerator->startNewPage();
            $this->headerInserted = false;
        }
        
        $this->insertPageHeader($model->companyId);
        $this->insertBusinessDocumentHeader($model);
        $this->insertBusinessDocumentBody($model);
        $this->insertBusinessDocumentFooter($model);
        
        $editableInvoiceTypes = ['CustomerInvoice', 'SupplierInvoice'];
        if (in_array($model->getModelClassName(), $editableInvoiceTypes) && $model->isEditable()) {
            $this->pdfGenerator->setColor(200, 0, 0);
            $this->pdfGenerator->addText(0, 230, 15, 
                Internationalization::translate('draft-invoice-warning'), 
                600, 'center', -35);
            $this->pdfGenerator->setColor(0, 0, 0);
        }
        
        return false;
    }
    
    /**
     * Export a list of models to PDF with pagination
     */
    public function exportModelList(ModelClass $model, array $conditions, array $orderBy, 
                                  int $startOffset, array $columns, string $title = ''): bool
    {
        $this->setOutputFilename($title);
        
        $pageOrientation = 'portrait';
        $tableColumns = [];
        $columnHeaders = [];
        $tableConfig = [
            'cols' => [], 
            'shadeCol' => [0.95, 0.95, 0.95], 
            'shadeHeadingCol' => [0.95, 0.95, 0.95]
        ];
        $tableData = [];
        $shortenedTitles = [];
        
        $this->configureTableColumns($columns, $tableColumns, $columnHeaders, $tableConfig);
        
        if (count($tableColumns) > 5) {
            $pageOrientation = 'landscape';
            $this->shortenColumnTitles($shortenedTitles, $columnHeaders);
        }
        
        $this->createNewPage($pageOrientation);
        $tableConfig['width'] = $this->tableWidth;
        $this->insertPageHeader();
        
        $records = $model->getAll($conditions, $orderBy, $startOffset, self::BATCH_LIMIT);
        
        if (empty($records)) {
            $this->pdfGenerator->createTable($tableData, $columnHeaders, '', $tableConfig);
        }
        
        while (!empty($records)) {
            $tableData = $this->prepareTableData($records, $tableColumns, $tableConfig);
            $this->filterEmptyColumns($tableData, $columnHeaders, NumberFormatter::formatNumber(0));
            $this->pdfGenerator->createTable($tableData, $columnHeaders, $title, $tableConfig);
            
            $startOffset += self::BATCH_LIMIT;
            $records = $model->getAll($conditions, $orderBy, $startOffset, self::BATCH_LIMIT);
        }
        
        $this->restoreColumnTitles($shortenedTitles, $columnHeaders);
        $this->insertPageFooter();
        return true;
    }
    
    /**
     * Export a single model to PDF
     */
    public function exportSingleModel(ModelClass $model, array $columns, string $title = ''): bool
    {
        $this->createNewPage();
        $companyId = $model->companyId ?? null;
        $this->insertPageHeader($companyId);
        
        $tableColumns = [];
        $columnHeaders = [];
        $tableConfig = [
            'width' => $this->tableWidth,
            'showHeadings' => 0,
            'shaded' => 0,
            'lineCol' => [1, 1, 1],
            'cols' => []
        ];
        
        $this->configureTableColumns($columns, $tableColumns, $columnHeaders, $tableConfig);
        
        $tableRows = [];
        foreach ($columnHeaders as $key => $header) {
            $value = $tableConfig['cols'][$key]['widget']->getPlainText($model);
            $tableRows[] = ['key' => $header, 'value' => $this->sanitizeCellValue($value)];
        }
        
        $title .= ': ' . $model->getPrimaryDescription();
        $this->pdfGenerator->addText("\n" . $this->sanitizeCellValue($title) . "\n", self::FONT_SIZE + 6);
        $this->addLineBreak();
        
        $this->insertTwoColumnTable($tableRows, '', $tableConfig);
        $this->insertPageFooter();
        return true;
    }
    
    /**
     * Export a generic table to PDF
     */
    public function exportTable(array $headers, array $rows, array $options = [], string $title = ''): bool
    {
        $pageOrientation = count($headers) > 5 ? 'landscape' : 'portrait';
        $this->createNewPage($pageOrientation);
        
        $tableConfig = [
            'width' => $this->tableWidth,
            'shadeCol' => [0.95, 0.95, 0.95],
            'shadeHeadingCol' => [0.95, 0.95, 0.95],
            'cols' => []
        ];
        
        foreach (array_keys($headers) as $key) {
            if (array_key_exists($key, $options)) {
                $tableConfig['cols'][$key]['justification'] = $options[$key]['display'] ?? 'left';
                continue;
            }
            
            $rightAlignColumns = ['debit', 'credit', 'balance', 'previous_balance', 'total'];
            if (in_array($key, $rightAlignColumns)) {
                $tableConfig['cols'][$key]['justification'] = 'right';
            }
        }
        
        $this->insertPageHeader();
        $this->pdfGenerator->createTable($rows, $headers, $title, $tableConfig);
        $this->insertPageFooter();
        return true;
    }
    
    /**
     * Get the complete PDF document content
     */
    public function getDocumentContent()
    {
        if ($this->pdfGenerator === null) {
            $this->createNewPage();
            $this->pdfGenerator->addText('');
        }
        
        return $this->pdfGenerator->generateOutput();
    }
    
    /**
     * Initialize a new PDF document
     */
    public function initializeDocument(string $title, int $formatId, string $languageCode): void
    {
        $this->setOutputFilename($title);
        
        if (!empty($formatId)) {
            $this->documentFormat = new FormatoDocumento();
            $this->documentFormat->loadById($formatId);
        }
        
        if (!empty($languageCode)) {
            Internationalization::setLanguage($languageCode);
        }
    }
    
    /**
     * Set company for document header
     */
    public function setDocumentCompany(int $companyId): void
    {
        if ($this->pdfGenerator === null) {
            $this->createNewPage();
        }
        
        $this->insertPageHeader($companyId);
    }
    
    /**
     * Send PDF document to HTTP response
     */
    public function sendToResponse(HttpResponse &$response): void
    {
        $response->setHeader('Content-Type', 'application/pdf');
        $response->setHeader('Content-Disposition', 'inline; filename=' . $this->getOutputFilename() . '.pdf');
        $response->setContent($this->getDocumentContent());
    }
    
    /**
     * Configure table columns from widget definitions
     */
    protected function configureTableColumns(array $columns, array &$tableCols, 
                                           array &$colHeaders, array &$tableConfig): void
    {
        foreach ($columns as $column) {
            if (is_string($column)) {
                continue;
            }
            
            if (isset($column->columns)) {
                $this->configureTableColumns($column->columns, $tableCols, $colHeaders, $tableConfig);
                continue;
            }
            
            if (!$column->isHidden()) {
                $fieldName = $column->widget->fieldName;
                $tableCols[] = $fieldName;
                $colHeaders[$fieldName] = Internationalization::translate($column->title);
                
                if (isset($column->display)) {
                    $tableConfig['cols'][$fieldName]['justification'] = $column->display;
                }
                
                $tableConfig['cols'][$fieldName]['widget'] = $column->widget;
            }
        }
    }
    
    /**
     * Prepare table data from model records
     */
    protected function prepareTableData(array $records, array $columns, array $config): array
    {
        $tableData = [];
        
        foreach ($records as $index => $record) {
            foreach ($columns as $column) {
                if (isset($config['cols'][$column]['widget'])) {
                    $widget = $config['cols'][$column]['widget'];
                    $value = $widget->getPlainText($record);
                    $tableData[$index][$column] = $this->sanitizeCellValue($value);
                } else {
                    $tableData[$index][$column] = $record->{$column} ?? '';
                }
            }
        }
        
        return $tableData;
    }
    
    /**
     * Shorten column titles for better display
     */
    protected function shortenColumnTitles(array &$shortened, array &$titles): void
    {
        foreach ($titles as $key => $title) {
            if (strlen($title) > 15) {
                $shortened[$key] = $title;
                $titles[$key] = substr($title, 0, 15) . '...';
            }
        }
    }
    
    /**
     * Restore original column titles
     */
    protected function restoreColumnTitles(array $shortened, array &$titles): void
    {
        foreach ($shortened as $key => $original) {
            if (isset($titles[$key])) {
                $titles[$key] = $original;
            }
        }
    }
    
    /**
     * Filter out empty columns from table data
     */
    protected function filterEmptyColumns(array &$data, array &$headers, string $zeroValue): void
    {
        if (empty($data)) {
            return;
        }
        
        $columnsToRemove = [];
        foreach ($headers as $key => $header) {
            $isEmpty = true;
            foreach ($data as $row) {
                if (isset($row[$key]) && $row[$key] !== $zeroValue && $row[$key] !== '') {
                    $isEmpty = false;
                    break;
                }
            }
            
            if ($isEmpty) {
                $columnsToRemove[] = $key;
            }
        }
        
        foreach ($columnsToRemove as $key) {
            unset($headers[$key]);
            foreach ($data as &$row) {
                unset($row[$key]);
            }
        }
    }
    
    /**
     * Sanitize cell value for PDF output
     */
    protected function sanitizeCellValue(string $value): string
    {
        return htmlspecialchars_decode($value, ENT_QUOTES);
    }
}