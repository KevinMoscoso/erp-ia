<?php

namespace ERPIA\Lib\Export;

use ERPIA\Core\DatabaseWhere;
use ERPIA\Models\Base\BusinessDocument;
use ERPIA\Models\Base\ModelClass;
use ERPIA\Core\HttpResponse;

/**
 * CSV exporter for ERPIA system
 */
class CSVExport extends ExportBase
{
    const BATCH_SIZE = 1000;
    
    /** @var array */
    private $csvData = [];
    
    /** @var string */
    private $textDelimiter = '"';
    
    /** @var string */
    private $fieldSeparator = ';';
    
    /**
     * Export a business document with combined header and lines
     */
    public function exportBusinessDocument(BusinessDocument $document): bool
    {
        $documentData = [];
        $properties = [];
        
        $headerData = $this->extractModelData([$document]);
        foreach ($document->getLines() as $line) {
            if (empty($properties)) {
                $headerProps = $this->getModelProperties($document);
                $lineProps = $this->getModelProperties($line);
                $properties = array_merge($lineProps, $headerProps);
            }
            
            $lineData = $this->extractModelData([$line]);
            $documentData[] = array_merge($lineData[0], $headerData[0]);
        }
        
        $this->writeCSVData($documentData, $properties);
        
        return false;
    }
    
    /**
     * Export a list of models with pagination
     */
    public function exportModelList(ModelClass $model, array $conditions, array $orderBy, int $startOffset, array $columns, string $title = ''): bool
    {
        $this->setOutputFilename($title);
        
        $properties = $this->getModelProperties($model);
        $records = $model->getAll($conditions, $orderBy, $startOffset, self::BATCH_SIZE);
        
        if (empty($records)) {
            $this->writeCSVData([], $properties);
        }
        
        while (!empty($records)) {
            $data = $this->extractModelData($records);
            $this->writeCSVData($data, $properties);
            $properties = [];
            
            $startOffset += self::BATCH_SIZE;
            $records = $model->getAll($conditions, $orderBy, $startOffset, self::BATCH_SIZE);
        }
        
        return false;
    }
    
    /**
     * Export a single model instance
     */
    public function exportSingleModel(ModelClass $model, array $columns, string $title = ''): bool
    {
        $properties = $this->getModelProperties($model);
        $data = $this->extractModelData([$model]);
        $this->writeCSVData($data, $properties);
        
        return false;
    }
    
    /**
     * Export a generic table
     */
    public function exportTable(array $headers, array $rows, array $options = [], string $title = ''): bool
    {
        $this->writeCSVData($rows, $headers);
        return false;
    }
    
    /**
     * Get the current text delimiter
     */
    public function getTextDelimiter(): string
    {
        return $this->textDelimiter;
    }
    
    /**
     * Get the complete CSV document content
     */
    public function getDocumentContent(): string
    {
        return implode(PHP_EOL, $this->csvData);
    }
    
    /**
     * Get the current field separator
     */
    public function getFieldSeparator(): string
    {
        return $this->fieldSeparator;
    }
    
    /**
     * Initialize a new CSV document
     */
    public function initializeDocument(string $title, int $formatId, string $languageCode): void
    {
        $this->csvData = [];
        $this->setOutputFilename($title);
    }
    
    /**
     * Set the text delimiter character
     */
    public function setTextDelimiter(string $delimiter): void
    {
        $this->textDelimiter = $delimiter;
    }
    
    /**
     * Set page orientation (not applicable for CSV)
     */
    public function setPageOrientation(string $orientation): void
    {
        // Not implemented for CSV export
    }
    
    /**
     * Set the field separator character
     */
    public function setFieldSeparator(string $separator): void
    {
        $this->fieldSeparator = $separator;
    }
    
    /**
     * Send the CSV document to HTTP response
     */
    public function sendToResponse(HttpResponse &$response): void
    {
        $response->setHeader('Content-Type', 'text/csv; charset=utf-8');
        $response->setHeader('Content-Disposition', 'attachment; filename=' . $this->getFilename() . '.csv');
        $response->setContent($this->getDocumentContent());
    }
    
    /**
     * Write data rows to CSV format
     */
    public function writeCSVData(array $data, array $properties = []): void
    {
        if (!empty($properties)) {
            $this->writeCSVHeaders($properties);
        }
        
        foreach ($data as $row) {
            $csvRow = [];
            foreach ($row as $cell) {
                $csvRow[] = $this->formatCSVCell($cell);
            }
            
            $this->csvData[] = implode($this->fieldSeparator, $csvRow);
        }
    }
    
    /**
     * Format a single cell for CSV output
     */
    private function formatCSVCell($value): string
    {
        if (is_string($value)) {
            $delimiter = $this->textDelimiter;
            $escapedValue = str_replace($delimiter, $delimiter . $delimiter, $value);
            return $delimiter . $escapedValue . $delimiter;
        }
        
        return (string)$value;
    }
    
    /**
     * Write CSV headers from property names
     */
    private function writeCSVHeaders(array $properties): void
    {
        $headers = [];
        foreach ($properties as $property) {
            $headers[] = $this->formatCSVCell($property);
        }
        
        $this->csvData[] = implode($this->fieldSeparator, $headers);
    }
}