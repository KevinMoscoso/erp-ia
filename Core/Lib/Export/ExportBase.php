<?php

namespace ERPIA\Lib\Export;

use ERPIA\Core\DataBase\DataBaseWhere;
use ERPIA\Models\Base\BusinessDocument;
use ERPIA\Models\Base\ModelClass;
use ERPIA\Core\I18n;
use ERPIA\Core\Response;

/**
 * Abstract base class for exporters
 */
abstract class ExportBase
{
    /** @var string */
    private $outputFileName;
    
    /**
     * Adds a page with business document data
     */
    abstract public function addBusinessDocumentPage($model): bool;
    
    /**
     * Adds a page with a list of models
     */
    abstract public function addModelListPage($model, $where, $order, $offset, $columns, $title = ''): bool;
    
    /**
     * Adds a page with a single model
     */
    abstract public function addModelPage($model, $columns, $title = ''): bool;
    
    /**
     * Adds a page with a table
     */
    abstract public function addTablePage($headers, $rows, $options = [], $title = ''): bool;
    
    /**
     * Gets the complete document
     */
    abstract public function getDocument();
    
    /**
     * Initializes a new document
     */
    abstract public function newDocument(string $title, int $formatId, string $languageCode);
    
    /**
     * Sets the document orientation
     */
    abstract public function setOrientation(string $orientation);
    
    /**
     * Outputs the document to response
     */
    abstract public function show(Response &$response);
    
    /**
     * Gets alignment settings for columns
     */
    protected function getColumnAlignmentMap(array $columns): array
    {
        $alignments = [];
        
        foreach ($columns as $column) {
            if (is_string($column)) {
                $alignments[$column] = 'left';
                continue;
            }
            
            if (isset($column->columns)) {
                $nestedAlignments = $this->getColumnAlignmentMap($column->columns);
                foreach ($nestedAlignments as $key => $alignment) {
                    $alignments[$key] = $alignment;
                }
                continue;
            }
            
            if (!$this->isColumnHidden($column)) {
                $alignments[$column->widget->fieldname] = $column->display;
            }
        }
        
        return $alignments;
    }
    
    /**
     * Gets title map for columns
     */
    protected function getColumnTitleMap(array $columns): array
    {
        $titles = [];
        $translator = I18n::getInstance();
        
        foreach ($columns as $column) {
            if (is_string($column)) {
                $titles[$column] = $column;
                continue;
            }
            
            if (isset($column->columns)) {
                $nestedTitles = $this->getColumnTitleMap($column->columns);
                foreach ($nestedTitles as $key => $title) {
                    $titles[$key] = $title;
                }
                continue;
            }
            
            if (!$this->isColumnHidden($column)) {
                $titles[$column->widget->fieldname] = $translator->trans($column->title);
            }
        }
        
        return $titles;
    }
    
    /**
     * Gets widget map for columns
     */
    protected function getColumnWidgetMap(array $columns): array
    {
        $widgets = [];
        
        foreach ($columns as $column) {
            if (is_string($column)) {
                continue;
            }
            
            if (isset($column->columns)) {
                $nestedWidgets = $this->getColumnWidgetMap($column->columns);
                foreach ($nestedWidgets as $key => $widget) {
                    $widgets[$key] = $widget;
                }
                continue;
            }
            
            if (!$this->isColumnHidden($column)) {
                $widgets[$column->widget->fieldname] = $column->widget;
            }
        }
        
        return $widgets;
    }
    
    /**
     * Gets formatted cursor data
     */
    protected function getFormattedCursorData(array $cursor, array $columns): array
    {
        $data = [];
        $widgets = $this->getColumnWidgetMap($columns);
        
        foreach ($cursor as $index => $row) {
            foreach ($widgets as $key => $widget) {
                $data[$index][$key] = $this->getWidgetPlainText($widget, $row);
            }
        }
        
        return $data;
    }
    
    /**
     * Gets raw cursor data
     */
    protected function getRawCursorData(array $cursor, array $fields = []): array
    {
        $data = [];
        
        foreach ($cursor as $index => $row) {
            if (empty($fields)) {
                $fields = array_keys($row->getModelFields());
            }
            
            foreach ($fields as $field) {
                $value = isset($row->{$field}) && $row->{$field} !== null ? $row->{$field} : '';
                $data[$index][$field] = $value;
            }
        }
        
        return $data;
    }
    
    /**
     * Gets document format settings
     */
    protected function getDocumentFormatSettings($model)
    {
        $documentFormat = new DocumentFormat();
        $where = [
            new DataBaseWhere('autoapply', true),
            new DataBaseWhere('company_id', $model->company_id)
        ];
        
        $allFormats = $documentFormat->getAll($where, ['document_type' => 'DESC', 'series_code' => 'DESC']);
        
        foreach ($allFormats as $format) {
            if ($format->document_type === $model->modelClassName() && $format->series_code === $model->series_code) {
                return $format;
            } elseif ($format->document_type === $model->modelClassName() && $format->series_code === null) {
                return $format;
            } elseif ($format->document_type === null && $format->series_code === $model->series_code) {
                return $format;
            } elseif ($format->document_type === null && $format->series_code === null) {
                return $format;
            }
        }
        
        return $documentFormat;
    }
    
    /**
     * Gets model column data map
     */
    protected function getModelColumnDataMap($model, array $columns): array
    {
        $data = [];
        $translator = I18n::getInstance();
        
        foreach ($columns as $column) {
            if (is_string($column)) {
                continue;
            }
            
            if (isset($column->columns)) {
                $nestedData = $this->getModelColumnDataMap($model, $column->columns);
                foreach ($nestedData as $key => $value) {
                    $data[$key] = $value;
                }
                continue;
            }
            
            if (!$this->isColumnHidden($column)) {
                $data[$column->widget->fieldname] = [
                    'title' => $translator->trans($column->title),
                    'value' => $this->getWidgetPlainText($column->widget, $model)
                ];
            }
        }
        
        return $data;
    }
    
    /**
     * Gets model field list
     */
    protected function getModelFieldList($model): array
    {
        $fields = [];
        
        foreach (array_keys($model->getModelFields()) as $field) {
            $fields[$field] = $field;
        }
        
        return $fields;
    }
    
    /**
     * Gets the output file name
     */
    protected function getOutputFileName(): string
    {
        if (empty($this->outputFileName)) {
            return 'export_' . mt_rand(1000, 9999);
        }
        
        return $this->outputFileName;
    }
    
    /**
     * Sets the output file name
     */
    protected function setOutputFileName(string $name)
    {
        if (empty($this->outputFileName)) {
            $this->outputFileName = $this->sanitizeFileName($name);
        }
    }
    
    /**
     * Checks if a column is hidden
     */
    private function isColumnHidden($column): bool
    {
        return isset($column->hidden) && $column->hidden();
    }
    
    /**
     * Gets plain text from a widget
     */
    private function getWidgetPlainText($widget, $model): string
    {
        if (method_exists($widget, 'plainText')) {
            return $widget->plainText($model);
        }
        
        return '';
    }
    
    /**
     * Sanitizes a file name
     */
    private function sanitizeFileName(string $name): string
    {
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        return str_replace([' ', '"', "'", '/', '\\', ','], '_', $name);
    }
}