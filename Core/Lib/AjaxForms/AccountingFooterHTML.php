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

use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Asiento;

/**
 * Description of AccountingFooterHTML
 *
 * @author ERPIA Development Team
 */
class AccountingFooterHTML
{
    public static function apply(Asiento &$model, array $formData): void
    {
    }

    public static function render(Asiento $model): string
    {
        return '<div class="container-fluid">'
            . '<div class="row g-2 align-items-center mt-3">'
            . self::newSubaccountInput($model)
            . self::moveLinesButton($model)
            . self::amountField($model)
            . self::balanceDifference($model)
            . '</div>'
            . '<div class="row g-2 mt-3">'
            . self::deleteButton($model)
            . '<div class="col-sm"></div>'
            . self::saveButton($model)
            . '</div>'
            . '</div>';
    }

    protected static function deleteButton(Asiento $model): string
    {
        if (false === $model->exists() || false === $model->editable) {
            return '';
        }

        $lockButton = '';
        if ($model->editable) {
            $lockButton .= '<div class="col-sm-auto">'
                . '<button type="button" class="btn w-100 btn-warning btn-spin-action mb-2" onclick="return accountingEntrySave(\'lock-entry\', \'0\');">'
                . '<i class="fa-solid fa-lock fa-fw"></i> ' . Tools::trans('lock-entry') . '</button>'
                . '</div>';
        }

        return '<div class="col-sm-auto">'
            . '<button type="button" class="btn w-100 btn-danger btn-spin-action mb-2" data-bs-toggle="modal" data-bs-target="#deleteEntryModal">'
            . '<i class="fa-solid fa-trash-alt fa-fw"></i> ' . Tools::trans('delete') . '</button>'
            . '</div>'
            . $lockButton
            . '<div class="modal fade" id="deleteEntryModal" tabindex="-1" aria-hidden="true">'
            . '<div class="modal-dialog">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title"></h5>'
            . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
            . '</div>'
            . '<div class="modal-body text-center">'
            . '<i class="fa-solid fa-trash-alt fa-3x"></i>'
            . '<h5 class="mt-3 mb-1">' . Tools::trans('confirm-deletion') . '</h5>'
            . '<p class="mb-0">' . Tools::trans('action-cannot-undone') . '</p>'
            . '</div>'
            . '<div class="modal-footer">'
            . '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Tools::trans('cancel') . '</button>'
            . '<button type="button" class="btn btn-danger btn-spin-action" onclick="return accountingEntrySave(\'delete-entry\', \'0\');">' . Tools::trans('delete') . '</button>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render the unbalance value
     */
    protected static function balanceDifference(Asiento $model): string
    {
        $decimalPlaces = Tools::settings('default', 'decimals', 2);
        $unbalance = isset($model->debe, $model->haber) ? round($model->debe - $model->haber, $decimalPlaces) : 0.0;
        if (empty($unbalance)) {
            return '';
        }

        return '<div class="col-sm-6 col-md-4 col-lg-2 mb-2">'
            . '<div class="input-group">'
            . '<span class="input-group-text text-danger">' . Tools::trans('balance-difference') . '</span>'
            . '<input type="number" value="' . $unbalance . '" class="form-control" step="any" readonly>'
            . '</div></div>';
    }

    /**
     * Render the amount field
     */
    protected static function amountField(Asiento $model): string
    {
        return '<div class="col-sm-6 col-md-4 col-lg-2 mb-2">'
            . '<div class="input-group">'
            . '<span class="input-group-text">' . Tools::trans('total-amount') . '</span>'
            . '<input type="number" value="' . $model->importe . '" class="form-control" step="any" tabindex="-1" readonly>'
            . '</div></div>';
    }

    protected static function newSubaccountInput(Asiento $model): string
    {
        if (false === $model->editable) {
            return '<div class="col-sm"></div>';
        }

        return '<div class="col-sm-12 col-md-6 col-lg-3 col-xl-2 mb-2">'
            . '<div class="input-group">'
            . '<input type="text" class="form-control" maxlength="15" autocomplete="off" placeholder="' . Tools::trans('subaccount-code')
            . '" id="new_subaccount_input" name="new_subaccount_input" onchange="return addNewLine(this.value);"/>'
            . '<button class="btn btn-info" type="button" title="' . Tools::trans('subaccount-list') . '"'
            . ' onclick="$(\'#findSubaccountModal\').modal(\'show\'); $(\'#findSubaccountInput\').focus();"><i class="fa-solid fa-book"></i></button>'
            . '</div>'
            . '</div>'
            . '<div class="col-sm-12 col-md-6 col-lg">'
            . '<p class="text-muted">' . Tools::trans('enter-subaccount-code') . '</p>'
            . '</div>';
    }

    protected static function saveButton(Asiento $model): string
    {
        if (false === $model->editable) {
            return '<div class="col-sm-auto">'
                . '<button type="button" class="btn w-100 btn-warning btn-spin-action mb-2" onclick="return accountingEntrySave(\'unlock-entry\', \'0\');">'
                . '<i class="fa-solid fa-lock-open fa-fw"></i> ' . Tools::trans('unlock-entry') . '</button>'
                . '</div>';
        }

        return '<div class="col-sm-auto">'
            . '<button type="button" class="btn w-100 btn-primary btn-spin-action mb-2" load-after="true" onclick="return accountingEntrySave(\'save-entry\', \'0\');">'
            . '<i class="fa-solid fa-save fa-fw"></i> ' . Tools::trans('save') . '</button>'
            . '</div>';
    }

    protected static function moveLinesButton(Asiento $model): string
    {
        if (false === $model->editable) {
            return '';
        }

        return '<div class="col-sm-auto">'
            . '<button type="button" class="btn btn-light mb-2" id="reorderLinesBtn">'
            . '<i class="fa-solid fa-arrows-alt-v fa-fw"></i> ' . Tools::trans('reorder-lines')
            . '</button>'
            . '</div>';
    }
}