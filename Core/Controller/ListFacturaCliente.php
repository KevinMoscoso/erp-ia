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
use ERPIA\Core\DataSrc\Empresas;
use ERPIA\Core\DataSrc\FormasPago;
use ERPIA\Core\DataSrc\Series;
use ERPIA\Core\Lib\ExtendedController\ListBusinessDocument;
use ERPIA\Core\Model\FacturaCliente;
use ERPIA\Core\Model\SecuenciaDocumento;
use ERPIA\Core\App\Translator;
use ERPIA\Core\App\Configuration;
use ERPIA\Core\App\Logger;

/**
 * Controller to list the items in the FacturaCliente model
 *
 * @author ERPIA Contributors
 */
class ListFacturaCliente extends ListBusinessDocument
{
    /**
     * Returns page configuration data
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'sales';
        $pageData['title'] = 'invoices';
        $pageData['icon'] = 'fa-solid fa-file-invoice-dollar';
        return $pageData;
    }

    /**
     * Create and configure the views
     */
    protected function createViews(): void
    {
        // Customer invoices list
        $this->createViewSales('ListFacturaCliente', 'FacturaCliente', 'invoices');

        // Only add additional views if user can see all data
        if ($this->permissions->onlyOwnerData) {
            return;
        }

        // Invoice lines
        $this->createViewLines('ListLineaFacturaCliente', 'LineaFacturaCliente');

        // Customer receipts
        $this->createViewReceipts();

        // Refund invoices
        $this->createViewRefunds();
    }

    /**
     * Creates and configures the receipts view
     *
     * @param string $viewName
     */
    protected function createViewReceipts(string $viewName = 'ListReciboCliente'): void
    {
        // Add receipts view
        $this->addView($viewName, 'ReciboCliente', 'receipts', 'fa-solid fa-dollar-sign');
        
        // Configure default order
        $this->addOrderBy(['codcliente'], 'customer-code');
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
        $this->addFilterAutocomplete($viewName, 'codcliente', 'customer', 'codcliente', 'Cliente');
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
    protected function createViewRefunds(string $viewName = 'ListFacturaCliente-rect'): void
    {
        // Add refunds view
        $this->addView($viewName, 'FacturaCliente', 'refunds', 'fa-solid fa-share-square');
        
        // Configure search fields
        $this->addSearchFields(['codigo', 'codigorect', 'numero2', 'observaciones']);
        
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
     * Creates and configures the sales view with additional filters and buttons
     *
     * @param string $viewName
     * @param string $modelName
     * @param string $label
     */
    protected function createViewSales(string $viewName, string $modelName, string $label): void
    {
        parent::createViewSales($viewName, $modelName, $label);

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

        // Add look for gaps button (only if user can see all data)
        if (!$this->permissions->onlyOwnerData) {
            $this->addButton($viewName, [
                'action' => 'look-for-gaps',
                'icon' => 'fa-solid fa-exclamation-triangle',
                'label' => 'look-for-gaps'
            ]);
        }
    }

    /**
     * Execute actions after reading data
     *
     * @param string $action
     */
    protected function execAfterAction($action)
    {
        parent::execAfterAction($action);
        if ($action === 'look-for-gaps') {
            $this->lookForGapsAction();
        }
    }

    /**
     * Look for gaps in a document sequence
     *
     * @param SecuenciaDocumento $sequence
     * @return array
     */
    protected function lookForGaps(SecuenciaDocumento $sequence): array
    {
        $gaps = [];
        $number = $sequence->inicio;

        // Find all customer invoices for the sequence
        $where = [
            new DataBaseWhere('codserie', $sequence->codserie),
            new DataBaseWhere('idempresa', $sequence->idempresa)
        ];
        
        if ($sequence->codejercicio) {
            $where[] = new DataBaseWhere('codejercicio', $sequence->codejercicio);
        }
        
        // Adapt order by based on database type
        $dbType = Configuration::get('db_type');
        $orderBy = strtolower($dbType) === 'postgresql' 
            ? ['CAST(numero as integer)' => 'ASC'] 
            : ['CAST(numero as unsigned)' => 'ASC'];
        
        $invoices = FacturaCliente::getAll($where, $orderBy, 0, 0);
        
        foreach ($invoices as $invoice) {
            // Skip invoices with number less than sequence start
            if ($invoice->numero < $sequence->inicio) {
                continue;
            }

            // If invoice number matches expected number, increment expected number
            if ($invoice->numero == $number) {
                $number++;
                continue;
            }

            // If invoice number is greater than expected, add gaps until the number
            while ($invoice->numero > $number) {
                $gaps[] = [
                    'codserie' => $invoice->codserie,
                    'numero' => $number,
                    'fecha' => $invoice->fecha,
                    'idempresa' => $invoice->idempresa
                ];
                $number++;
            }
            $number++;
        }

        return $gaps;
    }

    /**
     * Execute the look for gaps action
     */
    protected function lookForGapsAction(): void
    {
        $gaps = [];

        // Find all document sequences for customer invoices that use gaps
        $where = [
            new DataBaseWhere('tipodoc', 'FacturaCliente'),
            new DataBaseWhere('usarhuecos', true)
        ];
        
        $sequences = SecuenciaDocumento::getAll($where);
        
        foreach ($sequences as $sequence) {
            $gaps = array_merge($gaps, $this->lookForGaps($sequence));
        }

        // If no gaps found, show notice
        if (empty($gaps)) {
            Logger::notice('no-gaps-found');
            return;
        }

        // Show each gap found
        foreach ($gaps as $gap) {
            Logger::warning('gap-found', [
                '%codserie%' => Series::get($gap['codserie'])->descripcion,
                '%numero%' => $gap['numero'],
                '%fecha%' => $gap['fecha'],
                '%idempresa%' => Empresas::get($gap['idempresa'])->nombrecorto
            ]);
        }
    }
}