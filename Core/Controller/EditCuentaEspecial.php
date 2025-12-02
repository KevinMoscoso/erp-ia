<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;

/**
 * Controller to edit a single item from the CuentaEspecial model
 *
 * @author ERPIA Team
 */
class EditCuentaEspecial extends EditController
{
    public function getModelClassName(): string
    {
        return 'CuentaEspecial';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'accounting';
        $data['title'] = 'special-account';
        $data['icon'] = 'fa-solid fa-newspaper';
        return $data;
    }

    protected function createAccountsView(string $viewName = 'ListCuenta'): void
    {
        $this->addListView($viewName, 'Cuenta', 'accounts', 'fa-solid fa-book')
            ->addOrderBy(['codejercicio', 'codcuenta'], 'exercise', 2)
            ->addOrderBy(['descripcion'], 'description')
            ->addSearchFields(['codcuenta', 'descripcion'])
            ->disableColumn('special-account')
            ->setSettings('btnDelete', false)
            ->setSettings('btnNew', false)
            ->setSettings('checkBoxes', false);
    }

    protected function createSubaccountsView(string $viewName = 'ListSubcuenta'): void
    {
        $this->addListView($viewName, 'Subcuenta', 'subaccounts', 'fa-solid fa-th-list')
            ->addOrderBy(['codejercicio', 'codsubcuenta'], 'exercise', 2)
            ->addOrderBy(['descripcion'], 'description')
            ->addSearchFields(['codsubcuenta', 'descripcion'])
            ->disableColumn('special-account')
            ->setSettings('btnDelete', false)
            ->setSettings('btnNew', false)
            ->setSettings('checkBoxes', false);
    }

    protected function createViews(): void
    {
        parent::createViews();

        $mainView = $this->getMainViewName();
        $this->setSettings($mainView, 'btnDelete', false);
        $this->setSettings($mainView, 'btnNew', false);

        $this->setTabsPosition('bottom');
        $this->createAccountsView();
        $this->createSubaccountsView();
    }

    protected function loadData($viewName, $view): void
    {
        switch ($viewName) {
            case 'ListCuenta':
            case 'ListSubcuenta':
                $specialAccountCode = $this->getViewModelValue('EditCuentaEspecial', 'codcuentaesp');
                $where = [new DataBaseWhere('codcuentaesp', $specialAccountCode)];
                $view->loadData('', $where);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}