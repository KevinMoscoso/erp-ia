<?php

namespace ERPIA\Lib\Export;

use ERPIA\Core\I18n;
use ERPIA\Core\CurrencyFormatter;
use ERPIA\Core\ViewConfig;
use ERPIA\Models\Asiento;
use ERPIA\Models\Partida;
use ERPIA\Models\PageOption;

/**
 * Class for exporting accounting entries in various formats
 */
class AsientoExport
{
    /** @var float */
    private static $totalDebit = 0.0;
    
    /** @var float */
    private static $totalCredit = 0.0;
    
    /** @var float */
    private static $balance = 0.0;
    
    /**
     * Export an accounting entry in the specified format
     */
    public static function export(
        Asiento $entry, 
        string $format, 
        string $title, 
        int $formatId, 
        string $languageCode, 
        &$response
    ): void {
        $exportManager = new ExportManager();
        $exportManager->initializeDocument($format, $title, $formatId, $languageCode);
        
        // Export entry header using view configuration
        self::exportEntryHeader($entry, $exportManager, $title);
        
        // Export entry lines
        self::processLines($entry, $exportManager);
        
        // Export tax data if available
        self::processTaxData($entry, $exportManager);
        
        // Export totals
        self::processTotals($exportManager);
        
        $exportManager->outputDocument($response);
    }
    
    /**
     * Export the entry header using view configuration
     */
    private static function exportEntryHeader(Asiento $entry, ExportManager $manager, string $title): void
    {
        // Load view configuration for entry editing
        $viewConfig = new ViewConfig();
        $columns = $viewConfig->loadColumnsForView('EditAsiento');
        
        // If no columns loaded, use a default minimal set
        if (empty($columns)) {
            $columns = [
                'code' => ['title' => I18n::trans('code')],
                'date' => ['title' => I18n::trans('date')],
                'concept' => ['title' => I18n::trans('concept')]
            ];
        }
        
        $manager->addModelSection($entry, $columns, $title);
    }
    
    /**
     * Process and export entry lines
     */
    private static function processLines(Asiento $entry, ExportManager $manager): void
    {
        $lines = $entry->getLines();
        if (empty($lines)) {
            return;
        }
        
        $translator = I18n::getInstance();
        $formatter = CurrencyFormatter::getInstance();
        
        $headers = [
            'subaccount' => $translator->trans('subaccount'),
            'concept' => $translator->trans('concept'),
            'debit' => $translator->trans('debit'),
            'credit' => $translator->trans('credit'),
            'balance' => $translator->trans('balance'),
            'counterpart' => $translator->trans('counterpart')
        ];
        
        $alignments = [
            $headers['debit'] => ['align' => 'right', 'css' => 'no-wrap'],
            $headers['credit'] => ['align' => 'right', 'css' => 'no-wrap'],
            $headers['balance'] => ['align' => 'right', 'css' => 'no-wrap']
        ];
        
        // Reset totals
        self::$totalDebit = 0.0;
        self::$totalCredit = 0.0;
        self::$balance = 0.0;
        
        $tableData = [];
        foreach ($lines as $line) {
            self::$balance += $line->debit - $line->credit;
            
            $rowData = [
                $headers['subaccount'] => $line->accountCode,
                $headers['concept'] => $line->description,
                $headers['debit'] => $formatter->format($line->debit) . ' ',
                $headers['credit'] => $formatter->format($line->credit) . ' ',
                $headers['balance'] => $formatter->format(self::$balance, true) . ' ',
                $headers['counterpart'] => $line->counterpartCode
            ];
            
            $tableData[] = $rowData;
            
            self::$totalDebit += $line->debit;
            self::$totalCredit += $line->credit;
        }
        
        $sectionTitle = '<strong>' . $translator->trans('lines') . '</strong>';
        $manager->addTableSection($headers, $tableData, $alignments, $sectionTitle);
    }
    
    /**
     * Process and export tax data if present
     */
    private static function processTaxData(Asiento $entry, ExportManager $manager): void
    {
        $translator = I18n::getInstance();
        $formatter = CurrencyFormatter::getInstance();
        
        $headers = [
            'series' => $translator->trans('series'),
            'invoice' => $translator->trans('invoice'),
            'vat-document' => $translator->trans('vat-document'),
            'tax-id' => $translator->trans('tax-id'),
            'tax-base' => $translator->trans('tax-base'),
            'vat-rate' => $translator->trans('vat-rate'),
            'vat-amount' => $translator->trans('vat-amount'),
            'surcharge-rate' => $translator->trans('surcharge-rate'),
            'surcharge-amount' => $translator->trans('surcharge-amount')
        ];
        
        $tableData = [];
        foreach ($entry->getLines() as $line) {
            if (!self::hasTaxRecord($line)) {
                continue;
            }
            
            $vatAmount = $line->taxableBase * ($line->vatRate / 100);
            $surchargeAmount = $line->taxableBase * ($line->surchargeRate / 100);
            
            $rowData = [
                $headers['series'] => $line->seriesCode,
                $headers['invoice'] => $line->invoiceNumber,
                $headers['vat-document'] => $line->document,
                $headers['tax-id'] => $line->taxIdentification,
                $headers['tax-base'] => $formatter->format($line->taxableBase),
                $headers['vat-rate'] => $line->vatRate,
                $headers['vat-amount'] => $formatter->format($vatAmount) . ' ',
                $headers['surcharge-rate'] => $line->surchargeRate,
                $headers['surcharge-amount'] => $formatter->format($surchargeAmount) . ' '
            ];
            
            $tableData[] = $rowData;
        }
        
        if (empty($tableData)) {
            return;
        }
        
        $alignments = [
            $headers['tax-base'] => ['align' => 'right', 'css' => 'no-wrap'],
            $headers['vat-rate'] => ['align' => 'center', 'css' => 'no-wrap'],
            $headers['vat-amount'] => ['align' => 'right', 'css' => 'no-wrap'],
            $headers['surcharge-rate'] => ['align' => 'center', 'css' => 'no-wrap'],
            $headers['surcharge-amount'] => ['align' => 'right', 'css' => 'no-wrap']
        ];
        
        $sectionTitle = '<strong>' . $translator->trans('vat-register') . '</strong>';
        $manager->addTableSection($headers, $tableData, $alignments, $sectionTitle);
    }
    
    /**
     * Process and export totals section
     */
    private static function processTotals(ExportManager $manager): void
    {
        $translator = I18n::getInstance();
        $formatter = CurrencyFormatter::getInstance();
        
        $headers = [
            'total-debit' => $translator->trans('debit'),
            'total-credit' => $translator->trans('credit'),
            'difference' => $translator->trans('difference')
        ];
        
        $rowData = [
            $headers['total-debit'] => $formatter->format(self::$totalDebit) . ' ',
            $headers['total-credit'] => $formatter->format(self::$totalCredit) . ' ',
            $headers['difference'] => $formatter->format(self::$balance) . ' '
        ];
        
        $alignments = [
            $headers['total-debit'] => ['align' => 'center'],
            $headers['total-credit'] => ['align' => 'center'],
            $headers['difference'] => ['align' => 'center']
        ];
        
        $sectionTitle = '<strong>' . $translator->trans('totals') . '</strong>';
        $manager->addTableSection($headers, [$rowData], $alignments, $sectionTitle);
    }
    
    /**
     * Check if a line has tax-related data
     */
    private static function hasTaxRecord(Partida $line): bool
    {
        return !empty($line->taxableBase)
            || !empty($line->vatRate)
            || !empty($line->surchargeRate)
            || !empty($line->seriesCode)
            || !empty($line->invoiceNumber);
    }
}