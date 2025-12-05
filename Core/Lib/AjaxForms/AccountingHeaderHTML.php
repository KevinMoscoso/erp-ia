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
use ERPIA\Core\DataSrc\Empresas;
use ERPIA\Core\Lib\CodePatterns;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Asiento;
use ERPIA\Dinamic\Model\ConceptoPartida;
use ERPIA\Dinamic\Model\Diario;
use ERPIA\Dinamic\Model\FacturaCliente;
use ERPIA\Dinamic\Model\FacturaProveedor;

/**
 * Description of AccountingHeaderHTML
 *
 * @author ERPIA Development Team
 */
class AccountingHeaderHTML
{
    public static function apply(Asiento &$model, array $formData): void
    {
        $model->idempresa = $formData['idempresa'] ?? $model->idempresa;
        $model->setDate($formData['fecha'] ?? $model->fecha);
        $model->canal = $formData['canal'] ?? $model->canal;
        $model->concepto = $formData['concepto'] ?? $model->concepto;
        $model->iddiario = !empty($formData['iddiario']) ? $formData['iddiario'] : null;
        $model->documento = $formData['documento'] ?? $model->documento;
        $model->operacion = !empty($formData['operacion']) ? $formData['operacion'] : null;
    }

    public static function render(Asiento $model): string
    {
        return '<div class="container-fluid">'
            . '<div class="row g-2">'
            . self::companyField($model)
            . self::dateField($model)
            . self::conceptField($model)
            . self::documentField($model)
            . self::journalField($model)
            . self::channelField($model)
            . self::operationField($model)
            . '</div></div><br/>';
    }

    protected static function channelField(Asiento $model): string
    {
        $attributes = $model->editable ? 'name="canal"' : 'disabled';
        return '<div class="col-sm-6 col-md-4 col-lg-2">'
            . '<div class="mb-2">' . Tools::trans('channel')
            . '<input type="number" ' . $attributes . ' value="' . $model->canal . '" placeholder="'
            . Tools::trans('optional') . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    protected static function conceptField(Asiento $model): string
    {
        $attributes = $model->editable ? 'name="concepto" autocomplete="off" required' : 'disabled';
        return '<div class="col-sm-6 col-md-4 col-lg">'
            . '<div class="mb-2">' . Tools::trans('concept')
            . '<input type="text" list="concept-list" ' . $attributes . ' value="' . Tools::stripHtml($model->concepto) . '" class="form-control"/>'
            . '<datalist id="concept-list">' . self::getConceptOptions($model) . '</datalist>'
            . '</div>'
            . '</div>';
    }

    protected static function documentField(Asiento $model): string
    {
        if (empty($model->documento)) {
            return '';
        }

        $documentLink = '';
        $customerInvoice = new FacturaCliente();
        $documentConditions = [
            new DataBaseWhere('codigo', $model->documento),
            new DataBaseWhere('idasiento', $model->idasiento),
        ];
        if ($customerInvoice->loadWhere($documentConditions)) {
            $documentLink = $customerInvoice->url();
        } else {
            $supplierInvoice = new FacturaProveedor();
            if ($supplierInvoice->loadWhere($documentConditions)) {
                $documentLink = $supplierInvoice->url();
            }
        }

        if ($documentLink) {
            return '<div class="col-sm-6 col-md-4 col-lg-2">'
                . '<div class="mb-2">' . Tools::trans('document')
                . '<div class="input-group">'
                . '<a class="btn btn-outline-primary" href="' . $documentLink . '"><i class="fa-regular fa-eye"></i></a>'
                . '<input type="text" value="' . Tools::stripHtml($model->documento) . '" class="form-control" readonly/>'
                . '</div>'
                . '</div>'
                . '</div>';
        }

        return '<div class="col-sm-6 col-md-4 col-lg-2 mb-2">'
            . '<div class="mb-2">' . Tools::trans('document')
            . '<input type="text" value="' . Tools::stripHtml($model->documento) . '" class="form-control" readonly/>'
            . '</div></div>';
    }

    protected static function journalField(Asiento $model): string
    {
        $journalOptions = '<option value="">' . Tools::trans('optional') . '</option>'
            . '<option value="">------</option>';
        foreach (Diario::all([], [], 0, 0) as $journal) {
            $selected = $journal->iddiario === $model->iddiario ? 'selected' : '';
            $journalOptions .= '<option value="' . $journal->iddiario . '" ' . $selected . '>' . $journal->descripcion . '</option>';
        }

        $attributes = $model->editable ? 'name="iddiario"' : 'disabled';
        return '<div class="col-sm-6 col-md-4 col-lg-2">'
            . '<div class="mb-2">' . Tools::trans('journal')
            . '<select ' . $attributes . ' class="form-select">' . $journalOptions . '</select>'
            . '</div>'
            . '</div>';
    }

    protected static function dateField(Asiento $model): string
    {
        $attributes = $model->editable ? 'name="fecha" required' : 'disabled';
        return '<div class="col-sm-6 col-md-4 col-lg-2">'
            . '<div class="mb-2">' . Tools::trans('date')
            . '<input type="date" ' . $attributes . ' value="' . date('Y-m-d', strtotime($model->fecha)) . '" class="form-control" />'
            . '</div>'
            . '</div>';
    }

    private static function getConceptOptions(Asiento $model): string
    {
        $options = '';
        foreach (ConceptoPartida::all([], ['descripcion' => 'ASC']) as $concept) {
            $options .= '<option value="' . CodePatterns::translate($concept->descripcion, $model) . '">';
        }
        return $options;
    }

    /**
     * Returns the list of options.
     */
    private static function generateOptions(array &$items, string $key, string $name, $value): string
    {
        $options = '';
        foreach ($items as $item) {
            $selected = ($item->{$key} == $value) ? ' selected ' : '';
            $options .= '<option value="' . $item->{$key} . '"' . $selected . '>' . $item->{$name} . '</option>';
        }
        return $options;
    }

    protected static function companyField(Asiento $model): string
    {
        $companyList = Empresas::all();
        if (count($companyList) < 2) {
            return '<input type="hidden" name="idempresa" value=' . $model->idempresa . ' />';
        }

        $attributes = $model->id() ? 'readonly' : 'required';

        return '<div class="col-sm-6 col-md-4 col-lg-2">'
            . '<div class="mb-2">' . Tools::trans('company')
            . '<select name="idempresa" class="form-select" ' . $attributes . '>'
            . self::generateOptions($companyList, 'idempresa', 'nombre', $model->idempresa)
            . '</select>'
            . '</div>'
            . '</div>';
    }

    protected static function operationField(Asiento $model): string
    {
        $attributes = $model->editable ? 'name="operacion"' : 'disabled';
        return '<div class="col-sm-6 col-md-4 col-lg-2">'
            . '<div class="mb-2">' . Tools::trans('operation')
            . '<select ' . $attributes . ' class="form-select">'
            . '<option value="">' . Tools::trans('optional') . '</option>'
            . '<option value="">------</option>'
            . '<option value="A" ' . ($model->operacion === 'A' ? 'selected' : '') . '>' . Tools::trans('opening-operation') . '</option>'
            . '<option value="C" ' . ($model->operacion === 'C' ? 'selected' : '') . '>' . Tools::trans('closing-operation') . '</option>'
            . '<option value="R" ' . ($model->operacion === 'R' ? 'selected' : '') . '>' . Tools::trans('regularization-operation') . '</option>'
            . '</select>'
            . '</div>'
            . '</div>';
    }
}