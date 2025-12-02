<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;
use ERPIA\Core\SystemTools;
use ERPIA\Dinamic\Lib\Accounting\Ledger;
use ERPIA\Dinamic\Model\Cuenta;
use ERPIA\Dinamic\Model\Ejercicio;

/**
 * Controller to edit a single item from the Cuenta model
 *
 * @author ERPIA Team
 */
class EditCuenta extends EditController
{
    /**
     * Returns the model class name
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Cuenta';
    }

    /**
     * Returns page configuration data
     * @return array
     */
    public function getPageData(): array
    {
        $pageConfig = parent::getPageData();
        $pageConfig['menu'] = 'accounting';
        $pageConfig['title'] = 'account';
        $pageConfig['icon'] = 'fa-solid fa-book';
        return $pageConfig;
    }

    /**
     * Creates all views for the controller
     */
    protected function createViews()
    {
        parent::createViews();

        $mainView = $this->getMainViewName();
        $this->tab($mainView)->setSettings('btnPrint', false);
        $this->setTabsPosition('bottom');
        $this->createSubAccountsView();
        $this->createChildAccountsView();
    }

    /**
     * Creates the subaccounts view
     * @param string $viewName
     */
    protected function createSubAccountsView(string $viewName = 'ListSubcuenta'): void
    {
        $this->addListView($viewName, 'Subcuenta', 'subaccounts')
            ->addOrderBy(['codsubcuenta'], 'code', 1)
            ->addOrderBy(['descripcion'], 'description')
            ->addOrderBy(['debe'], 'debit')
            ->addOrderBy(['haber'], 'credit')
            ->addOrderBy(['saldo'], 'balance')
            ->addSearchFields(['codsubcuenta', 'descripcion'])
            ->disableColumn('fiscal-exercise');
    }

    /**
     * Creates the child accounts view
     * @param string $viewName
     */
    protected function createChildAccountsView(string $viewName = 'ListCuenta'): void
    {
        $this->addListView($viewName, 'Cuenta', 'children-accounts', 'fa-solid fa-level-down-alt')
            ->addOrderBy(['codcuenta'], 'code', 1)
            ->disableColumn('fiscal-exercise')
            ->disableColumn('parent-account');
    }

    /**
     * Executes actions before data reading
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        if ($action == 'ledger') {
            if (!$this->permissions->allowExport) {
                SystemTools::log()->warning('no-print-permission');
                return true;
            }

            $accountId = $this->request->query('code');
            if (!empty($accountId)) {
                $this->setTemplate(false);
                $this->generateLedgerReport($accountId);
            }
            return true;
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Generates the ledger report
     * @param int $accountId
     */
    protected function generateLedgerReport(int $accountId): void
    {
        $account = new Cuenta();
        $account->load($accountId);
        $requestData = $this->request->request->all();

        $ledger = new Ledger();
        $reportData = $ledger->generate($account->getExercise()->idempresa, $requestData['dateFrom'], $requestData['dateTo'], [
            'channel' => $requestData['channel'],
            'format' => $requestData['format'],
            'grouped' => $requestData['groupingtype'],
            'account-from' => $account->codcuenta
        ]);

        $reportTitle = SystemTools::trans('ledger') . ' ' . $account->codcuenta;
        $this->exportManager->newDoc($requestData['format'], $reportTitle);
        $this->exportManager->setCompany($account->getExercise()->idempresa);

        if ($requestData['format'] === 'PDF') {
            $headerInfo = [[
                SystemTools::trans('account') => $account->codcuenta,
                SystemTools::trans('exercise') => $account->codejercicio,
                SystemTools::trans('from-date') => $requestData['dateFrom'],
                SystemTools::trans('until-date') => $requestData['dateTo']
            ]];
            $this->exportManager->addTablePage(array_keys($headerInfo[0]), $headerInfo);
        }

        $columnOptions = [
            'debe' => ['display' => 'right', 'css' => 'nowrap'],
            'haber' => ['display' => 'right', 'css' => 'nowrap'],
            'saldo' => ['display' => 'right', 'css' => 'nowrap'],
        ];

        foreach ($reportData as $pageData) {
            $headers = empty($pageData) ? [] : array_keys($pageData[0]);
            $this->exportManager->addTablePage($headers, $pageData, $columnOptions);
        }

        $this->exportManager->show($this->response);
    }

    /**
     * Loads data for each view
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $mainView = $this->getMainViewName();
        $accountId = $this->getViewModelValue($mainView, 'idcuenta');

        switch ($viewName) {
            case 'ListCuenta':
                $conditions = [new DataBaseWhere('parent_idcuenta', $accountId)];
                $view->loadData('', $conditions);
                unset($view->totalAmounts['saldo']);
                break;

            case 'ListSubcuenta':
                $conditions = [new DataBaseWhere('idcuenta', $accountId)];
                $view->loadData('', $conditions);
                if ($view->count == 0) {
                    break;
                }

                unset($view->totalAmounts['saldo']);

                $this->addButton($mainView, [
                    'action' => 'ledger',
                    'color' => 'info',
                    'icon' => 'fa-solid fa-print fa-fw',
                    'label' => 'print',
                    'type' => 'modal'
                ]);
                $this->configureLedgerExportOptions($mainView);
                $this->setDefaultLedgerDates($mainView);
                break;

            case $mainView:
                parent::loadData($viewName, $view);
                if (!$view->model->exists()) {
                    $this->initializeNewAccount($view);
                }
                break;
        }
    }

    /**
     * Initializes a new account with parent's exercise
     * @param BaseView $view
     */
    protected function initializeNewAccount(BaseView $view): void
    {
        $parentAccount = new Cuenta();
        $parentId = $this->request->query('parent_idcuenta', '');
        if (!empty($parentId) && $parentAccount->load($parentId)) {
            $view->model->codejercicio = $parentAccount->codejercicio;
        }
    }

    /**
     * Configures export format options for ledger report
     * @param string $viewName
     */
    private function configureLedgerExportOptions(string $viewName): void
    {
        $formatColumn = $this->views[$viewName]->columnModalForName('format');
        if ($formatColumn && $formatColumn->widget->getType() === 'select') {
            $options = [];
            foreach ($this->exportManager->options() as $key => $details) {
                $options[] = ['title' => $details['description'], 'value' => $key];
            }
            $formatColumn->widget->setValuesFromArray($options, true);
        }
    }

    /**
     * Sets default dates for ledger report based on fiscal exercise
     * @param string $viewName
     */
    private function setDefaultLedgerDates(string $viewName): void
    {
        $exerciseCode = $this->getViewModelValue($viewName, 'codejercicio');
        $exercise = new Ejercicio();
        $exercise->load($exerciseCode);

        $model = $this->views[$viewName]->model;
        $model->dateFrom = $exercise->fechainicio;
        $model->dateTo = $exercise->fechafin;
    }
}