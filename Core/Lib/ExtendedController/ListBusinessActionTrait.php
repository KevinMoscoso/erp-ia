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

use ERPIA\Core\Base\DataBase;
use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Model\Base\Receipt;
use ERPIA\Core\Model\Base\TransformerDocument;
use ERPIA\Core\Logger;
use ERPIA\Core\DateUtils;
use ERPIA\Dinamic\Lib\Accounting\InvoiceToAccounting;

/**
 * Contains common utilities for grouping and collecting documents.
 *
 * @author ERPIA Team
 */
trait ListBusinessActionTrait
{
    abstract public function addButton(string $viewName, array $btnArray);

    abstract public function redirect(string $url, int $delay = 0);

    abstract protected function validateFormToken(): bool;

    /**
     * Adds buttons to approve documents.
     *
     * @param string $viewName
     */
    protected function addButtonApproveDocument(string $viewName)
    {
        $this->addButton($viewName, [
            'action' => 'approve-document-same-date',
            'confirm' => 'true',
            'icon' => 'fa-solid fa-calendar-check',
            'label' => 'approve-document-same-date'
        ]);

        $this->addButton($viewName, [
            'action' => 'approve-document',
            'confirm' => 'true',
            'icon' => 'fa-solid fa-check',
            'label' => 'approve-document'
        ]);
    }

    /**
     * Adds button to lock invoices.
     *
     * @param string $viewName
     * @param string|null $code
     */
    protected function addButtonGenerateAccountingInvoices(string $viewName, ?string $code = null): void
    {
        $model = $this->views[$viewName]->model;
        $allowedModels = ['CustomerInvoice', 'SupplierInvoice'];
        
        if (false === in_array($model->modelClassName(), $allowedModels)) {
            return;
        }

        $where = [
            new DataBaseWhere('accounting_entry_id', null, 'IS'),
            new DataBaseWhere('date', DateUtils::subtractYears(1), '>'),
            new DataBaseWhere('total_amount', 0, '!=')
        ];

        if (false === empty($code) && property_exists($model, 'customer_code')) {
            $where[] = new DataBaseWhere('customer_code', $code);
        } elseif (false === empty($code) && property_exists($model, 'supplier_code')) {
            $where[] = new DataBaseWhere('supplier_code', $code);
        }

        if ($model->count($where) <= 0) {
            return;
        }

        $this->addButton($viewName, [
            'action' => 'generate-accounting-entries',
            'color' => 'warning',
            'icon' => 'fa-solid fa-wand-magic-sparkles',
            'label' => 'generate-accounting-entries'
        ]);
    }

    /**
     * Adds button to group documents.
     *
     * @param string $viewName
     */
    protected function addButtonGroupDocument(string $viewName): void
    {
        $this->addButton($viewName, [
            'action' => 'group-document',
            'icon' => 'fa-solid fa-wand-magic-sparkles',
            'label' => 'group-or-split'
        ]);
    }

    /**
     * Adds button to lock invoices.
     *
     * @param string $viewName
     */
    protected function addButtonLockInvoice(string $viewName): void
    {
        $this->addButton($viewName, [
            'action' => 'lock-invoice',
            'confirm' => 'true',
            'icon' => 'fa-solid fa-lock fa-fw',
            'label' => 'lock-invoice'
        ]);
    }

    /**
     * Adds button to pay receipts.
     *
     * @param string $viewName
     */
    protected function addButtonPayReceipt(string $viewName): void
    {
        $this->addButton($viewName, [
            'action' => 'pay-receipt',
            'color' => 'outline-success',
            'confirm' => 'true',
            'icon' => 'fa-solid fa-check',
            'label' => 'paid',
            'type' => 'action'
        ]);
    }

    /**
     * Approves selected documents.
     *
     * @param mixed $codes
     * @param TransformerDocument $model
     * @param bool $allowUpdate
     * @param DataBase $dataBase
     *
     * @return bool
     */
    protected function approveDocumentAction($codes, $model, $allowUpdate, $dataBase): bool
    {
        if (false === $allowUpdate) {
            Logger::warning('not-allowed-modify');
            return true;
        } elseif (false === is_array($codes) || empty($model)) {
            Logger::warning('no-selected-item');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        $dataBase->beginTransaction();
        foreach ($codes as $code) {
            if (false === $model->loadFromCode($code)) {
                Logger::error('record-not-found');
                continue;
            }

            foreach ($model->getAvailableStatuses() as $status) {
                if (empty($status->generates_doc) || !$status->active) {
                    continue;
                }

                $model->status_id = $status->id;
                if ($model->save()) {
                    break;
                }

                Logger::error('record-save-error');
                $dataBase->rollback();
                return true;
            }
        }

        Logger::notice('record-updated-correctly');
        $dataBase->commit();
        $model->clear();
        return true;
    }

    protected function generateAccountingEntriesAction($model, $allowUpdate, $dataBase): bool
    {
        if (false === $allowUpdate) {
            Logger::warning('not-allowed-modify');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        $allowedModels = ['CustomerInvoice', 'SupplierInvoice'];
        if (false === in_array($model->modelClassName(), $allowedModels)) {
            return true;
        }

        $dataBase->beginTransaction();
        $where = [
            new DataBaseWhere('accounting_entry_id', null, 'IS'),
            new DataBaseWhere('date', DateUtils::subtractYears(1), '>'),
            new DataBaseWhere('total_amount', 0, '!=')
        ];
        
        foreach ($model->all($where, ['invoice_id' => 'ASC'], 0, 300) as $invoice) {
            if (false === empty($invoice->accounting_entry_id)) {
                continue;
            }

            $generator = new InvoiceToAccounting();
            $generator->generate($invoice);
            if (empty($invoice->accounting_entry_id)) {
                Logger::error('cannot-generate-accounting-entry', ['%invoice%' => $invoice->code]);
                $dataBase->rollback();
                return true;
            }

            if (false === $invoice->save()) {
                Logger::error('record-save-error', ['invoice' => $invoice->code]);
                $dataBase->rollback();
                return true;
            }
        }

        Logger::notice('record-updated-correctly');
        $dataBase->commit();
        return true;
    }

    /**
     * Group selected documents.
     *
     * @param mixed $codes
     * @param TransformerDocument $model
     *
     * @return bool
     */
    protected function groupDocumentAction($codes, $model): bool
    {
        if (!empty($codes) && $model) {
            $codes = implode(',', $codes);
            $url = 'DocumentStitcher?model=' . $model->modelClassName() . '&codes=' . $codes;
            $this->redirect($url);
            return false;
        }

        Logger::warning('no-selected-item');
        return true;
    }

    /**
     * Locks selected invoices.
     *
     * @param mixed $codes
     * @param TransformerDocument $model
     * @param bool $allowUpdate
     * @param DataBase $dataBase
     *
     * @return bool
     */
    protected function lockInvoiceAction($codes, $model, $allowUpdate, $dataBase): bool
    {
        if (false === $allowUpdate) {
            Logger::warning('not-allowed-modify');
            return true;
        } elseif (false === is_array($codes) || empty($model)) {
            Logger::warning('no-selected-item');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        $dataBase->beginTransaction();
        foreach ($codes as $code) {
            if (false === $model->loadFromCode($code)) {
                Logger::error('record-not-found');
                continue;
            }

            foreach ($model->getAvailableStatuses() as $status) {
                if ($status->editable || !$status->active) {
                    continue;
                }

                $model->status_id = $status->id;
                if ($model->save()) {
                    break;
                }

                Logger::error('record-save-error');
                $dataBase->rollback();
                return true;
            }
        }

        Logger::notice('record-updated-correctly');
        $dataBase->commit();
        $model->clear();
        return true;
    }

    /**
     * Sets selected receipts as paid.
     *
     * @param mixed $codes
     * @param Receipt $model
     * @param bool $allowUpdate
     * @param DataBase $dataBase
     * @param string $username
     *
     * @return bool
     */
    protected function payReceiptAction($codes, $model, $allowUpdate, $dataBase, $username): bool
    {
        if (false === $allowUpdate) {
            Logger::warning('not-allowed-modify');
            return true;
        } elseif (false === is_array($codes) || empty($model)) {
            Logger::warning('no-selected-item');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        $dataBase->beginTransaction();
        foreach ($codes as $code) {
            if (false === $model->loadFromCode($code)) {
                Logger::error('record-not-found');
                continue;
            }

            $model->username = $username;
            $model->paid = true;
            if (false === $model->save()) {
                Logger::error('record-save-error');
                $dataBase->rollback();
                return true;
            }
        }

        Logger::notice('record-updated-correctly');
        $dataBase->commit();
        $model->clear();
        return true;
    }
}