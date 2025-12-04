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

use ERPIA\Core\DataSrc\Empresas;
use ERPIA\Core\Lib\ExtendedController\ListController;

/**
 * Controller to list the items in the FormaPago model
 *
 * @author ERPIA Contributors
 */
class ListFormaPago extends ListController
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
        $pageData['title'] = 'payment-methods';
        $pageData['icon'] = 'fa-solid fa-credit-card';
        return $pageData;
    }

    /**
     * Create and configure the views
     */
    protected function createViews()
    {
        $this->createViewsPaymentMethods();
        $this->createViewsBankAccounts();
    }

    /**
     * Creates and configures the bank accounts view
     *
     * @param string $viewName
     */
    protected function createViewsBankAccounts(string $viewName = 'ListCuentaBanco'): void
    {
        // Add bank accounts view
        $this->addView($viewName, 'CuentaBanco', 'bank-accounts', 'fa-solid fa-piggy-bank');
        
        // Configure search fields
        $this->addSearchFields(['descripcion', 'codcuenta']);
        
        // Configure default order
        $this->addOrderBy(['codcuenta'], 'code');
        $this->addOrderBy(['descripcion'], 'description');

        // If there is only one company, hide the company column; otherwise, add company filter
        if (count(Empresas::getAll()) === 1) {
            $this->listView($viewName)->disableColumn('company');
        } else {
            $this->addFilterSelect($viewName, 'idempresa', 'company', 'idempresa', Empresas::codeModel());
        }
    }

    /**
     * Creates and configures the payment methods view
     *
     * @param string $viewName
     */
    protected function createViewsPaymentMethods(string $viewName = 'ListFormaPago'): void
    {
        // Add payment methods view
        $this->addView($viewName, 'FormaPago', 'payment-methods', 'fa-solid fa-credit-card');
        
        // Configure search fields
        $this->addSearchFields(['descripcion', 'codpago']);
        
        // Configure default order
        $this->addOrderBy(['codpago', 'idempresa'], 'code');
        $this->addOrderBy(['descripcion'], 'description');
        $this->addOrderBy(['idempresa', 'codpago'], 'company');

        // If there is only one company, hide the company column; otherwise, add company filter
        if (count(Empresas::getAll()) === 1) {
            $this->listView($viewName)->disableColumn('company');
        } else {
            $this->addFilterSelect($viewName, 'idempresa', 'company', 'idempresa', Empresas::codeModel());
        }

        // Additional filters
        $this->addFilterCheckbox($viewName, 'pagado', 'paid', 'pagado');
        $this->addFilterCheckbox($viewName, 'domiciliado', 'domiciled', 'domiciliado');
    }
}