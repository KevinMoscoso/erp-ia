<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;

/**
 * Controller to edit a single item from the CuentaBanco model
 *
 * @author ERPIA Team
 */
class EditCuentaBanco extends EditController
{
    /**
     * Returns the model class name
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'CuentaBanco';
    }

    /**
     * Returns page configuration data
     * @return array
     */
    public function getPageData(): array
    {
        $pageConfig = parent::getPageData();
        $pageConfig['menu'] = 'accounting';
        $pageConfig['title'] = 'bank-account';
        $pageConfig['icon'] = 'fa-solid fa-piggy-bank';
        return $pageConfig;
    }

    /**
     * Creates the subaccounting view
     * @param string $viewName
     */
    protected function createSubAccountingView(string $viewName = 'ListSubcuenta'): void
    {
        $this->addListView($viewName, 'Subcuenta', 'subaccounts', 'fa-solid fa-book')
            ->addOrderBy(['codejercicio'], 'exercise', 2)
            ->setSettings('btnNew', false);
    }

    /**
     * Creates all views for the controller
     */
    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        // Disable company column if only one company exists
        if ($this->empresa->count() < 2) {
            $this->views[$this->getMainViewName()]->disableColumn('company');
        }

        $this->createSubAccountingView();
    }

    /**
     * Loads data for each view
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'ListSubcuenta':
                $exercises = implode(',', $this->getCompanyExercises());
                $mainSubAccount = $this->getViewModelValue($this->getMainViewName(), 'codsubcuenta');
                $conditions = [
                    new DataBaseWhere('codejercicio', $exercises, 'IN'),
                    new DataBaseWhere('codsubcuenta', $mainSubAccount),
                ];
                $expenseSubAccount = $this->getViewModelValue($this->getMainViewName(), 'codsubcuentagasto');
                if ($expenseSubAccount && $expenseSubAccount != $mainSubAccount) {
                    $conditions[] = new DataBaseWhere('codsubcuenta', $expenseSubAccount, '=', 'OR');
                }
                $view->loadData('', $conditions, ['codejercicio' => 'DESC']);
                unset($view->totalAmounts['saldo']);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    /**
     * Returns the list of exercises for the selected company
     * @return array
     */
    private function getCompanyExercises(): array
    {
        $exerciseList = [];
        $companyId = $this->getViewModelValue($this->getMainViewName(), 'idempresa');
        $filters = [new DataBaseWhere('idempresa', $companyId)];
        $exerciseData = $this->codeModel->all('ejercicios', 'codejercicio', 'codejercicio', false, $filters);
        foreach ($exerciseData as $row) {
            $exerciseList[] = $row->code;
        }
        return $exerciseList;
    }
}