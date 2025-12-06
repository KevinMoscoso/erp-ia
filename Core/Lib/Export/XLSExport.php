<?php

namespace ERPIA\Lib\Export;

use ERPIA\Core\DatabaseWhere;
use ERPIA\Models\Base\BusinessDocument;
use ERPIA\Models\Base\ModelClass;
use ERPIA\Core\HttpResponse;
use ERPIA\Core\StringHelper;
use ERPIA\Core\Translation;

/**
 * Excel export implementation using XLSXWriter
 */
class XLSExport extends ExportBase
{
    const BATCH_LIMIT = 5000;
    
    /** @var int */
    protected $sheetCounter = 0;
    
    /** @var XLSXWriter */
    protected $xlsxWriter;
    
    /**
     * Export a business document to Excel with separate sheets for lines and header
     */
    public function exportBusinessDocument(BusinessDocument $model): bool
    {
        $lineRecords = [];
        $lineColumnTypes = [];
        
        foreach ($model->getLines() as $line) {
            if (empty($lineColumnTypes)) {
                $lineColumnTypes = $this->getModelColumnTypes($line);
            }
            
            $lineRecords[] = $line;
        }
        
        $lineData = $this->prepareExcelData($lineRecords);
        $this->xlsxWriter->writeSheet($lineData, Translation::translate('lines'), $lineColumnTypes);
        
        $headerColumnTypes = $this->getModelColumnTypes($model);
        $headerData = $this->prepareExcelData([$model]);
        $this->xlsxWriter->writeSheet($headerData, $model->getPrimaryDescription(), $headerColumnTypes);
        
        return false;
    }
    
    /**
     * Export a list of models to Excel with pagination
     */
    public function exportModelList(ModelClass $model, array $conditions, array $orderBy, 
                                   int $startOffset, array $columns, string $title = ''): bool
    {
        $this->setOutputFilename($title);
        $sheetName = empty($title) ? 'sheet' . $this->sheetCounter : StringHelper::createSlug($title);
        
        $columnTypes = $this->getModelColumnTypes($model);
        $records = $model->getAll($conditions, $orderBy, $startOffset, self::BATCH_LIMIT);
        
        if (empty($records)) {
            $this->xlsxWriter->writeSheet([], $sheetName, $columnTypes);
            return true;
        }
        
        $this->xlsxWriter->writeSheetHeader($sheetName, $columnTypes);
        
        while (!empty($records)) {
            $dataRows = $this->prepareExcelData($records);
            foreach ($dataRows as $row) {
                $this->xlsxWriter->writeSheetRow($sheetName, $row);
            }
            
            $startOffset += self::BATCH_LIMIT;
            $records = $model->getAll($conditions, $orderBy, $startOffset, self::BATCH_LIMIT);
        }
        
        return true;
    }
    
    /**
     * Export a single model to Excel
     */
    public function exportSingleModel(ModelClass $model, array $columns, string $title = ''): bool
    {
        $columnTypes = $this->getModelColumnTypes($model);
        $dataRows = $this->prepareExcelData([$model]);
        $this->xlsxWriter->writeSheet($dataRows, $title, $columnTypes);
        return true;
    }
    
    /**
     * Export a generic table to Excel
     */
    public function exportTable(array $headers, array $rows, array $options = [], string $title = ''): bool
    {
        $this->sheetCounter++;
        $sheetName = 'sheet' . $this->sheetCounter;
        
        $this->xlsxWriter->writeSheetRow($sheetName, $headers);
        foreach ($rows as $row) {
            $this->xlsxWriter->writeSheetRow($sheetName, $row);
        }
        
        return true;
    }
    
    /**
     * Get the complete Excel document content
     */
    public function getDocumentContent(): string
    {
        return (string)$this->xlsxWriter->writeToString();
    }
    
    /**
     * Initialize a new Excel document
     */
    public function initializeDocument(string $title, int $formatId, string $languageCode): void
    {
        $this->setOutputFilename($title);
        
        $this->xlsxWriter = new XLSXWriter();
        $this->xlsxWriter->setAuthor('ERPIA System');
        $this->xlsxWriter->setTitle($title);
    }
    
    /**
     * Set page orientation (not applicable for Excel)
     */
    public function setPageOrientation(string $orientation): void
    {
        // Not implemented for Excel export
    }
    
    /**
     * Send Excel document to HTTP response
     */
    public function sendToResponse(HttpResponse &$response): void
    {
        $response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->setHeader('Content-Disposition', 'attachment; filename=' . $this->getOutputFilename() . '.xlsx');
        $response->setContent($this->getDocumentContent());
    }
    
    /**
     * Get Excel column type definitions for a model
     */
    protected function getModelColumnTypes(ModelClass $model): array
    {
        $columnTypes = [];
        $modelProperties = $model->getModelProperties();
        
        foreach ($this->getModelPropertyNames($model) as $key) {
            if (!isset($modelProperties[$key])) {
                $columnTypes[$key] = 'string';
                continue;
            }
            
            $fieldType = $modelProperties[$key]['type'] ?? 'string';
            
            switch ($fieldType) {
                case 'int':
                case 'integer':
                    $columnTypes[$key] = 'integer';
                    break;
                    
                case 'double':
                case 'float':
                case 'decimal':
                    $columnTypes[$key] = 'price';
                    break;
                    
                default:
                    $columnTypes[$key] = 'string';
            }
        }
        
        return $columnTypes;
    }
    
    /**
     * Prepare data for Excel export with sanitization
     */
    protected function prepareExcelData(array $cursor, array $fields = []): array
    {
        $data = $this->getRawCursorData($cursor, $fields);
        
        foreach ($data as $index => $row) {
            foreach ($row as $key => $value) {
                $data[$index][$key] = StringHelper::sanitizeForExcel($value);
            }
        }
        
        return $data;
    }
}