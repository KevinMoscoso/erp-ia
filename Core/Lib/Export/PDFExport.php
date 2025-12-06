<?php

namespace ERPIA\Lib\Export;

use ERPIA\Core\DataBase\DataBaseWhere;
use ERPIA\Models\Base\BusinessDocument;
use ERPIA\Models\Base\ModelClass;
use ERPIA\Core\Response;
use ERPIA\Core\I18n;
use ERPIA\Core\NumberFormatter;
use ERPIA\Lib\PDF\PDFDocument;

/**
 * PDF export data.
 */
class PDFExport extends PDFDocument
{
    const EXPORT_BATCH_SIZE = 500;
    
    /**
     * Adds a new page with the document data.
     */
    public function addBusinessDocumentPage($model): bool
    {
        if ($this->documentFormat === null) {
            $this->documentFormat = $this->getDocumentFormatSettings($model);
        }
        
        // New page
        if ($this->pdfEngine === null) {
            $this->startNewPage();
        } else {
            $this->pdfEngine->addNewPage();
            $this->headerInserted = false;
        }
        
        $this->addPageHeader($model->company_id ?? null);
        $this->addDocumentHeader($model);
        $this->addDocumentBody($model);
        $this->addDocumentFooter($model);
        
        // Add draft warning for editable invoices
        $invoiceTypes = ['CustomerInvoice', 'SupplierInvoice'];
        if (in_array($model->modelClassName(), $invoiceTypes) && $model->isEditable()) {
            $this->pdfEngine->setTextColor(200, 0, 0);
            $this->pdfEngine->addText(0, 230, 15, 
                I18n::trans('draft-invoice-warning'), 600, 'center', -35);
            $this->pdfEngine->setTextColor(0, 0, 0);
        }
        
        return false;
    }
    
    /**
     * Adds a new page with a table listing the model's data.
     */
    public function addModelListPage($model, $where, $order, $offset, $columns, $title = ''): bool
    {
        $this->setOutputFileName($title);
        
        $pageOrientation = 'portrait';
        $tableColumns = [];
        $columnTitles = [];
        $tableConfig = [
            'cols' => [], 
            'shadeCol' => [0.95, 0.95, 0.95], 
            'shadeHeadingCol' => [0.95, 0.95, 0.95]
        ];
        $tableData = [];
        $longTitleList = [];
        
        // Convert widget columns to needed arrays
        $this->prepareTableColumns($columns, $tableColumns, $columnTitles, $tableConfig);
        if (count($tableColumns) > 5) {
            $pageOrientation = 'landscape';
            $this->truncateLongTitles($longTitleList, $columnTitles);
        }
        
        $this->startNewPage($pageOrientation);
        $tableConfig['width'] = $this->tableMaxWidth;
        $this->addPageHeader();
        
        $records = $model->getAll($where, $order, $offset, self::EXPORT_BATCH_SIZE);
        if (empty($records)) {
            $this->pdfEngine->createTable($tableData, $columnTitles, '', $tableConfig);
        }
        
        while (!empty($records)) {
            $tableData = $this->prepareTableRows($records, $tableColumns, $tableConfig);
            $this->filterEmptyColumns($tableData, $columnTitles, NumberFormatter::format(0));
            $this->pdfEngine->createTable($tableData, $columnTitles, $title, $tableConfig);
            
            // Advance within the results
            $offset += self::EXPORT_BATCH_SIZE;
            $records = $model->getAll($where, $order, $offset, self::EXPORT_BATCH_SIZE);
        }
        
        $this->restoreLongTitles($longTitleList, $columnTitles);
        $this->addPageFooter();
        return true;
    }
    
    /**
     * Adds a new page with the model data.
     */
    public function addModelPage($model, $columns, $title = ''): bool
    {
        $this->startNewPage();
        $companyId = $model->company_id ?? null;
        $this->addPageHeader($companyId);
        
        $tableColumns = [];
        $columnTitles = [];
        $tableConfig = [
            'width' => $this->tableMaxWidth,
            'showHeadings' => 0,
            'shaded' => 0,
            'lineCol' => [1, 1, 1],
            'cols' => []
        ];
        
        // Get the columns
        $this->prepareTableColumns($columns, $tableColumns, $columnTitles, $tableConfig);
        
        $modelData = [];
        foreach ($columnTitles as $key => $colTitle) {
            $value = $tableConfig['cols'][$key]['widget']->getPlainText($model);
            $modelData[] = ['field' => $colTitle, 'content' => $this->sanitizeValue($value)];
        }
        
        $title .= ': ' . $model->getPrimaryDescription();
        $this->pdfEngine->addText("\n" . $this->sanitizeValue($title) . "\n", self::TITLE_FONT_SIZE);
        $this->addEmptyLine();
        
        $this->createTwoColumnTable($modelData, '', $tableConfig);
        $this->addPageFooter();
        return true;
    }
    
    /**
     * Adds a new page with a table.
     */
    public function addTablePage($headers, $rows, $options = [], $title = ''): bool
    {
        $pageOrientation = count($headers) > 5 ? 'landscape' : 'portrait';
        $this->startNewPage($pageOrientation);
        
        $tableConfig = [
            'width' => $this->tableMaxWidth,
            'shadeCol' => [0.95, 0.95, 0.95],
            'shadeHeadingCol' => [0.95, 0.95, 0.95],
            'cols' => []
        ];
        
        foreach (array_keys($headers) as $key) {
            if (array_key_exists($key, $options)) {
                $tableConfig['cols'][$key]['justification'] = $options[$key]['display'] ?? 'left';
                continue;
            }
            
            $numericFields = ['debit', 'credit', 'balance', 'previous_balance', 'total'];
            if (in_array($key, $numericFields)) {
                $tableConfig['cols'][$key]['justification'] = 'right';
            }
        }
        
        $this->addPageHeader();
        $this->pdfEngine->createTable($rows, $headers, $title, $tableConfig);
        $this->addPageFooter();
        return true;
    }
    
    /**
     * Returns the full document.
     */
    public function getDocument()
    {
        if ($this->pdfEngine === null) {
            $this->startNewPage();
            $this->pdfEngine->addText('');
        }
        
        return $this->pdfEngine->generateOutput();
    }
    
    /**
     * Initializes a new document.
     */
    public function newDocument(string $title, int $formatId, string $languageCode)
    {
        $this->setOutputFileName($title);
        
        if (!empty($formatId)) {
            $this->documentFormat = new DocumentFormat();
            $this->documentFormat->loadById($formatId);
        }
        
        if (!empty($languageCode)) {
            I18n::setLanguage($languageCode);
        }
    }
    
    /**
     * Sets the company for the document.
     */
    public function setCompany(int $companyId): void
    {
        // New page
        if ($this->pdfEngine === null) {
            $this->startNewPage();
        }
        
        $this->addPageHeader($companyId);
    }
    
    /**
     * Sets headers and outputs document content to response.
     */
    public function show(Response &$response)
    {
        $response->setHeader('Content-Type', 'application/pdf');
        $response->setHeader('Content-Disposition', 'inline; filename=' . $this->getOutputFileName() . '.pdf');
        $response->setContent($this->getDocument());
    }
    
    /**
     * Prepares table columns from widget columns.
     */
    protected function prepareTableColumns(array $columns, array &$tableCols, array &$colTitles, array &$tableOptions): void
    {
        $widgetMap = $this->getColumnWidgetMap($columns);
        
        foreach ($widgetMap as $key => $widget) {
            $tableCols[$key] = $key;
            $colTitles[$key] = $this->getColumnTitleMap($columns)[$key] ?? $key;
            $tableOptions['cols'][$key] = [
                'widget' => $widget,
                'justification' => $this->getColumnAlignmentMap($columns)[$key] ?? 'left'
            ];
        }
    }
    
    /**
     * Prepares table rows from cursor data.
     */
    protected function prepareTableRows(array $cursor, array $columns, array $options): array
    {
        $tableData = [];
        
        foreach ($cursor as $index => $row) {
            foreach ($columns as $key) {
                $widget = $options['cols'][$key]['widget'] ?? null;
                if ($widget && method_exists($widget, 'getPlainText')) {
                    $tableData[$index][$key] = $this->sanitizeValue($widget->getPlainText($row));
                } else {
                    $tableData[$index][$key] = $this->sanitizeValue($row->{$key} ?? '');
                }
            }
        }
        
        return $tableData;
    }
    
    /**
     * Removes empty columns from table data.
     */
    protected function filterEmptyColumns(array &$data, array &$titles, $emptyValue): void
    {
        if (empty($data)) {
            return;
        }
        
        $keysToRemove = [];
        foreach (array_keys($titles) as $key) {
            $allEmpty = true;
            foreach ($data as $row) {
                if (isset($row[$key]) && $row[$key] !== $emptyValue && $row[$key] !== '') {
                    $allEmpty = false;
                    break;
                }
            }
            
            if ($allEmpty) {
                $keysToRemove[] = $key;
            }
        }
        
        foreach ($keysToRemove as $key) {
            unset($titles[$key]);
            foreach ($data as &$row) {
                unset($row[$key]);
            }
        }
    }
    
    /**
     * Truncates long titles for landscape mode.
     */
    protected function truncateLongTitles(array &$longTitles, array &$titles): void
    {
        foreach ($titles as $key => $title) {
            if (strlen($title) > 15) {
                $longTitles[$key] = $title;
                $titles[$key] = substr($title, 0, 12) . '...';
            }
        }
    }
    
    /**
     * Restores long titles after table.
     */
    protected function restoreLongTitles(array $longTitles, array &$titles): void
    {
        if (empty($longTitles)) {
            return;
        }
        
        $this->addEmptyLine();
        $this->pdfEngine->addText(I18n::trans('legend') . ':', self::NORMAL_FONT_SIZE);
        
        foreach ($longTitles as $key => $title) {
            $titles[$key] = $title;
            $this->pdfEngine->addText('  ' . $key . ': ' . $title, self::SMALL_FONT_SIZE);
        }
    }
    
    /**
     * Sanitizes a value for PDF output.
     */
    protected function sanitizeValue($value): string
    {
        if (is_null($value)) {
            return '';
        }
        
        $stringValue = (string)$value;
        // Remove any characters that might cause issues in PDF
        $stringValue = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $stringValue);
        return $stringValue;
    }
    
    /**
     * Creates a two-column table for model data.
     */
    protected function createTwoColumnTable(array $data, string $title, array $options): void
    {
        $halfPoint = ceil(count($data) / 2);
        $leftColumn = array_slice($data, 0, $halfPoint);
        $rightColumn = array_slice($data, $halfPoint);
        
        $tableConfig = [
            'width' => $this->tableMaxWidth,
            'showHeadings' => 0,
            'shaded' => 0,
            'lineCol' => [0.8, 0.8, 0.8],
            'cols' => [
                'field' => ['justification' => 'right', 'width' => 200],
                'content' => ['justification' => 'left', 'width' => 200]
            ]
        ];
        
        $combinedData = [];
        $maxRows = max(count($leftColumn), count($rightColumn));
        
        for ($i = 0; $i < $maxRows; $i++) {
            $row = [];
            if (isset($leftColumn[$i])) {
                $row['left_field'] = $leftColumn[$i]['field'];
                $row['left_content'] = $leftColumn[$i]['content'];
            }
            if (isset($rightColumn[$i])) {
                $row['right_field'] = $rightColumn[$i]['field'];
                $row['right_content'] = $rightColumn[$i]['content'];
            }
            $combinedData[] = $row;
        }
        
        $combinedHeaders = [
            'left_field' => '',
            'left_content' => '',
            'right_field' => '',
            'right_content' => ''
        ];
        
        $this->pdfEngine->createTable($combinedData, $combinedHeaders, $title, $tableConfig);
    }
    
    /**
     * Adds an empty line to the PDF.
     */
    protected function addEmptyLine(): void
    {
        $this->pdfEngine->addText("\n");
    }
}