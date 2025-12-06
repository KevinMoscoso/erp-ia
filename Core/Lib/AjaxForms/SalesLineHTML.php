<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2021-2025 ERPIA Team
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

namespace ERPIA\Core\Lib\AjaxForms;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Contract\SalesLineModInterface;
use ERPIA\Core\DataSrc\Taxes;
use ERPIA\Core\Model\Base\SalesDocument;
use ERPIA\Core\Model\Base\SalesDocumentLine;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Stock;
use ERPIA\Dinamic\Model\ProductVariant;

/**
 * Description of SalesLineHTML
 * @author ERPIA Team
 */
class SalesLineHTML
{
    use CommonLineHTML;
    
    /** @var array */
    private static $deletedLines = [];
    
    /** @var SalesLineModInterface[] */
    private static $mods = [];

    /**
     * Add a line modification module
     * @param SalesLineModInterface $mod
     */
    public static function addMod(SalesLineModInterface $mod): void
    {
        self::$mods[] = $mod;
    }

    /**
     * Apply form data to lines
     * @param SalesDocument $model
     * @param SalesDocumentLine[] $lines
     * @param array $formData
     */
    public static function apply(SalesDocument &$model, array &$lines, array $formData): void
    {
        self::$columnView = $formData['columnView'] ?? Tools::settings('default', 'columnetosubtotal', 'subtotal');
        
        // Update or remove lines
        $rmLineId = $formData['action'] === 'rm-line' ? $formData['selectedLine'] : 0;
        foreach ($lines as $key => $value) {
            if ($value->lineId === (int)$rmLineId || false === isset($formData['quantity_' . $value->lineId])) {
                self::$deletedLines[] = $value->lineId;
                unset($lines[$key]);
                continue;
            }
            self::applyToLine($formData, $value, $value->lineId);
        }
        
        // New lines
        for ($num = 1; $num < 1000; $num++) {
            if (isset($formData['quantity_n' . $num]) && $rmLineId !== 'n' . $num) {
                $newLine = isset($formData['reference_n' . $num]) ?
                    $model->getNewProductLine($formData['reference_n' . $num]) : $model->getNewLine();
                $idNewLine = 'n' . $num;
                self::applyToLine($formData, $newLine, $idNewLine);
                $lines[] = $newLine;
            }
        }
        
        // Add new line based on action
        if ($formData['action'] === 'add-product' || $formData['action'] === 'fast-product') {
            $lines[] = $model->getNewProductLine($formData['selectedLine']);
        } elseif ($formData['action'] === 'fast-line') {
            $newLine = self::getFastLine($model, $formData);
            if ($newLine) {
                $lines[] = $newLine;
            }
        } elseif ($formData['action'] === 'new-line') {
            $lines[] = $model->getNewLine();
        }
        
        // Apply mods
        foreach (self::$mods as $mod) {
            $mod->apply($model, $lines, $formData);
        }
    }

    /**
     * Load required assets
     */
    public static function assets(): void
    {
        foreach (self::$mods as $mod) {
            $mod->assets();
        }
    }

    /**
     * Get deleted lines
     * @return array
     */
    public static function getDeletedLines(): array
    {
        return self::$deletedLines;
    }

    /**
     * Generate line value map for AJAX updates
     * @param array $lines
     * @param SalesDocument $model
     * @return array
     */
    public static function map(array $lines, SalesDocument $model): array
    {
        $map = [];
        foreach ($lines as $line) {
            self::$num++;
            $lineId = $line->lineId ?? 'n' . self::$num;
            
            // Tax
            $map['vat_' . $lineId] = $line->vat;
            
            // Total
            $map['linetotal_' . $lineId] = self::subtotalValue($line, $model);
            
            // Net
            $map['linenet_' . $lineId] = $line->totalPrice;
        }
        
        // Add mods to map
        foreach (self::$mods as $mod) {
            foreach ($mod->map($lines, $model) as $key => $value) {
                $map[$key] = $value;
            }
        }
        
        return $map;
    }

    /**
     * Render all lines
     * @param array $lines
     * @param SalesDocument $model
     * @return string
     */
    public static function render(array $lines, SalesDocument $model): string
    {
        if (empty(self::$columnView)) {
            self::$columnView = Tools::settings('default', 'columnetosubtotal', 'subtotal');
        }
        
        self::$numlines = count($lines);
        self::loadProducts($lines, $model);
        
        $html = '';
        foreach ($lines as $line) {
            $html .= self::renderLine($line, $model);
        }
        
        if (empty($html)) {
            $html .= '<div class="container-fluid"><div class="row g-2"><div class="col p-3 table-warning text-center">'
                . Tools::trans('new-invoice-line-p') . '</div></div></div>';
        }
        
        return empty($model->customerCode) ? '' : self::renderTitles($model) . $html;
    }

    /**
     * Render a single line
     * @param SalesDocumentLine $line
     * @param SalesDocument $model
     * @return string
     */
    public static function renderLine(SalesDocumentLine $line, SalesDocument $model): string
    {
        self::$num++;
        $lineId = $line->lineId ?? 'n' . self::$num;
        
        return '<div class="container-fluid fs-line"><div class="row g-2 align-items-center border-bottom pb-3 pb-lg-0">'
            . self::renderField($lineId, $line, $model, 'reference')
            . self::renderField($lineId, $line, $model, 'description')
            . self::renderField($lineId, $line, $model, 'quantity')
            . self::renderNewFields($lineId, $line, $model)
            . self::renderField($lineId, $line, $model, 'unitPrice')
            . self::renderField($lineId, $line, $model, 'discount')
            . self::renderField($lineId, $line, $model, 'taxCode')
            . self::renderField($lineId, $line, $model, '_total')
            . self::renderExpandButton($lineId, $model, 'salesFormAction')
            . '</div>'
            . self::renderLineModal($line, $lineId, $model) . '</div>';
    }

    /**
     * Apply form data to a specific line
     * @param array $formData
     * @param SalesDocumentLine $line
     * @param string $id
     */
    private static function applyToLine(array $formData, SalesDocumentLine &$line, string $id): void
    {
        $line->order = (int)$formData['order_' . $id];
        $line->quantity = (float)$formData['quantity_' . $id];
        $line->cost = floatval($formData['cost_' . $id] ?? $line->cost);
        $line->discount = (float)$formData['discount_' . $id];
        $line->discount2 = (float)$formData['discount2_' . $id];
        $line->description = $formData['description_' . $id];
        $line->vatException = $formData['vatException_' . $id] ?? null;
        $line->irpf = (float)($formData['irpf_' . $id] ?? '0');
        $line->showQuantity = (bool)($formData['showQuantity_' . $id] ?? '0');
        $line->showPrice = (bool)($formData['showPrice_' . $id] ?? '0');
        $line->pageBreak = (bool)($formData['pageBreak_' . $id] ?? '0');
        $line->supplied = (bool)($formData['supplied_' . $id] ?? '0');
        $line->unitPrice = (float)$formData['unitPrice_' . $id];
        
        // Tax change?
        if (isset($formData['taxCode_' . $id]) && $formData['taxCode_' . $id] !== $line->taxCode) {
            $tax = Taxes::get($formData['taxCode_' . $id]);
            $line->taxCode = $tax->taxCode;
            $line->vat = $tax->vat;
            if ($line->surcharge) {
                // If line already had surcharge, assign new one
                $line->surcharge = $tax->surcharge;
            }
        } else {
            $line->surcharge = (float)($formData['surcharge_' . $id] ?? '0');
        }
        
        // Apply mods to line
        foreach (self::$mods as $mod) {
            $mod->applyToLine($formData, $line, $id);
        }
    }

    /**
     * Render quantity field
     * @param string $lineId
     * @param SalesDocumentLine $line
     * @param SalesDocument $model
     * @param string $jsFunc
     * @return string
     */
    private static function quantity(string $lineId, SalesDocumentLine $line, SalesDocument $model, string $jsFunc): string
    {
        if (false === $model->editable) {
            return '<div class="col-sm-2 col-lg-1 order-3">'
                . '<div class="d-lg-none mt-2 small">' . Tools::trans('quantity') . '</div>'
                . '<div class="input-group input-group-sm">'
                . self::remainingQuantity($line, $model)
                . '<input type="number" class="form-control text-lg-end border-0" value="' . $line->quantity . '" disabled=""/>'
                . '</div>'
                . '</div>';
        }
        
        return '<div class="col-sm-2 col-lg-1 order-3">'
            . '<div class="d-lg-none mt-2 small">' . Tools::trans('quantity') . '</div>'
            . '<div class="input-group input-group-sm">'
            . self::remainingQuantity($line, $model)
            . '<input type="number" name="quantity_' . $lineId . '" value="' . $line->quantity
            . '" class="form-control text-lg-end border-0 doc-line-qty" onkeyup="return ' . $jsFunc . '(\'recalculate-line\', \'0\', event);"/>'
            . self::quantityStock($line, $model)
            . '</div>'
            . '</div>';
    }

    /**
     * Render stock quantity
     * @param SalesDocumentLine $line
     * @param SalesDocument $model
     * @return string
     */
    private static function quantityStock(SalesDocumentLine $line, SalesDocument $model): string
    {
        $html = '';
        if (empty($line->reference) || $line->modelClassName() === 'CustomerInvoiceLine' || false === $model->editable) {
            return $html;
        }
        
        $product = $line->getProduct();
        if ($product->noStock) {
            return $html;
        }
        
        // Find stock for this product in this warehouse
        $stock = self::$stocks[$line->reference] ?? new Stock();
        
        switch ($line->stockUpdate) {
            case -1:
            case -2:
                $html = $stock->available > 0 ?
                    '<a href="' . $stock->url() . '" target="_Blank" class="btn btn-outline-success">' . $stock->available . '</a>' :
                    '<a href="' . $stock->url() . '" target="_Blank" class="btn btn-outline-danger">' . $stock->available . '</a>';
                break;
            default:
                $html = $line->quantity <= $stock->quantity ?
                    '<a href="' . $stock->url() . '" target="_Blank" class="btn btn-outline-success">' . $stock->quantity . '</a>' :
                    '<a href="' . $stock->url() . '" target="_Blank" class="btn btn-outline-danger">' . $stock->quantity . '</a>';
                break;
        }
        
        return empty($html) ? $html :
            '<div class="input-group-prepend" title="' . Tools::trans('stock') . '">' . $html . '</div>';
    }

    /**
     * Render cost field
     * @param string $lineId
     * @param SalesDocumentLine $line
     * @param SalesDocument $model
     * @param string $field
     * @return string
     */
    private static function cost(string $lineId, SalesDocumentLine $line, SalesDocument $model, string $field): string
    {
        if (false === SalesHeaderHTML::checkLevel(Tools::settings('default', 'levelcostsales', 0))) {
            return '';
        }
        
        $attributes = $model->editable ?
            'name="' . $field . '_' . $lineId . '" min="0" step="any"' :
            'disabled=""';
            
        return '<div class="col-6">'
            . '<div class="mb-2">' . Tools::trans('cost')
            . '<input type="number" ' . $attributes . ' value="' . $line->{$field} . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    /**
     * Get fast line from barcode or reference
     * @param SalesDocument $model
     * @param array $formData
     * @return SalesDocumentLine|null
     */
    private static function getFastLine(SalesDocument $model, array $formData): ?SalesDocumentLine
    {
        if (empty($formData['fastli'])) {
            return $model->getNewLine();
        }
        
        // Search barcode in variants
        $whereBarcode = [new DataBaseWhere('barcode', $formData['fastli'])];
        foreach (ProductVariant::all($whereBarcode, [], 0, 5) as $variant) {
            // Check if product can be sold
            $product = $variant->getProduct();
            if (!$product->blocked && $product->sellable) {
                return $model->getNewProductLine($variant->reference);
            }
        }
        
        // Search barcode with mods
        foreach (self::$mods as $mod) {
            $line = $mod->getFastLine($model, $formData);
            if ($line) {
                return $line;
            }
        }
        
        Tools::log()->warning('product-not-found', ['%ref%' => $formData['fastli']]);
        return null;
    }

    /**
     * Render price field
     * @param string $lineId
     * @param SalesDocumentLine $line
     * @param SalesDocument $model
     * @param string $jsFunc
     * @return string
     */
    private static function price(string $lineId, SalesDocumentLine $line, SalesDocument $model, string $jsFunc): string
    {
        if (false === $model->editable) {
            return '<div class="col-sm col-lg-1 order-4">'
                . '<span class="d-lg-none small">' . Tools::trans('price') . '</span>'
                . '<input type="number" value="' . $line->unitPrice . '" class="form-control form-control-sm text-lg-end border-0" disabled/>'
                . '</div>';
        }
        
        $attributes = 'name="unitPrice_' . $lineId . '" onkeyup="return ' . $jsFunc . '(\'recalculate-line\', \'0\', event);"';
        return '<div class="col-sm col-lg-1 order-4">'
            . '<span class="d-lg-none small">' . Tools::trans('price') . '</span>'
            . '<input type="number" ' . $attributes . ' value="' . $line->unitPrice . '" class="form-control form-control-sm text-lg-end border-0"/>'
            . '</div>';
    }

    /**
     * Render a specific field
     * @param string $lineId
     * @param SalesDocumentLine $line
     * @param SalesDocument $model
     * @param string $field
     * @return string|null
     */
    private static function renderField(string $lineId, SalesDocumentLine $line, SalesDocument $model, string $field): ?string
    {
        foreach (self::$mods as $mod) {
            $html = $mod->renderField($lineId, $line, $model, $field);
            if ($html !== null) {
                return $html;
            }
        }
        
        switch ($field) {
            case '_total':
                return self::lineTotal($lineId, $line, $model, 'salesLineTotalWithTaxes', 'salesLineTotalWithoutTaxes');
            case 'quantity':
                return self::quantity($lineId, $line, $model, 'salesFormActionWait');
            case 'taxCode':
                return self::taxCode($lineId, $line, $model, 'salesFormAction');
            case 'cost':
                return self::cost($lineId, $line, $model, 'cost');
            case 'description':
                return self::description($lineId, $line, $model);
            case 'discount':
                return self::discount($lineId, $line, $model, 'salesFormActionWait');
            case 'discount2':
                return self::discount2($lineId, $line, $model, 'discount2', 'salesFormActionWait');
            case 'vatException':
                return self::vatException($lineId, $line, $model, 'vatException', 'salesFormActionWait');
            case 'irpf':
                return self::irpf($lineId, $line, $model, 'salesFormAction');
            case 'showQuantity':
                return self::genericBool($lineId, $line, $model, 'showQuantity', 'print-quantity');
            case 'showPrice':
                return self::genericBool($lineId, $line, $model, 'showPrice', 'print-price');
            case 'unitPrice':
                return self::price($lineId, $line, $model, 'salesFormActionWait');
            case 'surcharge':
                return self::surcharge($lineId, $line, $model, 'salesFormActionWait');
            case 'reference':
                return self::reference($lineId, $line, $model);
            case 'pageBreak':
                return self::genericBool($lineId, $line, $model, 'pageBreak', 'page-break');
            case 'supplied':
                return self::supplied($lineId, $line, $model, 'salesFormAction');
        }
        
        return null;
    }

    /**
     * Render line modal
     * @param SalesDocumentLine $line
     * @param string $lineId
     * @param SalesDocument $model
     * @return string
     */
    private static function renderLineModal(SalesDocumentLine $line, string $lineId, SalesDocument $model): string
    {
        return '<div class="modal fade" id="lineModal-' . $lineId . '" tabindex="-1" aria-labelledby="lineModal-' . $lineId . 'Label" aria-hidden="true">'
            . '<div class="modal-dialog modal-dialog-centered">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title"><i class="fa-solid fa-edit fa-fw" aria-hidden="true"></i> ' . $line->reference . '</h5>'
            . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
            . '</div>'
            . '<div class="modal-body">'
            . '<div class="row g-2">'
            . self::renderField($lineId, $line, $model, 'discount2')
            . self::renderField($lineId, $line, $model, 'surcharge')
            . self::renderField($lineId, $line, $model, 'irpf')
            . self::renderField($lineId, $line, $model, 'vatException')
            . self::renderField($lineId, $line, $model, 'supplied')
            . self::renderField($lineId, $line, $model, 'cost')
            . self::renderField($lineId, $line, $model, 'showQuantity')
            . self::renderField($lineId, $line, $model, 'showPrice')
            . self::renderField($lineId, $line, $model, 'pageBreak')
            . self::renderNewModalFields($lineId, $line, $model)
            . '</div>'
            . '</div>'
            . '<div class="modal-footer">'
            . '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Tools::trans('close') . '</button>'
            . '<button type="button" class="btn btn-primary" data-bs-dismiss="modal">' . Tools::trans('accept') . '</button>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render new modal fields from mods
     * @param string $lineId
     * @param SalesDocumentLine $line
     * @param SalesDocument $model
     * @return string
     */
    private static function renderNewModalFields(string $lineId, SalesDocumentLine $line, SalesDocument $model): string
    {
        $newFields = [];
        foreach (self::$mods as $mod) {
            foreach ($mod->newModalFields() as $field) {
                if (false === in_array($field, $newFields)) {
                    $newFields[] = $field;
                }
            }
        }
        
        $html = '';
        foreach ($newFields as $field) {
            foreach (self::$mods as $mod) {
                $fieldHtml = $mod->renderField($lineId, $line, $model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }

    /**
     * Render new fields from mods
     * @param string $lineId
     * @param SalesDocumentLine $line
     * @param SalesDocument $model
     * @return string
     */
    private static function renderNewFields(string $lineId, SalesDocumentLine $line, SalesDocument $model): string
    {
        $newFields = [];
        foreach (self::$mods as $mod) {
            foreach ($mod->newFields() as $field) {
                if (false === in_array($field, $newFields)) {
                    $newFields[] = $field;
                }
            }
        }
        
        $html = '';
        foreach ($newFields as $field) {
            foreach (self::$mods as $mod) {
                $fieldHtml = $mod->renderField($lineId, $line, $model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }

    /**
     * Render new titles from mods
     * @param SalesDocument $model
     * @return string
     */
    private static function renderNewTitles(SalesDocument $model): string
    {
        $newFields = [];
        foreach (self::$mods as $mod) {
            foreach ($mod->newTitles() as $field) {
                if (false === in_array($field, $newFields)) {
                    $newFields[] = $field;
                }
            }
        }
        
        $html = '';
        foreach ($newFields as $field) {
            foreach (self::$mods as $mod) {
                $fieldHtml = $mod->renderTitle($model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }

    /**
     * Render a specific title
     * @param SalesDocument $model
     * @param string $field
     * @return string|null
     */
    private static function renderTitle(SalesDocument $model, string $field): ?string
    {
        foreach (self::$mods as $mod) {
            $html = $mod->renderTitle($model, $field);
            if ($html !== null) {
                return $html;
            }
        }
        
        switch ($field) {
            case '_actionsButton':
                return self::titleActionsButton($model);
            case '_total':
                return self::titleTotal();
            case 'quantity':
                return self::titleQuantity();
            case 'taxCode':
                return self::titleTaxCode();
            case 'description':
                return self::titleDescription();
            case 'discount':
                return self::titleDiscount();
            case 'unitPrice':
                return self::titlePrice();
            case 'reference':
                return self::titleReference();
        }
        
        return null;
    }

    /**
     * Render column titles
     * @param SalesDocument $model
     * @return string
     */
    private static function renderTitles(SalesDocument $model): string
    {
        return '<div class="container-fluid d-none d-lg-block titles pt-3"><div class="row g-2 border-bottom">'
            . self::renderTitle($model, 'reference')
            . self::renderTitle($model, 'description')
            . self::renderTitle($model, 'quantity')
            . self::renderNewTitles($model)
            . self::renderTitle($model, 'unitPrice')
            . self::renderTitle($model, 'discount')
            . self::renderTitle($model, 'taxCode')
            . self::renderTitle($model, '_total')
            . self::renderTitle($model, '_actionsButton')
            . '</div></div>';
    }
}