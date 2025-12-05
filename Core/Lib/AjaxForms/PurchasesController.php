<?php
/**
 * ERPIA - Controlador Abstracto para Documentos de Compras
 * Este archivo es parte de ERPIA, un sistema ERP de código abierto.
 * 
 * Copyright (C) 2025 ERPIA
 *
 * Este programa es software libre: puede redistribuirlo y/o modificarlo
 * bajo los términos de la Licencia Pública General GNU como publicada por
 * la Free Software Foundation, ya sea la versión 3 de la Licencia, o
 * (a su elección) cualquier versión posterior.
 *
 * Este programa se distribuye con la esperanza de que sea útil,
 * pero SIN NINGUNA GARANTÍA; sin siquiera la garantía implícita de
 * COMERCIALIZACIÓN o IDONEIDAD PARA UN PROPÓSITO PARTICULAR. Consulte la
 * Licencia Pública General GNU para obtener más detalles.
 *
 * Debería haber recibido una copia de la Licencia Pública General GNU
 * junto con este programa. Si no es así, consulte <http://www.gnu.org/licenses/>.
 */

namespace ERPIA\Core\Lib\AjaxForms;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\DataSrc\Series;
use ERPIA\Core\Lib\Calculator;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\DocFilesTrait;
use ERPIA\Core\Lib\ExtendedController\LogAuditTrait;
use ERPIA\Core\Lib\ExtendedController\PanelController;
use ERPIA\Core\Model\Base\BusinessDocumentLine;
use ERPIA\Core\Model\Base\PurchaseDocument;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Lib\AssetManager;
use ERPIA\Dinamic\Model\Proveedor;
use ERPIA\Dinamic\Model\Variante;

/**
 * Controlador abstracto para documentos de compras.
 * 
 * @author ERPIA
 * @version 1.0
 */
abstract class PurchasesController extends PanelController
{
    use DocFilesTrait;
    use LogAuditTrait;

    const MAIN_VIEW_NAME = 'main';
    const MAIN_VIEW_TEMPLATE = 'Tab/PurchasesDocument';

    /** @var array */
    private $logLevels = ['critical', 'error', 'info', 'notice', 'warning'];

    /**
     * Obtiene el nombre de la clase del modelo.
     *
     * @return string
     */
    abstract public function getModelClassName();

    /**
     * Obtiene el modelo del documento de compra.
     *
     * @param bool $reload
     * @return PurchaseDocument
     */
    public function getModel(bool $reload = false): PurchaseDocument
    {
        $mainView = $this->views[static::MAIN_VIEW_NAME];
        
        if ($reload) {
            $mainView->model->clear();
        }

        if ($mainView->model->id()) {
            return $mainView->model;
        }

        $code = $this->request->queryOrInput('code');
        if (empty($code)) {
            $formData = $this->request->query->all();
            PurchasesHeaderHTML::apply($mainView->model, $formData);
            return $mainView->model;
        }

        $mainView->model->loadFromCode($code);
        return $mainView->model;
    }

    /**
     * Renderiza el formulario completo de compras.
     *
     * @param PurchaseDocument $model
     * @param BusinessDocumentLine[] $lines
     * @return string
     */
    public function renderPurchasesForm(PurchaseDocument $model, array $lines): string
    {
        $url = empty($model->id()) ? $this->url() : $model->url();
        return '<div id="purchasesFormHeader">' . PurchasesHeaderHTML::render($model) . '</div>'
            . '<div id="purchasesFormLines">' . PurchasesLineHTML::render($lines, $model) . '</div>'
            . '<div id="purchasesFormFooter">' . PurchasesFooterHTML::render($model) . '</div>'
            . PurchasesModalHTML::render($model, $url);
    }

    /**
     * Obtiene las series disponibles.
     *
     * @param string $type
     * @return array
     */
    public function series(string $type = ''): array
    {
        if (empty($type)) {
            return Series::all();
        }

        $filtered = [];
        foreach (Series::all() as $serie) {
            if ($serie->tipo == $type) {
                $filtered[] = $serie;
            }
        }
        return $filtered;
    }

    /**
     * Acción de autocompletar productos.
     *
     * @return bool
     */
    protected function autocompleteProductAction(): bool
    {
        $this->setTemplate(false);
        $results = [];
        $variant = new Variante();
        $search = (string)$this->request->queryOrInput('term');
        $conditions = [
            new DataBaseWhere('p.bloqueado', 0),
            new DataBaseWhere('p.secompra', 1)
        ];

        foreach ($variant->codeModelSearch($search, 'referencia', $conditions) as $item) {
            $results[] = [
                'key' => Tools::fixHtml($item->code),
                'value' => Tools::fixHtml($item->description)
            ];
        }

        if (empty($results)) {
            $results[] = ['key' => null, 'value' => Tools::trans('no-data')];
        }

        $this->response->json($results);
        return false;
    }

    /**
     * Crea las vistas del controlador.
     */
    protected function createViews()
    {
        $this->setTabsPosition('top');
        $this->createDocumentViews();
        $this->createDocumentFilesView();
        $this->createAuditLogView();
    }

    /**
     * Crea la vista principal del documento.
     */
    protected function createDocumentViews(): void
    {
        $pageInfo = $this->getPageData();
        $this->addHtmlView(
            static::MAIN_VIEW_NAME,
            static::MAIN_VIEW_TEMPLATE,
            $this->getModelClassName(),
            $pageInfo['title'],
            'fa-solid fa-file'
        );

        $route = Tools::config('route');
        AssetManager::addCss($route . '/node_modules/jquery-ui-dist/jquery-ui.min.css', 2);
        AssetManager::addJs($route . '/node_modules/jquery-ui-dist/jquery-ui.min.js', 2);
        
        PurchasesHeaderHTML::assets();
        PurchasesLineHTML::assets();
        PurchasesFooterHTML::assets();
    }

    /**
     * Acción para eliminar documento.
     *
     * @return bool
     */
    protected function deleteDocAction(): bool
    {
        $this->setTemplate(false);
        
        if (!$this->permissions->allowDelete) {
            Tools::log()->warning('not-allowed-delete');
            $this->sendJsonResponse(['ok' => false]);
            return false;
        }

        $model = $this->getModel();
        if (!$model->delete()) {
            $this->sendJsonResponse(['ok' => false]);
            return false;
        }

        $this->sendJsonResponse(['ok' => true, 'newurl' => $model->url('list')]);
        return false;
    }

    /**
     * Ejecuta acciones previas al procesamiento principal.
     *
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
            case 'set-supplier':
                return $this->recalculateAction(true);
            case 'delete-doc':
                return $this->deleteDocAction();
            case 'delete-file':
                return $this->deleteFileAction();
            case 'edit-file':
                return $this->editFileAction();
            case 'find-supplier':
                return $this->findSupplierAction();
            case 'find-product':
                return $this->findProductAction();
            case 'recalculate-line':
                return $this->recalculateAction(false);
            case 'save-doc':
                $this->saveDocumentAction();
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
     * Acción de exportación de documentos.
     */
    protected function exportAction()
    {
        $this->setTemplate(false);
        $model = $this->views[static::MAIN_VIEW_NAME]->model;
        $subjectLang = $model->getSubject()->langcode ?? '';
        $requestLang = $this->request->input('langcode');
        $langCode = $requestLang ?? $subjectLang ?? '';

        $this->exportManager->newDoc(
            $this->request->queryOrInput('option', ''),
            $this->title,
            (int)$this->request->input('idformat', ''),
            $langCode
        );

        $this->exportManager->addBusinessDocPage($model);
        $this->exportManager->show($this->response);
    }

    /**
     * Acción de búsqueda de proveedores.
     *
     * @return bool
     */
    protected function findSupplierAction(): bool
    {
        $this->setTemplate(false);
        $supplier = new Proveedor();
        $list = [];
        $term = $this->request->queryOrInput('term');

        foreach ($supplier->codeModelSearch($term) as $item) {
            $list[$item->code] = $item->code . ' | ' . Tools::fixHtml($item->description);
        }

        $this->response->json($list);
        return false;
    }

    /**
     * Acción de búsqueda de productos.
     *
     * @return bool
     */
    protected function findProductAction(): bool
    {
        $this->setTemplate(false);
        $model = $this->getModel();
        $formData = json_decode($this->request->input('data'), true);

        PurchasesHeaderHTML::apply($model, $formData);
        PurchasesFooterHTML::apply($model, $formData);
        PurchasesModalHTML::apply($model, $formData);

        $content = [
            'header' => '',
            'lines' => '',
            'linesMap' => [],
            'footer' => '',
            'products' => PurchasesModalHTML::renderProductList()
        ];

        $this->sendJsonResponse($content);
        return false;
    }

    /**
     * Carga datos en las vistas.
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $code = $this->request->queryOrInput('code');
        
        switch ($viewName) {
            case 'docfiles':
                $this->loadDocumentFiles($view, $this->getModelClassName(), $code);
                break;
            case 'ListLogMessage':
                $this->loadAuditLogs($view, $this->getModelClassName(), $code);
                break;
            case static::MAIN_VIEW_NAME:
                if (empty($code)) {
                    $this->getModel(true);
                    break;
                }

                $view->loadData($code);
                $action = $this->request->input('action', '');

                if ($action === '' && empty($view->model->primaryColumnValue())) {
                    Tools::log()->warning('record-not-found');
                    break;
                }

                $this->title .= ' ' . $view->model->primaryDescription();
                $view->settings['btnPrint'] = true;

                $this->addButton($viewName, [
                    'action' => 'CopyModel?model=' . $this->getModelClassName() . '&code=' . $view->model->primaryColumnValue(),
                    'icon' => 'fa-solid fa-cut',
                    'label' => 'copy',
                    'type' => 'link'
                ]);
                break;
        }
    }

    /**
     * Acción de recálculo del documento.
     *
     * @param bool $renderLines
     * @return bool
     */
    protected function recalculateAction(bool $renderLines): bool
    {
        $this->setTemplate(false);
        $model = $this->getModel();
        $lines = $model->getLines();
        $formData = json_decode($this->request->input('data'), true);

        PurchasesHeaderHTML::apply($model, $formData);
        PurchasesFooterHTML::apply($model, $formData);
        PurchasesLineHTML::apply($model, $lines, $formData);

        Calculator::calculate($model, $lines, false);

        $content = [
            'header' => PurchasesHeaderHTML::render($model),
            'lines' => $renderLines ? PurchasesLineHTML::render($lines, $model) : '',
            'linesMap' => $renderLines ? [] : PurchasesLineHTML::map($lines, $model),
            'footer' => PurchasesFooterHTML::render($model),
            'products' => ''
        ];

        $this->sendJsonResponse($content);
        return false;
    }

    /**
     * Acción para guardar documento.
     *
     * @return bool
     */
    protected function saveDocumentAction(): bool
    {
        $this->setTemplate(false);
        
        if (!$this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            $this->sendJsonResponse(['ok' => false]);
            return false;
        }

        $this->dataBase->beginTransaction();
        $model = $this->getModel();
        $formData = json_decode($this->request->input('data'), true);

        PurchasesHeaderHTML::apply($model, $formData);
        PurchasesFooterHTML::apply($model, $formData);

        if (!$model->save()) {
            $this->sendJsonResponse(['ok' => false]);
            $this->dataBase->rollback();
            return false;
        }

        $lines = $model->getLines();
        PurchasesLineHTML::apply($model, $lines, $formData);
        Calculator::calculate($model, $lines, false);

        foreach ($lines as $line) {
            if (!$line->save()) {
                $this->sendJsonResponse(['ok' => false]);
                $this->dataBase->rollback();
                return false;
            }
        }

        $deletedLines = PurchasesLineHTML::getDeletedLines();
        foreach ($model->getLines() as $oldLine) {
            if (in_array($oldLine->idlinea, $deletedLines) && !$oldLine->delete()) {
                $this->sendJsonResponse(['ok' => false]);
                $this->dataBase->rollback();
                return false;
            }
        }

        $lines = $model->getLines();
        if (!Calculator::calculate($model, $lines, true)) {
            $this->sendJsonResponse(['ok' => false]);
            $this->dataBase->rollback();
            return false;
        }

        $this->sendJsonResponse(['ok' => true, 'newurl' => $model->url() . '&action=save-ok']);
        $this->dataBase->commit();
        return true;
    }

    /**
     * Acción para guardar estado de pago.
     *
     * @return bool
     */
    protected function savePaidAction(): bool
    {
        $this->setTemplate(false);
        
        if (!$this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            $this->sendJsonResponse(['ok' => false]);
            return false;
        }

        if ($this->getModel()->editable && !$this->saveDocumentAction()) {
            $this->sendJsonResponse(['ok' => false]);
            return false;
        }

        $model = $this->getModel();
        $formData = json_decode($this->request->input('data'), true);

        if (empty($model->total) && $model->hasColumn('pagada')) {
            $model->pagada = (bool)($formData['paid-status'] ?? 0);
            $model->save();
            $this->sendJsonResponse(['ok' => true, 'newurl' => $model->url() . '&action=save-ok']);
            return false;
        }

        $receipts = $model->getReceipts();
        if (empty($receipts)) {
            Tools::log()->warning('invoice-has-no-receipts');
            $this->sendJsonResponse(['ok' => false]);
            return false;
        }

        foreach ($receipts as $receipt) {
            $receipt->nick = $this->user->nick;
            
            if (!$receipt->pagado) {
                $receipt->fechapago = $formData['paid-date-modal'] ?? Tools::date();
                $receipt->codpago = $formData['paid-payment-modal'] ?? $model->codpago;
            }
            
            $receipt->pagado = (bool)($formData['paid-status'] ?? 0);
            if (!$receipt->save()) {
                $this->sendJsonResponse(['ok' => false]);
                return false;
            }
        }

        $this->sendJsonResponse(['ok' => true, 'newurl' => $model->url() . '&action=save-ok']);
        return false;
    }

    /**
     * Acción para guardar estado del documento.
     *
     * @return bool
     */
    protected function saveStatusAction(): bool
    {
        $this->setTemplate(false);
        
        if (!$this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            $this->sendJsonResponse(['ok' => false]);
            return false;
        }

        if ($this->getModel()->editable && !$this->saveDocumentAction()) {
            return false;
        }

        $model = $this->getModel();
        $model->idestado = (int)$this->request->input('selectedLine');
        
        if (!$model->save()) {
            $this->sendJsonResponse(['ok' => false]);
            return false;
        }

        $this->sendJsonResponse(['ok' => true, 'newurl' => $model->url() . '&action=save-ok']);
        return false;
    }

    /**
     * Envía respuesta JSON con logs.
     *
     * @param array $data
     */
    private function sendJsonResponse(array $data): void
    {
        $data['messages'] = [];
        $logs = Tools::log()::read('', $this->logLevels);
        
        foreach ($logs as $message) {
            if ($message['channel'] != 'audit') {
                $data['messages'][] = $message;
            }
        }
        
        $this->response->json($data);
    }
}