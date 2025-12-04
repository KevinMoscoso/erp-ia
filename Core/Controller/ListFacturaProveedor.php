<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2017-2025 ERPIA Contributors
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

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\DataSrc\Divisas;
use ERPIA\Core\DataSrc\FormasPago;
use ERPIA\Core\Lib\FacturaProveedorRenumber;
use ERPIA\Core\Lib\ExtendedController\ListBusinessDocument;
use ERPIA\Core\App\Translator;
use ERPIA\Core\App\Logger;

/**
 * Controller to list the items in the FacturaProveedor model
 *
 * @author ERPIA Contributors
 */
class ListFacturaProveedor extends ListBusinessDocument
{
    /**
     * Returns page configuration data
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'purchases';
        $pageData['title'] = 'invoices';
        $pageData['icon'] = 'fa-solid fa-file-invoice-dollar';
        return $pageData;
    }

    /**
     * Create and configure the views
     */
    protected function createViews(): void
    {
        // Supplier invoices list
        $this->createViewPurchases('ListFacturaProveedor', 'FacturaProveedor', 'invoices');

        // Only add additional views if user can see all data
        if ($this->permissions->onlyOwnerData) {
            return;
        }

        // Invoice lines
        $this->createViewLines('ListLineaFacturaProveedor', 'LineaFacturaProveedor');

        // Supplier receipts
        $this->createViewReceipts();

        // Refund invoices
        $this->createViewRefunds();
    }

    /**
     * Creates and configures the purchases view with additional filters and buttons
     *
     * @param string $viewName
     * @param string $modelName
     * @param string $label
     */
    protected function createViewPurchases(string $viewName, string $modelName, string $label): void
    {
        parent::createViewPurchases($viewName, $modelName, $label);

        // Additional search field
        $this->addSearchFields(['codigorect']);

        // Status filter
        $this->addFilterSelectWhere($viewName, 'status', [
            ['label' => Translator::trans('paid-or-unpaid'), 'where' => []],
            ['label' => Translator::trans('paid'), 'where' => [new DataBaseWhere('pagada', true)]],
            ['label' => Translator::trans('unpaid'), 'where' => [new DataBaseWhere('pagada', false)]],
            ['label' => Translator::trans('expired-receipt'), 'where' => [new DataBaseWhere('vencida', true)]],
        ]);
        
        // Filter for invoices without accounting entry
        $this->addFilterCheckbox($viewName, 'idasiento', 'invoice-without-acc-entry', 'idasiento', 'IS', null);

        // Add lock invoice button
        $this->addButtonLockInvoice($viewName);
        
        // Add generate accounting invoices button
        $this->addButtonGenerateAccountingInvoices($viewName);

        // Add renumber button for admin users only
        if ($this->user->admin) {
            $this->addButton($viewName, [
                'action' => 'renumber-invoices',
                'icon' => 'fa-solid fa-sort-numeric-down',
                'label' => 'renumber',
                'type' => 'modal'
            ]);
        }
    }

    /**
     * Creates and configures the receipts view
     *
     * @param string $viewName
     */
    protected function createViewReceipts(string $viewName = 'ListReciboProveedor'): void
    {
        // Add receipts view
        $this->addView($viewName, 'ReciboProveedor', 'receipts', 'fa-solid fa-dollar-sign');
        
        // Configure default order
        $this->addOrderBy(['codproveedor'], 'supplier-code');
        $this->addOrderBy(['fecha', 'idrecibo'], 'date');
        $this->addOrderBy(['fechapago'], 'payment-date');
        $this->addOrderBy(['vencimiento'], 'expiration', 2);
        $this->addOrderBy(['importe'], 'amount');
        
        // Configure search fields
        $this->addSearchFields(['codigofactura', 'observaciones']);
        
        // Disable new button
        $this->setSettings('btnNew', false);

        // Filters
        $this->addFilterPeriod($viewName, 'expiration', 'expiration', 'vencimiento');
        $this->addFilterAutocomplete($viewName, 'codproveedor', 'supplier', 'codproveedor', 'Proveedor');
        $this->addFilterNumber($viewName, 'min-total', 'amount', 'importe', '>=');
        $this->addFilterNumber($viewName, 'max-total', 'amount', 'importe', '<=');

        // Currency filter (only if more than 2 options)
        $currencies = Divisas::codeModel();
        if (count($currencies) > 2) {
            $this->addFilterSelect($viewName, 'coddivisa', 'currency', 'coddivisa', $currencies);
        }

        // Payment method filter (only if more than 2 options)
        $payMethods = FormasPago::codeModel();
        if (count($payMethods) > 2) {
            $this->addFilterSelect($viewName, 'codpago', 'payment-method', 'codpago', $payMethods);
        }

        // Status filter
        $this->addFilterSelectWhere($viewName, 'status', [
            ['label' => Translator::trans('paid-or-unpaid'), 'where' => []],
            ['label' => Translator::trans('paid'), 'where' => [new DataBaseWhere('pagado', true)]],
            ['label' => Translator::trans('unpaid'), 'where' => [new DataBaseWhere('pagado', false)]],
            ['label' => Translator::trans('expired-receipt'), 'where' => [new DataBaseWhere('vencido', true)]],
        ]);
        
        // Payment date filter
        $this->addFilterPeriod($viewName, 'payment-date', 'payment-date', 'fechapago');

        // Pay receipt button
        $this->addButtonPayReceipt($viewName);
    }

    /**
     * Creates and configures the refunds view
     *
     * @param string $viewName
     */
    protected function createViewRefunds(string $viewName = 'ListFacturaProveedor-rect'): void
    {
        // Add refunds view
        $this->addView($viewName, 'FacturaProveedor', 'refunds', 'fa-solid fa-share-square');
        
        // Configure search fields
        $this->addSearchFields(['codigo', 'codigorect', 'numproveedor', 'observaciones']);
        
        // Configure default order
        $this->addOrderBy(['fecha', 'idfactura'], 'date', 2);
        $this->addOrderBy(['total'], 'total');
        
        // Disable original column and new button
        $this->disableColumn('original', false);
        $this->setSettings('btnNew', false);

        // Date period filter
        $this->addFilterPeriod($viewName, 'date', 'period', 'fecha');

        // Filter for rectified invoices (only those with idfacturarect not null)
        $this->addFilterSelectWhere($viewName, 'idfacturarect', [
            [
                'label' => Translator::trans('rectified-invoices'),
                'where' => [new DataBaseWhere('idfacturarect', null, 'IS NOT')]
            ]
        ]);
    }

    /**
     * Execute actions before reading data
     *
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        if ($action == 'renumber-invoices') {
            $this->renumberInvoicesAction();
            return true;
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Renumber supplier invoices for an exercise
     */
    protected function renumberInvoicesAction(): void
    {
        if (!$this->user->admin) {
            Logger::warning('not-allowed-modify');
            return;
        }

        if (!$this->validateFormToken()) {
            return;
        }

        $codejercicio = $this->request->input('exercise');
        if (FacturaProveedorRenumber::run($codejercicio)) {
            Logger::notice('renumber-invoices-success', ['%exercise%' => $codejercicio]);
            return;
        }

        Logger::warning('record-save-error');
    }
}