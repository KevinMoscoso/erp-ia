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
use ERPIA\Dinamic\Model\Cliente;
use ERPIA\Dinamic\Model\Cuenta;
use ERPIA\Dinamic\Model\CuentaBanco;
use ERPIA\Dinamic\Model\Ejercicio;
use ERPIA\Dinamic\Model\FormaPago;
use ERPIA\Dinamic\Model\GrupoClientes;
use ERPIA\Dinamic\Model\Impuesto;
use ERPIA\Dinamic\Model\Proveedor;
use ERPIA\Dinamic\Model\Retencion;
use ERPIA\Dinamic\Model\Subcuenta;

/**
 * Class for calculate/obtain accounting sub-account of:
 * (Respecting the additional levels)
 *   - Customer
 *   - Customer Group
 *   - Supplier
 *   - Payment
 */
class AccountingAccounts
{
    const SPECIAL_CUSTOMER_ACCOUNT = 'CLIENT';
    const SPECIAL_CREDITOR_ACCOUNT = 'ACREED';
    const SPECIAL_EXPENSE_ACCOUNT = 'GTOBAN';
    const SPECIAL_PAYMENT_ACCOUNT = 'CAJA';
    const SPECIAL_SUPPLIER_ACCOUNT = 'PROVEE';
    const SPECIAL_TAX_IMPACTED_ACCOUNT = 'IVAREP';
    const SPECIAL_TAX_SUPPORTED_ACCOUNT = 'IVASOP';
    const SPECIAL_IRPF_SALES_ACCOUNT = 'IRPF';
    const SPECIAL_IRPF_PURCHASE_ACCOUNT = 'IRPFPR';
    const SPECIAL_PROFIT_LOSS_ACCOUNT = 'PYG';
    const SPECIAL_POSITIVE_PREV_ACCOUNT = 'PREVIO';
    const SPECIAL_NEGATIVE_PREV_ACCOUNT = 'PRENEG';

    /**
     * @var AccountingCreation
     */
    protected $accountCreator;

    /**
     * @var Ejercicio
     */
    public $currentExercise;

    public function __construct()
    {
        $this->accountCreator = new AccountingCreation();
        $this->currentExercise = new Ejercicio();
    }

    public function getCustomerAccount(Cliente $customer, string $specialAccount = self::SPECIAL_CUSTOMER_ACCOUNT): Subcuenta
    {
        if (!empty($customer->codsubcuenta)) {
            $subAccount = $this->findSubAccount($customer->codsubcuenta);
            if ($subAccount->exists()) {
                return $subAccount;
            }
            $parentAccount = $this->findSpecialAccount($specialAccount);
            return $this->accountCreator->createSubjectAccount($customer, $parentAccount);
        }

        $group = new GrupoClientes();
        if (!empty($customer->codgrupo) && $group->loadByCode($customer->codgrupo)) {
            $groupSubAccount = $this->getCustomerGroupAccount($group, $specialAccount);
            if ($groupSubAccount->exists()) {
                return $groupSubAccount;
            }
        }

        $parentAccount = $this->findSpecialAccount($specialAccount);
        return $this->accountCreator->createSubjectAccount($customer, $parentAccount);
    }

    public function getCustomerGroupAccount(GrupoClientes $group, string $specialAccount = self::SPECIAL_CUSTOMER_ACCOUNT): Subcuenta
    {
        if (empty($group->codsubcuenta)) {
            return new Subcuenta();
        }

        $subAccount = $this->findSubAccount($group->codsubcuenta);
        if ($subAccount->exists()) {
            return $subAccount;
        }

        $parentAccount = $this->findSpecialAccount($specialAccount);
        return $this->accountCreator->createFromAccount($parentAccount, $group->codsubcuenta, $group->nombre);
    }

    public function getExpenseAccount(string $paymentCode, string $specialAccount = self::SPECIAL_EXPENSE_ACCOUNT): Subcuenta
    {
        $bankAccount = new CuentaBanco();
        $paymentMethod = new FormaPago();
        if ($paymentMethod->loadByCode($paymentCode) &&
            $paymentMethod->codcuentabanco &&
            $bankAccount->loadByCode($paymentMethod->codcuentabanco) &&
            !empty($bankAccount->codsubcuentagasto)) {
            $subAccount = $this->findSubAccount($bankAccount->codsubcuentagasto);
            return $subAccount->exists() ? $subAccount : $this->getSpecialSubAccount($specialAccount);
        }

        return $this->getSpecialSubAccount($specialAccount);
    }

    public function getIRPFPurchaseAccount(Retencion $retencion, string $specialAccount = self::SPECIAL_IRPF_PURCHASE_ACCOUNT): Subcuenta
    {
        return $this->getAccountFromCode($retencion->codsubcuentaacr, $specialAccount);
    }

    public function getIRPFSalesAccount(Retencion $retencion, string $specialAccount = self::SPECIAL_IRPF_SALES_ACCOUNT): Subcuenta
    {
        return $this->getAccountFromCode($retencion->codsubcuentaret, $specialAccount);
    }

    public function getPaymentAccount(string $paymentCode, string $specialAccount = self::SPECIAL_PAYMENT_ACCOUNT): Subcuenta
    {
        $bankAccount = new CuentaBanco();
        $paymentMethod = new FormaPago();
        if ($paymentMethod->loadByCode($paymentCode) &&
            $paymentMethod->codcuentabanco &&
            $bankAccount->loadByCode($paymentMethod->codcuentabanco) &&
            !empty($bankAccount->codsubcuenta)) {
            $subAccount = $this->findSubAccount($bankAccount->codsubcuenta);
            return $subAccount->exists() ? $subAccount : $this->getSpecialSubAccount($specialAccount);
        }

        return $this->getSpecialSubAccount($specialAccount);
    }

    public function findSpecialAccount(string $specialAccount): Cuenta
    {
        $conditions = [
            new DataBaseWhere('codejercicio', $this->currentExercise->codejercicio),
            new DataBaseWhere('codcuentaesp', $specialAccount)
        ];
        $order = ['codcuenta' => 'ASC'];
        $account = new Cuenta();
        $account->loadFromConditions($conditions, $order);
        return $account;
    }

    public function getSpecialSubAccount(string $specialAccount): Subcuenta
    {
        $conditions = [
            new DataBaseWhere('codejercicio', $this->currentExercise->codejercicio),
            new DataBaseWhere('codcuentaesp', $specialAccount)
        ];
        $order = ['codsubcuenta' => 'ASC'];
        $subAccount = new Subcuenta();
        if ($subAccount->loadFromConditions($conditions, $order)) {
            return $subAccount;
        }

        $parentAccount = $this->findSpecialAccount($specialAccount);
        foreach ($parentAccount->getSubAccounts() as $sub) {
            return $sub;
        }

        return new Subcuenta();
    }

    public function findSubAccount(string $code): Subcuenta
    {
        $conditions = [
            new DataBaseWhere('codejercicio', $this->currentExercise->codejercicio),
            new DataBaseWhere('codsubcuenta', $code)
        ];
        $subAccount = new Subcuenta();
        $subAccount->loadFromConditions($conditions);
        return $subAccount;
    }

    public function getSupplierAccount(Proveedor $supplier, string $specialAccount = self::SPECIAL_SUPPLIER_ACCOUNT): Subcuenta
    {
        if ($supplier->acreedor) {
            $specialAccount = self::SPECIAL_CREDITOR_ACCOUNT;
        }

        if (!empty($supplier->codsubcuenta)) {
            $subAccount = $this->findSubAccount($supplier->codsubcuenta);
            if ($subAccount->exists()) {
                return $subAccount;
            }
            $parentAccount = $this->findSpecialAccount($specialAccount);
            return $this->accountCreator->createSubjectAccount($supplier, $parentAccount);
        }

        $parentAccount = $this->findSpecialAccount($specialAccount);
        return $this->accountCreator->createSubjectAccount($supplier, $parentAccount);
    }

    public function getTaxImpactedAccount(Impuesto $tax, string $specialAccount = self::SPECIAL_TAX_IMPACTED_ACCOUNT): Subcuenta
    {
        return $this->getAccountFromCode($tax->codsubcuentarep, $specialAccount);
    }

    public function getTaxSupportedAccount(Impuesto $tax, string $specialAccount = self::SPECIAL_TAX_SUPPORTED_ACCOUNT): Subcuenta
    {
        return $this->getAccountFromCode($tax->codsubcuentasop, $specialAccount);
    }

    private function getAccountFromCode(string $code, string $specialAccount): Subcuenta
    {
        if (!empty($code)) {
            $subAccount = $this->findSubAccount($code);
            if ($subAccount->exists()) {
                return $subAccount;
            }
            $parentAccount = $this->findSpecialAccount($specialAccount);
            return $this->accountCreator->createFromAccount($parentAccount, $code);
        }

        $subAccount = $this->getSpecialSubAccount($specialAccount);
        if ($subAccount->exists()) {
            return $subAccount;
        }

        $parentAccount = $this->findSpecialAccount($specialAccount);
        $firstSubAccount = new Subcuenta();
        $firstSubAccount->loadFromConditions(
            [new DataBaseWhere('idcuenta', $parentAccount->idcuenta)],
            ['idsubcuenta' => 'ASC']
        );
        return $firstSubAccount;
    }
}