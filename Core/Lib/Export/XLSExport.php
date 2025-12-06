<?php

namespace ERPIA\Lib\Export;

use ERPIA\Core\DataBase\DataBaseWhere;
use ERPIA\Models\Base\BusinessDocument;
use ERPIA\Models\Base\ModelClass;
use ERPIA\Core\Response;
use ERPIA\Core\I18n;
use ERPIA\Core\Html;
use XLSXWriter;

/**
 * Excel XLSX export data.
 */
class XLSExport extends ExportBase
{
    const EXPORT_BATCH_SIZE = 5000;
    
    /** @var int */
    protected $sheetCounter = 0;
    
    /** @var XLSXWriter */
    protected $excelWriter;
    
    /**
     * Adds a new page with the document data.
     */
    public function addBusinessDocumentPage($model): bool
    {
        // Process document lines
        $lineData = [];
        $lineHeaders = [];
        
        foreach ($model->getLines() as $line) {
            if (empty($lineHeaders)) {
                $lineHeaders = $this->getModelColumnTypes($line);
            }
            $lineData[] = $line;
        }
        
        $lineRows = $this->getSanitizedCursorData($lineData);
        $sheetName = I18n::trans('lines');
        $this->excelWriter->writeSheet($lineRows, $sheetName, $lineHeaders);
        
        // Process document header
        $docHeaders = $this->getModelColumnTypes($model);
        $docRows = $this->getSanitizedCursorData([$model]);
        $docSheetName = $model->getPrimaryDescription();
        $this->excelWriter->writeSheet($docRows, $docSheetName, $docHeaders);
        
        return false;
    }
    
    /**
     * Adds a new page with a table listing all models data.
     */
    public function addModelListPage($model, $where, $order, $offset, $columns, $title = ''): bool
    {
        $this->setOutputFileName($title);
        
        $sheetName = $this->generateSheetName($title);
        $headers = $this->getModelColumnTypes($model);
        
        $records = $model->getAll($where, $order, $offset, self::EXPORT_BATCH_SIZE);
        if (empty($records)) {
            $this->excelWriter->writeSheet([], $sheetName, $headers);
            return true;
        }
        
        // Write header once
        $this->excelWriter->writeSheetHeader($sheetName, $headers);
        
        // Write rows in batches
        while (!empty($records)) {
            $rows = $this->getSanitizedCursorData($records);
            foreach ($rows as $row) {
                $this->excelWriter->writeSheetRow($sheetName, $row);
            }
            
            $offset += self::EXPORT_BATCH_SIZE;
            $records = $model->getAll($where, $order, $offset, self::EXPORT_BATCH_SIZE);
        }
        
        return true;
    }
    
    /**
     * Adds a new page with the model data.
     */
    public function addModelPage($model, $columns, $title = ''): bool
    {
        $headers = $this->getModelColumnTypes($model);
        $rows = $this->getSanitizedCursorData([$model]);
        $this->excelWriter->writeSheet($rows, $title, $headers);
        return true;
    }
    
    /**
     * Adds a new page with the table.
     */
    public function addTablePage($headers, $rows, $options = [], $title = ''): bool
    {
        $this->sheetCounter++;
        $sheetName = 'sheet' . $this->sheetCounter;
        
        $headerTypes = [];
        foreach ($headers as $header) {
            $headerTypes[$header] = 'string';
        }
        
        $this->excelWriter->writeSheetRow($sheetName, $headers);
        foreach ($rows as $row) {
            $this->excelWriter->writeSheetRow($sheetName, $row);
        }
        
        return true;
    }
    
    /**
     * Returns the full document.
     */
    public function getDocument()
    {
        return $this->excelWriter->writeToString();
    }
    
    /**
     * Initializes a new document.
     */
    public function newDocument(string $title, int $formatId, string $languageCode)
    {
        $this->setOutputFileName($title);
        
        $this->excelWriter = new XLSXWriter();
        $this->excelWriter->setAuthor('ERPIA');
        $this->excelWriter->setTitle($title);
    }
    
    /**
     * Sets the document orientation (not applicable for Excel).
     */
    public function setOrientation(string $orientation)
    {
        // Not applicable for Excel
    }
    
    /**
     * Sets headers and outputs document content to response.
     */
    public function show(Response &$response)
    {
        $response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->setHeader('Content-Disposition', 'attachment; filename=' . $this->getOutputFileName() . '.xlsx');
        $response->setContent($this->getDocument());
    }
    
    /**
     * Gets column type mapping for Excel.
     */
    protected function getColumnTypeMap(array $columns): array
    {
        $types = [];
        $columnTitles = $this->getColumnTitleMap($columns);
        
        foreach ($columnTitles as $col) {
            $types[$col] = 'string';
        }
        
        return $types;
    }
    
    /**
     * Gets model column types for Excel.
     */
    protected function getModelColumnTypes($model): array
    {
        $types = [];
        $modelFields = $model->getModelFields();
        
        foreach ($this->getModelFieldList($model) as $key) {
            $fieldType = $modelFields[$key]['type'] ?? 'string';
            
            switch ($fieldType) {
                case 'int':
                case 'integer':
                    $types[$key] = 'integer';
                    break;
                    
                case 'double':
                case 'float':
                case 'decimal':
                    $types[$key] = 'price';
                    break;
                    
                default:
                    $types[$key] = 'string';
            }
        }
        
        return $types;
    }
    
    /**
     * Gets sanitized cursor data with HTML cleanup.
     */
    protected function getSanitizedCursorData(array $cursor, array $fields = []): array
    {
        $data = parent::getRawCursorData($cursor, $fields);
        
        foreach ($data as $index => $row) {
            foreach ($row as $key => $value) {
                $data[$index][$key] = $this->sanitizeCellValue($value);
            }
        }
        
        return $data;
    }
    
    /**
     * Generates a safe sheet name from title.
     */
    private function generateSheetName(string $title): string
    {
        if (empty($title)) {
            $this->sheetCounter++;
            return 'sheet' . $this->sheetCounter;
        }
        
        return $this->createSlug($title);
    }
    
    /**
     * Creates a URL-safe slug from a string.
     */
    private function createSlug(string $text): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/\s+/', '_', $slug);
        
        // Limit length for Excel sheet name (31 characters max)
        if (strlen($slug) > 31) {
            $slug = substr($slug, 0, 28) . '...';
        }
        
        return $slug;
    }
    
    /**
     * Sanitizes a cell value for Excel.
     */
    private function sanitizeCellValue($value): string
    {
        if (is_null($value)) {
            return '';
        }
        
        $stringValue = (string)$value;
        return Html::escape($stringValue);
    }
}