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

use Exception;
use ERPIA\Core\Base\DataBase;
use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Lib\Import\CSVImport;
use ERPIA\Dinamic\Model\Cuenta;
use ERPIA\Dinamic\Model\CuentaEspecial;
use ERPIA\Dinamic\Model\Ejercicio;
use ERPIA\Dinamic\Model\Subcuenta;
use ParseCsv\Csv;
use SimpleXMLElement;

/**
 * Description of AccountingPlanImport
 *
 * @author ERPIA Development Team
 */
class AccountingPlanImport
{
    /**
     * @var DataBase
     */
    protected $dbConnection;

    /**
     * Exercise related to the accounting plan.
     *
     * @var Ejercicio
     */
    protected $fiscalPeriod;

    public function __construct()
    {
        $this->dbConnection = new DataBase();
        $this->fiscalPeriod = new Ejercicio();
    }

    /**
     * Import data from CSV file.
     */
    public function importCSV(string $filePath, string $exerciseCode): bool
    {
        if (false === $this->fiscalPeriod->load($exerciseCode)) {
            Tools::log()->error('fiscal-period-not-found');
            return false;
        }

        if (false === file_exists($filePath)) {
            Tools::log()->warning('file-missing', ['%file%' => $filePath]);
            return false;
        }

        try {
            $this->dbConnection->beginTransaction();

            $this->refreshSpecialAccounts();
            if (false === $this->parseCsvFile($filePath)) {
                $this->dbConnection->rollback();
                return false;
            }

            $this->dbConnection->commit();
            return true;
        } catch (Exception $ex) {
            $this->dbConnection->rollback();
            Tools::log()->error($ex->getLine() . ' -> ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * Import data from XML file.
     */
    public function importXML(string $filePath, string $exerciseCode): bool
    {
        if (false === $this->fiscalPeriod->load($exerciseCode)) {
            Tools::log()->error('fiscal-period-not-found');
            return false;
        }

        $xmlData = $this->parseXmlFile($filePath);
        if (is_array($xmlData) || $xmlData->count() == 0) {
            return false;
        }

        try {
            $this->dbConnection->beginTransaction();

            $this->refreshSpecialAccounts();
            if (false === $this->importEpigrafeGroups($xmlData->grupo_epigrafes)) {
                $this->dbConnection->rollback();
                return false;
            }
            if (false === $this->importEpigrafes($xmlData->epigrafes)) {
                $this->dbConnection->rollback();
                return false;
            }
            if (false === $this->importAccounts($xmlData->cuenta)) {
                $this->dbConnection->rollback();
                return false;
            }
            if (false === $this->importSubaccounts($xmlData->subcuenta)) {
                $this->dbConnection->rollback();
                return false;
            }

            $this->dbConnection->commit();
            return true;
        } catch (Exception $ex) {
            $this->dbConnection->rollback();
            Tools::log()->error($ex->getLine() . ' -> ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * Insert/update an account in accounting plan.
     */
    protected function addAccount(string $code, string $description, ?string $parentCode = '', ?string $specialAccountCode = ''): bool
    {
        $account = new Cuenta();
        $conditions = [
            new DataBaseWhere('codejercicio', $this->fiscalPeriod->codejercicio),
            new DataBaseWhere('codcuenta', $code)
        ];
        
        if ($account->loadWhere($conditions)) {
            return true;
        }

        $account->codcuenta = $code;
        $account->codcuentaesp = empty($specialAccountCode) ? null : $specialAccountCode;
        $account->codejercicio = $this->fiscalPeriod->codejercicio;
        $account->descripcion = $description;
        $account->parent_codcuenta = empty($parentCode) ? null : $parentCode;
        return $account->save();
    }

    /**
     * Insert or update a subaccount in accounting plan.
     */
    protected function addSubaccount(string $code, string $description, string $parentCode, ?string $specialAccountCode = ''): bool
    {
        $subaccount = new Subcuenta();
        $conditions = [
            new DataBaseWhere('codejercicio', $this->fiscalPeriod->codejercicio),
            new DataBaseWhere('codsubcuenta', $code)
        ];
        
        if ($subaccount->loadWhere($conditions)) {
            return true;
        }

        if ($this->fiscalPeriod->longsubcuenta != strlen($code)) {
            $this->fiscalPeriod->longsubcuenta = strlen($code);
            if (false === $this->fiscalPeriod->save()) {
                return false;
            }
        }

        $subaccount->codcuenta = $parentCode;
        $subaccount->codcuentaesp = empty($specialAccountCode) ? null : $specialAccountCode;
        $subaccount->codejercicio = $this->fiscalPeriod->codejercicio;
        $subaccount->codsubcuenta = $code;
        $subaccount->descripcion = $description;
        return $subaccount->save();
    }

    /**
     * Returns the content of XML file.
     */
    protected function parseXmlFile(string $filePath)
    {
        if (file_exists($filePath)) {
            return simplexml_load_string(file_get_contents($filePath));
        }

        return [];
    }

    /**
     * Import accounts from XML data.
     */
    protected function importAccounts(SimpleXMLElement $accountData): bool
    {
        foreach ($accountData as $accountItem) {
            $item = (array)$accountItem;
            if (false === $this->addAccount($item['codcuenta'], base64_decode($item['descripcion']), $item['codepigrafe'], $item['idcuentaesp'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Import epigrafes from XML data.
     */
    protected function importEpigrafes(SimpleXMLElement $epigrafeData): bool
    {
        foreach ($epigrafeData as $epigrafeItem) {
            $item = (array)$epigrafeItem;
            if (false === $this->addAccount($item['codepigrafe'], base64_decode($item['descripcion']), $item['codgrupo'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Import epigrafe groups from XML data.
     */
    protected function importEpigrafeGroups(SimpleXMLElement $groupData): bool
    {
        foreach ($groupData as $groupItem) {
            $item = (array)$groupItem;
            if (false === $this->addAccount($item['codgrupo'], base64_decode($item['descripcion']))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Import subaccounts from XML data.
     */
    protected function importSubaccounts(SimpleXMLElement $subaccountData): bool
    {
        foreach ($subaccountData as $subaccountItem) {
            $item = (array)$subaccountItem;
            if (false === $this->addSubaccount($item['codsubcuenta'], base64_decode($item['descripcion']), $item['codcuenta'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Process CSV file data and import accounting plan.
     */
    protected function parseCsvFile(string $filePath): bool
    {
        $csvParser = new Csv();
        $csvParser->auto($filePath);

        if (count($csvParser->titles) < 2) {
            Tools::log()->warning('csv-invalid-column-count');
            return false;
        }

        $accountLengths = [];
        $accountingPlan = [];
        
        foreach ($csvParser->data as $record) {
            $accountCode = $record[$csvParser->titles[0]] ?? $record[0];
            if (strlen($accountCode) > 0) {
                $accountingPlan[$accountCode] = [
                    'descripcion' => $record[$csvParser->titles[1]] ?? $record[1],
                    'codcuentaesp' => $record[$csvParser->titles[2]] ?? $record[2]
                ];
                $accountLengths[] = strlen($accountCode);
            }
        }

        $uniqueLengths = array_unique($accountLengths);
        sort($uniqueLengths);

        if (count($uniqueLengths) < 2) {
            Tools::log()->warning('accounting-plan-levels-insufficient');
            return false;
        }

        $minimumLength = min($uniqueLengths);
        $maximumLength = max($uniqueLengths);
        $accountCodes = array_keys($accountingPlan);
        ksort($accountingPlan);

        foreach ($accountingPlan as $code => $details) {
            switch (strlen($code)) {
                case $minimumLength:
                    $success = $this->addAccount($code, $details['descripcion'], '', $details['codcuentaesp']);
                    break;

                case $maximumLength:
                    $parentAccount = $this->findParentAccount($accountCodes, $code);
                    $success = $this->addSubaccount($code, $details['descripcion'], $parentAccount, $details['codcuentaesp']);
                    break;

                default:
                    $parentAccount = $this->findParentAccount($accountCodes, $code);
                    $success = $this->addAccount($code, $details['descripcion'], $parentAccount, $details['codcuentaesp']);
                    break;
            }

            if (false === $success) {
                return false;
            }
        }

        return true;
    }

    /**
     * Find parent account code for a given account.
     */
    protected function findParentAccount(array &$accountCodes, string $currentCode): string
    {
        $parentCode = '';
        foreach ($accountCodes as $code) {
            $codeString = (string)$code;
            if ($codeString === $currentCode) {
                continue;
            } elseif (strpos($currentCode, $codeString) === 0 && strlen($codeString) > strlen($parentCode)) {
                $parentCode = $code;
            }
        }

        return $parentCode;
    }

    /**
     * Update special accounts from data file.
     */
    protected function refreshSpecialAccounts(): void
    {
        $sqlStatement = CSVImport::updateTableSQL(CuentaEspecial::tableName());
        if (!empty($sqlStatement) && $this->dbConnection->tableExists(CuentaEspecial::tableName())) {
            $this->dbConnection->exec($sqlStatement);
        }
    }
}