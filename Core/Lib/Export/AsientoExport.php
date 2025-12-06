<?php

namespace ERPIA\Lib\Export;

use ERPIA\Core\Internationalization;
use ERPIA\Core\CurrencyFormatter;
use ERPIA\Models\Asiento;
use ERPIA\Models\Partida;
use ERPIA\Core\ViewConfigLoader;

/**
 * Exporter for accounting entries in multiple formats
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
    public static function export(Asiento $entry, string $format, string $title, int $formatId, string $languageCode, &$output): void
    {
        $exporter = new ExportManager();
        $exporter->initializeDocument($format, $title, $formatId, $languageCode);
        
        // Export entry header information
        self::exportEntryHeader($entry, $exporter, $title);
        
        // Export entry lines
        self::processEntryLines($entry, $exporter);
        
        // Export tax data if available
        self::processTaxData($entry, $exporter);
        
        // Export totals
        self::processTotals($exporter);
        
        $exporter->generateOutput($output);
    }
    
    /**
     * Export the entry header using view configuration
     */
    private static function exportEntryHeader(Asiento $entry, ExportManager $exporter, string $title): void
    {
        $viewConfig = new \stdClass();
        $columns = [];
        $modals = [];
        $rows = [];
        
        // Load view configuration for accounting entry editing
        ViewConfigLoader::loadConfiguration('EditAccountingEntry', $viewConfig);
        ViewConfigLoader::processConfiguration($columns, $modals, $rows, $viewConfig);
        
        $exporter->addModelSection($entry, $columns, $title);
    }
    
    /**
     * Process and export entry lines
     */
    private static function processEntryLines(Asiento $entry, ExportManager $exporter): void
    {
        $translator = Internationalization::getTranslator();
        $currency = CurrencyFormatter::getInstance();
        
        $headers = [
            $translator->translate('subaccount'),
            $translator->translate('concept'),
            $translator->translate('debit'),
            $translator->translate('credit'),
            $translator->translate('balance'),
            $translator->translate('counterpart'),
        ];
        
        $formatting = [
            $headers[2] => ['alignment' => 'right', 'cssClass' => 'no-wrap'],
            $headers[3] => ['alignment' => 'right', 'cssClass' => 'no-wrap'],
            $headers[4] => ['alignment' => 'right', 'cssClass' => 'no-wrap'],
        ];
        
        self::$totalDebit = 0.0;
        self::$totalCredit = 0.0;
        self::$balance = 0.0;
        
        $linesData = [];
        $entryLines = $entry->getLines();
        
        foreach ($entryLines as $line) {
            $debitAmount = $line->getDebit();
            $creditAmount = $line->getCredit();
            self::$balance += $debitAmount - $creditAmount;
            
            $linesData[] = [
                $headers[0] => $line->getSubaccountCode(),
                $headers[1] => $line->getConcept(),
                $headers[2] => $currency->format($debitAmount) . ' ',
                $headers[3] => $currency->format($creditAmount) . ' ',
                $headers[4] => $currency->format(self::$balance, true) . ' ',
                $headers[5] => $line->getCounterpartCode(),
            ];
            
            self::$totalDebit += $debitAmount;
            self::$totalCredit += $creditAmount;
        }
        
        $sectionTitle = '<strong>' . $translator->translate('lines') . '</strong>';
        $exporter->addTableSection($headers, $linesData, $formatting, $sectionTitle);
    }
    
    /**
     * Process and export tax-related data
     */
    private static function processTaxData(Asiento $entry, ExportManager $exporter): void
    {
        $translator = Internationalization::getTranslator();
        $currency = CurrencyFormatter::getInstance();
        
        $headers = [
            $translator->translate('series'),
            $translator->translate('invoice'),
            $translator->translate('vat-document'),
            $translator->translate('tax-id'),
            $translator->translate('tax-base'),
            $translator->translate('vat-percentage'),
            $translator->translate('vat-amount'),
            $translator->translate('surcharge-percentage'),
            $translator->translate('surcharge-amount'),
        ];
        
        $taxData = [];
        $entryLines = $entry->getLines();
        
        foreach ($entryLines as $line) {
            if (!self::hasTaxRecord($line)) {
                continue;
            }
            
            $taxBase = $line->getTaxableBase();
            $vatAmount = $taxBase * ($line->getVatRate() / 100);
            $surchargeAmount = $taxBase * ($line->getSurchargeRate() / 100);
            
            $taxData[] = [
                $headers[0] => $line->getSeriesCode(),
                $headers[1] => $line->getInvoiceNumber(),
                $headers[2] => $line->getDocumentNumber(),
                $headers[3] => $line->getTaxId(),
                $headers[4] => $currency->format($taxBase),
                $headers[5] => $line->getVatRate(),
                $headers[6] => $currency->format($vatAmount) . ' ',
                $headers[7] => $line->getSurchargeRate(),
                $headers[8] => $currency->format($surchargeAmount) . ' ',
            ];
        }
        
        if (empty($taxData)) {
            return;
        }
        
        $formatting = [
            $headers[4] => ['alignment' => 'right', 'cssClass' => 'no-wrap'],
            $headers[5] => ['alignment' => 'center', 'cssClass' => 'no-wrap'],
            $headers[6] => ['alignment' => 'right', 'cssClass' => 'no-wrap'],
            $headers[7] => ['alignment' => 'center', 'cssClass' => 'no-wrap'],
            $headers[8] => ['alignment' => 'right', 'cssClass' => 'no-wrap'],
        ];
        
        $sectionTitle = '<strong>' . $translator->translate('VAT-register') . '</strong>';
        $exporter->addTableSection($headers, $taxData, $formatting, $sectionTitle);
    }
    
    /**
     * Process and export totals section
     */
    private static function processTotals(ExportManager $exporter): void
    {
        $translator = Internationalization::getTranslator();
        $currency = CurrencyFormatter::getInstance();
        
        $headers = [
            $translator->translate('debit'),
            $translator->translate('credit'),
            $translator->translate('difference'),
        ];
        
        $totalsData = [[
            $headers[0] => $currency->format(self::$totalDebit) . ' ',
            $headers[1] => $currency->format(self::$totalCredit) . ' ',
            $headers[2] => $currency->format(self::$balance) . ' '
        ]];
        
        $formatting = [
            $headers[0] => ['alignment' => 'center'],
            $headers[1] => ['alignment' => 'center'],
            $headers[2] => ['alignment' => 'center']
        ];
        
        $sectionTitle = '<strong>' . $translator->translate('totals') . '</strong>';
        $exporter->addTableSection($headers, $totalsData, $formatting, $sectionTitle);
    }
    
    /**
     * Check if a line contains tax-related data
     */
    private static function hasTaxRecord(Partida $line): bool
    {
        return !empty($line->getTaxableBase())
            || !empty($line->getVatRate())
            || !empty($line->getSurchargeRate())
            || !empty($line->getSeriesCode())
            || !empty($line->getInvoiceNumber());
    }
}