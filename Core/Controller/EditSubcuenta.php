<?php
/**
 * ERPIA - Sistema ERP de Código Abierto
 * Controlador para la edición de subcuentas contables
 * 
 * @package    ERPIA\Core\Controller
 * @copyright  2025 ERPIA Project
 * @license    LGPL 3.0
 */

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;
use ERPIA\Core\Helpers;
use ERPIA\Core\Where;
use ERPIA\Dinamic\Lib\Accounting\GeneralLedgerReport;
use ERPIA\Dinamic\Model\CodeModel;
use ERPIA\Dinamic\Model\Account;
use ERPIA\Dinamic\Model\FiscalYear;
use ERPIA\Dinamic\Model\AccountingEntry;
use ERPIA\Dinamic\Model\Subaccount;

/**
 * Controlador para la edición de un registro del modelo Subcuenta
 */
class EditSubcuenta extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     *
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Subcuenta';
    }

    /**
     * Obtiene los datos de configuración de la página
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageInfo = parent::getPageData();
        $pageInfo['menu'] = 'contabilidad';
        $pageInfo['title'] = 'subcuenta';
        $pageInfo['icon'] = 'fa-solid fa-th-list';
        return $pageInfo;
    }

    /**
     * Crea las vistas del controlador
     */
    protected function createViews(): void
    {
        parent::createViews();
        $this->configureTabPosition('bottom');

        // Establecer límite de registros para el select de cuentas
        CodeModel::setLimit(9999);

        // Ocultar botón de imprimir
        $mainView = $this->getMainViewName();
        $this->tab($mainView)->setOption('btnPrint', false);

        // Crear vista de partidas de asientos
        $this->createAccountingEntriesView();
    }

    /**
     * Crea vista de partidas de asientos
     *
     * @param string $viewName
     */
    protected function createAccountingEntriesView(string $viewName = 'ListPartidaAsiento'): void
    {
        $this->addListView($viewName, 'Join\PartidaAsiento', 'partidas-asientos', 'fa-solid fa-balance-scale')
            ->addOrderBy(['fecha', 'numero', 'idpartida'], 'fecha', 2)
            ->addSearchFields(['partidas.concepto'])
            ->disableColumn('subcuenta')
            ->setOption('btnDelete', false);

        $vatOptions = $this->codeModel->getAll('partidas', 'iva', 'iva');

        // Filtros
        $this->listView($viewName)
            ->addFilterPeriod('date', 'fecha', 'fecha')
            ->addFilterSelect('iva', 'iva', 'iva', $vatOptions)
            ->addFilterCheckbox('no-iva', 'sin-iva', 'iva', 'IS', null)
            ->addFilterNumber('debe-mayor', 'debe', 'debe')
            ->addFilterNumber('debe-menor', 'debe', 'debe', '<=')
            ->addFilterNumber('haber-mayor', 'haber', 'haber')
            ->addFilterNumber('haber-menor', 'haber', 'haber', '<=');

        // Botones
        $this->addCustomButton($viewName, [
            'action' => 'dot-accounting-on',
            'color' => 'info',
            'icon' => 'fa-solid fa-check-double',
            'label' => 'marcar-saldadas'
        ]);
        $this->addCustomButton($viewName, [
            'action' => 'dot-accounting-off',
            'color' => 'warning',
            'icon' => 'fa-regular fa-square',
            'label' => 'desmarcar-saldadas'
        ]);
    }

    /**
     * Ejecuta acciones previas personalizadas
     *
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction(string $action): bool
    {
        switch ($action) {
            case 'ledger':
                if ($this->userPermissions->allowExport === false) {
                    Helpers::logWarning('no-permisos-exportacion');
                    return true;
                }

                $subaccountCode = (int)$this->request->get('code');
                if (!empty($subaccountCode)) {
                    $this->disableTemplate();
                    $this->generateLedgerReport($subaccountCode);
                }
                return true;

            case 'dot-accounting-off':
                return $this->updateDottedStatus(false);

            case 'dot-accounting-on':
                return $this->updateDottedStatus(true);
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Genera el informe de mayor
     *
     * @param int $subaccountId
     */
    protected function generateLedgerReport(int $subaccountId): void
    {
        $subaccount = new Subaccount();
        $subaccount->load($subaccountId);
        $requestData = $this->request->all();

        $ledgerGenerator = new GeneralLedgerReport();
        $reportPages = $ledgerGenerator->generate(
            $subaccount->getFiscalYear()->idempresa,
            $requestData['dateFrom'],
            $requestData['dateTo'],
            [
                'canal' => $requestData['channel'],
                'formato' => $requestData['format'],
                'agrupado' => $requestData['groupingtype'] ?? false,
                'subcuenta-desde' => $subaccount->codsubcuenta
            ]
        );

        $reportTitle = Helpers::translate('libro-mayor') . ' ' . $subaccount->codsubcuenta;
        $this->exportManager->createDocument($requestData['format'], $reportTitle);
        $this->exportManager->setCompany($subaccount->getFiscalYear()->idempresa);

        // Añadir tabla de cabecera en formato PDF
        if ($requestData['format'] === 'PDF') {
            $headerData = [[
                Helpers::translate('subcuenta') => $subaccount->codsubcuenta,
                Helpers::translate('ejercicio') => $subaccount->codejercicio,
                Helpers::translate('fecha-desde') => $requestData['dateFrom'],
                Helpers::translate('fecha-hasta') => $requestData['dateTo']
            ]];
            $this->exportManager->addTablePage(array_keys($headerData[0]), $headerData);
        }

        // Añadir tablas con los listados
        $columnOptions = [
            'debe' => ['align' => 'right', 'css' => 'nowrap'],
            'haber' => ['align' => 'right', 'css' => 'nowrap'],
            'saldo' => ['align' => 'right', 'css' => 'nowrap'],
        ];

        foreach ($reportPages as $pageData) {
            $headers = empty($pageData) ? [] : array_keys($pageData[0]);
            $this->exportManager->addTablePage($headers, $pageData, $columnOptions);
        }

        $this->exportManager->outputDocument($this->response);
    }

    /**
     * Carga datos en una vista específica
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData(string $viewName, BaseView $view): void
    {
        $mainView = $this->getMainViewName();

        switch ($viewName) {
            case 'ListPartidaAsiento':
                $subaccountId = $this->getViewModelValue($mainView, 'idsubcuenta');
                $filterCondition = [new DataBaseWhere('idsubcuenta', $subaccountId)];
                $view->loadData('', $filterCondition);

                if ($view->count === 0) {
                    break;
                }

                // Ocultar columna de saldo en los totales
                unset($view->totals['saldo']);

                // Añadir botón para informe de mayor
                $this->addCustomButton($mainView, [
                    'action' => 'ledger',
                    'color' => 'info',
                    'icon' => 'fa-solid fa-book fa-fw',
                    'label' => 'libro-mayor',
                    'type' => 'modal'
                ]);

                $this->configureLedgerExportOptions($mainView);
                $this->setLedgerDefaultValues($mainView);
                break;

            case $mainView:
                parent::loadData($viewName, $view);
                if ($view->model->exists() === false) {
                    $this->initializeSubaccount($view);
                }
                break;
        }
    }

    /**
     * Inicializa una nueva subcuenta
     *
     * @param BaseView $view
     */
    protected function initializeSubaccount(BaseView $view): void
    {
        $account = new Account();
        $accountId = $this->request->get('idcuenta', '');

        if (!empty($accountId) && $account->load($accountId)) {
            $view->model->codcuenta = $account->codcuenta;
            $view->model->codejercicio = $account->codejercicio;
            $view->model->idcuenta = $account->idcuenta;
        }
    }

    /**
     * Actualiza el estado de saldado de las partidas
     *
     * @param bool $newStatus
     * @return bool
     */
    private function updateDottedStatus(bool $newStatus): bool
    {
        $selectedIds = $this->request->getArray('codes');
        if (empty($selectedIds)) {
            Helpers::logWarning('ningun-item-seleccionado');
            return true;
        }

        $filterCondition = [Where::in('idpartida', $selectedIds)];
        foreach (AccountingEntry::getAll($filterCondition) as $entry) {
            $entry->setDottedStatus($newStatus);
        }

        Helpers::logNotice('registros-actualizados-correctamente');
        return true;
    }

    /**
     * Configura las opciones de exportación para el informe de mayor
     *
     * @param string $viewName
     */
    private function configureLedgerExportOptions(string $viewName): void
    {
        $formatColumn = $this->views[$viewName]->getModalColumn('format');
        
        if ($formatColumn && $formatColumn->widget->getType() === 'select') {
            $exportOptions = [];
            foreach ($this->exportManager->getAvailableFormats() as $formatKey => $formatInfo) {
                $exportOptions[] = ['title' => $formatInfo['description'], 'value' => $formatKey];
            }
            $formatColumn->widget->setOptionsFromArray($exportOptions, true);
        }
    }

    /**
     * Establece los valores por defecto para el informe de mayor
     *
     * @param string $viewName
     */
    private function setLedgerDefaultValues(string $viewName): void
    {
        $fiscalYearCode = $this->getViewModelValue($viewName, 'codejercicio');
        $fiscalYear = new FiscalYear();
        $fiscalYear->load($fiscalYearCode);

        $currentModel = $this->views[$viewName]->model;
        $currentModel->dateFrom = $fiscalYear->fechainicio;
        $currentModel->dateTo = $fiscalYear->fechafin;
    }
}