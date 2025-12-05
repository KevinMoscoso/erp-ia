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

use ERPIA\Core\Model\Base\ModelClass;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Asiento;
use ERPIA\Dinamic\Model\Partida;
use ERPIA\Dinamic\Model\Subcuenta;

/**
 * Base class for creation of accounting processes
 */
abstract class AccountingClass extends AccountingAccounts
{
    /** @var ModelClass */
    protected $sourceDocument;

    /**
     * Method to launch the accounting process
     *
     * @param ModelClass $model
     */
    public function generate($model)
    {
        $this->sourceDocument = $model;
        $this->currentExercise->idempresa = $model->idempresa ?? Tools::config('default', 'idempresa');
    }

    /**
     * Add a standard line to the accounting entry based on the reported sub-account
     *
     * @param Asiento $accountEntry
     * @param Subcuenta $subaccount
     * @param bool $isDebit
     * @param float $amount
     *
     * @return bool
     */
    protected function addBasicLine($accountEntry, $subaccount, $isDebit, $amount = null): bool
    {
        return $this->createBasicLine($accountEntry, $subaccount, $isDebit, $amount)->save();
    }

    /**
     * Add a group of lines from array of subaccounts/amount.
     *
     * @param Asiento $accountEntry
     * @param array $totals
     * @param bool $isDebit
     * @param Subcuenta $counterpart
     * @param string $accountError
     * @param string $saveError
     *
     * @return bool
     */
    protected function addLinesFromTotals($accountEntry, $totals, $isDebit, $counterpart, $accountError, $saveError): bool
    {
        foreach ($totals as $code => $total) {
            $subaccount = $this->findSubAccount($code);
            if (empty($subaccount->codsubcuenta)) {
                Tools::log()->warning($accountError);
                return false;
            }

            $line = $this->createBasicLine($accountEntry, $subaccount, $isDebit, $total);

            if (!empty($counterpart)) {
                $line->setCounterpart($counterpart);
            }

            if (false === $line->save()) {
                Tools::log()->warning($saveError);
                return false;
            }
        }

        return true;
    }

    /**
     * Add a line of taxes to the accounting entry based on the sub-account
     * and values reported
     *
     * @param Asiento $accountEntry
     * @param Subcuenta $subaccount
     * @param Subcuenta $counterpart
     * @param bool $isDebit
     * @param array $values
     *
     * @return bool
     */
    protected function addSurchargeLine($accountEntry, $subaccount, $counterpart, $isDebit, $values): bool
    {
        if (empty($values['totalrecargo'])) {
            return true;
        }

        // add basic data
        $line = $this->createBasicLine($accountEntry, $subaccount, $isDebit, $values['totalrecargo']);

        // counterpart?
        if (!empty($counterpart)) {
            $line->setCounterpart($counterpart);
        }

        // add tax register data
        $line->baseimponible = (float)$values['neto'];
        $line->iva = 0;
        $line->recargo = (float)$values['recargo'];
        $line->cifnif = $this->sourceDocument->cifnif;
        $line->codserie = $this->sourceDocument->codserie;
        $line->documento = $this->sourceDocument->codigo;
        $line->factura = $this->sourceDocument->numero;

        // save new line
        return $line->save();
    }

    /**
     * Add a line of taxes to the accounting entry based on the sub-account
     * and values reported
     *
     * @param Asiento $accountEntry
     * @param Subcuenta $subaccount
     * @param Subcuenta $counterpart
     * @param bool $isDebit
     * @param array $values
     *
     * @return bool
     */
    protected function addTaxLine($accountEntry, $subaccount, $counterpart, $isDebit, $values): bool
    {
        // add basic data
        $line = $this->createBasicLine($accountEntry, $subaccount, $isDebit, $values['totaliva']);

        // counterpart?
        if (!empty($counterpart)) {
            $line->setCounterpart($counterpart);
        }

        // add tax register data
        $line->baseimponible = (float)$values['neto'];
        $line->iva = (float)$values['iva'];
        $line->recargo = 0;
        $line->cifnif = $this->sourceDocument->cifnif;
        $line->codserie = $this->sourceDocument->codserie;
        $line->documento = $this->sourceDocument->codigo;
        $line->factura = $this->sourceDocument->numero;

        // save new line
        return $line->save();
    }

    /**
     * Obtain a standard line to the accounting entry based on the reported sub-account
     *
     * @param Asiento $accountEntry
     * @param Subcuenta $subaccount
     * @param bool $isDebit
     * @param float $amount
     *
     * @return Partida
     */
    protected function createBasicLine($accountEntry, $subaccount, $isDebit, $amount = null)
    {
        $line = $accountEntry->newLine();
        $line->setAccount($subaccount);

        $total = ($amount === null) ? $this->sourceDocument->total : $amount;
        if ($isDebit) {
            $line->debe = max($total, 0);
            $line->haber = $total < 0 ? abs($total) : 0;
            return $line;
        }

        $line->debe = $total < 0 ? abs($total) : 0;
        $line->haber = max($total, 0);
        return $line;
    }

    /**
     * Alias for createBasicLine to maintain compatibility with original method name.
     * @deprecated Use createBasicLine instead.
     */
    protected function getBasicLine($accountEntry, $subaccount, $isDebit, $amount = null)
    {
        return $this->createBasicLine($accountEntry, $subaccount, $isDebit, $amount);
    }
}