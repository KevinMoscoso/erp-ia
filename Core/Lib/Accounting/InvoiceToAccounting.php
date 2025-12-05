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

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\DataSrc\Impuestos;
use ERPIA\Core\Lib\Calculator;
use ERPIA\Core\Lib\InvoiceOperation;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Asiento;
use ERPIA\Dinamic\Model\Cliente;
use ERPIA\Dinamic\Model\Cuenta;
use ERPIA\Dinamic\Model\FacturaCliente;
use ERPIA\Dinamic\Model\FacturaProveedor;
use ERPIA\Dinamic\Model\Join\PurchasesDocIrpfAccount;
use ERPIA\Dinamic\Model\Join\PurchasesDocLineAccount;
use ERPIA\Dinamic\Model\Join\SalesDocLineAccount;
use ERPIA\Dinamic\Model\Proveedor;
use ERPIA\Dinamic\Model\Retencion;
use ERPIA\Dinamic\Model\Serie;
use ERPIA\Dinamic\Model\Subcuenta;

/**
 * Class for the generation of accounting entries of a sale/purchase document
 * and the settlement of your receipts.
 * @author ERPIA Development Team
 */
class InvoiceToAccounting extends AccountingClass
{
    /**
     * @var Subcuenta
     */
    protected $counterpartyAccount;

    /**
     * @var FacturaCliente|FacturaProveedor
     */
    protected $invoiceDocument;

    /**
     * Document Subtotals Lines array
     * @var array
     */
    protected $calculationResults;

    /**
     * Method to launch the accounting process
     * @param FacturaCliente|FacturaProveedor $model
     */
    public function generate($model)
    {
        parent::generate($model);
        if (false === $this->initialVerifications()) {
            return;
        }
        switch ($model->modelClassName()) {
            case 'FacturaCliente':
                $this->createSalesEntry();
                break;
            case 'FacturaProveedor':
                $this->createPurchasesEntry();
                break;
        }
    }

    /**
     * Add the customer line to the accounting entry
     * @param Asiento $entry
     * @return bool
     */
    protected function addCustomerAccountLine(Asiento $entry): bool
    {
        $customerRecord = new Cliente();
        if (false === $customerRecord->load($this->invoiceDocument->codcliente)) {
            Tools::log()->warning('client-record-not-found');
            $this->counterpartyAccount = null;
            return false;
        }
        $customerSubaccount = $this->getCustomerAccount($customerRecord);
        if (false === $customerSubaccount->exists()) {
            Tools::log()->warning('client-account-missing');
            $this->counterpartyAccount = null;
            return false;
        }
        $this->counterpartyAccount = $customerSubaccount;
        return $this->addBasicAccountingLine($entry, $customerSubaccount, true);
    }

    /**
     * Add the goods purchase line to the accounting entry.
     * Make one line for each product/family purchase subaccount.
     * @param Asiento $entry
     * @return bool
     */
    protected function addPurchasedGoodsLine(Asiento $entry): bool
    {
        $rectificationAccount = $this->getSpecialSubAccount('DEVCOM');
        $purchaseAccount = $this->invoiceDocument->idfacturarect && $rectificationAccount->exists() ? $rectificationAccount :
            $this->getSpecialSubAccount('COMPRA');
        $purchaseTool = new PurchasesDocLineAccount();
        $purchaseTotals = $purchaseTool->getTotalsForDocument($this->invoiceDocument, $purchaseAccount->codsubcuenta ?? '');
        return $this->addLinesFromCalculations(
            $entry,
            $purchaseTotals,
            true,
            $this->counterpartyAccount,
            'purchases-account-not-found',
            'purchase-goods-line-error'
        );
    }

    /**
     * Add the goods sales line to the accounting entry.
     * Make one line for each product/family sale subaccount.
     * @param Asiento $entry
     * @return bool
     */
    protected function addSoldGoodsLine(Asiento $entry): bool
    {
        $rectificationAccount = $this->getSpecialSubAccount('DEVVEN');
        $salesAccount = $this->invoiceDocument->idfacturarect && $rectificationAccount->exists() ? $rectificationAccount :
            $this->getSpecialSubAccount('VENTAS');
        $salesTool = new SalesDocLineAccount();
        $salesTotals = $salesTool->getTotalsForDocument($this->invoiceDocument, $salesAccount->codsubcuenta ?? '');
        return $this->addLinesFromCalculations(
            $entry,
            $salesTotals,
            false,
            $this->counterpartyAccount,
            'sales-account-not-found',
            'sold-goods-line-error'
        );
    }

    /**
     * @param Asiento $entry
     * @return bool
     */
    protected function addPurchaseIrpfAccountLines(Asiento $entry): bool
    {
        if (empty($this->invoiceDocument->totalirpf) || count($this->calculationResults) == 0) {
            return true;
        }
        $retentionRecord = new Retencion();
        if (false === $retentionRecord->loadFromPercentage($this->calculationResults['irpf'])) {
            Tools::log()->warning('irpf-data-not-found', ['%value%' => $this->calculationResults['irpf']]);
            return false;
        }
        $irpfAccount = $this->getPurchaseIrpfAccount($retentionRecord);
        if (false === $irpfAccount->exists()) {
            Tools::log()->warning('purchase-irpf-account-missing');
            return false;
        }
        $irpfTool = new PurchasesDocIrpfAccount();
        $irpfTotals = $irpfTool->getTotalsForDocument($this->invoiceDocument, $irpfAccount->codsubcuenta ?? '', $this->calculationResults['irpf']);
        return $this->addLinesFromCalculations(
            $entry,
            $irpfTotals,
            false,
            $this->counterpartyAccount,
            'irpf-account-missing',
            'irpf-purchase-line-failure'
        );
    }

    /**
     * @param Asiento $entry
     * @return bool
     */
    protected function addPurchaseSuppliedAccountLines(Asiento $entry): bool
    {
        if (empty($this->invoiceDocument->totalsuplidos)) {
            return true;
        }
        $suppliedAccount = $this->getSpecialSubAccount('SUPLI');
        if (false === $suppliedAccount->exists()) {
            Tools::log()->warning('supplied-account-missing');
            return false;
        }
        return $this->addBasicAccountingLine($entry, $suppliedAccount, true, $this->invoiceDocument->totalsuplidos);
    }

    /**
     * Add the purchase line to the accounting entry
     * @param Asiento $entry
     * @return bool
     */
    protected function addPurchaseTaxAccountLines(Asiento $entry): bool
    {
        foreach ($this->calculationResults['iva'] as $taxDetail) {
            $taxRecord = Impuestos::get($taxDetail['codimpuesto']);
            $inputTaxAccount = $taxRecord->getInputTaxAccount($this->fiscalPeriod->codejercicio);
            if (false === $inputTaxAccount->exists()) {
                Tools::log()->warning('input-vat-account-missing');
                return false;
            }
            $inputSurchargeAccount = $taxRecord->getInputSurchargeAccount($this->fiscalPeriod->codejercicio);
            if (false === $inputSurchargeAccount->exists()) {
                Tools::log()->warning('input-surcharge-account-missing');
                return false;
            }
            $outputTaxAccount = $taxRecord->getOutputTaxAccount($this->fiscalPeriod->codejercicio);
            if (false === $outputTaxAccount->exists()) {
                Tools::log()->warning('output-vat-account-missing');
                return false;
            }
            $outputSurchargeAccount = $taxRecord->getOutputSurchargeAccount($this->fiscalPeriod->codejercicio);
            if (false === $outputSurchargeAccount->exists()) {
                Tools::log()->warning('output-surcharge-account-missing');
                return false;
            }
            if ($this->invoiceDocument->operacion === InvoiceOperation::INTRA_COMMUNITY) {
                $taxDetail['totaliva'] = round($taxDetail['neto'] * $taxDetail['iva'] / 100, 2);
                $taxDetail['totalrecargo'] = round($taxDetail['neto'] * $taxDetail['recargo'] / 100, 2);
                $operationResult = $this->addTaxAccountLine($entry, $inputTaxAccount, $this->counterpartyAccount, true, $taxDetail) &&
                    $this->addSurchargeAccountLine($entry, $inputSurchargeAccount, $this->counterpartyAccount, true, $taxDetail) &&
                    $this->addTaxAccountLine($entry, $outputTaxAccount, $this->counterpartyAccount, false, $taxDetail) &&
                    $this->addSurchargeAccountLine($entry, $outputSurchargeAccount, $this->counterpartyAccount, false, $taxDetail);
                if (false === $operationResult) {
                    return false;
                }
                continue;
            }
            $operationResult = $this->addTaxAccountLine($entry, $inputTaxAccount, $this->counterpartyAccount, true, $taxDetail) &&
                $this->addSurchargeAccountLine($entry, $inputSurchargeAccount, $this->counterpartyAccount, true, $taxDetail);
            if (false === $operationResult) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param Asiento $entry
     * @return bool
     */
    protected function addSalesIrpfAccountLines(Asiento $entry): bool
    {
        if (empty($this->invoiceDocument->totalirpf) || count($this->calculationResults) == 0) {
            return true;
        }
        $retentionRecord = new Retencion();
        if (false === $retentionRecord->loadFromPercentage($this->calculationResults['irpf'])) {
            Tools::log()->warning('irpf-data-not-found', ['%value%' => $this->calculationResults['irpf']]);
            return false;
        }
        $irpfAccount = $this->getSalesIrpfAccount($retentionRecord);
        if (false === $irpfAccount->exists()) {
            Tools::log()->warning('sales-irpf-account-missing');
            return false;
        }
        $newAccountingLine = $this->getBasicAccountingLine($entry, $irpfAccount, true, $this->calculationResults['totalirpf']);
        $newAccountingLine->setCounterpart($this->counterpartyAccount);
        return $newAccountingLine->save();
    }

    /**
     * Add the supplied line to the accounting entry
     * @param Asiento $entry
     * @return bool
     */
    protected function addSalesSuppliedAccountLines(Asiento $entry): bool
    {
        if (empty($this->invoiceDocument->totalsuplidos)) {
            return true;
        }
        $suppliedAccount = $this->getSpecialSubAccount('SUPLI');
        if (false === $suppliedAccount->exists()) {
            Tools::log()->warning('supplied-account-missing');
            return false;
        }
        return $this->addBasicAccountingLine($entry, $suppliedAccount, false, $this->invoiceDocument->totalsuplidos);
    }

    /**
     * Add the sales line to the accounting entry
     * @param Asiento $entry
     * @return bool
     */
    protected function addSalesTaxAccountLines(Asiento $entry): bool
    {
        foreach ($this->calculationResults['iva'] as $taxDetail) {
            $taxRecord = Impuestos::get($taxDetail['codimpuesto']);
            $outputTaxAccount = $taxRecord->getOutputTaxAccount($this->fiscalPeriod->codejercicio);
            if (false === $outputTaxAccount->exists()) {
                Tools::log()->warning('output-vat-account-missing');
                return false;
            }
            $outputSurchargeAccount = $taxRecord->getOutputSurchargeAccount($this->fiscalPeriod->codejercicio);
            if (false === $outputSurchargeAccount->exists()) {
                Tools::log()->warning('output-surcharge-account-missing');
                return false;
            }
            $inputTaxAccount = $taxRecord->getInputTaxAccount($this->fiscalPeriod->codejercicio);
            if (false === $inputTaxAccount->exists()) {
                Tools::log()->warning('input-vat-account-missing');
                return false;
            }
            $inputSurchargeAccount = $taxRecord->getInputSurchargeAccount($this->fiscalPeriod->codejercicio);
            if (false === $inputSurchargeAccount->exists()) {
                Tools::log()->warning('input-surcharge-account-missing');
                return false;
            }
            if ($this->invoiceDocument->operacion === InvoiceOperation::INTRA_COMMUNITY) {
                $taxDetail['totaliva'] = round($taxDetail['neto'] * $taxDetail['iva'] / 100, 2);
                $taxDetail['totalrecargo'] = round($taxDetail['neto'] * $taxDetail['recargo'] / 100, 2);
                $operationResult = $this->addTaxAccountLine($entry, $outputTaxAccount, $this->counterpartyAccount, false, $taxDetail) &&
                    $this->addSurchargeAccountLine($entry, $outputSurchargeAccount, $this->counterpartyAccount, false, $taxDetail) &&
                    $this->addTaxAccountLine($entry, $inputTaxAccount, $this->counterpartyAccount, true, $taxDetail) &&
                    $this->addSurchargeAccountLine($entry, $inputSurchargeAccount, $this->counterpartyAccount, true, $taxDetail);
                if (false === $operationResult) {
                    return false;
                }
                continue;
            }
            $operationResult = $this->addTaxAccountLine($entry, $outputTaxAccount, $this->counterpartyAccount, false, $taxDetail) &&
                $this->addSurchargeAccountLine($entry, $outputSurchargeAccount, $this->counterpartyAccount, false, $taxDetail);
            if (false === $operationResult) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param Asiento $entry
     * @return bool
     */
    protected function addSupplierAccountLine(Asiento $entry): bool
    {
        $supplierRecord = new Proveedor();
        if (false === $supplierRecord->load($this->invoiceDocument->codproveedor)) {
            Tools::log()->warning('supplier-record-not-found');
            $this->counterpartyAccount = null;
            return false;
        }
        $supplierSubaccount = $this->getSupplierAccount($supplierRecord);
        if (false === $supplierSubaccount->exists()) {
            Tools::log()->warning('supplier-account-missing');
            $this->counterpartyAccount = null;
            return false;
        }
        $this->counterpartyAccount = $supplierSubaccount;
        return $this->addBasicAccountingLine($entry, $supplierSubaccount, false);
    }

    /**
     * Perform the initial checks to continue with the accounting process
     * @return bool
     */
    protected function initialVerifications(): bool
    {
        if (!empty($this->invoiceDocument->idasiento)) {
            Tools::log()->warning('document-already-accounted', ['%document%' => $this->invoiceDocument->codigo]);
            return false;
        }
        if (empty($this->invoiceDocument->total)) {
            Tools::log()->warning('document-total-missing', ['%document%' => $this->invoiceDocument->codigo]);
            return false;
        }
        if (false === $this->fiscalPeriod->loadFromCode($this->invoiceDocument->codejercicio) || false === $this->fiscalPeriod->isOpened()) {
            Tools::log()->warning('fiscal-period-closed', ['%exerciseName%' => $this->invoiceDocument->codejercicio]);
            return false;
        }
        if (false === $this->fiscalPeriod->hasAccountingPlan()) {
            Tools::log()->warning('fiscal-period-no-accounting-plan', ['%exercise%' => $this->fiscalPeriod->codejercicio]);
            return false;
        }
        if (false === $this->loadCalculationResults()) {
            Tools::log()->warning('invoice-calculations-error');
            return false;
        }
        $accountConditions = [new DataBaseWhere('codejercicio', $this->invoiceDocument->codejercicio)];
        if (0 === Cuenta::count($accountConditions)) {
            Tools::log()->warning('accounting-data-absent', ['%exerciseName%' => $this->invoiceDocument->codejercicio]);
            return false;
        }
        return true;
    }

    protected function loadCalculationResults(): bool
    {
        $this->calculationResults = Calculator::getSubtotals($this->invoiceDocument, $this->invoiceDocument->getLines());
        return !empty($this->invoiceDocument->total);
    }

    /**
     * Generate the accounting entry for a purchase document.
     */
    protected function createPurchasesEntry()
    {
        $entryDescription = Tools::trans('supplier-invoice') . ' ' . $this->invoiceDocument->codigo;
        $entryDescription .= $this->invoiceDocument->numproveedor ? ' (' . $this->invoiceDocument->numproveedor . ') - ' . $this->invoiceDocument->nombre :
            ' - ' . $this->invoiceDocument->nombre;
        $accountingEntry = new Asiento();
        $this->setAccountingEntryData($accountingEntry, $entryDescription);
        if (false === $accountingEntry->save()) {
            Tools::log()->warning('accounting-entry-failure');
            return;
        }
        if ($this->addSupplierAccountLine($accountingEntry) &&
            $this->addPurchaseTaxAccountLines($accountingEntry) &&
            $this->addPurchaseIrpfAccountLines($accountingEntry) &&
            $this->addPurchaseSuppliedAccountLines($accountingEntry) &&
            $this->addPurchasedGoodsLine($accountingEntry) &&
            $accountingEntry->isBalanced()) {
            $this->invoiceDocument->idasiento = $accountingEntry->id();
            return;
        }
        Tools::log()->warning('accounting-lines-failure');
        $accountingEntry->delete();
    }

    /**
     * Generate the accounting entry for a sales document.
     */
    protected function createSalesEntry()
    {
        $entryDescription = Tools::trans('customer-invoice') . ' ' . $this->invoiceDocument->codigo;
        $entryDescription .= $this->invoiceDocument->numero2 ? ' (' . $this->invoiceDocument->numero2 . ') - ' . $this->invoiceDocument->nombrecliente :
            ' - ' . $this->invoiceDocument->nombrecliente;
        $accountingEntry = new Asiento();
        $this->setAccountingEntryData($accountingEntry, $entryDescription);
        if (false === $accountingEntry->save()) {
            Tools::log()->warning('accounting-entry-failure');
            return;
        }
        if ($this->addCustomerAccountLine($accountingEntry) &&
            $this->addSalesTaxAccountLines($accountingEntry) &&
            $this->addSalesIrpfAccountLines($accountingEntry) &&
            $this->addSalesSuppliedAccountLines($accountingEntry) &&
            $this->addSoldGoodsLine($accountingEntry) &&
            $accountingEntry->isBalanced()) {
            $this->invoiceDocument->idasiento = $accountingEntry->id();
            return;
        }
        Tools::log()->warning('accounting-lines-failure');
        $accountingEntry->delete();
    }

    /**
     * Assign the document data to the accounting entry
     * @param Asiento $entry
     * @param string $concept
     */
    protected function setAccountingEntryData(Asiento &$entry, string $concept)
    {
        $entry->codejercicio = $this->invoiceDocument->codejercicio;
        $entry->concepto = $concept;
        $entry->documento = $this->invoiceDocument->codigo;
        $entry->fecha = $this->invoiceDocument->fechadevengo ?? $this->invoiceDocument->fecha;
        $entry->idempresa = $this->invoiceDocument->idempresa;
        $entry->importe = $this->invoiceDocument->total;
        $seriesRecord = new Serie();
        $seriesRecord->load($this->invoiceDocument->codserie);
        $entry->iddiario = $seriesRecord->iddiario;
        $entry->canal = $seriesRecord->canal;
    }
}