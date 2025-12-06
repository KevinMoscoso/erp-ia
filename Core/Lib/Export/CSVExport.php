<?php

namespace ERPIA\Lib\Export;

use ERPIA\Core\DataBase\DataBaseWhere;
use ERPIA\Models\Base\BusinessDocument;
use ERPIA\Models\Base\ModelClass;
use ERPIA\Core\Response;

/**
 * Exports data to CSV format
 */
class CSVExport extends ExportBase
{
    const BATCH_SIZE = 1000;
    
    /** @var array */
    protected $csvData = [];
    
    /** @var string */
    protected $textDelimiter = '"';
    
    /** @var string */
    protected $fieldSeparator = ';';
    
    /**
     * Adds business document data, merging header and line data
     */
    public function addBusinessDocumentPage($model): bool
    {
        $headerData = $this->extractModelData([$model]);
        $documentLines = $model->getLines();
        
        $fields = [];
        $rows = [];
        
        foreach ($documentLines as $line) {
            if (empty($fields)) {
                $modelFields = $this->getModelFields($model);
                $lineFields = $this->getModelFields($line);
                $fields = array_merge($lineFields, $modelFields);
            }
            
            $lineData = $this->extractModelData([$line]);
            $rows[] = array_merge($lineData[0], $headerData[0]);
        }
        
        $this->writeData($rows, $fields);
        
        return false;
    }
    
    /**
     * Adds a page with a list of models
     */
    public function addModelListPage($model, $where, $order, $offset, $columns, $title = ''): bool
    {
        $this->setFileName($title);
        
        $fields = $this->getModelFields($model);
        $records = $model->getAll($where, $order, $offset, self::BATCH_SIZE);
        
        if (empty($records)) {
            $this->writeData([], $fields);
            return false;
        }
        
        while (!empty($records)) {
            $data = $this->extractModelData($records);
            $this->writeData($data, $fields);
            $fields = [];
            
            $offset += self::BATCH_SIZE;
            $records = $model->getAll($where, $order, $offset, self::BATCH_SIZE);
        }
        
        return false;
    }
    
    /**
     * Adds a page with a single model
     */
    public function addModelPage($model, $columns, $title = ''): bool
    {
        $fields = $this->getModelFields($model);
        $data = $this->extractModelData([$model]);
        $this->writeData($data, $fields);
        
        return false;
    }
    
    /**
     * Adds a page with a table
     */
    public function addTablePage($headers, $rows, $options = [], $title = ''): bool
    {
        $this->writeData($rows, $headers);
        return false;
    }
    
    /**
     * Gets the current text delimiter
     */
    public function getTextDelimiter(): string
    {
        return $this->textDelimiter;
    }
    
    /**
     * Gets the complete CSV document
     */
    public function getDocument(): string
    {
        return implode(PHP_EOL, $this->csvData);
    }
    
    /**
     * Gets the current field separator
     */
    public function getFieldSeparator(): string
    {
        return $this->fieldSeparator;
    }
    
    /**
     * Initializes a new CSV document
     */
    public function newDocument(string $title, int $formatId, string $languageCode)
    {
        $this->csvData = [];
        $this->setFileName($title);
    }
    
    /**
     * Sets the text delimiter
     */
    public function setTextDelimiter(string $delimiter)
    {
        $this->textDelimiter = $delimiter;
    }
    
    /**
     * Sets the orientation (not applicable for CSV, kept for compatibility)
     */
    public function setOrientation(string $orientation)
    {
        // Not applicable for CSV
    }
    
    /**
     * Sets the field separator
     */
    public function setFieldSeparator(string $separator)
    {
        $this->fieldSeparator = $separator;
    }
    
    /**
     * Sends the CSV document to the response
     */
    public function show(Response &$response)
    {
        $response->setHeader('Content-Type', 'text/csv; charset=utf-8');
        $response->setHeader('Content-Disposition', 'attachment; filename=' . $this->getFileName() . '.csv');
        $response->setContent($this->getDocument());
    }
    
    /**
     * Writes data to the CSV
     */
    public function writeData(array $data, array $fields = [])
    {
        if (!empty($fields)) {
            $this->writeHeader($fields);
        }
        
        foreach ($data as $row) {
            $csvRow = [];
            foreach ($row as $cell) {
                $csvRow[] = $this->formatCell($cell);
            }
            $this->csvData[] = implode($this->fieldSeparator, $csvRow);
        }
    }
    
    /**
     * Writes the header row
     */
    private function writeHeader(array $fields)
    {
        $headerRow = [];
        foreach ($fields as $field) {
            $headerRow[] = $this->textDelimiter . $field . $this->textDelimiter;
        }
        $this->csvData[] = implode($this->fieldSeparator, $headerRow);
    }
    
    /**
     * Formats a cell for CSV output
     */
    private function formatCell($cell): string
    {
        if (!is_string($cell)) {
            return (string)$cell;
        }
        
        // Escape the text delimiter if it appears in the string
        $cell = str_replace($this->textDelimiter, $this->textDelimiter . $this->textDelimiter, $cell);
        
        // Enclose in delimiters if the cell contains separator, newline, or delimiter
        if (strpos($cell, $this->fieldSeparator) !== false || 
            strpos($cell, "\n") !== false || 
            strpos($cell, "\r") !== false || 
            strpos($cell, $this->textDelimiter) !== false) {
            return $this->textDelimiter . $cell . $this->textDelimiter;
        }
        
        return $cell;
    }
    
    /**
     * Extracts raw data from a cursor of models
     */
    private function extractModelData(array $models): array
    {
        $data = [];
        foreach ($models as $model) {
            $data[] = $this->modelToArray($model);
        }
        return $data;
    }
    
    /**
     * Converts a model to an array of values
     */
    private function modelToArray(ModelClass $model): array
    {
        $array = [];
        $fields = $this->getModelFields($model);
        
        foreach ($fields as $field) {
            $getter = 'get' . str_replace('_', '', ucwords($field, '_'));
            if (method_exists($model, $getter)) {
                $array[$field] = $model->$getter();
            } else {
                $array[$field] = $model->{$field} ?? '';
            }
        }
        
        return $array;
    }
}