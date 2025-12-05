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

namespace ERPIA\Core\Lib\AjaxForms;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\DataSrc\Impuestos;
use ERPIA\Core\DataSrc\Series;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Asiento;
use ERPIA\Dinamic\Model\Partida;
use ERPIA\Dinamic\Model\Subcuenta;

/**
 * Description of AccountingLineHTML
 *
 * @author ERPIA Development Team
 */
class AccountingLineHTML
{
    /** @var array */
    protected static $removedLines = [];

    /** @var int */
    protected static $lineCounter = 0;

    /**
     * @param Asiento $model
     * @param Partida[] $lines
     * @param array $formData
     */
    public static function apply(Asiento &$model, array &$lines, array $formData): void
    {
        // update or remove lines
        $removeLineId = $formData['action'] === 'remove-line' ? $formData['selectedLine'] : 0;
        foreach ($lines as $key => $value) {
            if ($value->idpartida === (int)$removeLineId || false === isset($formData['codsubcuenta_' . $value->idpartida])) {
                self::$removedLines[] = $value->idpartida;
                unset($lines[$key]);
                continue;
            }

            self::updateLineFromForm($formData, $lines[$key], (string)$value->idpartida);
        }

        // new lines
        for ($counter = 1; $counter < 1000; $counter++) {
            if (isset($formData['codsubcuenta_n' . $counter]) && $removeLineId !== 'n' . $counter) {
                $newLine = $model->getNewLine();
                $newLineId = 'n' . $counter;
                self::updateLineFromForm($formData, $newLine, $newLineId);
                $lines[] = $newLine;
            }
        }

        // Calculate model debit and credit
        self::recalculateBalance($model, $lines);

        // add new line
        if ($formData['action'] === 'add-line' && !empty($formData['new_subaccount_input'])) {
            $subcuenta = self::getSubAccount($formData['new_subaccount_input'], $model);
            if (false === $subcuenta->exists()) {
                Tools::log()->error('subaccount-not-found', ['%code%' => $formData['new_subaccount_input']]);
                return;
            }

            $decimalPlaces = Tools::settings('default', 'decimals', 2);

            $newLine = $model->getNewLine();
            $newLine->setAccount($subcuenta);
            $newLine->debe = ($model->debe < $model->haber) ? round($model->haber - $model->debe, $decimalPlaces) : 0.00;
            $newLine->haber = ($model->debe > $model->haber) ? round($model->debe - $model->haber, $decimalPlaces) : 0.00;
            $lines[] = $newLine;

            self::recalculateBalance($model, $lines);
        }
    }

    /**
     * Returns the list of deleted lines.
     */
    public static function getRemovedLines(): array
    {
        return self::$removedLines;
    }

    /**
     * Render the lines of the accounting entry.
     */
    public static function render(array $lines, Asiento $model): string
    {
        $html = '';
        foreach ($lines as $line) {
            $html .= self::renderSingleLine($line, $model);
        }

        return empty($html) ?
            '<div class="alert alert-warning border-top mb-0">' . Tools::trans('no-accounting-lines-found') . '</div>' :
            $html;
    }

    /**
     * Render one of the lines of the accounting entry
     */
    public static function renderSingleLine(Partida $line, Asiento $model): string
    {
        self::$lineCounter++;
        $lineId = $line->idpartida ?? 'n' . self::$lineCounter;
        $cssClass = self::$lineCounter % 2 == 0 ? 'bg-white border-top' : 'bg-light border-top';
        return '<div class="' . $cssClass . ' line ps-2 pe-2">'
            . '<div class="row g-2 align-items-end">'
            . self::subAccountField($line, $model)
            . self::debitField($line, $model)
            . self::creditField($line, $model)
            . self::expandButton($lineId, $model)
            . '</div>'
            . self::lineModal($line, $lineId, $model)
            . '</div>';
    }

    private static function lineModal(Partida $line, string $lineId, Asiento $model): string
    {
        return '<div class="modal fade" id="lineModal-' . $lineId . '" tabindex="-1" aria-labelledby="lineModal-' . $lineId . 'Label" aria-hidden="true">'
            . '<div class="modal-dialog modal-dialog-centered">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title">' . $line->codsubcuenta . '</h5>'
            . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
            . '</div>'
            . '<div class="modal-body">'
            . '<div class="row g-2">'
            . self::vatField($line, $model)
            . self::surchargeField($line, $model)
            . '</div>'
            . '<div class="row g-2">'
            . self::taxBaseField($line, $model)
            . self::taxIdField($line, $model)
            . '</div>'
            . '<div class="row g-2">'
            . self::documentField($line, $model)
            . self::seriesField($line, $model)
            . '</div>'
            . '</div>'
            . '<div class="modal-footer">'
            . '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">'
            . Tools::trans('close')
            . '</button>'
            . '<button type="button" class="btn btn-primary" data-bs-dismiss="modal">'
            . Tools::trans('accept')
            . '</button>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    protected static function updateLineFromForm(array &$formData, Partida &$line, string $id): void
    {
        $line->baseimponible = (float)($formData['baseimponible_' . $id] ?? '0');
        $line->cifnif = $formData['cifnif_' . $id] ?? '';
        $line->codserie = $formData['codserie_' . $id] ?? '';
        $line->concepto = $formData['concepto_' . $id] ?? '';
        $line->codcontrapartida = $formData['codcontrapartida_' . $id] ?? '';
        $line->codsubcuenta = $formData['codsubcuenta_' . $id] ?? '';
        $line->debe = (float)($formData['debe_' . $id] ?? '0');
        $line->documento = $formData['documento_' . $id] ?? '';
        $line->haber = (float)($formData['haber_' . $id] ?? '0');

        $line->iva = $formData['iva_' . $id] === '' ? null : (float)$formData['iva_' . $id];
        $line->orden = (int)($formData['orden_' . $id] ?? '0');
        $line->recargo = (float)($formData['recargo_' . $id] ?? '0');
    }

    /**
     * Amount base for apply tax.
     */
    protected static function taxBaseField(Partida $line, Asiento $model): string
    {
        $lineId = $line->idpartida ?? 'n' . self::$lineCounter;
        $attributes = $model->editable ? 'name="baseimponible_' . $lineId . '"' : 'disabled';
        return '<div class="col pb-2 small">' . Tools::trans('tax-base')
            . '<input type="number" ' . $attributes . ' value="' . floatval($line->baseimponible)
            . '" class="form-control" step="any" autocomplete="off">'
            . '</div>';
    }

    public static function recalculateBalance(Asiento &$model, array $lines): void
    {
        $model->debe = 0.0;
        $model->haber = 0.0;
        foreach ($lines as $line) {
            $model->debe += $line->debe;
            $model->haber += $line->haber;
        }
        $model->importe = max([$model->debe, $model->haber]);
    }

    protected static function taxIdField(Partida $line, Asiento $model): string
    {
        $lineId = $line->idpartida ?? 'n' . self::$lineCounter;
        $attributes = $model->editable ? 'name="cifnif_' . $lineId . '"' : 'disabled';
        return '<div class="col pb-2 small">' . Tools::trans('tax-id')
            . '<input type="text" ' . $attributes . ' value="' . Tools::stripHtml($line->cifnif)
            . '" class="form-control" maxlength="30" autocomplete="off"/>'
            . '</div>';
    }

    protected static function seriesField(Partida $line, Asiento $model): string
    {
        $options = ['<option value="">------</option>'];
        foreach (Series::all() as $row) {
            if ($row->codserie === $line->codserie) {
                $options[] = '<option value="' . $row->codserie . '" selected>' . $row->descripcion . '</option>';
                continue;
            }

            $options[] = '<option value="' . $row->codserie . '">' . $row->descripcion . '</option>';
        }

        $lineId = $line->idpartida ?? 'n' . self::$lineCounter;
        $attributes = $model->editable ? 'name="codserie_' . $lineId . '"' : 'disabled';
        return '<div class="col pb-2 small"><a href="ListSerie">' . Tools::trans('series') . '</a>'
            . '<select ' . $attributes . ' class="form-select">' . implode('', $options) . '</select>'
            . '</div>';
    }

    protected static function conceptField(Partida $line, Asiento $model): string
    {
        $lineId = $line->idpartida ?? 'n' . self::$lineCounter;
        $attributes = $model->editable
            ? 'name="concepto_' . $lineId . '" onchange="return recalculateAccountingLine(\'recalculate\', \'' . $lineId . '\');"'
            : 'disabled';

        return '<div class="col-sm-12 col-lg pb-2 small">' . Tools::trans('concept')
            . '<input type="text" ' . $attributes . ' class="form-control" value="' . Tools::stripHtml($line->concepto) . '">'
            . '</div>';
    }

    protected static function counterpartField(Partida $line, Asiento $model): string
    {
        $lineId = $line->idpartida ?? 'n' . self::$lineCounter;
        $attributes = $model->editable
            ? 'name="codcontrapartida_' . $lineId . '" onchange="return recalculateAccountingLine(\'recalculate\', \'' . $lineId . '\');"'
            : 'disabled';

        return '<div class="col-sm-6 col-lg pb-2 small">' . Tools::trans('counterpart')
            . '<input type="text" ' . $attributes . ' value="' . $line->codcontrapartida
            . '" class="form-control" maxlength="15" autocomplete="off" placeholder="' . Tools::trans('optional') . '"/>'
            . '</div>';
    }

    protected static function debitField(Partida $line, Asiento $model): string
    {
        $lineId = $line->idpartida ?? 'n' . self::$lineCounter;
        $attributes = $model->editable
            ? 'name="debe_' . $lineId . '" step="1" onchange="return recalculateAccountingLine(\'recalculate\', \'' . $lineId . '\');"'
            : 'disabled';

        return '<div class="col pb-2 small">' . Tools::trans('debit')
            . '<input type="number" class="form-control line-debit" ' . $attributes . ' value="' . floatval($line->debe) . '"/>'
            . '</div>';
    }

    protected static function documentField(Partida $line, Asiento $model): string
    {
        $lineId = $line->idpartida ?? 'n' . self::$lineCounter;
        $attributes = $model->editable ? 'name="documento_' . $lineId . '"' : 'disabled';
        return '<div class="col pb-2 small">' . Tools::trans('document')
            . '<input type="text" ' . $attributes . ' value="' . Tools::stripHtml($line->documento)
            . '" class="form-control" maxlength="30" autocomplete="off"/>'
            . '</div>';
    }

    protected static function getSubAccount(string $code, Asiento $model): Subcuenta
    {
        $subcuenta = new Subcuenta();
        if (empty($code) || empty($model->codejercicio)) {
            return $subcuenta;
        }

        $where = [
            new DataBaseWhere('codejercicio', $model->codejercicio),
            new DataBaseWhere('codsubcuenta', $subcuenta->transformCodsubcuenta($code, $model->codejercicio))
        ];
        $subcuenta->loadWhere($where);
        return $subcuenta;
    }

    protected static function creditField(Partida $line, Asiento $model): string
    {
        $lineId = $line->idpartida ?? 'n' . self::$lineCounter;
        $attributes = $model->editable
            ? 'name="haber_' . $lineId . '" step="1" onchange="return recalculateAccountingLine(\'recalculate\', \'' . $lineId . '\');"'
            : 'disabled';

        $decimalPlaces = Tools::settings('default', 'decimals', 2);
        return '<div class="col pb-2 small">' . Tools::trans('credit')
            . '<input type="number" class="form-control" ' . $attributes . ' value="' . round($line->haber, $decimalPlaces) . '"/>'
            . '</div>';
    }

    protected static function vatField(Partida $line, Asiento $model): string
    {
        $selectedTax = null;
        foreach (Impuestos::all() as $tax) {
            if ($tax->codsubcuentarep || $tax->codsubcuentasop) {
                if (in_array($line->codsubcuenta, [$tax->codsubcuentarep, $tax->codsubcuentasop])) {
                    $selectedTax = $tax->codimpuesto;
                    break;
                }
            }

            if ($tax->iva === $line->iva) {
                $selectedTax = $tax->codimpuesto;
            }
        }

        $options = ['<option value="">------</option>'];
        foreach (Impuestos::all() as $tax) {
            $selected = $tax->codimpuesto === $selectedTax ? ' selected' : '';
            $options[] = '<option value="' . $tax->iva . '"' . $selected . '>' . $tax->descripcion . '</option>';
        }

        $lineId = $line->idpartida ?? 'n' . self::$lineCounter;
        $attributes = $model->editable ? 'name="iva_' . $lineId . '"' : 'disabled';
        return '<div class="col pb-2 small"><a href="ListImpuesto">' . Tools::trans('vat') . '</a>'
            . '<select ' . $attributes . ' class="form-select">' . implode('', $options) . '</select>'
            . '</div>';
    }

    protected static function surchargeField(Partida $line, Asiento $model): string
    {
        $lineId = $line->idpartida ?? 'n' . self::$lineCounter;
        $attributes = $model->editable ? 'name="recargo_' . $lineId . '"' : 'disabled';
        return '<div class="col pb-2 small">' . Tools::trans('surcharge')
            . '<input type="number" ' . $attributes . ' value="' . floatval($line->recargo)
            . '" class="form-control" step="any" autocomplete="off">'
            . '</div>';
    }

    protected static function expandButton(string $lineId, Asiento $model): string
    {
        if ($model->editable) {
            return '<div class="col-sm-auto pb-1">'
                . '<button type="button" data-bs-toggle="modal" data-bs-target="#lineModal-' . $lineId . '" class="btn btn-outline-secondary mb-1" title="'
                . Tools::trans('more') . '"><i class="fa-solid fa-ellipsis-h"></i></button>'
                . '<button class="btn btn-outline-danger btn-spin-action ms-2 mb-1" type="button" title="' . Tools::trans('delete') . '"'
                . ' onclick="return accountingEntryAction(\'remove-line\', \'' . $lineId . '\');">'
                . '<i class="fa-solid fa-trash-alt"></i></button></div>';
        }

        return '<div class="col-sm-auto pb-1">'
            . '<button type="button" data-bs-toggle="modal" data-bs-target="#lineModal-' . $lineId . '" class="btn btn-outline-secondary mb-1" title="'
            . Tools::trans('more') . '"><i class="fa-solid fa-ellipsis-h"></i></button></div>';
    }

    protected static function balanceField(Subcuenta $subcuenta): string
    {
        return '<div class="col pb-2 small">' . Tools::trans('balance')
            . '<input type="text" class="form-control" value="' . Tools::formatNumber($subcuenta->saldo) . '" tabindex="-1" readonly>'
            . '</div>';
    }

    protected static function subAccountField(Partida $line, Asiento $model): string
    {
        $lineId = $line->idpartida ?? 'n' . self::$lineCounter;
        $subcuenta = self::getSubAccount($line->codsubcuenta, $model);
        if (false === $model->editable) {
            return '<div class="col-sm-6 col-lg pb-2 small">' . $subcuenta->descripcion
                . '<div class="input-group">'
                . '<input type="text" value="' . $line->codsubcuenta . '" class="form-control" tabindex="-1" readonly>'
                . '<a href="' . $subcuenta->url() . '" target="_blank" class="btn btn-outline-primary">'
                . '<i class="fa-regular fa-eye"></i></a>'
                . '</div>'
                . '</div>'
                . self::counterpartField($line, $model)
                . self::conceptField($line, $model);
        }

        return '<div class="col-sm-6 col-lg pb-2 small">'
            . '<input type="hidden" name="orden_' . $lineId . '" value="' . $line->orden . '"/>' . $subcuenta->descripcion
            . '<div class="input-group">'
            . '<input type="text" name="codsubcuenta_' . $lineId . '" value="' . $line->codsubcuenta . '" class="form-control" tabindex="-1" readonly>'
            . '<a href="' . $subcuenta->url() . '" target="_blank" class="btn btn-outline-primary">'
            . '<i class="fa-regular fa-eye"></i></a>'
            . '</div>'
            . '</div>'
            . self::counterpartField($line, $model)
            . self::conceptField($line, $model);
    }
}