<?php

namespace ERPIA\Lib\ExtendedController;

use ERPIA\Core\DatabaseWhere;
use ERPIA\Lib\Widget\VisualItem;
use ERPIA\Models\Base\ModelClass;
use ERPIA\Core\Logger;
use ERPIA\Core\Translation;
use ERPIA\Core\Config;
use ERPIA\Lib\Widget\ColumnItem;
use ERPIA\Lib\Widget\GroupItem;
use ERPIA\Lib\Widget\VisualItemLoadEngine;
use ERPIA\Models\PageOption;
use ERPIA\Models\User;

/**
 * Base view for extended controllers
 */
abstract class BaseView
{
    const DEFAULT_TEMPLATE = 'Templates/Master/BaseView.html.twig';
    const DEFAULT_ITEM_LIMIT = 50;
    
    /** @var GroupItem[] */
    protected $viewColumns = [];
    
    /** @var int */
    public $recordCount = 0;
    
    /** @var array */
    public $dataCursor = [];
    
    /** @var string */
    public $icon;
    
    /** @var GroupItem[] */
    protected $viewModals = [];
    
    /** @var ModelClass */
    public $model;
    
    /** @var string */
    private $viewName;
    
    /** @var string */
    public $newRecordCode;
    
    /** @var int */
    public $offsetPosition = 0;
    
    /** @var array */
    public $sortOrder = [];
    
    /** @var PageOption */
    protected $pageConfiguration;
    
    /** @var array */
    protected $viewRows = [];
    
    /** @var array */
    public $viewSettings;
    
    /** @var string */
    public $template;
    
    /** @var string */
    public $title;
    
    /** @var DatabaseWhere[] */
    public $filterConditions = [];
    
    /**
     * Export view data
     */
    abstract public function export($exportManager, $codes): bool;
    
    /**
     * Load view data
     */
    abstract public function loadData($code = '', $conditions = [], $order = [], $offset = 0, $limit = self::DEFAULT_ITEM_LIMIT);
    
    /**
     * Process form data
     */
    abstract public function processFormData($request, $case);
    
    /**
     * Initialize the view
     */
    public function __construct(string $name, string $title, string $modelClass, string $icon)
    {
        if (class_exists($modelClass)) {
            $this->model = new $modelClass();
        } else {
            Logger::critical('Model class not found', ['model' => $modelClass]);
        }
        
        $this->icon = $icon;
        $this->viewName = $name;
        $this->pageConfiguration = new PageOption();
        
        $this->viewSettings = [
            'active' => true,
            'deleteButton' => true,
            'newButton' => true,
            'printButton' => false,
            'saveButton' => true,
            'undoButton' => true,
            'optionsButton' => true,
            'card' => true,
            'checkboxes' => true,
            'clickable' => true,
            'customized' => false,
            'itemLimit' => Config::get('default.item_limit', self::DEFAULT_ITEM_LIMIT),
            'megasearch' => false,
            'saveFilters' => false,
        ];
        
        $this->template = static::DEFAULT_TEMPLATE;
        $this->title = Translation::translate($title);
        $this->loadAssets();
    }
    
    /**
     * Get modal column by name
     */
    public function getModalColumn(string $columnName): ?ColumnItem
    {
        return $this->findColumnInSource($columnName, $this->viewModals);
    }
    
    /**
     * Get column by name
     */
    public function getColumnByName(string $columnName): ?ColumnItem
    {
        return $this->findColumnInSource($columnName, $this->viewColumns);
    }
    
    /**
     * Get column by field name
     */
    public function getColumnForField(string $fieldName): ?ColumnItem
    {
        foreach ($this->viewColumns as $group) {
            foreach ($group->columns as $column) {
                if ($column->widget->fieldName === $fieldName) {
                    return $column;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Enable or disable a column
     */
    public function setColumnState(string $columnName, bool $disabled = true, string $readOnly = ''): self
    {
        $column = $this->getColumnByName($columnName);
        
        if ($column !== null) {
            $column->display = $disabled ? 'hidden' : 'visible';
            $column->widget->readOnly = empty($readOnly) ? $column->widget->readOnly : $readOnly;
        }
        
        return $this;
    }
    
    /**
     * Get all columns
     */
    public function getColumns(): array
    {
        return $this->viewColumns;
    }
    
    /**
     * Get all modals
     */
    public function getModals(): array
    {
        return $this->viewModals;
    }
    
    /**
     * Calculate pagination
     */
    public function getPagination(): array
    {
        $pages = [];
        $currentPage = 0;
        $itemsPerPage = (int)$this->viewSettings['itemLimit'];
        
        // Calculate total pages
        $totalPages = ceil($this->recordCount / $itemsPerPage);
        
        for ($page = 0; $page < $totalPages; $page++) {
            $pageOffset = $page * $itemsPerPage;
            
            if ($pageOffset === $this->offsetPosition) {
                $currentPage = $page;
            }
            
            $pages[$page] = [
                'active' => ($pageOffset === $this->offsetPosition),
                'number' => $page + 1,
                'offset' => $pageOffset,
            ];
        }
        
        // Filter pages to show only relevant ones
        if ($totalPages > 10) {
            $filteredPages = [];
            
            foreach ($pages as $index => $page) {
                // Always show first and last pages
                if ($index === 0 || $index === $totalPages - 1) {
                    $filteredPages[$index] = $page;
                    continue;
                }
                
                // Show pages around current page
                if ($index >= $currentPage - 2 && $index <= $currentPage + 2) {
                    $filteredPages[$index] = $page;
                    continue;
                }
                
                // Show middle page if we have many pages
                if ($totalPages > 20 && $index === floor($totalPages / 2)) {
                    $filteredPages[$index] = $page;
                }
            }
            
            $pages = $filteredPages;
        }
        
        return count($pages) > 1 ? $pages : [];
    }
    
    /**
     * Get a specific row configuration
     */
    public function getRow(string $rowType)
    {
        return $this->viewRows[$rowType] ?? null;
    }
    
    /**
     * Get view name
     */
    public function getName(): string
    {
        return $this->viewName;
    }
    
    /**
     * Load model from data array
     */
    public function loadFromData(array $data): void
    {
        $primaryKey = $this->model->getPrimaryKey();
        $primaryValue = $data[$primaryKey] ?? null;
        
        if ($primaryValue !== null && $primaryValue !== $this->model->getPrimaryKeyValue() && $primaryValue !== '') {
            $this->model->loadById($primaryValue);
        }
        
        $this->model->loadFromArray($data, ['action', 'activetab']);
    }
    
    /**
     * Load page configuration for user
     */
    public function loadPageConfiguration($user = false): void
    {
        if ($user !== false) {
            VisualItem::setSecurityLevel($user->securityLevel);
        }
        
        $order = ['username' => 'ASC'];
        $conditions = $this->getPageConfigurationConditions($user);
        
        if ($this->pageConfiguration->loadWhere($conditions, $order)) {
            $this->viewSettings['customized'] = true;
        } else {
            $baseViewName = explode('-', $this->viewName)[0];
            VisualItemLoadEngine::loadXmlConfiguration($baseViewName, $this->pageConfiguration);
        }
        
        VisualItemLoadEngine::loadConfigurationArray(
            $this->viewColumns, 
            $this->viewModals, 
            $this->viewRows, 
            $this->pageConfiguration
        );
    }
    
    /**
     * Update view setting
     */
    public function setViewSetting(string $key, $value): self
    {
        $this->viewSettings[$key] = $value;
        return $this;
    }
    
    /**
     * Load view assets
     */
    protected function loadAssets(): void
    {
        // To be overridden by child classes
    }
    
    /**
     * Find column in source array
     */
    protected function findColumnInSource(string $columnName, array &$source): ?ColumnItem
    {
        foreach ($source as $group) {
            foreach ($group->columns as $key => $column) {
                if ($key === $columnName) {
                    return $column;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get conditions for loading page configuration
     */
    protected function getPageConfigurationConditions($user = false): array
    {
        $baseViewName = explode('-', $this->viewName)[0];
        
        if ($user === false) {
            return [new DatabaseWhere('name', $baseViewName)];
        }
        
        return [
            new DatabaseWhere('name', $baseViewName),
            new DatabaseWhere('username', $user->username),
            new DatabaseWhere('username', null, 'IS NULL', 'OR')
        ];
    }
}