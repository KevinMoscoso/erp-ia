<?php

namespace ERPIA\Lib\ExtendedController;

use Exception;
use ERPIA\Core\Controller;
use ERPIA\Core\ControllerPermissions;
use ERPIA\Core\DatabaseWhere;
use ERPIA\Lib\Widget\VisualItem;
use ERPIA\Models\Base\ModelClass;
use ERPIA\Core\HttpResponse;
use ERPIA\Core\Logger;
use ERPIA\Core\Translation;
use ERPIA\Core\StringHelper;
use ERPIA\Lib\Export\ExportManager;
use ERPIA\Models\CodeModel;
use ERPIA\Models\User;

/**
 * Base controller for extended views and tabs
 */
abstract class BaseController extends Controller
{
    const MODEL_NAMESPACE = '\\ERPIA\\Models\\';
    
    /** @var string */
    public $activeView;
    
    /** @var CodeModel */
    public $codeModel;
    
    /** @var string */
    private $currentView;
    
    /** @var ExportManager */
    public $exportManager;
    
    /** @var BaseView[] */
    public $views = [];
    
    /**
     * Create the views/tabs to display
     */
    abstract protected function createViews(): void;
    
    /**
     * Load data for a specific view
     */
    abstract protected function loadData(string $viewName, BaseView $view): void;
    
    /**
     * Initialize controller
     */
    public function __construct(string $className, string $uri = '')
    {
        parent::__construct($className, $uri);
        
        $activeTabFromQuery = $this->request->getQuery('activetab', '');
        $this->activeView = $this->request->getInput('activetab', $activeTabFromQuery);
        $this->codeModel = new CodeModel();
        $this->exportManager = new ExportManager();
    }
    
    /**
     * Add a button to a view's action or footer row
     */
    public function addButton(string $viewName, array $buttonConfig): BaseView
    {
        if (!isset($this->views[$viewName])) {
            throw new Exception('View not found: ' . $viewName);
        }
        
        $rowType = isset($buttonConfig['row']) ? 'footer' : 'actions';
        $row = $this->views[$viewName]->getRow($rowType);
        
        if ($row !== null) {
            $row->addButton($buttonConfig);
        }
        
        return $this->getView($viewName);
    }
    
    /**
     * Add a custom view to the controller
     */
    public function addCustomView(string $viewName, BaseView $view): BaseView
    {
        if ($viewName !== $view->getName()) {
            throw new Exception('View name mismatch: ' . $viewName);
        }
        
        $view->loadUserPreferences($this->user);
        
        $this->views[$viewName] = $view;
        if (empty($this->activeView)) {
            $this->activeView = $viewName;
        }
        
        return $view;
    }
    
    /**
     * Get the currently processing view
     */
    public function getCurrentView(): BaseView
    {
        return $this->getView($this->currentView);
    }
    
    /**
     * Get the main view name (first view in the array)
     */
    public function getMainViewName(): string
    {
        foreach (array_keys($this->views) as $key) {
            return $key;
        }
        
        return '';
    }
    
    /**
     * Get a setting value for a view
     */
    public function getViewSetting(string $viewName, string $property)
    {
        return $this->getView($viewName)->settings[$property] ?? null;
    }
    
    /**
     * Get a field value from the view's model
     */
    public function getViewFieldValue(string $viewName, string $fieldName)
    {
        return $this->getView($viewName)->model->{$fieldName} ?? null;
    }
    
    /**
     * Get a ListView instance
     */
    public function getListView(string $viewName): ListView
    {
        if (isset($this->views[$viewName]) && $this->views[$viewName] instanceof ListView) {
            return $this->views[$viewName];
        }
        
        throw new Exception('ListView not found: ' . $viewName);
    }
    
    /**
     * Main controller logic
     */
    public function privateCore(HttpResponse &$response, User $user, ControllerPermissions $permissions): void
    {
        parent::privateCore($response, $user, $permissions);
        
        VisualItem::setSecurityToken($this->multiRequestProtection->createToken());
        
        $this->createViews();
    }
    
    /**
     * Set the current processing view
     */
    public function setCurrentView(string $viewName): void
    {
        $this->currentView = $viewName;
    }
    
    /**
     * Set a setting value for a view
     */
    public function setViewSetting(string $viewName, string $property, $value): BaseView
    {
        return $this->getView($viewName)->setSetting($property, $value);
    }
    
    /**
     * Get a view by name
     */
    public function getView(string $viewName): BaseView
    {
        if (isset($this->views[$viewName])) {
            return $this->views[$viewName];
        }
        
        throw new Exception('View not found: ' . $viewName);
    }
    
    /**
     * Handle autocomplete action
     */
    protected function handleAutocomplete(): array
    {
        $data = $this->extractRequestData([
            'field', 'fieldcode', 'fieldfilter', 'fieldtitle', 
            'formname', 'source', 'strict', 'term'
        ]);
        
        if (empty($data['source'])) {
            return $this->getWidgetAutocompleteValues($data['formname'], $data['field']);
        }
        
        $conditions = [];
        $filterOperations = DatabaseWhere::parseFilterOperations($data['fieldfilter'] ?? '');
        
        foreach ($filterOperations as $field => $operation) {
            $fieldValue = $this->request->get($field);
            $conditions[] = new DatabaseWhere($field, $fieldValue, '=', $operation);
        }
        
        $results = [];
        $searchResults = $this->codeModel->search(
            $data['source'],
            $data['fieldcode'],
            $data['fieldtitle'],
            $data['term'],
            $conditions
        );
        
        foreach ($searchResults as $item) {
            $results[] = [
                'key' => StringHelper::sanitize($item->code),
                'value' => StringHelper::sanitize($item->description)
            ];
        }
        
        if (empty($results) && $data['strict'] == '0') {
            $results[] = [
                'key' => $data['term'],
                'value' => $data['term']
            ];
        } elseif (empty($results)) {
            $results[] = [
                'key' => null,
                'value' => Translation::translate('no-data')
            ];
        }
        
        return $results;
    }
    
    /**
     * Check if user has ownership permission for model data
     */
    protected function hasDataOwnershipPermission(ModelClass $model): bool
    {
        if (!$this->permissions->restrictToOwner || empty($model->getPrimaryKeyValue())) {
            return true;
        }
        
        // Check by username if model has nick property
        if (property_exists($model, 'username')) {
            if ($model->username === null || $model->username === $this->user->username) {
                return true;
            }
            
            // Check by agent code if user has agent assigned
            if (property_exists($model, 'agentCode') && $this->user->agentCode) {
                return $model->agentCode === $this->user->agentCode;
            }
            
            return false;
        }
        
        // Check by agent code if model has agent property
        if (property_exists($model, 'agentCode')) {
            return $model->agentCode === $this->user->agentCode;
        }
        
        // No ownership restrictions found, allow access
        return true;
    }
    
    /**
     * Handle datalist action
     */
    protected function handleDatalist(): array
    {
        $data = $this->extractRequestData([
            'field', 'fieldcode', 'fieldfilter', 'fieldtitle', 
            'formname', 'source', 'term'
        ]);
        
        $conditions = [];
        $filterOperations = DatabaseWhere::parseFilterOperations($data['fieldfilter'] ?? '');
        
        foreach ($filterOperations as $field => $operation) {
            $conditions[] = new DatabaseWhere($field, $data['term'], '=', $operation);
        }
        
        $results = [];
        $listResults = $this->codeModel->getAll(
            $data['source'],
            $data['fieldcode'],
            $data['fieldtitle'],
            false,
            $conditions
        );
        
        foreach ($listResults as $item) {
            $results[] = [
                'key' => $item->code,
                'value' => $item->description
            ];
        }
        
        return $results;
    }
    
    /**
     * Handle delete action
     */
    protected function handleDelete(): bool
    {
        // Check delete permissions
        if (!$this->permissions->allowDelete || !$this->views[$this->activeView]->settings['deleteButton']) {
            Logger::warning('Delete operation not permitted');
            return false;
        }
        
        if (!$this->validateFormToken()) {
            return false;
        }
        
        $model = $this->views[$this->activeView]->model;
        $selectedCodes = $this->request->getArray('codes');
        $singleCode = $this->request->get('code');
        
        if (empty($selectedCodes) && empty($singleCode)) {
            Logger::warning('No items selected for deletion');
            return false;
        }
        
        // Multiple deletion
        if (!empty($selectedCodes) && is_array($selectedCodes)) {
            $this->database->beginTransaction();
            $deletedCount = 0;
            
            foreach ($selectedCodes as $code) {
                if ($model->loadById($code) && $model->remove()) {
                    $deletedCount++;
                    continue;
                }
                
                $this->database->rollback();
                break;
            }
            
            $model->clear();
            $this->database->commit();
            
            if ($deletedCount > 0) {
                Logger::notice('Records deleted successfully');
                return true;
            }
        }
        // Single deletion
        elseif ($model->loadById($singleCode) && $model->remove()) {
            Logger::notice('Record deleted successfully');
            $model->clear();
            return true;
        }
        
        Logger::warning('Failed to delete record');
        $model->clear();
        return false;
    }
    
    /**
     * Handle export action
     */
    protected function handleExport(): void
    {
        $activeView = $this->views[$this->activeView];
        
        if (!$activeView->settings['printButton'] || !$this->permissions->allowExport) {
            Logger::warning('Export permission denied');
            return;
        }
        
        $this->setTemplate(false);
        
        $exportOption = $this->request->get('option', '');
        $formatId = (int)$this->request->get('idformat', 0);
        $languageCode = $this->request->get('langcode', '');
        
        $this->exportManager->initializeDocument($exportOption, $this->title, $formatId, $languageCode);
        
        foreach ($this->views as $view) {
            if (!$view->settings['isActive']) {
                continue;
            }
            
            $selectedCodes = $this->request->getArray('codes');
            if (!$view->exportData($this->exportManager, $selectedCodes)) {
                break;
            }
        }
        
        $this->exportManager->sendToResponse($this->response);
    }
    
    /**
     * Get autocomplete values from widget configuration
     */
    protected function getWidgetAutocompleteValues(string $viewName, string $fieldName): array
    {
        $values = [];
        $column = $this->views[$viewName]->getColumnForField($fieldName);
        
        if ($column !== null) {
            foreach ($column->widget->values as $item) {
                $values[] = [
                    'key' => Translation::translate($item['title']),
                    'value' => $item['value']
                ];
            }
        }
        
        return $values;
    }
    
    /**
     * Extract data from request
     */
    protected function extractRequestData(array $keys): array
    {
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = $this->request->get($key);
        }
        return $data;
    }
    
    /**
     * Handle select action
     */
    protected function handleSelect(): array
    {
        $isRequired = (bool)$this->request->get('required', false);
        $data = $this->extractRequestData([
            'field', 'fieldcode', 'fieldfilter', 'fieldtitle', 
            'formname', 'source', 'term'
        ]);
        
        $conditions = [];
        $filterOperations = DatabaseWhere::parseFilterOperations($data['fieldfilter'] ?? '');
        
        foreach ($filterOperations as $field => $operation) {
            $conditions[] = new DatabaseWhere($field, $data['term'], '=', $operation);
        }
        
        $results = [];
        $selectResults = $this->codeModel->getAll(
            $data['source'],
            $data['fieldcode'],
            $data['fieldtitle'],
            !$isRequired,
            $conditions
        );
        
        foreach ($selectResults as $item) {
            $results[] = [
                'key' => $item->code,
                'value' => $item->description
            ];
        }
        
        return $results;
    }
}