<?php

namespace ERPIA\Lib\ExtendedController;

use ERPIA\Core\Logger;

/**
 * Base controller for editing models
 */
abstract class EditController extends PanelController
{
    /**
     * Get the model class name for this controller
     */
    abstract public function getModelClassName(): string;
    
    /**
     * Get the model instance from the main view
     */
    public function getModel()
    {
        $mainView = $this->getMainViewName();
        return $this->views[$mainView]->model;
    }
    
    /**
     * Get page metadata with menu visibility disabled
     */
    public function getPageMetadata(): array
    {
        $metadata = parent::getPageMetadata();
        $metadata['showInMenu'] = false;
        return $metadata;
    }
    
    /**
     * Initialize controller views
     */
    protected function initializeViews(): void
    {
        $viewName = 'Edit' . $this->getModelClassName();
        $modelName = $this->getModelClassName();
        $pageData = $this->getPageMetadata();
        
        $this->addEditView($viewName, $modelName, $pageData['title'], $pageData['icon']);
        $this->setViewSetting($viewName, 'printButton', true);
    }
    
    /**
     * Handle export action
     */
    protected function handleExport(): void
    {
        $activeView = $this->views[$this->activeView];
        
        // Check permissions
        if (!$activeView->viewSettings['printButton'] || !$this->permissions->allowExport) {
            Logger::warning('Export permission denied');
            return;
        }
        
        $this->disableTemplate();
        
        $exportOption = $this->request->get('option', '');
        $formatId = (int)$this->request->get('idformat', 0);
        $languageCode = $this->request->get('langcode', '');
        
        $this->exportManager->initializeDocument(
            $exportOption, 
            $this->title, 
            $formatId, 
            $languageCode
        );
        
        $activeTab = $this->request->get('activetab', '');
        
        foreach ($this->views as $name => $view) {
            if (!$view->viewSettings['active']) {
                continue;
            }
            
            // If an active tab is specified, skip other tabs
            if (!empty($activeTab) && $activeTab !== $name) {
                continue;
            }
            
            $selectedCodes = $this->request->getArray('codes');
            if (!$view->export($this->exportManager, $selectedCodes)) {
                break;
            }
        }
        
        $this->exportManager->sendToResponse($this->response);
    }
    
    /**
     * Load data for a specific view
     */
    protected function loadViewData(string $viewName, BaseView $view): void
    {
        $mainViewName = $this->getMainViewName();
        
        if ($viewName !== $mainViewName) {
            return;
        }
        
        $model = $view->model;
        $primaryKey = $model->getPrimaryKey();
        
        // Get identifier from request
        $primaryValue = $this->request->get($primaryKey);
        $codeValue = $this->request->get('code', $primaryValue);
        
        $view->loadData($codeValue);
        
        // Check ownership permissions
        if (!$this->hasDataOwnershipPermission($model)) {
            $this->setTemplate('Error/AccessDenied');
            return;
        }
        
        $action = $this->request->get('action', '');
        
        // Check if record exists (only when not performing an action)
        if (empty($action) && !empty($codeValue) && !$model->exists()) {
            Logger::warning('Record not found');
            return;
        }
        
        $this->title .= ' ' . $model->getPrimaryDescription();
    }
}