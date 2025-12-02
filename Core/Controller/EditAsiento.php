<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Lib\AjaxForms\AccountingFooterHTML;
use ERPIA\Core\Lib\AjaxForms\AccountingHeaderHTML;
use ERPIA\Core\Lib\AjaxForms\AccountingLineHTML;
use ERPIA\Core\Lib\AjaxForms\AccountingModalHTML;
use ERPIA\Core\Lib\Export\AsientoExport;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\DocFilesTrait;
use ERPIA\Core\Lib\ExtendedController\LogAuditTrait;
use ERPIA\Core\SystemTools;
use ERPIA\Dinamic\Lib\AssetManager;
use ERPIA\Dinamic\Lib\ExtendedController\PanelController;
use ERPIA\Dinamic\Model\Asiento;
use ERPIA\Dinamic\Model\Partida;

/**
 * Description of EditAsiento
 *
 * @author ERPIA Team
 */
class EditAsiento extends PanelController
{
    use DocFilesTrait;
    use LogAuditTrait;

    const MAIN_VIEW_NAME = 'main';
    const MAIN_VIEW_TEMPLATE = 'Tab/AccountingEntry';

    /** @var array */
    private $logLevels = ['critical', 'error', 'info', 'notice', 'warning'];

    /**
     * Gets the main model and loads the data based on the primary key.
     *
     * @return Asiento
     */
    public function getModel(): Asiento
    {
        // Check if model is already loaded
        if ($this->views[static::MAIN_VIEW_NAME]->model->id()) {
            return $this->views[static::MAIN_VIEW_NAME]->model;
        }

        // Get the record identifier
        $primaryKeyColumn = $this->views[static::MAIN_VIEW_NAME]->model->primaryColumn();
        $primaryKeyValue = $this->request->input($primaryKeyColumn);
        $recordCode = $this->request->query('code', $primaryKeyValue);
        
        if (empty($recordCode)) {
            return $this->views[static::MAIN_VIEW_NAME]->model;
        }

        $this->views[static::MAIN_VIEW_NAME]->model->load($recordCode);
        return $this->views[static::MAIN_VIEW_NAME]->model;
    }

    /**
     * Returns the model class name
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Asiento';
    }

    /**
     * Returns page configuration data
     * @return array
     */
    public function getPageData(): array
    {
        $pageConfig = parent::getPageData();
        $pageConfig['menu'] = 'accounting';
        $pageConfig['title'] = 'accounting-entry';
        $pageConfig['icon'] = 'fa-solid fa-balance-scale';
        $pageConfig['showonmenu'] = false;
        return $pageConfig;
    }

    /**
     * Gets the HTML code to render the main form.
     *
     * @param Asiento $model
     * @param Partida[] $lines
     *
     * @return string
     */
    public function renderAccEntryForm(Asiento $model, array $lines): string
    {
        AccountingLineHTML::calculateUnbalance($model, $lines);
        return '<div id="accEntryFormHeader">' . AccountingHeaderHTML::render($model) . '</div>'
            . '<div id="accEntryFormLines">' . AccountingLineHTML::render($lines, $model) . '</div>'
            . '<div id="accEntryFormFooter">' . AccountingFooterHTML::render($model) . '</div>'
            . AccountingModalHTML::render($model);
    }

    /**
     * Apply the changes made to the form to the models.
     *
     * @param Asiento $model
     * @param Partida[] $lines
     * @param bool $applyModal
     */
    private function applyMainFormData(Asiento &$model, array &$lines, bool $applyModal = false): void
    {
        $formData = json_decode($this->request->input('data'), true);
        AccountingHeaderHTML::apply($model, $formData);
        AccountingFooterHTML::apply($model, $formData);
        AccountingLineHTML::apply($model, $lines, $formData);
        if ($applyModal) {
            AccountingModalHTML::apply($model, $formData);
        }
    }

    /**
     * Creates all views for the controller
     */
    protected function createViews()
    {
        $this->setTabsPosition('top');
        $this->createMainView();
        $this->createViewDocFiles();
        $this->createViewLogAudit();
    }

    /**
     * Add main view (Accounting)
     */
    private function createMainView(): void
    {
        $this->addHtmlView(
            static::MAIN_VIEW_NAME,
            static::MAIN_VIEW_TEMPLATE,
            $this->getModelClassName(),
            'accounting-entry',
            'fa-solid fa-balance-scale'
        );

        $this->setSettings(static::MAIN_VIEW_NAME, 'btnPrint', true);
        $appRoute = SystemTools::config('route');
        AssetManager::addCss($appRoute . '/node_modules/jquery-ui-dist/jquery-ui.min.css', 2);
        AssetManager::addJs($appRoute . '/node_modules/jquery-ui-dist/jquery-ui.min.js', 2);
        AssetManager::addJs($appRoute . '/Dinamic/Assets/JS/WidgetAutocomplete.js');
    }

    /**
     * Unlink the main model.
     *
     * @return bool
     */
    protected function deleteDocAction(): bool
    {
        $this->setTemplate(false);
        if (!$this->permissions->allowDelete) {
            SystemTools::log()->warning('not-allowed-delete');
            return $this->sendJsonError();
        } elseif (!$this->validateFileActionToken()) {
            return $this->sendJsonError();
        }

        $model = $this->getModel();
        if (!$model->delete()) {
            return $this->sendJsonError();
        }

        $this->response->json(['ok' => true, 'newurl' => $model->url('list')]);
        return false;
    }

    /**
     * Run the actions that alter data before reading it.
     *
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'add-file':
                return $this->addFileAction();

            case 'delete-file':
                return $this->deleteFileAction();

            case 'delete-doc':
                return $this->deleteDocAction();

            case 'edit-file':
                return $this->editFileAction();

            case 'find-subaccount':
                return $this->findSubaccountAction();

            case 'lock-doc':
                return $this->unlockAction(false);

            case 'new-line':
            case 'rm-line':
            case 'recalculate':
                return $this->recalculateAction($action != 'recalculate');

            case 'save-doc':
                return $this->saveDocAction();

            case 'unlink-file':
                return $this->unlinkFileAction();

            case 'unlock-doc':
                return $this->unlockAction(true);
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Handles export action
     */
    protected function exportAction()
    {
        if (!$this->views[$this->active]->settings['btnPrint'] || !$this->permissions->allowExport) {
            SystemTools::log()->warning('no-print-permission');
            return;
        }

        $this->setTemplate(false);
        AsientoExport::show(
            $this->getModel(),
            $this->request->queryOrInput('option', ''),
            $this->title,
            (int)$this->request->input('idformat', ''),
            $this->request->input('langcode', ''),
            $this->response
        );
    }

    /**
     * Recalculate the list of ledger subaccounts.
     *
     * @return bool
     */
    protected function findSubaccountAction(): bool
    {
        $this->setTemplate(false);
        $model = $this->getModel();
        $lines = [];
        $this->applyMainFormData($model, $lines, true);
        $content = [
            'header' => '',
            'lines' => '',
            'footer' => '',
            'list' => AccountingModalHTML::renderSubaccountList($model),
            'messages' => SystemTools::log()::read('', $this->logLevels)
        ];
        $this->response->json($content);
        return false;
    }

    /**
     * Load the data from the indicated view.
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $primaryKeyColumn = $view->model->primaryColumn();
        $primaryKeyValue = $this->request->input($primaryKeyColumn);
        $recordCode = $this->request->query('code', $primaryKeyValue);

        switch ($viewName) {
            case 'docfiles':
                $this->loadDataDocFiles($view, $this->getModelClassName(), $recordCode);
                break;

            case 'ListLogMessage':
                $this->loadDataLogAudit($view, $this->getModelClassName(), $recordCode);
                break;

            case static::MAIN_VIEW_NAME:
                if (empty($recordCode)) {
                    $view->model->clear();
                    break;
                }

                $view->loadData($recordCode);
                $actionParam = $this->request->input('action', '');
                if ('' === $actionParam && !$view->model->exists()) {
                    SystemTools::log()->warning('record-not-found');
                    break;
                }

                if (!$view->model->isBalanced()) {
                    SystemTools::log()->warning('unbalanced-entry');
                    break;
                }

                $this->title .= ' ' . $view->model->primaryDescription();
                $this->addButton($viewName, [
                    'action' => 'CopyModel?model=' . $this->getModelClassName() . '&code=' . $view->model->id(),
                    'icon' => 'fa-solid fa-cut',
                    'label' => 'copy',
                    'type' => 'link'
                ]);
                break;
        }
    }

    /**
     * Recalculate the models and get the new html code
     *
     * @param bool $renderLines
     *
     * @return bool
     */
    protected function recalculateAction(bool $renderLines): bool
    {
        $this->setTemplate(false);
        $model = $this->getModel();
        $lines = $model->getLines();
        $this->applyMainFormData($model, $lines);
        $content = [
            'header' => AccountingHeaderHTML::render($model),
            'lines' => $renderLines ? AccountingLineHTML::render($lines, $model) : '',
            'footer' => AccountingFooterHTML::render($model),
            'list' => '',
            'messages' => SystemTools::log()::read('', $this->logLevels)
        ];
        $this->response->json($content);
        return false;
    }

    /**
     * Save the data in the database.
     *
     * @return bool
     */
    protected function saveDocAction(): bool
    {
        $this->setTemplate(false);
        if (!$this->permissions->allowUpdate) {
            SystemTools::log()->warning('not-allowed-modify');
            return $this->sendJsonError();
        }

        $this->dataBase->beginTransaction();
        $model = $this->getModel();
        $lines = $model->getLines();
        $this->applyMainFormData($model, $lines);
        
        if (!$model->save()) {
            $this->dataBase->rollback();
            return $this->sendJsonError();
        }

        foreach ($lines as $line) {
            $line->idasiento = $line->idasiento ?? $model->idasiento;
            if (!$line->save()) {
                $this->dataBase->rollback();
                return $this->sendJsonError();
            }
        }

        foreach ($model->getLines() as $oldLine) {
            if (in_array($oldLine->idpartida, AccountingLineHTML::getDeletedLines()) && !$oldLine->delete()) {
                $this->dataBase->rollback();
                return $this->sendJsonError();
            }
        }

        $this->response->json(['ok' => true, 'newurl' => $model->url() . '&action=save-ok']);
        $this->dataBase->commit();
        return false;
    }

    /**
     * Send JSON error response
     * @return bool
     */
    protected function sendJsonError(): bool
    {
        $this->response->json(['ok' => false, 'messages' => SystemTools::log()::read('', $this->logLevels)]);
        return false;
    }

    /**
     * Lock or unlock the document
     * @param bool $value
     * @return bool
     */
    protected function unlockAction(bool $value): bool
    {
        $this->setTemplate(false);
        if (!$this->permissions->allowUpdate) {
            SystemTools::log()->warning('not-allowed-modify');
            return $this->sendJsonError();
        } elseif (!$this->validateFileActionToken()) {
            return $this->sendJsonError();
        }

        $model = $this->getModel();
        $model->editable = $value;
        if (!$model->save()) {
            return $this->sendJsonError();
        }

        $this->response->json(['ok' => true, 'newurl' => $model->url() . '&action=save-ok']);
        return false;
    }
}