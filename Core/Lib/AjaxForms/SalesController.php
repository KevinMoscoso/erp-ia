<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2021-2025 ERPIA Team
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

namespace ERPIA\Core\Lib\AjaxForms;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\DataSrc\Series;
use ERPIA\Core\Lib\Calculator;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\DocFilesTrait;
use ERPIA\Core\Lib\ExtendedController\LogAuditTrait;
use ERPIA\Core\Lib\ExtendedController\PanelController;
use ERPIA\Core\Model\Base\SalesDocument;
use ERPIA\Core\Model\Base\SalesDocumentLine;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Lib\AssetManager;
use ERPIA\Dinamic\Model\Customer;
use ERPIA\Dinamic\Model\RoleAccess;
use ERPIA\Dinamic\Model\ProductVariant;

/**
 * Description of SalesController
 * @author ERPIA Team
 */
abstract class SalesController extends PanelController
{
    use DocFilesTrait;
    use LogAuditTrait;
    
    const MAIN_VIEW_NAME = 'main';
    const MAIN_VIEW_TEMPLATE = 'Tab/SalesDocument';
    
    /** @var array */
    private $logLevels = ['critical', 'error', 'info', 'notice', 'warning'];

    /**
     * Get the model class name for this controller
     * @return string
     */
    abstract public function getModelClassName();

    /**
     * Get the sales document model
     * @param bool $reload Whether to reload the model
     * @return SalesDocument
     */
    public function getModel(bool $reload = false): SalesDocument
    {
        if ($reload) {
            $this->views[static::MAIN_VIEW_NAME]->model->clear();
        }
        
        // Return loaded record if exists
        if ($this->views[static::MAIN_VIEW_NAME]->model->exists()) {
            return $this->views[static::MAIN_VIEW_NAME]->model;
        }
        
        // Get record identifier
        $code = $this->request->queryOrInput('code');
        if (empty($code)) {
            // Set initial parameters for new record
            $formData = $this->request->query->all();
            SalesHeaderHTML::apply($this->views[static::MAIN_VIEW_NAME]->model, $formData);
            SalesFooterHTML::apply($this->views[static::MAIN_VIEW_NAME]->model, $formData);
            return $this->views[static::MAIN_VIEW_NAME]->model;
        }
        
        // Load existing record
        $this->views[static::MAIN_VIEW_NAME]->model->loadFromCode($code);
        return $this->views[static::MAIN_VIEW_NAME]->model;
    }

    /**
     * Render the sales form
     * @param SalesDocument $model
     * @param SalesDocumentLine[] $lines
     * @return string
     */
    public function renderSalesForm(SalesDocument $model, array $lines): string
    {
        $url = empty($model->id()) ? $this->url() : $model->url();
        return '<div id="salesFormHeader">' . SalesHeaderHTML::render($model) . '</div>'
            . '<div id="salesFormLines">' . SalesLineHTML::render($lines, $model) . '</div>'
            . '<div id="salesFormFooter">' . SalesFooterHTML::render($model) . '</div>'
            . SalesModalHTML::render($model, $url);
    }

    /**
     * Get series by type
     * @param string $type Series type
     * @return array
     */
    public function series(string $type = ''): array
    {
        if (empty($type)) {
            return Series::all();
        }
        
        $list = [];
        foreach (Series::all() as $serie) {
            if ($serie->type == $type) {
                $list[] = $serie;
            }
        }
        return $list;
    }

    /**
     * Handle product autocomplete action
     * @return bool
     */
    protected function autocompleteProductAction(): bool
    {
        $this->setTemplate(false);
        $list = [];
        $variant = new ProductVariant();
        $query = (string)$this->request->queryOrInput('term');
        $where = [
            new DataBaseWhere('p.blocked', 0),
            new DataBaseWhere('p.sellable', 1)
        ];
        
        foreach ($variant->codeModelSearch($query, 'reference', $where) as $value) {
            $list[] = [
                'key' => Tools::fixHtml($value->code),
                'value' => Tools::fixHtml($value->description)
            ];
        }
        
        if (empty($list)) {
            $list[] = ['key' => null, 'value' => Tools::trans('no-data')];
        }
        
        $this->response->json($list);
        return false;
    }

    /**
     * Create views
     */
    protected function createViews()
    {
        $this->setTabsPosition('top');
        $this->createViewsDoc();
        $this->createViewDocFiles();
        $this->createViewLogAudit();
    }

    /**
     * Create document views
     */
    protected function createViewsDoc(): void
    {
        $pageData = $this->getPageData();
        $this->addHtmlView(
            static::MAIN_VIEW_NAME, 
            static::MAIN_VIEW_TEMPLATE, 
            $this->getModelClassName(), 
            $pageData['title'], 
            'fa-solid fa-file'
        );
        
        $route = Tools::config('route');
        AssetManager::addCss($route . '/node_modules/jquery-ui-dist/jquery-ui.min.css', 2);
        AssetManager::addJs($route . '/node_modules/jquery-ui-dist/jquery-ui.min.js', 2);
        
        SalesHeaderHTML::assets();
        SalesLineHTML::assets();
        SalesFooterHTML::assets();
    }

    /**
     * Handle document deletion
     * @return bool
     */
    protected function deleteDocAction(): bool
    {
        $this->setTemplate(false);
        
        // Check permissions
        if (false === $this->permissions->allowDelete) {
            Tools::log()->warning('not-allowed-delete');
            $this->sendJsonWithLogs(['ok' => false]);
            return false;
        }
        
        $model = $this->getModel();
        if (false === $model->delete()) {
            $this->sendJsonWithLogs(['ok' => false]);
            return false;
        }
        
        $this->sendJsonWithLogs(['ok' => true, 'newurl' => $model->url('list')]);
        return false;
    }

    /**
     * Execute previous action
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'add-file':
                return $this->addFileAction();
            case 'autocomplete-product':
                return $this->autocompleteProductAction();
            case 'add-product':
            case 'fast-line':
            case 'fast-product':
            case 'new-line':
            case 'recalculate':
            case 'rm-line':
            case 'set-customer':
                return $this->recalculateAction(true);
            case 'delete-doc':
                return $this->deleteDocAction();
            case 'delete-file':
                return $this->deleteFileAction();
            case 'edit-file':
                return $this->editFileAction();
            case 'find-customer':
                return $this->findCustomerAction();
            case 'find-product':
                return $this->findProductAction();
            case 'recalculate-line':
                return $this->recalculateAction(false);
            case 'save-doc':
                $this->saveDocAction();
                return false;
            case 'save-paid':
                return $this->savePaidAction();
            case 'save-status':
                return $this->saveStatusAction();
            case 'unlink-file':
                return $this->unlinkFileAction();
        }
        
        return parent::execPreviousAction($action);
    }

    /**
     * Handle export action
     */
    protected function exportAction()
    {
        $this->setTemplate(false);
        $subjectLang = $this->views[static::MAIN_VIEW_NAME]->model->getSubject()->langcode;
        $requestLang = $this->request->input('langcode');
        $langCode = $requestLang ?? $subjectLang ?? '';
        
        $this->exportManager->newDoc(
            $this->request->queryOrInput('option', ''),
            $this->title,
            (int)$this->request->input('idformat', ''),
            $langCode
        );
        
        $this->exportManager->addBusinessDocPage($this->views[static::MAIN_VIEW_NAME]->model);
        $this->exportManager->show($this->response);
    }

    /**
     * Find customer action
     * @return bool
     */
    protected function findCustomerAction(): bool
    {
        $this->setTemplate(false);
        
        // Check if user can view all customers
        $showAll = false;
        foreach (RoleAccess::allFromUser($this->user->nick, 'EditCustomer') as $access) {
            if (false === $access->onlyOwnerData) {
                $showAll = true;
            }
        }
        
        $where = [];
        if ($this->permissions->onlyOwnerData && !$showAll) {
            $where[] = new DataBaseWhere('codagent', $this->user->codagent);
            $where[] = new DataBaseWhere('codagent', null, 'IS NOT');
        }
        
        $list = [];
        $customer = new Customer();
        $term = $this->request->queryOrInput('term');
        
        foreach ($customer->codeModelSearch($term, '', $where) as $item) {
            $list[$item->code] = $item->code . ' | ' . Tools::fixHtml($item->description);
        }
        
        $this->response->json($list);
        return false;
    }

    /**
     * Find product action
     * @return bool
     */
    protected function findProductAction(): bool
    {
        $this->setTemplate(false);
        $model = $this->getModel();
        $formData = json_decode($this->request->input('data'), true);
        
        SalesHeaderHTML::apply($model, $formData);
        SalesFooterHTML::apply($model, $formData);
        SalesModalHTML::apply($model, $formData);
        
        $content = [
            'header' => '',
            'lines' => '',
            'linesMap' => [],
            'footer' => '',
            'products' => SalesModalHTML::renderProductList()
        ];
        
        $this->sendJsonWithLogs($content);
        return false;
    }

    /**
     * Load data for view
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $code = $this->request->queryOrInput('code');
        
        switch ($viewName) {
            case 'docfiles':
                $this->loadDataDocFiles($view, $this->getModelClassName(), $code);
                break;
            case 'ListLogMessage':
                $this->loadDataLogAudit($view, $this->getModelClassName(), $code);
                break;
            case static::MAIN_VIEW_NAME:
                if (empty($code)) {
                    $this->getModel(true);
                    break;
                }
                
                // Load data or show not found
                $view->loadData($code);
                $action = $this->request->input('action', '');
                
                if ('' === $action && empty($view->model->primaryColumnValue())) {
                    Tools::log()->warning('record-not-found');
                    break;
                }
                
                $this->title .= ' ' . $view->model->primaryDescription();
                $view->settings['btnPrint'] = true;
                
                $this->addButton($viewName, [
                    'action' => 'CopyModel?model=' . $this->getModelClassName() 
                        . '&code=' . $view->model->primaryColumnValue(),
                    'icon' => 'fa-solid fa-cut',
                    'label' => 'copy',
                    'type' => 'link'
                ]);
                break;
        }
    }

    /**
     * Recalculate action
     * @param bool $renderLines Whether to render lines
     * @return bool
     */
    protected function recalculateAction(bool $renderLines): bool
    {
        $this->setTemplate(false);
        $model = $this->getModel();
        $lines = $model->getLines();
        $formData = json_decode($this->request->input('data'), true);
        
        SalesHeaderHTML::apply($model, $formData);
        SalesFooterHTML::apply($model, $formData);
        SalesLineHTML::apply($model, $lines, $formData);
        
        Calculator::calculate($model, $lines, false);
        
        $content = [
            'header' => SalesHeaderHTML::render($model),
            'lines' => $renderLines ? SalesLineHTML::render($lines, $model) : '',
            'linesMap' => $renderLines ? [] : SalesLineHTML::map($lines, $model),
            'footer' => SalesFooterHTML::render($model),
            'products' => '',
        ];
        
        $this->sendJsonWithLogs($content);
        return false;
    }

    /**
     * Save document action
     * @return bool
     */
    protected function saveDocAction(): bool
    {
        $this->setTemplate(false);
        
        // Check permissions
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            $this->sendJsonWithLogs(['ok' => false]);
            return false;
        }
        
        $this->dataBase->beginTransaction();
        $model = $this->getModel();
        $formData = json_decode($this->request->input('data'), true);
        
        SalesHeaderHTML::apply($model, $formData);
        SalesFooterHTML::apply($model, $formData);
        
        if (false === $model->save()) {
            $this->sendJsonWithLogs(['ok' => false]);
            $this->dataBase->rollback();
            return false;
        }
        
        $lines = $model->getLines();
        SalesLineHTML::apply($model, $lines, $formData);
        Calculator::calculate($model, $lines, false);
        
        foreach ($lines as $line) {
            if (false === $line->save()) {
                $this->sendJsonWithLogs(['ok' => false]);
                $this->dataBase->rollback();
                return false;
            }
        }
        
        // Remove missing lines
        foreach ($model->getLines() as $oldLine) {
            if (in_array($oldLine->lineId, SalesLineHTML::getDeletedLines()) && false === $oldLine->delete()) {
                $this->sendJsonWithLogs(['ok' => false]);
                $this->dataBase->rollback();
                return false;
            }
        }
        
        $lines = $model->getLines();
        if (false === Calculator::calculate($model, $lines, true)) {
            $this->sendJsonWithLogs(['ok' => false]);
            $this->dataBase->rollback();
            return false;
        }
        
        $this->sendJsonWithLogs(['ok' => true, 'newurl' => $model->url() . '&action=save-ok']);
        $this->dataBase->commit();
        return true;
    }

    /**
     * Save paid action
     * @return bool
     */
    protected function savePaidAction(): bool
    {
        $this->setTemplate(false);
        
        // Check permissions
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            $this->sendJsonWithLogs(['ok' => false]);
            return false;
        }
        
        // Save document first if editable
        if ($this->getModel()->editable && false === $this->saveDocAction()) {
            $this->sendJsonWithLogs(['ok' => false]);
            return false;
        }
        
        // Load updated model and form data
        $model = $this->getModel();
        $formData = json_decode($this->request->input('data'), true);
        
        // If invoice is 0€, mark as paid
        if (empty($model->total) && $model->hasColumn('paid')) {
            $model->paid = (bool)$formData['paid-status'];
            $model->save();
            $this->sendJsonWithLogs(['ok' => true, 'newurl' => $model->url() . '&action=save-ok']);
            return false;
        }
        
        // Check for receipts
        $receipts = $model->getReceipts();
        if (empty($receipts)) {
            Tools::log()->warning('invoice-has-no-receipts');
            $this->sendJsonWithLogs(['ok' => false]);
            return false;
        }
        
        // Mark receipts as paid
        foreach ($receipts as $receipt) {
            $receipt->nick = $this->user->nick;
            
            // If not paid, update payment date and method
            if (false == $receipt->paid) {
                $receipt->paymentDate = $formData['paid-date-modal'] ?? Tools::date();
                $receipt->paymentMethod = $formData['paid-payment-modal'] ?? $model->paymentMethod;
            }
            
            $receipt->paid = (bool)$formData['paid-status'];
            if (false === $receipt->save()) {
                $this->sendJsonWithLogs(['ok' => false]);
                return false;
            }
        }
        
        $this->sendJsonWithLogs(['ok' => true, 'newurl' => $model->url() . '&action=save-ok']);
        return false;
    }

    /**
     * Save status action
     * @return bool
     */
    protected function saveStatusAction(): bool
    {
        $this->setTemplate(false);
        
        // Check permissions
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            $this->sendJsonWithLogs(['ok' => false]);
            return false;
        }
        
        if ($this->getModel()->editable && false === $this->saveDocAction()) {
            return false;
        }
        
        $model = $this->getModel();
        $model->statusId = (int)$this->request->input('selectedLine');
        
        if (false === $model->save()) {
            $this->sendJsonWithLogs(['ok' => false]);
            return false;
        }
        
        $this->sendJsonWithLogs(['ok' => true, 'newurl' => $model->url() . '&action=save-ok']);
        return false;
    }

    /**
     * Send JSON response with logs
     * @param array $data Response data
     */
    private function sendJsonWithLogs(array $data): void
    {
        $data['messages'] = [];
        foreach (Tools::log()::read('', $this->logLevels) as $message) {
            if ($message['channel'] != 'audit') {
                $data['messages'][] = $message;
            }
        }
        $this->response->json($data);
    }
}