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
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Ejercicio;
use ERPIA\Dinamic\Model\Partida;

/**
 * Description of Ledger
 *
 * @author ERPIA Development Team
 */
class Ledger
{
    /** @var DataBase */
    protected $dbConnection;

    /** @var string */
    protected $startDate;

    /** @var string */
    protected $endDate;

    /** @var Ejercicio */
    protected $fiscalPeriod;

    /** @var string */
    protected $outputFormat;

    public function __construct()
    {
        $this->dbConnection = new DataBase();

        // needed dependencies
        new Partida();
    }

    public function generate(int $companyId, string $dateFrom, string $dateTo, array $parameters = []): array
    {
        $this->fiscalPeriod = new Ejercicio();
        $this->fiscalPeriod->idempresa = $companyId;
        if (false === $this->fiscalPeriod->loadFromDate($dateFrom, false, false)) {
            return [];
        }

        $this->startDate = $dateFrom;
        $this->endDate = $dateTo;
        $this->outputFormat = $parameters['format'] ?? 'csv';
        $debitTotal = $creditTotal = 0.0;
        $ledgerData = [];

        switch ($parameters['grouped'] ?? '') {
            case 'C':
                // group by account
                $accountBalances = [];
                foreach ($this->fetchGroupedByAccount($parameters) as $record) {
                    $this->processGroupedAccountRecord($accountBalances, $ledgerData, $record);
                    $debitTotal += (float)$record['debe'];
                    $creditTotal += (float)$record['haber'];
                }
                $ledgerData['totals'] = [[
                    'debe' => $this->formatCurrency($debitTotal, true),
                    'haber' => $this->formatCurrency($creditTotal, true),
                    'saldo' => $this->formatCurrency($debitTotal - $creditTotal, true)
                ]];
                break;

            case 'S':
                // group by subaccount
                $subAccountBalances = [];
                foreach ($this->fetchGroupedBySubAccount($parameters) as $record) {
                    $this->processGroupedSubAccountRecord($subAccountBalances, $ledgerData, $record);
                    $debitTotal += (float)$record['debe'];
                    $creditTotal += (float)$record['haber'];
                }
                $ledgerData['totals'] = [[
                    'debe' => $this->formatCurrency($debitTotal, true),
                    'haber' => $this->formatCurrency($creditTotal, true),
                    'saldo' => $this->formatCurrency($debitTotal - $creditTotal, true)
                ]];
                break;

            default:
                // do not group data
                $ledgerData['lines'] = [];
                foreach ($this->fetchDetailedData($parameters) as $record) {
                    $this->processDetailedRecord($ledgerData['lines'], $record, $parameters);
                    $debitTotal += (float)$record['debe'];
                    $creditTotal += (float)$record['haber'];
                }
                $ledgerData['lines'][] = [
                    'asiento' => '',
                    'fecha' => '',
                    'concepto' => '',
                    'debe' => $this->formatCurrency($debitTotal, true),
                    'haber' => $this->formatCurrency($creditTotal, true),
                    'saldo' => $this->formatCurrency($debitTotal - $creditTotal, true)
                ];
                break;
        }

        return $ledgerData;
    }

    protected function formatCurrency(float $amount, bool $boldText): string
    {
        $decimalPlaces = Tools::settings('default', 'decimals', 2);
        $decimalSeparator = Tools::settings('default', 'decimal_separator', ',');
        $thousandsSeparator = Tools::settings('default', 'thousands_separator', ' ');

        if ($this->outputFormat != 'PDF') {
            return number_format($amount, $decimalPlaces, '.', '');
        }

        $formattedAmount = number_format($amount, $decimalPlaces, $decimalSeparator, $thousandsSeparator);
        return $boldText ? '<b>' . $formattedAmount . '</b>' : $formattedAmount;
    }

    protected function fetchDetailedData(array $parameters = []): array
    {
        if (false === $this->dbConnection->tableExists('partidas')) {
            return [];
        }

        $query = 'SELECT asientos.numero, asientos.fecha, partidas.codsubcuenta,'
            . ' partidas.concepto, partidas.debe, partidas.haber, partidas.saldo,'
            . ' subcuentas.codcuenta, subcuentas.descripcion as subcuentadesc,'
            . ' cuentas.descripcion as cuentadesc'
            . ' FROM partidas'
            . ' LEFT JOIN asientos ON partidas.idasiento = asientos.idasiento'
            . ' LEFT JOIN subcuentas ON subcuentas.idsubcuenta = partidas.idsubcuenta'
            . ' LEFT JOIN cuentas ON cuentas.idcuenta = subcuentas.idcuenta'
            . ' WHERE ' . $this->buildWhereConditions($parameters)
            . ' ORDER BY asientos.numero, partidas.codsubcuenta ASC';
        return $this->dbConnection->select($query);
    }

    protected function fetchGroupedByAccount(array $parameters = []): array
    {
        if (false === $this->dbConnection->tableExists('partidas')) {
            return [];
        }

        $query = 'SELECT asientos.numero, asientos.fecha, partidas.codsubcuenta,'
            . ' partidas.concepto, partidas.debe, partidas.haber, partidas.saldo,'
            . ' subcuentas.codcuenta, subcuentas.descripcion as subcuentadesc,'
            . ' cuentas.descripcion as cuentadesc'
            . ' FROM partidas'
            . ' LEFT JOIN asientos ON partidas.idasiento = asientos.idasiento'
            . ' LEFT JOIN subcuentas ON subcuentas.idsubcuenta = partidas.idsubcuenta'
            . ' LEFT JOIN cuentas ON cuentas.idcuenta = subcuentas.idcuenta'
            . ' WHERE ' . $this->buildWhereConditions($parameters)
            . ' ORDER BY cuentas.codcuenta, asientos.numero ASC';
        return $this->dbConnection->select($query);
    }

    protected function fetchGroupedBySubAccount(array $parameters = []): array
    {
        if (false === $this->dbConnection->tableExists('partidas')) {
            return [];
        }

        $query = 'SELECT asientos.numero, asientos.fecha, partidas.codsubcuenta,'
            . ' partidas.concepto, partidas.debe, partidas.haber, partidas.saldo,'
            . ' subcuentas.codcuenta, subcuentas.descripcion as subcuentadesc,'
            . ' cuentas.descripcion as cuentadesc'
            . ' FROM partidas'
            . ' LEFT JOIN asientos ON partidas.idasiento = asientos.idasiento'
            . ' LEFT JOIN subcuentas ON subcuentas.idsubcuenta = partidas.idsubcuenta'
            . ' LEFT JOIN cuentas ON cuentas.idcuenta = subcuentas.idcuenta'
            . ' WHERE ' . $this->buildWhereConditions($parameters)
            . ' ORDER BY partidas.codsubcuenta, asientos.numero ASC';

        return $this->dbConnection->select($query);
    }

    protected function buildWhereConditions(array $parameters = []): string
    {
        $conditions = 'asientos.codejercicio = ' . $this->dbConnection->escapeString($this->fiscalPeriod->codejercicio)
            . ' AND asientos.fecha BETWEEN ' . $this->dbConnection->escapeString($this->startDate)
            . ' AND ' . $this->dbConnection->escapeString($this->endDate);

        $channelFilter = $parameters['channel'] ?? '';
        if (!empty($channelFilter)) {
            $conditions .= ' AND asientos.canal = ' . $this->dbConnection->escapeString($channelFilter);
        }

        $accountFrom = $parameters['account-from'] ?? '';
        $accountTo = $parameters['account-to'] ?? $accountFrom;
        if (!empty($accountFrom) || !empty($accountTo)) {
            $conditions .= ' AND subcuentas.codcuenta BETWEEN ' . $this->dbConnection->escapeString($accountFrom)
                . ' AND ' . $this->dbConnection->escapeString($accountTo);
        }

        $subAccountFrom = $parameters['subaccount-from'] ?? '';
        $subAccountTo = $parameters['subaccount-to'] ?? $subAccountFrom;
        if (!empty($subAccountFrom) || !empty($subAccountTo)) {
            $conditions .= ' AND partidas.codsubcuenta BETWEEN ' . $this->dbConnection->escapeString($subAccountFrom)
                . ' AND ' . $this->dbConnection->escapeString($subAccountTo);
        }

        $entryFrom = $parameters['entry-from'] ?? '';
        $entryTo = $parameters['entry-to'] ?? $entryFrom;
        if (!empty($entryFrom) || !empty($entryTo)) {
            $conditions .= ' AND asientos.numero BETWEEN ' . $this->dbConnection->escapeString($entryFrom)
                . ' AND ' . $this->dbConnection->escapeString($entryTo);
        }

        return $conditions;
    }

    protected function getAccountBalance(string $accountCode): float
    {
        $balanceQuery = 'SELECT SUM(partidas.debe) as debe, SUM(partidas.haber) as haber'
            . ' FROM partidas'
            . ' LEFT JOIN asientos ON partidas.idasiento = asientos.idasiento'
            . ' LEFT JOIN subcuentas ON subcuentas.idsubcuenta = partidas.idsubcuenta'
            . ' LEFT JOIN cuentas ON cuentas.idcuenta = subcuentas.idcuenta'
            . ' WHERE cuentas.codcuenta = ' . $this->dbConnection->escapeString($accountCode)
            . ' AND asientos.codejercicio = ' . $this->dbConnection->escapeString($this->fiscalPeriod->codejercicio)
            . ' AND asientos.fecha < ' . $this->dbConnection->escapeString($this->startDate);
        foreach ($this->dbConnection->select($balanceQuery) as $row) {
            return (float)$row['debe'] - (float)$row['haber'];
        }

        return 0.00;
    }

    protected function getSubAccountBalance(string $subAccountCode): float
    {
        $balanceQuery = 'SELECT SUM(partidas.debe) as debe, SUM(partidas.haber) as haber'
            . ' FROM partidas'
            . ' LEFT JOIN asientos ON partidas.idasiento = asientos.idasiento'
            . ' LEFT JOIN subcuentas ON subcuentas.idsubcuenta = partidas.idsubcuenta'
            . ' WHERE subcuentas.codsubcuenta = ' . $this->dbConnection->escapeString($subAccountCode)
            . ' AND asientos.codejercicio = ' . $this->dbConnection->escapeString($this->fiscalPeriod->codejercicio)
            . ' AND asientos.fecha < ' . $this->dbConnection->escapeString($this->startDate);
        foreach ($this->dbConnection->select($balanceQuery) as $row) {
            return (float)$row['debe'] - (float)$row['haber'];
        }

        return 0.00;
    }

    protected function processDetailedRecord(array &$ledgerLines, array $record, array $parameters): void
    {
        $lineData = [
            'asiento' => $record['numero'],
            'fecha' => Tools::formatDate($record['fecha']),
            'cuenta' => $record['codsubcuenta'],
            'concepto' => Tools::sanitizeHtml($record['concepto']),
            'debe' => $this->formatCurrency($record['debe'], false),
            'haber' => $this->formatCurrency($record['haber'], false),
            'saldo' => $this->formatCurrency($record['saldo'], false)
        ];

        if (!empty($parameters['subaccount-from'])) {
            unset($lineData['cuenta']);
        } else {
            unset($lineData['saldo']);
        }

        $ledgerLines[] = $lineData;
    }

    protected function processGroupedAccountRecord(array &$balances, array &$ledger, array $record)
    {
        $accountCode = $record['codcuenta'];
        if (!isset($balances[$accountCode])) {
            $balances[$accountCode] = $this->getAccountBalance($accountCode);
        }

        if (!isset($ledger[$accountCode])) {
            $ledger[$accountCode][] = [
                'asiento' => '',
                'fecha' => Tools::formatDate($this->startDate),
                'cuenta' => $accountCode,
                'concepto' => Tools::sanitizeHtml($record['cuentadesc']),
                'debe' => $this->formatCurrency(0, false),
                'haber' => $this->formatCurrency(0, false),
                'saldo' => $this->formatCurrency($balances[$accountCode], false)
            ];
        }

        $balances[$accountCode] += (float)$record['debe'] - (float)$record['haber'];
        $ledger[$accountCode][] = [
            'asiento' => $record['numero'],
            'fecha' => Tools::formatDate($record['fecha']),
            'cuenta' => $accountCode,
            'concepto' => Tools::sanitizeHtml($record['concepto']),
            'debe' => $this->formatCurrency($record['debe'], false),
            'haber' => $this->formatCurrency($record['haber'], false),
            'saldo' => $this->formatCurrency($balances[$accountCode], false)
        ];
    }

    protected function processGroupedSubAccountRecord(array &$balances, array &$ledger, array $record)
    {
        $subAccountCode = $record['codsubcuenta'];
        if (!isset($balances[$subAccountCode])) {
            $balances[$subAccountCode] = $this->getSubAccountBalance($subAccountCode);
        }

        if (!isset($ledger[$subAccountCode])) {
            $ledger[$subAccountCode][] = [
                'asiento' => '',
                'fecha' => Tools::formatDate($this->startDate),
                'cuenta' => $subAccountCode,
                'concepto' => Tools::sanitizeHtml($record['subcuentadesc']),
                'debe' => $this->formatCurrency(0, false),
                'haber' => $this->formatCurrency(0, false),
                'saldo' => $this->formatCurrency($balances[$subAccountCode], false)
            ];
        }

        $balances[$subAccountCode] += (float)$record['debe'] - (float)$record['haber'];
        $ledger[$subAccountCode][] = [
            'asiento' => $record['numero'],
            'fecha' => Tools::formatDate($record['fecha']),
            'cuenta' => $subAccountCode,
            'concepto' => Tools::sanitizeHtml($record['concepto']),
            'debe' => $this->formatCurrency($record['debe'], false),
            'haber' => $this->formatCurrency($record['haber'], false),
            'saldo' => $this->formatCurrency($balances[$subAccountCode], false)
        ];
    }
}