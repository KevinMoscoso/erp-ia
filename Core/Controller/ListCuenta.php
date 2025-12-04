<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2017-2024 ERPIA Contributors
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

namespace ERPIA\Core\Controller;

use ERPIA\Core\DataSrc\Ejercicios;
use ERPIA\Core\Lib\ExtendedController\ListController;
use ERPIA\Core\Lib\Import\CSVImporter;
use ERPIA\Core\Model\CuentaEspecial;
use ERPIA\Core\App\Logger;

/**
 * Controller to list the items in the Cuenta model.
 *
 * @author ERPIA Contributors
 */
class ListCuenta extends ListController
{
    /**
     * Returns page configuration data
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'accounting';
        $pageData['title'] = 'accounting-accounts';
        $pageData['icon'] = 'fa-solid fa-book';
        return $pageData;
    }

    /**
     * Create and configure the views
     */
    protected function createViews()
    {
        $this->createViewsSubaccounts();
        $this->createViewsAccounts();
        $this->createViewsSpecialAccounts();
    }

    /**
     * Creates and configures the accounts view
     *
     * @param string $viewName
     */
    protected function createViewsAccounts(string $viewName = 'ListCuenta'): void
    {
        // Add the main accounts view
        $this->addView($viewName, 'Cuenta', 'accounts', 'fa-solid fa-book');
        
        // Configure search fields
        $this->addSearchFields(['descripcion', 'codcuenta', 'codejercicio', 'codcuentaesp']);
        
        // Configure default order
        $this->addOrderBy(['codejercicio desc, codcuenta'], 'code');
        $this->addOrderBy(['codejercicio desc, descripcion'], 'description');

        // Filters
        $this->addFilterNumber('debit-major', 'debit', 'debe', '>=');
        $this->addFilterNumber('debit-minor', 'debit', 'debe', '<=');
        $this->addFilterNumber('credit-major', 'credit', 'haber', '>=');
        $this->addFilterNumber('credit-minor', 'credit', 'haber', '<=');
        $this->addFilterNumber('balance-major', 'balance', 'saldo', '>=');
        $this->addFilterNumber('balance-minor', 'balance', 'saldo', '<=');
        
        // Exercise filter
        $this->addFilterSelect($viewName, 'codejercicio', 'exercise', 'codejercicio', Ejercicios::codeModel());

        // Special account filter
        $specialAccounts = $this->codeModel->getAll('cuentasesp', 'codcuentaesp', 'codcuentaesp');
        $this->addFilterSelect($viewName, 'codcuentaesp', 'special-account', 'codcuentaesp', $specialAccounts);
    }

    /**
     * Creates and configures the special accounts view
     *
     * @param string $viewName
     */
    protected function createViewsSpecialAccounts(string $viewName = 'ListCuentaEspecial'): void
    {
        // Add special accounts view
        $this->addView($viewName, 'CuentaEspecial', 'special-accounts', 'fa-solid fa-newspaper');
        
        // Configure search fields
        $this->addSearchFields(['descripcion', 'codcuentaesp']);
        
        // Configure default order
        $this->addOrderBy(['codcuentaesp'], 'code', 1);
        $this->addOrderBy(['descripcion'], 'description');

        // Disable buttons and checkboxes
        $this->tab($viewName)
            ->setSettings('btnDelete', false)
            ->setSettings('btnNew', false)
            ->setSettings('checkBoxes', false);

        // Add restore button for admin users only
        if ($this->user->admin) {
            $this->addButton($viewName, [
                'action' => 'restore-special',
                'color' => 'warning',
                'confirm' => true,
                'icon' => 'fa-solid fa-trash-restore',
                'label' => 'restore'
            ]);
        }
    }

    /**
     * Creates and configures the subaccounts view
     *
     * @param string $viewName
     */
    protected function createViewsSubaccounts(string $viewName = 'ListSubcuenta'): void
    {
        // Add subaccounts view
        $this->addView($viewName, 'Subcuenta', 'subaccounts', 'fa-solid fa-th-list');
        
        // Configure search fields
        $this->addSearchFields(['codsubcuenta', 'descripcion', 'codejercicio', 'codcuentaesp']);
        
        // Configure default order
        $this->addOrderBy(['codejercicio desc, codsubcuenta'], 'code');
        $this->addOrderBy(['codejercicio desc, descripcion'], 'description');
        $this->addOrderBy(['debe'], 'debit');
        $this->addOrderBy(['haber'], 'credit');
        $this->addOrderBy(['saldo'], 'balance');

        // Filters
        $this->addFilterNumber('debit-major', 'debit', 'debe', '>=');
        $this->addFilterNumber('debit-minor', 'debit', 'debe', '<=');
        $this->addFilterNumber('credit-major', 'credit', 'haber', '>=');
        $this->addFilterNumber('credit-minor', 'credit', 'haber', '<=');
        $this->addFilterNumber('balance-major', 'balance', 'saldo', '>=');
        $this->addFilterNumber('balance-minor', 'balance', 'saldo', '<=');
        
        // Exercise filter
        $this->addFilterSelect($viewName, 'codejercicio', 'exercise', 'codejercicio', Ejercicios::codeModel());

        // Special account filter
        $specialAccounts = $this->codeModel->getAll('cuentasesp', 'codcuentaesp', 'codcuentaesp');
        $this->addFilterSelect($viewName, 'codcuentaesp', 'special-account', 'codcuentaesp', $specialAccounts);
    }

    /**
     * Execute actions before reading data
     *
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        if ($action === 'restore-special') {
            $this->restoreSpecialAccountsAction();
            return true;
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Load data into the view and adjust totals
     *
     * @param string $viewName
     * @param object $view
     */
    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);

        // Remove 'saldo' column from totals if present
        if (isset($view->totalAmounts['saldo'])) {
            unset($view->totalAmounts['saldo']);
        }
    }

    /**
     * Restore special accounts from CSV file
     */
    protected function restoreSpecialAccountsAction(): void
    {
        $sql = CSVImporter::getUpdateSQL(CuentaEspecial::tableName());
        if (!empty($sql)) {
            $this->dataBase->exec($sql);
        }

        Logger::notice('record-updated-correctly');
    }
}