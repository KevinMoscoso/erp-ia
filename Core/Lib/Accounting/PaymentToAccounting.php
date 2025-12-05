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

use ERPIA\Core\Model\Asiento;
use ERPIA\Core\Model\PagoCliente;
use ERPIA\Core\Model\PagoProveedor;
use ERPIA\Core\Model\ReciboCliente;
use ERPIA\Core\Model\ReciboProveedor;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Asiento as DinAsiento;
use ERPIA\Dinamic\Model\Ejercicio;

/**
 * Description of PaymentToAccounting
 *
 * @author ERPIA Development Team
 */
class PaymentToAccounting
{
    /** @var Ejercicio */
    protected $fiscalPeriod;

    /** @var PagoCliente|PagoProveedor */
    protected $paymentRecord;

    /** @var ReciboCliente|ReciboProveedor */
    protected $receiptRecord;

    public function __construct()
    {
        $this->fiscalPeriod = new Ejercicio();
    }

    /**
     * @param PagoCliente|PagoProveedor $payment
     * @return bool
     */
    public function generate($payment): bool
    {
        switch ($payment->modelClassName()) {
            case 'PagoCliente':
            case 'PagoProveedor':
                $this->paymentRecord = $payment;
                $this->receiptRecord = $payment->getReceipt();
                $this->fiscalPeriod->idempresa = $this->receiptRecord->idempresa;
                if (false === $this->fiscalPeriod->loadFromDate($this->paymentRecord->fecha)) {
                    Tools::log()->warning('closed-fiscal-period', [
                        '%period%' => $this->fiscalPeriod->codejercicio
                    ]);
                    return false;
                }
                if (false === $this->fiscalPeriod->hasAccountingPlan()) {
                    Tools::log()->warning('period-without-accounting-plan', [
                        '%period%' => $this->fiscalPeriod->codejercicio
                    ]);
                    return false;
                }
                break;
        }

        switch ($payment->modelClassName()) {
            case 'PagoCliente':
                return $this->createCustomerPaymentEntry();

            case 'PagoProveedor':
                return $this->createSupplierPaymentEntry();
        }

        return false;
    }

    protected function createCustomerPaymentEntry(): bool
    {
        $accountingEntry = new DinAsiento();

        $entryConcept = $this->paymentRecord->importe > 0 ?
            Tools::trans('customer-payment-description', ['%document%' => $this->receiptRecord->getCode()]) :
            Tools::trans('payment-refund-description', ['%document%' => $this->receiptRecord->getCode()]);

        $invoiceDocument = $this->receiptRecord->getInvoice();
        $entryConcept .= $invoiceDocument->numero2 ?
            ' (' . $invoiceDocument->numero2 . ') - ' . $invoiceDocument->nombrecliente :
            ' - ' . $invoiceDocument->nombrecliente;

        $this->setEntryCommonData($accountingEntry, $entryConcept, $invoiceDocument);
        $accountingEntry->importe += $this->paymentRecord->gastos;
        if (false === $accountingEntry->save()) {
            Tools::log()->warning('accounting-entry-failure');
            return false;
        }

        if ($this->addCustomerAccountLine($accountingEntry)
            && $this->addBankAccountLine($accountingEntry)
            && $this->addExpenseAccountLine($accountingEntry)
            && $accountingEntry->isBalanced()) {
            $this->paymentRecord->idasiento = $accountingEntry->id();
            return true;
        }

        Tools::log()->warning('accounting-lines-failure');
        $accountingEntry->delete();
        return false;
    }

    protected function addBankAccountLine(Asiento &$entry): bool
    {
        $bankAccount = $this->paymentRecord->getPaymentMethod()->getSubcuenta($this->fiscalPeriod->codejercicio, true);
        if (false === $bankAccount->exists()) {
            return false;
        }

        $paymentAmount = $this->paymentRecord->importe + abs($this->paymentRecord->gastos);

        $accountLine = $entry->getNewLine($bankAccount);
        $accountLine->debe = max($paymentAmount, 0);
        $accountLine->haber = $paymentAmount < 0 ? abs($paymentAmount) : 0;
        return $accountLine->save();
    }

    protected function addExpenseAccountLine(Asiento &$entry): bool
    {
        if (empty($this->paymentRecord->gastos)) {
            return true;
        }

        $expenseAccount = $this->paymentRecord->getPaymentMethod()->getSubcuentaGastos($this->fiscalPeriod->codejercicio, true);

        $expenseLine = $entry->getNewLine($expenseAccount);
        $expenseLine->concepto = Tools::trans('receipt-expense-line', ['%document%' => $entry->documento]);
        $expenseLine->haber = abs($this->paymentRecord->gastos);
        return $expenseLine->save();
    }

    protected function addCustomerAccountLine(Asiento &$entry): bool
    {
        $customerAccount = $this->receiptRecord->getSubject()->getSubcuenta($this->fiscalPeriod->codejercicio, true);
        if (false === $customerAccount->exists()) {
            return false;
        }

        $accountLine = $entry->getNewLine($customerAccount);
        $accountLine->debe = $this->paymentRecord->importe < 0 ? abs($this->paymentRecord->importe) : 0;
        $accountLine->haber = max($this->paymentRecord->importe, 0);
        return $accountLine->save();
    }

    protected function createSupplierPaymentEntry(): bool
    {
        $accountingEntry = new DinAsiento();

        $entryConcept = $this->paymentRecord->importe > 0 ?
            Tools::trans('supplier-payment-description', ['%document%' => $this->receiptRecord->getCode()]) :
            Tools::trans('payment-refund-description', ['%document%' => $this->receiptRecord->getCode()]);

        $invoiceDocument = $this->receiptRecord->getInvoice();
        $entryConcept .= $invoiceDocument->numproveedor ?
            ' (' . $invoiceDocument->numproveedor . ') - ' . $invoiceDocument->nombre :
            ' - ' . $invoiceDocument->nombre;

        $this->setEntryCommonData($accountingEntry, $entryConcept, $invoiceDocument);
        if (false === $accountingEntry->save()) {
            Tools::log()->warning('accounting-entry-failure');
            return false;
        }

        if ($this->addSupplierAccountLine($accountingEntry)
            && $this->addSupplierBankAccountLine($accountingEntry)
            && $accountingEntry->isBalanced()) {
            $this->paymentRecord->idasiento = $accountingEntry->id();
            return true;
        }

        Tools::log()->warning('accounting-lines-failure');
        $accountingEntry->delete();
        return false;
    }

    protected function addSupplierBankAccountLine(Asiento &$entry): bool
    {
        $bankAccount = $this->paymentRecord->getPaymentMethod()->getSubcuenta($this->fiscalPeriod->codejercicio, true);
        if (false === $bankAccount->exists()) {
            return false;
        }

        $accountLine = $entry->getNewLine($bankAccount);
        $accountLine->debe = $this->paymentRecord->importe < 0 ? abs($this->paymentRecord->importe) : 0;
        $accountLine->haber = max($this->paymentRecord->importe, 0);
        return $accountLine->save();
    }

    protected function addSupplierAccountLine(Asiento &$entry): bool
    {
        $supplierAccount = $this->receiptRecord->getSubject()->getSubcuenta($this->fiscalPeriod->codejercicio, true);
        if (false === $supplierAccount->exists()) {
            return false;
        }

        $accountLine = $entry->getNewLine($supplierAccount);
        $accountLine->debe = max($this->paymentRecord->importe, 0);
        $accountLine->haber = $this->paymentRecord->importe < 0 ? abs($this->paymentRecord->importe) : 0;
        return $accountLine->save();
    }

    protected function setEntryCommonData(Asiento &$entry, string $concept, $invoice): void
    {
        $entry->codejercicio = $this->fiscalPeriod->codejercicio;
        $entry->concepto = $concept;
        $entry->documento = $invoice->codigo;
        $entry->canal = $invoice->getSerie()->canal;
        $entry->fecha = $this->paymentRecord->fecha;
        $entry->idempresa = $this->fiscalPeriod->idempresa;
        $entry->importe = $this->paymentRecord->importe;
    }
}