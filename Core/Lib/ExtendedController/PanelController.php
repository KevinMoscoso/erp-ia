<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2023-2025 ERPIA Team
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace ERPIA\Lib\ExtendedController;

use ERPIA\Core\Base\ControllerPermissions;
use ERPIA\Core\Response;
use ERPIA\Core\Logger;
use ERPIA\Core\Formatter;
use ERPIA\Dinamic\Model\User;

/**
 * Controller to edit data through the vertical panel
 * @author ERPIA Team
 */
abstract class PanelController extends BaseController
{
    /**
     * Indicates if the main view has data or is empty.
     * @var bool
     */
    public $hasData = false;

    /**
     * Tabs position in page: left, bottom.
     * @var string
     */
    public $tabsPosition;

    /**
     * Starts all the objects and properties.
     * @param string $className
     * @param string $uri
     */
    public function __construct(string $className, string $uri = '')
    {
        parent::__construct($className, $uri);
        $this->setTabsPosition('left');
    }

    /**
     * Get image URL for the controller (override if needed).
     * @return string
     */
    public function getImageUrl(): string
    {
        return '';
    }

    /**
     * Runs the controller's private logic.
     * @param Response $response
     * @param User $user
     * @param ControllerPermissions $permissions
     */
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        // Get any operations that have to be performed
        $action = $this->request->inputOrQuery('action', '');
        // Runs operations before reading data
        if ($this->execPreviousAction($action) === false || $this->pipeFalse('execPreviousAction', $action) === false) {
            return;
        }
        // Load the data for each view
        $mainViewName = $this->getMainViewName();
        foreach ($this->views as $viewName => $view) {
            // disable views if main view has no data
            if ($viewName != $mainViewName && false === $this->hasData) {
                $this->setSettings($viewName, 'active', false);
            }
            if (false === $view->settings['active']) {
                // exclude inactive views
                continue;
            } elseif ($this->active == $viewName) {
                $view->processFormData($this->request, 'load');
            } else {
                $view->processFormData($this->request, 'preload');
            }
            $this->loadData($viewName, $view);
            $this->pipeFalse('loadData', $viewName, $view);
            if ($viewName === $mainViewName && $view->model->exists()) {
                $this->hasData = true;
            }
        }
        // General operations with the loaded data
        $this->execAfterAction($action);
        $this->pipeFalse('execAfterAction', $action);
    }

    /**
     * Sets the tabs position, by default is set to 'left', also supported 'bottom', 'top' and 'left-bottom'.
     * @param string $position
     */
    public function setTabsPosition(string $position): void
    {
        $this->tabsPosition = $position;
        switch ($this->tabsPosition) {
            case 'bottom':
                $this->setTemplate('Master/PanelControllerBottom');
                break;
            case 'left-bottom':
                $this->setTemplate('Master/PanelControllerLeftBottom');
                break;
            case 'top':
                $this->setTemplate('Master/PanelControllerTop');
                break;
            default:
                $this->tabsPosition = 'left';
                $this->setTemplate('Master/PanelController');
        }
        foreach (array_keys($this->views) as $viewName) {
            $this->views[$viewName]->settings['card'] = $this->tabsPosition !== 'top';
        }
    }

    /**
     * Adds a EditList type view to the controller.
     * @param string $viewName
     * @param string $modelName
     * @param string $viewTitle
     * @param string $viewIcon
     * @return EditListView
     */
    protected function addEditListView(string $viewName, string $modelName, string $viewTitle, string $viewIcon = 'fa-solid fa-bars'): EditListView
    {
        $view = new EditListView($viewName, $viewTitle, self::MODEL_NAMESPACE . $modelName, $viewIcon);
        $view->settings['card'] = $this->tabsPosition !== 'top';
        $this->addCustomView($viewName, $view);
        return $view;
    }

    /**
     * Adds an Edit type view to the controller.
     * @param string $viewName
     * @param string $modelName
     * @param string $viewTitle
     * @param string $viewIcon
     * @return EditView
     */
    protected function addEditView(string $viewName, string $modelName, string $viewTitle, string $viewIcon = 'fa-solid fa-edit'): EditView
    {
        $view = new EditView($viewName, $viewTitle, self::MODEL_NAMESPACE . $modelName, $viewIcon);
        $view->settings['card'] = $this->tabsPosition !== 'top';
        $this->addCustomView($viewName, $view);
        return $view;
    }

    /**
     * Adds an HTML type view to the controller.
     * @param string $viewName
     * @param string $fileName
     * @param string $modelName
     * @param string $viewTitle
     * @param string $viewIcon
     * @return HtmlView
     */
    protected function addHtmlView(string $viewName, string $fileName, string $modelName, string $viewTitle, string $viewIcon = 'fa-brands fa-html5'): HtmlView
    {
        $view = new HtmlView($viewName, $viewTitle, self::MODEL_NAMESPACE . $modelName, $fileName, $viewIcon);
        $this->addCustomView($viewName, $view);
        return $view;
    }

    /**
     * Adds a List type view to the controller.
     * @param string $viewName
     * @param string $modelName
     * @param string $viewTitle
     * @param string $viewIcon
     * @return ListView
     */
    protected function addListView(string $viewName, string $modelName, string $viewTitle, string $viewIcon = 'fa-solid fa-list'): ListView
    {
        $view = new ListView($viewName, $viewTitle, self::MODEL_NAMESPACE . $modelName, $viewIcon);
        $view->settings['card'] = $this->tabsPosition !== 'top';
        $this->addCustomView($viewName, $view);
        return $view;
    }

    /**
     * Runs the data edit action.
     * @return bool
     */
    protected function editAction()
    {
        if (false === $this->permissions->allowUpdate) {
            Logger::warning('not-allowed-modify');
            return false;
        } elseif (false === $this->validateFormToken()) {
            return false;
        }
        // loads model data
        $code = $this->request->input('code', '');
        if (!$this->views[$this->active]->model->loadFromCode($code)) {
            Logger::error('record-not-found');
            return false;
        }
        // loads form data
        $this->views[$this->active]->processFormData($this->request, 'edit');
        // has PK value been changed?
        $this->views[$this->active]->newCode = $this->views[$this->active]->model->primaryKeyValue();
        if ($code !== $this->views[$this->active]->newCode && $this->views[$this->active]->model->test()) {
            $pkColumn = $this->views[$this->active]->model->primaryKey();
            $this->views[$this->active]->model->{$pkColumn} = $code;
            // change in database
            if (!$this->views[$this->active]->model->changePrimaryKeyValue($this->views[$this->active]->newCode)) {
                Logger::error('record-save-error');
                return false;
            }
        }
        // save in database
        if ($this->views[$this->active]->model->save()) {
            Logger::notice('record-updated-correctly');
            return true;
        }
        Logger::error('record-save-error');
        return false;
    }

    /**
     * Run the controller after actions.
     * @param string $action
     */
    protected function execAfterAction($action)
    {
        switch ($action) {
            case 'export':
                $this->exportAction();
                break;
            case 'save-ok':
                Logger::notice('record-updated-correctly');
                break;
            case 'widget-library-search':
                $this->setTemplate(false);
                $results = $this->widgetLibrarySearchAction();
                $this->response->json($results);
                break;
            case 'widget-library-upload':
                $this->setTemplate(false);
                $results = $this->widgetLibraryUploadAction();
                $this->response->json($results);
                break;
            case 'widget-variant-search':
                $this->setTemplate(false);
                $results = $this->widgetVariantSearchAction();
                $this->response->json($results);
                break;
            case 'widget-subaccount-search':
                $this->setTemplate(false);
                $results = $this->widgetSubaccountSearchAction();
                $this->response->json($results);
                break;
        }
    }

    /**
     * Run the actions that alter data before reading it.
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'autocomplete':
                $this->setTemplate(false);
                $results = $this->autocompleteAction();
                $this->response->json($results);
                return false;
            case 'datalist':
                $this->setTemplate(false);
                $results = $this->datalistAction();
                $this->response->json($results);
                return false;
            case 'delete':
            case 'delete-document':
                if ($this->deleteAction() && $this->active === $this->getMainViewName()) {
                    // al eliminar el registro principal, redirigimos al listado para mostrar ahí el mensaje de éxito
                    $listUrl = $this->views[$this->active]->model->url('list');
                    $redirect = strpos($listUrl, '?') === false ?
                        $listUrl . '?action=delete-ok' :
                        $listUrl . '&action=delete-ok';
                    $this->redirect($redirect);
                }
                break;
            case 'edit':
                if ($this->editAction()) {
                    $this->views[$this->active]->model->clear();
                }
                break;
            case 'insert':
                if ($this->insertAction() || !empty($this->views[$this->active]->model->primaryKeyValue())) {
                    // we need to clear model in these scenarios
                    $this->views[$this->active]->model->clear();
                }
                break;
            case 'select':
                $this->setTemplate(false);
                $results = $this->selectAction();
                $this->response->json($results);
                return false;
        }
        return true;
    }

    /**
     * Runs data insert action.
     * @return bool
     */
    protected function insertAction()
    {
        if (false === $this->permissions->allowUpdate) {
            Logger::warning('not-allowed-modify');
            return false;
        } elseif (false === $this->validateFormToken()) {
            return false;
        }
        // loads form data
        $this->views[$this->active]->processFormData($this->request, 'edit');
        if ($this->views[$this->active]->model->exists()) {
            Logger::error('duplicate-record');
            return false;
        }
        // save in database
        if (false === $this->views[$this->active]->model->save()) {
            Logger::error('record-save-error');
            return false;
        }
        // redirect to new model url only if this is the first view
        if ($this->active === $this->getMainViewName()) {
            $this->redirect($this->views[$this->active]->model->url() . '&action=save-ok');
        }
        $this->views[$this->active]->newCode = $this->views[$this->active]->model->primaryKeyValue();
        Logger::notice('record-updated-correctly');
        return true;
    }

    /**
     * Widget library search action.
     * @return array
     */
    protected function widgetLibrarySearchAction(): array
    {
        // locate the tab and column name
        $activeTab = $this->request->input('active_tab', '');
        $colName = $this->request->input('col_name', '');
        $widgetId = $this->request->input('widget_id', '');
        // if empty, do nothing
        if (empty($activeTab) || empty($colName)) {
            return ['records' => 0, 'html' => ''];
        }
        // find the column
        $column = $this->tab($activeTab)->columnForField($colName);
        if (empty($column) || strtolower($column->widget->getType()) !== 'library') {
            return ['records' => 0, 'html' => ''];
        }
        $files = $column->widget->files(
            $this->request->input('query', ''),
            $this->request->input('sort', '')
        );
        $selectedValue = (int)$column->widget->plainText($this->tab($activeTab)->model);
        return [
            'html' => $column->widget->renderFileList($files, $selectedValue, $widgetId),
            'records' => count($files),
        ];
    }

    /**
     * Widget library upload action.
     * @return array
     */
    protected function widgetLibraryUploadAction(): array
    {
        // locate the tab and column name
        $activeTab = $this->request->input('active_tab', '');
        $colName = $this->request->input('col_name', '');
        $widgetId = $this->request->input('widget_id', '');
        // if empty, do nothing
        if (empty($activeTab) || empty($colName)) {
            return [];
        }
        // find the column
        $column = $this->tab($activeTab)->columnForField($colName);
        if (empty($column) || strtolower($column->widget->getType()) !== 'library') {
            return [];
        }
        $file = $this->request->file('file');
        if (empty($file)) {
            return [];
        }
        $attachedFile = $column->widget->uploadFile($file);
        if (false === $attachedFile->exists()) {
            return [];
        }
        $files = $column->widget->files();
        return [
            'html' => $column->widget->renderFileList($files, $attachedFile->id, $widgetId),
            'records' => count($files),
            'new_file' => $attachedFile->id,
            'new_filename' => $attachedFile->getShortName(),
        ];
    }

    /**
     * Widget variant search action.
     * @return array
     */
    protected function widgetVariantSearchAction(): array
    {
        // locate the tab and column name
        $activeTab = $this->request->input('active_tab', '');
        $colName = $this->request->input('col_name', '');
        // if empty, do nothing
        if (empty($activeTab) || empty($colName)) {
            return [];
        }
        // find the column
        $column = $this->tab($activeTab)->columnForField($colName);
        if (empty($column) || strtolower($column->widget->getType()) !== 'variant') {
            return [];
        }
        $variants = $column->widget->variants(
            $this->request->input('query', ''),
            $this->request->input('manufacturer_code', ''),
            $this->request->input('category_code', ''),
            $this->request->input('sort', '')
        );
        $results = [];
        foreach ($variants as $variant) {
            $results[] = [
                'variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'reference' => $variant->reference,
                'description' => $variant->getDescription(),
                'price' => $variant->price,
                'price_str' => Formatter::formatCurrency($variant->price),
                'stock' => $variant->physical_stock,
                'stock_str' => Formatter::formatNumber($variant->physical_stock, 0),
                'match' => $variant->{$column->widget->match},
                'url' => $variant->url()
            ];
        }
        return $results;
    }

    /**
     * Widget subaccount search action.
     * @return array
     */
    protected function widgetSubaccountSearchAction(): array
    {
        // locate the tab and column name
        $activeTab = $this->request->input('active_tab', '');
        $colName = $this->request->input('col_name', '');
        // if empty, do nothing
        if (empty($activeTab) || empty($colName)) {
            return [];
        }
        // find the column
        $column = $this->tab($activeTab)->columnForField($colName);
        if (empty($column) || strtolower($column->widget->getType()) !== 'subaccount') {
            return [];
        }
        $subaccounts = $column->widget->subaccounts(
            $this->request->input('query', ''),
            $this->request->request->get('fiscal_year_code', ''),
            $this->request->request->get('sort', '')
        );
        $results = [];
        foreach ($subaccounts as $subaccount) {
            $results[] = [
                'subaccount_code' => $subaccount->code,
                'description' => $subaccount->description,
                'balance' => $subaccount->balance,
                'url' => $subaccount->url()
            ];
        }
        return $results;
    }
}