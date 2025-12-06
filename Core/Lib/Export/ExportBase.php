<?php

namespace ERPIA\Lib\Export;

use ERPIA\Core\DatabaseWhere;
use ERPIA\Models\Base\BusinessDocument;
use ERPIA\Models\Base\ModelClass;
use ERPIA\Core\HttpResponse;
use ERPIA\Core\Translation;
use ERPIA\Core\StringHelper;

/**
 * Base abstract class for export formats
 */
abstract class ExportBase
{
    /** @var string */
    private $outputFilename;

    /**
     * Export a business document with header and lines combined
     */
    abstract public function exportBusinessDocument(BusinessDocument $model): bool;

    /**
     * Export a list of models with pagination
     */
    abstract public function exportModelList(ModelClass $model, array $where, array $order, int $offset, array $columns, string $title = ''): bool;

    /**
     * Export a single model instance
     */
    abstract public function exportSingleModel(ModelClass $model, array $columns, string $title = ''): bool;

    /**
     * Export a generic table
     */
    abstract public function exportTable(array $headers, array $rows, array $options = [], string $title = ''): bool;

    /**
     * Get the complete document content
     */
    abstract public function getDocumentContent();

    /**
     * Initialize a new document
     */
    abstract public function initializeDocument(string $title, int $formatId, string $languageCode): void;

    /**
     * Set page orientation (if applicable)
     */
    abstract public function setPageOrientation(string $orientation): void;

    /**
     * Send document to HTTP response
     */
    abstract public function sendToResponse(HttpResponse &$response): void;

    /**
     * Get column alignment mappings from column definitions
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

            if (!$column->isHidden()) {
                $alignments[$column->widget->fieldName] = $column->display;
            }
        }

        return $alignments;
    }

    /**
     * Get column title mappings from column definitions
     */
    protected function getColumnTitleMap(array $columns): array
    {
        $titles = [];
        $translator = Translation::getInstance();
        
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

            if (!$column->isHidden()) {
                $titles[$column->widget->fieldName] = $translator->translate($column->title);
            }
        }

        return $titles;
    }

    /**
     * Get column widget mappings from column definitions
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

            if (!$column->isHidden()) {
                $widgets[$column->widget->fieldName] = $column->widget;
            }
        }

        return $widgets;
    }

    /**
     * Get data from model cursor using column widgets
     */
    protected function getModelCursorData(array $cursor, array $columns): array
    {
        $data = [];
        $widgets = $this->getColumnWidgetMap($columns);
        
        foreach ($cursor as $index => $row) {
            foreach ($widgets as $key => $widget) {
                $data[$index][$key] = $widget->getPlainText($row);
            }
        }

        return $data;
    }

    /**
     * Get raw data from model cursor
     */
    protected function getRawCursorData(array $cursor, array $fields = []): array
    {
        $data = [];
        
        foreach ($cursor as $index => $row) {
            if (empty($fields)) {
                $fields = array_keys($row->getModelProperties());
            }

            foreach ($fields as $key) {
                $value = isset($row->{$key}) && $row->{$key} !== null ? $row->{$key} : '';
                $data[$index][$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Get document formatting for a business document
     */
    protected function getDocumentFormatting(BusinessDocument $model)
    {
        $documentFormat = new \ERPIA\Models\FormatoDocumento();
        $conditions = [
            new DatabaseWhere('autoapply', true),
            new DatabaseWhere('company_id', $model->companyId)
        ];
        
        $formats = $documentFormat->getAll($conditions, ['doc_type' => 'DESC', 'series_code' => 'DESC']);
        
        foreach ($formats as $format) {
            if ($format->doc_type === $model->getModelClassName() && $format->series_code === $model->seriesCode) {
                return $format;
            } elseif ($format->doc_type === $model->getModelClassName() && $format->series_code === null) {
                return $format;
            } elseif ($format->doc_type === null && $format->series_code === $model->seriesCode) {
                return $format;
            } elseif ($format->doc_type === null && $format->series_code === null) {
                return $format;
            }
        }

        return $documentFormat;
    }

    /**
     * Get model column data for display
     */
    protected function getModelColumnData($model, array $columns): array
    {
        $data = [];
        $translator = Translation::getInstance();
        
        foreach ($columns as $column) {
            if (is_string($column)) {
                continue;
            }

            if (isset($column->columns)) {
                $nestedData = $this->getModelColumnData($model, $column->columns);
                foreach ($nestedData as $key => $value) {
                    $data[$key] = $value;
                }
                continue;
            }

            if (!$column->isHidden()) {
                $data[$column->widget->fieldName] = [
                    'title' => $translator->translate($column->title),
                    'value' => $column->widget->getPlainText($model)
                ];
            }
        }

        return $data;
    }

    /**
     * Get model property names
     */
    protected function getModelPropertyNames(ModelClass $model): array
    {
        $properties = [];
        foreach (array_keys($model->getModelProperties()) as $key) {
            $properties[$key] = $key;
        }

        return $properties;
    }

    /**
     * Get the output filename
     */
    protected function getOutputFilename(): string
    {
        return empty($this->outputFilename) ? 'export_' . mt_rand(1000, 9999) : $this->outputFilename;
    }

    /**
     * Set the output filename with sanitization
     */
    protected function setOutputFilename(string $name): void
    {
        if (empty($this->outputFilename)) {
            $sanitizedName = StringHelper::sanitizeFilename($name);
            $this->outputFilename = $sanitizedName;
        }
    }
}