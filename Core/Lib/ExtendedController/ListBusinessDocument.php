<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2023-2025 ERPIA Team
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

namespace ERPIA\Lib\ExtendedController;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\DataSrc\Agents;
use ERPIA\Core\DataSrc\Warehouses;
use ERPIA\Core\DataSrc\Companies;
use ERPIA\Core\DataSrc\PaymentMethods;
use ERPIA\Core\DataSrc\Taxes;
use ERPIA\Core\DataSrc\Series;
use ERPIA\Core\DataSrc\Currencies;
use ERPIA\Core\DataSrc\CustomerGroups;
use ERPIA\Core\Lib\InvoiceOperations;
use ERPIA\Core\Config;
use ERPIA\Core\Translation;
use ERPIA\Dinamic\Lib\BusinessDocumentProcessor;
use ERPIA\Dinamic\Model\DocumentStatus;

/**
 * Abstract controller for business document lists
 *
 * @author ERPIA Team
 */
abstract class ListBusinessDocument extends ListController
{
    use ListBusinessActionTrait;

    /**
     * Adds color coding based on document status
     *
     * @param string $viewName
     * @param string $modelType
     */
    protected function addStatusColors(string $viewName, string $modelType): void
    {
        $condition = [new DataBaseWhere('document_type', $modelType)];
        foreach (DocumentStatus::all($condition) as $status) {
            if ($status->color_value) {
                $this->addColor($viewName, 'status_id', $status->id, $status->color_value, $status->description);
            }
        }
    }

    /**
     * Adds common filters for document views
     *
     * @param string $viewName
     * @param string $modelType
     */
    protected function addDocumentCommonFilters(string $viewName, string $modelType): void
    {
        $this->addFilterPeriod($viewName, 'date', 'period', 'document_date');
        $this->addFilterNumber($viewName, 'min-amount', 'total', 'total_amount', '>=');
        $this->addFilterNumber($viewName, 'max-amount', 'total', 'total_amount', '<=');

        $condition = [new DataBaseWhere('document_type', $modelType)];
        $statusOptions = $this->codeModel->all('document_statuses', 'id', 'name', true, $condition);
        $this->addFilterSelect($viewName, 'status_id', 'status', 'status_id', $statusOptions);

        if ($this->permissions->ownerDataOnly === false) {
            $users = $this->codeModel->all('users', 'username', 'username');
            if (count($users) > 1) {
                $this->addFilterSelect($viewName, 'username', 'user', 'username', $users);
            }
        }

        $companyList = Companies::codeModel();
        if (count($companyList) > 2) {
            $this->addFilterSelect($viewName, 'company_id', 'company', 'company_id', $companyList);
        }

        $warehouseList = Warehouses::codeModel();
        if (count($warehouseList) > 2) {
            $this->addFilterSelect($viewName, 'warehouse_code', 'warehouse', 'warehouse_code', $warehouseList);
        }

        $seriesList = Series::codeModel();
        if (count($seriesList) > 2) {
            $this->addFilterSelect($viewName, 'series_code', 'series', 'series_code', $seriesList);
        }

        $operations = [['code' => '', 'description' => '------']];
        foreach (InvoiceOperations::all() as $key => $value) {
            $operations[] = [
                'code' => $key,
                'description' => Translation::translate($value)
            ];
        }
        $this->addFilterSelect($viewName, 'operation_type', 'operation', 'operation_type', $operations);

        $paymentMethodList = PaymentMethods::codeModel();
        if (count($paymentMethodList) > 2) {
            $this->addFilterSelect($viewName, 'payment_method_code', 'payment-method', 'payment_method_code', $paymentMethodList);
        }

        $currencyList = Currencies::codeModel();
        if (count($currencyList) > 2) {
            $this->addFilterSelect($viewName, 'currency_code', 'currency', 'currency_code', $currencyList);
        }

        $this->addFilterCheckbox($viewName, 'surcharge-total', 'surcharge', 'surcharge_total', '!=', 0);
        $this->addFilterCheckbox($viewName, 'retention-total', 'retention', 'retention_total', '!=', 0);
        $this->addFilterCheckbox($viewName, 'supplied-amount', 'supplied', 'supplied_amount', '!=', 0);
        $this->addFilterCheckbox($viewName, 'attachment-count', 'has-attachments', 'attachment_count', '!=', 0);
    }

    /**
     * Creates view for document lines
     *
     * @param string $viewName
     * @param string $modelType
     */
    protected function createLinesView(string $viewName, string $modelType): void
    {
        $this->addView($viewName, $modelType, 'lines', 'fa-solid fa-list')
            ->addOrderBy(['reference'], 'reference')
            ->addOrderBy(['quantity'], 'quantity')
            ->addOrderBy(['served_qty'], 'quantity-served')
            ->addOrderBy(['description'], 'description')
            ->addOrderBy(['total_price'], 'amount')
            ->addOrderBy(['line_id'], 'code', 2)
            ->addSearchFields(['reference', 'description']);

        // Filters
        $this->addFilterAutocomplete($viewName, 'product_id', 'product', 'product_id', 'products', 'product_id', 'reference');
        $this->addFilterAutocomplete($viewName, 'reference', 'variant', 'reference', 'variants', 'reference', 'reference');
        $this->addFilterSelect($viewName, 'tax_code', 'tax', 'tax_code', Taxes::codeModel());

        $stockActions = [
            ['code' => '', 'description' => '------'],
            ['code' => -2, 'description' => Translation::translate('book')],
            ['code' => -1, 'description' => Translation::translate('subtract')],
            ['code' => 0, 'description' => Translation::translate('do-nothing')],
            ['code' => 1, 'description' => Translation::translate('add')],
            ['code' => 2, 'description' => Translation::translate('foresee')]
        ];
        $this->addFilterSelect($viewName, 'stock_action', 'stock', 'stock_action', $stockActions);

        $this->addFilterNumber($viewName, 'quantity-gt', 'quantity', 'quantity');
        $this->addFilterNumber($viewName, 'quantity-lt', 'quantity', 'quantity', '<=');

        $this->addFilterNumber($viewName, 'served-gt', 'quantity-served', 'served_qty');
        $this->addFilterNumber($viewName, 'served-lt', 'quantity-served', 'served_qty', '<=');

        $this->addFilterNumber($viewName, 'discount-gt', 'discount', 'discount_percent');
        $this->addFilterNumber($viewName, 'discount-lt', 'discount', 'discount_percent', '<=');

        $this->addFilterNumber($viewName, 'unit-price-gt', 'unit-price', 'unit_price');
        $this->addFilterNumber($viewName, 'unit-price-lt', 'unit-price', 'unit_price', '<=');

        $this->addFilterNumber($viewName, 'total-price-gt', 'total-price', 'total_price');
        $this->addFilterNumber($viewName, 'total-price-lt', 'total-price', 'total_price', '<=');

        $this->addFilterCheckbox($viewName, 'no-ref', 'no-reference', 'reference', 'IS', null);
        $this->addFilterCheckbox($viewName, 'has-surcharge', 'surcharge', 'surcharge', '!=', 0);
        $this->addFilterCheckbox($viewName, 'has-retention', 'retention', 'retention', '!=', 0);
        $this->addFilterCheckbox($viewName, 'is-supplied', 'supplied', 'is_supplied');

        // Disable buttons, checkboxes and megasearch
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'checkBoxes', false);
        $this->setSettings($viewName, 'megasearch', false);
    }

    /**
     * Creates view for purchase documents
     *
     * @param string $viewName
     * @param string $modelType
     * @param string $label
     */
    protected function createPurchasesView(string $viewName, string $modelType, string $label): void
    {
        $this->addView($viewName, $modelType, $label, 'fa-solid fa-copy')
            ->addOrderBy(['code'], 'code')
            ->addOrderBy(['document_date', $this->convertColumnToNumber('number')], 'date', 2)
            ->addOrderBy([$this->tab($viewName)->model->primaryKey()], 'id')
            ->addOrderBy([$this->convertColumnToNumber('number')], 'number')
            ->addOrderBy(['supplier_number'], 'supplier-number')
            ->addOrderBy(['supplier_code'], 'supplier-code')
            ->addOrderBy(['total_amount'], 'total')
            ->addSearchFields(['tax_id', 'code', 'name', 'supplier_number', 'notes']);

        // Filters
        $this->addDocumentCommonFilters($viewName, $modelType);
        $this->addFilterAutocomplete($viewName, 'supplier_code', 'supplier', 'supplier_code', 'Supplier');
        $this->addFilterCheckbox($viewName, 'email-sent', 'email-not-sent', 'email_sent_flag', 'IS', null);

        // Add status colors
        $this->addStatusColors($viewName, $modelType);
    }

    /**
     * Creates view for sales documents
     *
     * @param string $viewName
     * @param string $modelType
     * @param string $label
     */
    protected function createSalesView(string $viewName, string $modelType, string $label): void
    {
        $this->addView($viewName, $modelType, $label, 'fa-solid fa-copy')
            ->addOrderBy(['code'], 'code')
            ->addOrderBy(['customer_code'], 'customer-code')
            ->addOrderBy(['document_date', $this->convertColumnToNumber('number')], 'date', 2)
            ->addOrderBy([$this->tab($viewName)->model->primaryKey()], 'id')
            ->addOrderBy([$this->convertColumnToNumber('number')], 'number')
            ->addOrderBy(['secondary_number'], 'number2')
            ->addOrderBy(['total_amount'], 'total')
            ->addSearchFields(['tax_id', 'code', 'delivery_code', 'customer_name', 'secondary_number', 'notes']);

        // Filters
        $this->addDocumentCommonFilters($viewName, $modelType);

        // Filter by customer groups
        $groupOptions = [
            ['label' => Translation::translate('any-group'), 'where' => []],
            [
                'label' => Translation::translate('without-groups'),
                'where' => [new DataBaseWhere('customer_code', "SELECT DISTINCT customer_code FROM customers WHERE group_code IS NULL", 'IN')]
            ],
            ['label' => '------', 'where' => []],
        ];
        foreach (CustomerGroups::all() as $group) {
            $groupQuery = 'SELECT DISTINCT customer_code FROM customers WHERE group_code = ' . $this->dataBase->var2str($group->code);
            $groupOptions[] = [
                'label' => $group->name,
                'where' => [new DataBaseWhere('customer_code', $groupQuery, 'IN')]
            ];
        }
        if (count($groupOptions) > 3) {
            $this->addFilterSelectWhere($viewName, 'group_code', $groupOptions);
        }

        // Filter by customers and addresses
        $this->addFilterAutocomplete($viewName, 'customer_code', 'customer', 'customer_code', 'Customer');
        $this->addFilterAutocomplete($viewName, 'billing_contact_id', 'billing-address', 'billing_contact_id', 'contacts', 'contact_id', 'address');
        $this->addFilterAutocomplete($viewName, 'shipping_contact_id', 'shipping-address', 'shipping_contact_id', 'contacts', 'contact_id', 'address');

        if ($this->permissions->ownerDataOnly === false) {
            $agentList = Agents::codeModel();
            if (count($agentList) > 1) {
                $this->addFilterSelect($viewName, 'agent_code', 'agent', 'agent_code', $agentList);
            }
        }

        $carrierList = $this->codeModel->all('transport_agencies', 'carrier_code', 'name');
        $this->addFilterSelect($viewName, 'carrier_code', 'carrier', 'carrier_code', $carrierList);
        $this->addFilterCheckbox($viewName, 'email-sent', 'email-not-sent', 'email_sent_flag', 'IS', null);

        // Add status colors
        $this->addStatusColors($viewName, $modelType);
    }

    /**
     * Execute actions that modify data before reading
     *
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        $allowUpdate = $this->permissions->allowUpdate;
        $codes = $this->request->request->getArray('codes');
        $model = $this->views[$this->active]->model;

        switch ($action) {
            case 'approve-document':
                return $this->approveDocumentAction($codes, $model, $allowUpdate, $this->dataBase);

            case 'approve-document-same-date':
                BusinessDocumentProcessor::setSameDate(true);
                return $this->approveDocumentAction($codes, $model, $allowUpdate, $this->dataBase);

            case 'generate-accounting-entries':
                return $this->generateAccountingEntriesAction($model, $allowUpdate, $this->dataBase);

            case 'group-document':
                return $this->groupDocumentAction($codes, $model);

            case 'lock-invoice':
                return $this->lockInvoiceAction($codes, $model, $allowUpdate, $this->dataBase);

            case 'pay-receipt':
                return $this->payReceiptAction($codes, $model, $allowUpdate, $this->dataBase, $this->user->username);
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Convert column to number for database compatibility
     *
     * @param string $columnName
     * @return string
     */
    private function convertColumnToNumber(string $columnName): string
    {
        $dbType = Config::get('db_type');
        return strtolower($dbType) == 'postgresql' ?
            'CAST(' . $columnName . ' as integer)' :
            'CAST(' . $columnName . ' as unsigned)';
    }
}