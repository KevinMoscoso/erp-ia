<?php

namespace ERPIA\Core\Base\AjaxForms;

use ERPIA\Core\Base\Calculator;
use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\DataSrc\Series;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\DocFilesTrait;
use ERPIA\Core\Lib\ExtendedController\LogAuditTrait;
use ERPIA\Core\Lib\ExtendedController\PanelController;
use ERPIA\Core\Model\Base\PurchaseDocument;
use ERPIA\Core\Model\Base\PurchaseDocumentLine;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Lib\AssetManager;
use ERPIA\Dinamic\Model\Proveedor;
use ERPIA\Dinamic\Model\Variante;

/**
 * PurchasesController (compatibility layer)
 *
 * Controller that exposes the same integration points and behavior expected
 * by the UI and other modules. It mirrors the original controller logic
 * while keeping method signatures and integration hooks intact.
 *
 * @deprecated replaced by Core/Lib/AjaxForms/PurchasesController
 */
abstract class PurchasesController extends PanelController
{
    use DocFilesTrait;
    use LogAuditTrait;

    const MAIN_VIEW_NAME = 'main';
    const MAIN_VIEW_TEMPLATE = 'Tab/PurchasesDocument';

    /** @var string[] */
    private $logLevels = ['critical', 'error', 'info', 'notice', 'warning'];

    abstract public function getModelClassName();

    /**
     * Returns the current model instance, optionally reloading defaults.
     *
     * @param bool $reload
     * @return PurchaseDocument
     */
    public function getModel(bool $reload = false): PurchaseDocument
    {
        if ($reload) {
            $this->views[static::MAIN_VIEW_NAME]->model->clear();
        }

        // If already loaded, return it
        if ($this->views[static::MAIN_VIEW_NAME]->model->primaryColumnValue()) {
            return $this->views[static::MAIN_VIEW_NAME]->model;
        }

        $code = $this->request->get('code');
        if (empty($code)) {
            // New record: apply initial parameters from query
            $formData = $this->request->query->all();
            PurchasesHeaderHTML::apply($this->views[static::MAIN_VIEW_NAME]->model, $formData, $this->user);
            return $this->views[static::MAIN_VIEW_NAME]->model;
        }

        // Existing record: load by code
        $this->views[static::MAIN_VIEW_NAME]->model->loadFromCode($code);
        return $this->views[static::MAIN_VIEW_NAME]->model;
    }

    /**
     * Render full purchases form (header, lines, footer and modals).
     *
     * @param PurchaseDocument $model
     * @param PurchaseDocumentLine[] $lines
     * @return string
     */
    public function renderPurchasesForm(PurchaseDocument $model, array $lines): string
    {
        $url = empty($model->primaryColumnValue()) ? $this->url() : $model->url();

        return '<div id="purchasesFormHeader">' . PurchasesHeaderHTML::render($model) . '</div>'
            . '<div id="purchasesFormLines">' . PurchasesLineHTML::render($lines, $model) . '</div>'
            . '<div id="purchasesFormFooter">' . PurchasesFooterHTML::render($model) . '</div>'
            . PurchasesModalHTML::render($model, $url);
    }

    /**
     * Return series list optionally filtered by type.
     *
     * @param string $type
     * @return array
     */
    public function series(string $type = ''): array
    {
        if (empty($type)) {
            return Series::all();
        }

        $list = [];
        foreach (Series::all() as $serie) {
            if ($serie->tipo == $type) {
                $list[] = $serie;
            }
        }

        return $list;
    }

    /**
     * Autocomplete action for product references (AJAX).
     *
     * @return bool
     */
    protected function autocompleteProductAction(): bool
    {
        $this->setTemplate(false);

        $list = [];
        $variante = new Variante();
        $query = (string)$this->request->get('term');
        $where = [
            new DataBaseWhere('p.bloqueado', 0),
            new DataBaseWhere('p.secompra', 1)
        ];

        foreach ($variante->codeModelSearch($query, 'referencia', $where) as $value) {
            $list[] = [
                'key' => Tools::fixHtml($value->code),
                'value' => Tools::fixHtml($value->description)
            ];
        }

        if (empty($list)) {
            $list[] = ['key' => null, 'value' => Tools::lang()->trans('no-data')];
        }

        $this->response->setContent(json_encode($list));
        return false;
    }

    /**
     * Create views and register assets.
     */
    protected function createViews()
    {
        $this->setTabsPosition('top');
        $this->createViewsDoc();
        $this->createViewDocFiles();
        $this->createViewLogAudit();
    }

    /**
     * Create document views and enqueue assets required by the UI.
     */
    protected function createViewsDoc()
    {
        $pageData = $this->getPageData();
        $this->addHtmlView(static::MAIN_VIEW_NAME, static::MAIN_VIEW_TEMPLATE, $this->getModelClassName(), $pageData['title'], 'fas fa-file');

        // UI helpers
        AssetManager::addCss(FS_ROUTE . '/node_modules/jquery-ui-dist/jquery-ui.min.css', 2);
        AssetManager::addJs(FS_ROUTE . '/node_modules/jquery-ui-dist/jquery-ui.min.js', 2);

        // Ensure HTML helpers register their assets
        PurchasesHeaderHTML::assets();
        PurchasesLineHTML::assets();
        PurchasesFooterHTML::assets();
    }

    /**
     * Delete document action (AJAX).
     *
     * @return bool
     */
    protected function deleteDocAction(): bool
    {
        $this->setTemplate(false);

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
     * Route previous action to the corresponding handler.
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
     * Export action wrapper.
     */
    protected function exportAction()
    {
        $this->setTemplate(false);

        $subjectLang = $this->views[static::MAIN_VIEW_NAME]->model->getSubject()->langcode;
        $requestLang = $this->request->request->get('langcode');
        $langCode = $requestLang ?? $subjectLang ?? '';

        $this->exportManager->newDoc(
            $this->request->get('option', ''),
            $this->title,
            (int)$this->request->request->get('idformat', ''),
            $langCode
        );
        $this->exportManager->addBusinessDocPage($this->views[static::MAIN_VIEW_NAME]->model);
        $this->exportManager->show($this->response);
    }

    /**
     * Find supplier action (AJAX).
     *
     * @return bool
     */
    protected function findSupplierAction(): bool
    {
        $this->setTemplate(false);
        $supplier = new Proveedor();
        $list = [];
        $term = $this->request->get('term');

        foreach ($supplier->codeModelSearch($term) as $item) {
            $list[$item->code] = $item->code . ' | ' . Tools::fixHtml($item->description);
        }

        $this->response->setContent(json_encode($list));
        return false;
    }

    /**
     * Find product action used by modal (AJAX).
     *
     * @return bool
     */
    protected function findProductAction(): bool
    {
        $this->setTemplate(false);
        $model = $this->getModel();
        $formData = json_decode($this->request->request->get('data'), true);

        // Apply header/footer/modal temporary changes from client
        PurchasesHeaderHTML::apply($model, $formData, $this->user);
        PurchasesFooterHTML::apply($model, $formData, $this->user);
        PurchasesModalHTML::apply($model, $formData);

        $content = [
            'header' => '',
            'lines' => '',
            'linesMap' => [],
            'footer' => '',
            'products' => PurchasesModalHTML::renderProductList()
        ];

        $this->sendJsonWithLogs($content);
        return false;
    }

    /**
     * Load data for views (docfiles, logs, main).
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $code = $this->request->get('code');

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

                $view->loadData($code);
                $action = $this->request->request->get('action', '');
                if ('' === $action && empty($view->model->primaryColumnValue())) {
                    Tools::log()->warning('record-not-found');
                    break;
                }

                $this->title .= ' ' . $view->model->primaryDescription();
                $view->settings['btnPrint'] = true;
                $this->addButton($viewName, [
                    'action' => 'CopyModel?model=' . $this->getModelClassName() . '&code=' . $view->model->primaryColumnValue(),
                    'icon' => 'fas fa-cut',
                    'label' => 'copy',
                    'type' => 'link'
                ]);
                break;
        }
    }

    /**
     * Recalculate action used for global and per-line recalculations.
     *
     * @param bool $renderLines
     * @return bool
     */
    protected function recalculateAction(bool $renderLines): bool
    {
        $this->setTemplate(false);
        $model = $this->getModel();
        $lines = $model->getLines();
        $formData = json_decode($this->request->request->get('data'), true);

        // Apply temporary client changes
        PurchasesHeaderHTML::apply($model, $formData, $this->user);
        PurchasesFooterHTML::apply($model, $formData, $this->user);
        PurchasesLineHTML::apply($model, $lines, $formData);

        // Perform calculation (server-side authoritative)
        Calculator::calculate($model, $lines, false);

        $content = [
            'header' => PurchasesHeaderHTML::render($model),
            'lines' => $renderLines ? PurchasesLineHTML::render($lines, $model) : '',
            'linesMap' => $renderLines ? [] : PurchasesLineHTML::map($lines, $model),
            'footer' => PurchasesFooterHTML::render($model),
            'products' => ''
        ];

        $this->sendJsonWithLogs($content);
        return false;
    }

    /**
     * Save document action with transactional safety.
     *
     * @return bool
     */
    protected function saveDocAction(): bool
    {
        $this->setTemplate(false);

        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            $this->sendJsonWithLogs(['ok' => false]);
            return false;
        }

        $this->dataBase->beginTransaction();

        $model = $this->getModel();
        $formData = json_decode($this->request->request->get('data'), true);

        PurchasesHeaderHTML::apply($model, $formData, $this->user);
        PurchasesFooterHTML::apply($model, $formData, $this->user);

        if (false === $model->save()) {
            $this->sendJsonWithLogs(['ok' => false]);
            $this->dataBase->rollback();
            return false;
        }

        $lines = $model->getLines();
        PurchasesLineHTML::apply($model, $lines, $formData);

        // Recalculate before saving lines
        Calculator::calculate($model, $lines, false);

        foreach ($lines as $line) {
            if (false === $line->save()) {
                $this->sendJsonWithLogs(['ok' => false]);
                $this->dataBase->rollback();
                return false;
            }
        }

        // Remove deleted lines
        foreach ($model->getLines() as $oldLine) {
            if (in_array($oldLine->idlinea, PurchasesLineHTML::getDeletedLines()) && false === $oldLine->delete()) {
                $this->sendJsonWithLogs(['ok' => false]);
                $this->dataBase->rollback();
                return false;
            }
        }

        // Final calculation (with stock/ledger effects if required)
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
     * Mark as paid action (handles receipts and zero-total documents).
     *
     * @return bool
     */
    protected function savePaidAction(): bool
    {
        $this->setTemplate(false);

        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            $this->sendJsonWithLogs(['ok' => false]);
            return false;
        }

        // Ensure document saved if editable
        if ($this->getModel()->editable && false === $this->saveDocAction()) {
            return false;
        }

        $model = $this->getModel();

        // If total is zero and model supports paid flag, toggle it
        if (empty($model->total) && property_exists($model, 'pagada')) {
            $model->pagada = (bool)$this->request->request->get('selectedLine');
            $model->save();
            $this->sendJsonWithLogs(['ok' => true, 'newurl' => $model->url() . '&action=save-ok']);
            return false;
        }

        // Work with receipts
        $receipts = $model->getReceipts();
        if (empty($receipts)) {
            Tools::log()->warning('invoice-has-no-receipts');
            $this->sendJsonWithLogs(['ok' => false]);
            return false;
        }

        $formData = json_decode($this->request->request->get('data'), true);
        foreach ($receipts as $receipt) {
            $receipt->nick = $this->user->nick;
            if (false == $receipt->pagado) {
                $receipt->fechapago = $formData['fechapagorecibo'] ?? Tools::date();
                $receipt->codpago = $model->codpago;
            }
            $receipt->pagado = (bool)$this->request->request->get('selectedLine');
            if (false === $receipt->save()) {
                $this->sendJsonWithLogs(['ok' => false]);
                return false;
            }
        }

        $this->sendJsonWithLogs(['ok' => true, 'newurl' => $model->url() . '&action=save-ok']);
        return false;
    }

    /**
     * Change document status action.
     *
     * @return bool
     */
    protected function saveStatusAction(): bool
    {
        $this->setTemplate(false);

        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            $this->sendJsonWithLogs(['ok' => false]);
            return false;
        }

        if ($this->getModel()->editable && false === $this->saveDocAction()) {
            return false;
        }

        $model = $this->getModel();
        $model->idestado = (int)$this->request->request->get('selectedLine');

        if (false === $model->save()) {
            $this->sendJsonWithLogs(['ok' => false]);
            return false;
        }

        $this->sendJsonWithLogs(['ok' => true, 'newurl' => $model->url() . '&action=save-ok']);
        return false;
    }

    /**
     * Send JSON response enriched with recent logs (non-audit).
     *
     * @param array $data
     */
    private function sendJsonWithLogs(array $data): void
    {
        $data['messages'] = [];
        foreach (Tools::log()::read('', $this->logLevels) as $message) {
            if ($message['channel'] != 'audit') {
                $data['messages'][] = $message;
            }
        }

        $this->response->setContent(json_encode($data));
    }
}