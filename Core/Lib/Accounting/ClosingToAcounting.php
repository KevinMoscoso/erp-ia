<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2025 ERPIA Development Team
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

namespace ERPIA\Core\Lib\Accounting;

use ERPIA\Core\Base\DataBase;
use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Model\Ejercicio;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Lib\Import\CSVImport;
use ERPIA\Dinamic\Model\CuentaEspecial;
use ERPIA\Dinamic\Model\FacturaCliente;
use ERPIA\Dinamic\Model\FacturaProveedor;

/**
 * Class that performs accounting closures
 *
 * @author ERPIA Development Team
 */
class ClosingToAcounting
{
    /**
     * Indicates whether the accounting account plan should be copied
     * to the new fiscal year.
     *
     * @var bool
     */
    protected $copySubAccountsFlag;

    /**
     * It provides direct access to the database.
     *
     * @var DataBase
     */
    protected static $dbInstance;

    /**
     * Exercise where the accounting process is performed.
     *
     * @var Ejercicio
     */
    protected $fiscalPeriod;

    /**
     * Journal Id for closing accounting entry.
     *
     * @var int
     */
    protected $closingJournalId;

    /**
     * Journal Id for opening accounting entry.
     *
     * @var int
     */
    protected $openingJournalId;

    /**
     * Class Constructor
     */
    public function __construct()
    {
        if (self::$dbInstance === null) {
            self::$dbInstance = new DataBase();
        }
    }

    /**
     * Execute the delete process, deleting selected entry accounts
     * and reopening exercise.
     *
     * @param Ejercicio $exercise
     * @param array $data
     *
     * @return bool
     */
    public function delete($exercise, $data): bool
    {
        $this->fiscalPeriod = $exercise;
        $deleteClosing = $data['deleteClosing'] ?? true;
        $deleteOpening = $data['deleteOpening'] ?? true;

        self::$dbInstance->beginTransaction();

        try {
            $this->fiscalPeriod->estado = Ejercicio::EXERCISE_STATUS_OPEN;
            $this->fiscalPeriod->save();

            if ($deleteOpening && !$this->removeOpeningEntries()) {
                return false;
            }

            if ($deleteClosing && (!$this->removeClosingEntries() || !$this->removeRegularizationEntries())) {
                return false;
            }

            self::$dbInstance->commit();
        } finally {
            $operationResult = !self::$dbInstance->inTransaction();
            if ($operationResult == false) {
                self::$dbInstance->rollback();
            }
        }

        return $operationResult;
    }

    /**
     * Execute the main process of regularization, closing and opening
     * of accounts.
     *
     * @param Ejercicio $exercise
     * @param array $data
     *
     * @return bool
     */
    public function exec($exercise, $data): bool
    {
        $this->fiscalPeriod = $exercise;
        $this->closingJournalId = $data['journalClosing'] ?? 0;
        $this->openingJournalId = $data['journalOpening'] ?? 0;
        $this->copySubAccountsFlag = $data['copySubAccounts'] ?? false;

        self::$dbInstance->beginTransaction();

        try {
            $this->refreshSpecialAccounts();

            if ($this->processInvoiceClosure() && $this->executeRegularization() && $this->executeClosing() && $this->executeOpening()) {
                $this->fiscalPeriod->estado = Ejercicio::EXERCISE_STATUS_CLOSED;
                $this->fiscalPeriod->save();
                self::$dbInstance->commit();
            }
        } finally {
            $operationResult = !self::$dbInstance->inTransaction();
            if ($operationResult == false) {
                self::$dbInstance->rollback();
            }
        }

        return $operationResult;
    }

    /**
     * Delete closing accounting entry
     *
     * @return bool
     */
    protected function removeClosingEntries(): bool
    {
        $closingProcessor = new AccountingClosingClosing();
        return $closingProcessor->delete($this->fiscalPeriod);
    }

    /**
     * Delete opening accounting entry
     *
     * @return bool
     */
    protected function removeOpeningEntries(): bool
    {
        $openingProcessor = new AccountingClosingOpening();
        return $openingProcessor->delete($this->fiscalPeriod);
    }

    /**
     * Delete regularization accounting entry
     *
     * @return bool
     */
    protected function removeRegularizationEntries(): bool
    {
        $regularizationProcessor = new AccountingClosingRegularization();
        return $regularizationProcessor->delete($this->fiscalPeriod);
    }

    /**
     * Lock all invoices from this exercise.
     *
     * @return bool
     */
    protected function processInvoiceClosure(): bool
    {
        // find customer invoices without accounting entry
        $customerInvoiceModel = new FacturaCliente();
        $missingEntryConditions = [
            new DataBaseWhere('codejercicio', $this->fiscalPeriod->codejercicio),
            new DataBaseWhere('idasiento', null),
            new DataBaseWhere('total', 0, '!=')
        ];
        if ($customerInvoiceModel->count($missingEntryConditions) > 0) {
            Tools::log()->warning('invoice-missing-accounting-entry');
            return false;
        }

        // close customer invoices
        $customerStatuses = $customerInvoiceModel->getAvailableStatus();
        $customerConditions = [
            new DataBaseWhere('editable', true),
            new DataBaseWhere('codejercicio', $this->fiscalPeriod->codejercicio)
        ];
        foreach ($customerStatuses as $status) {
            if ($status->editable || $status->generadoc) {
                continue;
            }

            foreach ($customerInvoiceModel->all($customerConditions, [], 0, 0) as $invoice) {
                $invoice->idestado = $status->idestado;
                if (false === $invoice->save()) {
                    Tools::log()->error('cannot-close-customer-invoice-' . $invoice->idfactura);
                    return false;
                }
            }
            break;
        }

        // find supplier invoices without accounting entry
        $supplierInvoiceModel = new FacturaProveedor();
        if ($supplierInvoiceModel->count($missingEntryConditions) > 0) {
            Tools::log()->warning('supplier-invoice-missing-accounting-entry');
            return false;
        }

        // close supplier invoices
        $supplierStatuses = $supplierInvoiceModel->getAvailableStatus();
        $supplierConditions = [
            new DataBaseWhere('editable', true),
            new DataBaseWhere('codejercicio', $this->fiscalPeriod->codejercicio)
        ];
        foreach ($supplierStatuses as $status) {
            if ($status->editable || $status->generadoc) {
                continue;
            }

            foreach ($supplierInvoiceModel->all($supplierConditions, [], 0, 0) as $invoice) {
                $invoice->idestado = $status->idestado;
                if (false === $invoice->save()) {
                    Tools::log()->error('cannot-close-supplier-invoice-' . $invoice->idfactura);
                    return false;
                }
            }
            break;
        }

        return true;
    }

    /**
     * Execute account closing
     *
     * @return bool
     */
    protected function executeClosing(): bool
    {
        $closingProcessor = new AccountingClosingClosing();
        return $closingProcessor->exec($this->fiscalPeriod, $this->closingJournalId);
    }

    /**
     * Execute account opening
     *
     * @return bool
     */
    protected function executeOpening(): bool
    {
        $openingProcessor = new AccountingClosingOpening();
        $openingProcessor->setCopySubAccounts($this->copySubAccountsFlag);
        return $openingProcessor->exec($this->fiscalPeriod, $this->openingJournalId);
    }

    /**
     * Execute account regularization
     *
     * @return bool
     */
    protected function executeRegularization(): bool
    {
        $regularizationProcessor = new AccountingClosingRegularization();
        return $regularizationProcessor->exec($this->fiscalPeriod, $this->closingJournalId);
    }

    /**
     * Update special accounts from data file.
     */
    protected function refreshSpecialAccounts(): void
    {
        $sqlStatement = CSVImport::updateTableSQL(CuentaEspecial::tableName());
        if (!empty($sqlStatement) && self::$dbInstance->tableExists(CuentaEspecial::tableName())) {
            self::$dbInstance->exec($sqlStatement);
        }
    }
}