<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2018-2025 ERPIA Contributors
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

namespace ERPIA\Core\Base;

use ERPIA\Core\Base\Contract\CalculatorModInterface;
use ERPIA\Core\DataSrc\Taxes;
use ERPIA\Core\Lib\InvoiceOperation;
use ERPIA\Core\Lib\ProductType;
use ERPIA\Core\Lib\VATRegime;
use ERPIA\Core\Model\Base\BusinessDocument;
use ERPIA\Core\Model\Base\BusinessDocumentLine;
use ERPIA\Core\Model\Tax;
use ERPIA\Core\Model\TaxZone;

/**
 * Calculator for business document financial operations
 *
 * @author ERPIA Contributors
 */
final class Calculator
{
    /** @var CalculatorModInterface[] */
    public static $modifiers = [];

    /**
     * Add a modifier to the calculation process
     */
    public static function addModifier(CalculatorModInterface $modifier): void
    {
        self::$modifiers[] = $modifier;
    }

    /**
     * Calculate document totals and taxes
     */
    public static function calculate(BusinessDocument &$document, array &$lines, bool $saveData): bool
    {
        // Reset all totals
        self::resetTotals($document, $lines);

        // Apply configurations and exceptions
        self::applySettings($document, $lines);

        // Calculate line subtotals
        foreach ($lines as $line) {
            self::computeLine($document, $line);
        }

        // Calculate document totals
        $subtotals = self::computeSubtotals($document, $lines);
        $document->irpf = $subtotals['irpf'];
        $document->neto = $subtotals['neto'];
        $document->netosindto = $subtotals['netosindto'];
        $document->total = $subtotals['total'];
        $document->totalirpf = $subtotals['totalirpf'];
        $document->totaliva = $subtotals['totaliva'];
        $document->totalrecargo = $subtotals['totalrecargo'];
        $document->totalsuplidos = $subtotals['totalsuplidos'];

        // Assign profit if property exists
        if (property_exists($document, 'totalbeneficio')) {
            $document->totalbeneficio = $subtotals['totalbeneficio'];
        }

        // Assign cost if property exists
        if (property_exists($document, 'totalcoste')) {
            $document->totalcoste = $subtotals['totalcoste'];
        }

        // Allow modifiers to apply changes
        foreach (self::$modifiers as $modifier) {
            if (false === $modifier->calculate($document, $lines)) {
                break;
            }
        }

        return $saveData && self::persistData($document, $lines);
    }

    /**
     * Compute subtotals for document and lines
     */
    public static function computeSubtotals(BusinessDocument $document, array $lines): array
    {
        $subtotals = [
            'irpf' => 0.0,
            'iva' => [],
            'neto' => 0.0,
            'netosindto' => 0.0,
            'totalcoste' => 0.0,
            'totalirpf' => 0.0,
            'totaliva' => 0.0,
            'totalrecargo' => 0.0,
            'totalsuplidos' => 0.0,
            'totalbeneficio' => 0.0
        ];

        // Process each line
        foreach ($lines as $line) {
            // Calculate cost
            $lineCost = isset($line->coste) ? $line->quantity * $line->coste : 0.0;
            if (isset($line->coste)) {
                $subtotals['totalcoste'] += $lineCost;
            }

            $totalPrice = $line->totalprice * (100 - $document->discount1) / 100 * (100 - $document->discount2) / 100;
            if (empty($totalPrice)) {
                continue;
            }

            // Handle supplied goods (no taxes)
            if ($line->supplied) {
                $subtotals['totalsuplidos'] += $totalPrice;
                continue;
            }

            // IRPF calculation
            $subtotals['irpf'] = max([$line->irpf, $subtotals['irpf']]);
            $subtotals['totalirpf'] += $totalPrice * $line->irpf / 100;

            // VAT grouping
            $vatKey = $line->vat . '|' . $line->surcharge;
            if (!array_key_exists($vatKey, $subtotals['iva'])) {
                $subtotals['iva'][$vatKey] = [
                    'taxcode' => $line->taxcode,
                    'vat' => $line->vat,
                    'neto' => 0.0,
                    'netosindto' => 0.0,
                    'surcharge' => $line->surcharge,
                    'totalvat' => 0.0,
                    'totalsurcharge' => 0.0
                ];
            }

            // Handle second-hand goods
            if (self::processSecondHandGoods($subtotals, $document, $line, $vatKey, $totalPrice, $lineCost)) {
                continue;
            }

            // Net amounts
            $subtotals['iva'][$vatKey]['neto'] += $totalPrice;
            $subtotals['iva'][$vatKey]['netosindto'] += $line->totalprice;

            // VAT calculation
            if ($line->vat > 0 && $document->operation != InvoiceOperation::INTRA_COMMUNITY) {
                $tax = $line->getTax();
                $subtotals['iva'][$vatKey]['totalvat'] += $tax->type === Tax::TYPE_FIXED_VALUE ?
                    $totalPrice * $line->vat :
                    $totalPrice * $line->vat / 100;
            }

            // Surcharge calculation
            if ($line->surcharge > 0 && $document->operation != InvoiceOperation::INTRA_COMMUNITY) {
                $tax = $line->getTax();
                $subtotals['iva'][$vatKey]['totalsurcharge'] += $tax->type === Tax::TYPE_FIXED_VALUE ?
                    $totalPrice * $line->surcharge :
                    $totalPrice * $line->surcharge / 100;
            }
        }

        // Round VAT amounts
        foreach ($subtotals['iva'] as $key => $values) {
            $subtotals['iva'][$key]['neto'] = round($values['neto'], ERPIA_NF0);
            $subtotals['iva'][$key]['netosindto'] = round($values['netosindto'], ERPIA_NF0);
            $subtotals['iva'][$key]['totalvat'] = round($values['totalvat'], ERPIA_NF0);
            $subtotals['iva'][$key]['totalsurcharge'] = round($values['totalsurcharge'], ERPIA_NF0);

            // Add to main subtotals
            $subtotals['neto'] += round($values['neto'], ERPIA_NF0);
            $subtotals['netosindto'] += round($values['netosindto'], ERPIA_NF0);
            $subtotals['totaliva'] += round($values['totalvat'], ERPIA_NF0);
            $subtotals['totalrecargo'] += round($values['totalsurcharge'], ERPIA_NF0);
        }

        // Round main subtotals
        $subtotals['neto'] = round($subtotals['neto'], ERPIA_NF0);
        $subtotals['netosindto'] = round($subtotals['netosindto'], ERPIA_NF0);
        $subtotals['totalirpf'] = round($subtotals['totalirpf'], ERPIA_NF0);
        $subtotals['totaliva'] = round($subtotals['totaliva'], ERPIA_NF0);
        $subtotals['totalrecargo'] = round($subtotals['totalrecargo'], ERPIA_NF0);
        $subtotals['totalsuplidos'] = round($subtotals['totalsuplidos'], ERPIA_NF0);

        // Calculate profit
        $subtotals['totalbeneficio'] = round($subtotals['neto'] - $subtotals['totalcoste'], ERPIA_NF0);

        // Calculate grand total
        $subtotals['total'] = round(
            $subtotals['neto'] + 
            $subtotals['totalsuplidos'] + 
            $subtotals['totaliva'] +
            $subtotals['totalrecargo'] - 
            $subtotals['totalirpf'],
            ERPIA_NF0
        );

        // Allow modifiers to adjust subtotals
        foreach (self::$modifiers as $modifier) {
            if (false === $modifier->getSubtotals($subtotals, $document, $lines)) {
                break;
            }
        }

        return $subtotals;
    }

    /**
     * Apply document settings and tax exceptions
     */
    private static function applySettings(BusinessDocument &$document, array &$lines): void
    {
        $subject = $document->getSubject();
        $noTaxSeries = $document->getSeries()->notax;
        $taxException = $subject->vatexception ?? null;
        $regime = $subject->vatregime ?? VATRegime::GENERAL;
        $company = $document->getCompany();

        // Load tax zones
        $taxZones = [];
        if (isset($document->countrycode) && $document->countrycode) {
            $taxZoneModel = new TaxZone();
            foreach ($taxZoneModel->all([], ['priority' => 'DESC']) as $zone) {
                if ($zone->countrycode == $document->countrycode && $zone->province() == $document->province) {
                    $taxZones[] = $zone;
                } elseif ($zone->countrycode == $document->countrycode && $zone->isocode == null) {
                    $taxZones[] = $zone;
                } elseif ($zone->countrycode == null) {
                    $taxZones[] = $zone;
                }
            }
        }

        foreach ($lines as $line) {
            // Handle used goods purchases
            if ($document->getSubjectType() === 'supplier' &&
                $company->vatregime === VATRegime::USED_GOODS &&
                $line->getProduct()->type === ProductType::SECOND_HAND) {
                $line->taxcode = null;
                $line->vat = $line->surcharge = 0.0;
                continue;
            }

            // Apply tax zone exceptions
            foreach ($taxZones as $zone) {
                if ($line->taxcode === $zone->taxcode) {
                    $line->taxcode = $zone->selectedtax;
                    $line->vat = $line->getTax()->vat;
                    $line->surcharge = $line->getTax()->surcharge;
                    break;
                }
            }

            // No tax series or exempt regime
            if ($noTaxSeries || $regime === VATRegime::EXEMPT) {
                $line->taxcode = Taxes::get('VAT0')->taxcode;
                $line->vat = $line->surcharge = 0.0;
                $line->vatexception = $taxException;
                continue;
            }

            // No surcharge regime
            if ($regime != VATRegime::SURCHARGE) {
                $line->surcharge = 0.0;
            }
        }

        // Allow modifiers to apply settings
        foreach (self::$modifiers as $modifier) {
            if (false === $modifier->apply($document, $lines)) {
                break;
            }
        }
    }

    /**
     * Process second-hand goods calculations
     */
    private static function processSecondHandGoods(
        array &$subtotals, 
        BusinessDocument $document, 
        BusinessDocumentLine $line, 
        string $vatKey, 
        float $totalPrice, 
        float $cost
    ): bool {
        if ($document->getSubjectType() === 'customer' &&
            $document->getCompany()->vatregime === VATRegime::USED_GOODS &&
            $line->getProduct()->type === ProductType::SECOND_HAND) {
            
            // 0% VAT entry
            $zeroVatKey = '0|0';
            if (!array_key_exists($zeroVatKey, $subtotals['iva'])) {
                $subtotals['iva'][$zeroVatKey] = [
                    'taxcode' => null,
                    'vat' => 0.0,
                    'neto' => 0.0,
                    'netosindto' => 0.0,
                    'surcharge' => 0.0,
                    'totalvat' => 0.0,
                    'totalsurcharge' => 0.0
                ];
            }
            $subtotals['iva'][$zeroVatKey]['neto'] += $cost;
            $subtotals['iva'][$zeroVatKey]['netosindto'] += $cost;

            // No VAT if negative profit and not rectification series
            $profit = $totalPrice - $cost;
            if ($profit <= 0 && $document->getSeries()->type !== 'R') {
                return true;
            }

            // Apply VAT to profit only
            $subtotals['iva'][$vatKey]['neto'] += $profit;
            $subtotals['iva'][$vatKey]['netosindto'] += $profit;
            $tax = $line->getTax();
            $subtotals['iva'][$vatKey]['totalvat'] += $tax->type === Tax::TYPE_FIXED_VALUE ?
                $profit * $line->vat :
                $profit * $line->vat / 100;
            return true;
        }

        return false;
    }

    /**
     * Calculate line totals
     */
    private static function computeLine(BusinessDocument $document, BusinessDocumentLine &$line): void
    {
        $line->pricewithoutdiscount = $line->quantity * $line->unitprice;
        $line->totalprice = $line->pricewithoutdiscount * (100 - $line->discount) / 100 
            * (100 - $line->discount2) / 100;

        // Allow modifiers to adjust line
        foreach (self::$modifiers as $modifier) {
            if (false === $modifier->calculateLine($document, $line)) {
                break;
            }
        }
    }

    /**
     * Reset all totals to zero
     */
    private static function resetTotals(BusinessDocument &$document, array &$lines): void
    {
        $document->neto = $document->netosindto = 0.0;
        $document->total = $document->totaleuros = 0.0;
        $document->totalirpf = 0.0;
        $document->totaliva = 0.0;
        $document->totalrecargo = 0.0;
        $document->totalsuplidos = 0.0;

        // Reset cost if property exists
        if (property_exists($document, 'totalcoste')) {
            $document->totalcoste = 0.0;
        }

        foreach ($lines as $line) {
            $line->pricewithoutdiscount = $line->totalprice = 0.0;
        }

        // Allow modifiers to reset
        foreach (self::$modifiers as $modifier) {
            if (false === $modifier->clear($document, $lines)) {
                break;
            }
        }
    }

    /**
     * Save document and lines
     */
    private static function persistData(BusinessDocument &$document, array &$lines): bool
    {
        foreach ($lines as $line) {
            if (false === $line->save()) {
                return false;
            }
        }

        return $document->save();
    }
}