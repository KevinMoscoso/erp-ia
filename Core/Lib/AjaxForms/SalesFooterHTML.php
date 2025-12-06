<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2021-2024 ERPIA Team
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

use ERPIA\Core\Contract\SalesModInterface;
use ERPIA\Core\Model\Base\SalesDocument;
use ERPIA\Core\Tools;

/**
 * Description of SalesFooterHTML
 *
 * @author ERPIA Team
 */
class SalesFooterHTML
{
    use CommonSalesPurchases;

    /** @var SalesModInterface[] */
    private static $mods = [];

    /**
     * Add a modification module
     * @param SalesModInterface $mod
     */
    public static function addMod(SalesModInterface $mod): void
    {
        self::$mods[] = $mod;
    }

    /**
     * Apply form data to the model
     * @param SalesDocument $model
     * @param array $formData
     */
    public static function apply(SalesDocument &$model, array $formData): void
    {
        // Apply mods before
        foreach (self::$mods as $mod) {
            $mod->applyBefore($model, $formData);
        }

        self::$columnView = $formData['columnView'] ?? Tools::settings('default', 'columnetosubtotal', 'subtotal');

        $model->discount1 = isset($formData['discount1']) ? (float)$formData['discount1'] : $model->discount1;
        $model->discount2 = isset($formData['discount2']) ? (float)$formData['discount2'] : $model->discount2;
        $model->notes = $formData['notes'] ?? $model->notes;

        // Apply mods after
        foreach (self::$mods as $mod) {
            $mod->apply($model, $formData);
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
     * Render the sales footer
     * @param SalesDocument $model
     * @return string
     */
    public static function render(SalesDocument $model): string
    {
        if (empty(self::$columnView)) {
            self::$columnView = Tools::settings('default', 'columnetosubtotal', 'subtotal');
        }

        if (empty($model->customerCode)) {
            return '';
        }

        return '<div class="container-fluid mt-3">'
            . '<div class="row g-2">'
            . self::renderField($model, '_productBtn')
            . self::renderField($model, '_newLineBtn')
            . self::renderField($model, '_sortableBtn')
            . self::renderField($model, '_fastLineInput')
            . self::renderField($model, '_subtotalNetBtn')
            . '</div>'
            . '<div class="row g-2">'
            . self::renderField($model, 'notes')
            . self::renderNewFields($model)
            . self::renderField($model, 'netWithoutDiscount')
            . self::renderField($model, 'discount1')
            . self::renderField($model, 'discount2')
            . self::renderField($model, 'net')
            . self::renderField($model, 'totalVat')
            . self::renderField($model, 'totalSurcharge')
            . self::renderField($model, 'totalIrpf')
            . self::renderField($model, 'totalSupplied')
            . self::renderField($model, 'totalCost')
            . self::renderField($model, 'totalProfit')
            . self::renderField($model, 'total')
            . '</div>'
            . '<div class="row g-2">'
            . '<div class="col-auto">'
            . self::renderField($model, '_deleteBtn')
            . '</div>'
            . '<div class="col text-end">'
            . self::renderNewBtnFields($model)
            . self::renderField($model, '_modalFooter')
            . self::renderField($model, '_undoBtn')
            . self::renderField($model, '_saveBtn')
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render modal footer button
     * @param SalesDocument $model
     * @return string
     */
    private static function modalFooter(SalesDocument $model): string
    {
        $htmlModal = self::renderNewModalFields($model);

        if (empty($htmlModal)) {
            return '';
        }

        return '<button class="btn btn-outline-secondary me-2" type="button" data-bs-toggle="modal" data-bs-target="#footerModal">'
            . '<i class="fa-solid fa-plus fa-fw" aria-hidden="true"></i></button>'
            . self::modalFooterHtml($htmlModal);
    }

    /**
     * Generate modal HTML
     * @param string $htmlModal
     * @return string
     */
    private static function modalFooterHtml(string $htmlModal): string
    {
        return '<div class="modal fade" id="footerModal" tabindex="-1" aria-labelledby="footerModalLabel" aria-hidden="true">'
            . '<div class="modal-dialog modal-dialog-centered modal-lg">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title">' . Tools::trans('detail') . ' ' . Tools::trans('footer') . '</h5>'
            . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
            . '</div>'
            . '<div class="modal-body">'
            . '<div class="row g-2">'
            . $htmlModal
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
     * Render a specific field
     * @param SalesDocument $model
     * @param string $field
     * @return string|null
     */
    private static function renderField(SalesDocument $model, string $field): ?string
    {
        foreach (self::$mods as $mod) {
            $html = $mod->renderField($model, $field);
            if ($html !== null) {
                return $html;
            }
        }

        switch ($field) {
            case '_deleteBtn':
                return self::deleteBtn($model, 'salesFormSave');

            case '_fastLineInput':
                return self::fastLineInput($model, 'salesFastLine');

            case '_modalFooter':
                return self::modalFooter($model);

            case '_newLineBtn':
                return self::newLineBtn($model, 'salesFormAction');

            case '_productBtn':
                return self::productBtn($model);

            case '_saveBtn':
                return self::saveBtn($model, 'salesFormSave');

            case '_sortableBtn':
                return self::sortableBtn($model);

            case '_subtotalNetBtn':
                return self::subtotalNetBtn();

            case '_undoBtn':
                return self::undoBtn($model);

            case 'discount1':
                return self::discount1($model, 'salesFormActionWait');

            case 'discount2':
                return self::discount2($model, 'salesFormActionWait');

            case 'net':
                return self::column($model, 'net', 'net', true);

            case 'netWithoutDiscount':
                return self::netWithoutDiscount($model);

            case 'notes':
                return self::notes($model);

            case 'total':
                return self::column($model, 'total', 'total');

            case 'totalProfit':
                return self::column($model, 'totalProfit', 'profits', true, Tools::settings('default', 'levelbenefitsales', 0));

            case 'totalCost':
                return self::column($model, 'totalCost', 'total-cost', true, Tools::settings('default', 'levelcostsales', 0));

            case 'totalIrpf':
                return self::column($model, 'totalIrpf', 'irpf', true);

            case 'totalVat':
                return self::column($model, 'totalVat', 'taxes', true);

            case 'totalSurcharge':
                return self::column($model, 'totalSurcharge', 're', true);

            case 'totalSupplied':
                return self::column($model, 'totalSupplied', 'supplied-amount', true);
        }

        return null;
    }

    /**
     * Render new button fields from mods
     * @param SalesDocument $model
     * @return string
     */
    private static function renderNewBtnFields(SalesDocument $model): string
    {
        $newFields = [];
        foreach (self::$mods as $mod) {
            foreach ($mod->newBtnFields() as $field) {
                if (false === in_array($field, $newFields)) {
                    $newFields[] = $field;
                }
            }
        }

        $html = '';
        foreach ($newFields as $field) {
            foreach (self::$mods as $mod) {
                $fieldHtml = $mod->renderField($model, $field);
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
     * @param SalesDocument $model
     * @return string
     */
    private static function renderNewFields(SalesDocument $model): string
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
                $fieldHtml = $mod->renderField($model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }

    /**
     * Render new modal fields from mods
     * @param SalesDocument $model
     * @return string
     */
    private static function renderNewModalFields(SalesDocument $model): string
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
                $fieldHtml = $mod->renderField($model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }

        return $html;
    }
}