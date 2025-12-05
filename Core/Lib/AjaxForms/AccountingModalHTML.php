<?php
/**
 * ERPIA - Modal HTML para Contabilidad
 * Este archivo es parte de ERPIA, un sistema ERP de código abierto.
 * 
 * Copyright (C) 2025 ERPIA
 *
 * Este programa es software libre: puede redistribuirlo y/o modificarlo
 * bajo los términos de la Licencia Pública General GNU como publicada por
 * la Free Software Foundation, ya sea la versión 3 de la Licencia, o
 * (a su elección) cualquier versión posterior.
 *
 * Este programa se distribuye con la esperanza de que sea útil,
 * pero SIN NINGUNA GARANTÍA; sin siquiera la garantía implícita de
 * COMERCIALIZACIÓN o IDONEIDAD PARA UN PROPÓSITO PARTICULAR. Consulte la
 * Licencia Pública General GNU para obtener más detalles.
 *
 * Debería haber recibido una copia de la Licencia Pública General GNU
 * junto con este programa. Si no es así, consulte <http://www.gnu.org/licenses/>.
 */

namespace ERPIA\Core\Lib\AjaxForms;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Asiento;
use ERPIA\Dinamic\Model\Subcuenta;

/**
 * Clase para generar modales de contabilidad en formularios Ajax.
 * 
 * @author ERPIA
 * @version 1.0
 */
class AccountingModalHTML
{
    /** @var string */
    protected static $order;

    /** @var string */
    protected static $searchQuery;

    /**
     * Aplica los filtros del formulario al modal.
     *
     * @param Asiento $model
     * @param array $formData
     */
    public static function apply(Asiento &$model, array $formData): void
    {
        self::$order = $formData['fp_orden'] ?? 'ref_asc';
        $query = $formData['fp_query'] ?? '';
        self::$searchQuery = $query ? Tools::noHtml(mb_strtolower($query, 'UTF8')) : '';
    }

    /**
     * Renderiza el modal de subcuentas.
     *
     * @param Asiento $model
     * @return string
     */
    public static function render(Asiento $model): string
    {
        return self::modalSubaccount($model);
    }

    /**
     * Renderiza la lista de subcuentas para el modal.
     *
     * @param Asiento $model
     * @return string
     */
    public static function renderSubaccountList(Asiento $model): string
    {
        $rows = '';
        $subaccounts = self::fetchSubaccounts($model);

        if (empty($subaccounts)) {
            $rows = '<tr class="table-warning"><td colspan="2">' . Tools::trans('no-data') . '</td></tr>';
        } else {
            foreach ($subaccounts as $sub) {
                $rowClass = $sub->saldo > 0 ? 'table-success clickableRow' : 'clickableRow';
                $onclick = '$(\'#findSubaccountModal\').modal(\'hide\');'
                    . ' return newLineAction(\'' . $sub->codsubcuenta . '\');';

                $rows .= '<tr class="' . $rowClass . '" onclick="' . $onclick . '">'
                    . '<td><b>' . $sub->codsubcuenta . '</b> ' . $sub->descripcion . '</td>'
                    . '<td class="text-end">' . Tools::money($sub->saldo) . '</td>'
                    . '</tr>';
            }
        }

        return '<table class="table table-hover mb-0">'
            . '<thead>'
            . '<tr>'
            . '<th>' . Tools::trans('subaccount') . '</th>'
            . '<th class="text-end">' . Tools::trans('balance') . '</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>';
    }

    /**
     * Obtiene las subcuentas filtradas y ordenadas.
     *
     * @param Asiento $model
     * @return array
     */
    protected static function fetchSubaccounts(Asiento $model): array
    {
        if (empty($model->codejercicio)) {
            $model->setDate($model->fecha);
        }

        $conditions = [new DataBaseWhere('codejercicio', $model->codejercicio)];
        if (self::$searchQuery) {
            $conditions[] = new DataBaseWhere('descripcion|codsubcuenta', self::$searchQuery, 'XLIKE');
        }

        $ordering = self::getOrdering();

        return Subcuenta::all($conditions, $ordering);
    }

    /**
     * Devuelve el array de ordenación basado en self::$order.
     *
     * @return array
     */
    protected static function getOrdering(): array
    {
        switch (self::$order) {
            case 'desc_asc':
                return ['descripcion' => 'ASC'];
            case 'saldo_desc':
                return ['saldo' => 'DESC'];
            default:
                return ['codsubcuenta' => 'ASC'];
        }
    }

    /**
     * Genera el HTML del modal de subcuentas.
     *
     * @param Asiento $model
     * @return string
     */
    protected static function modalSubaccount(Asiento $model): string
    {
        $modalHeader = '
            <div class="modal" id="findSubaccountModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fa-solid fa-book fa-fw"></i> ' . Tools::trans('subaccounts') . '
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>';

        $modalBody = '
                        <div class="modal-body">
                            <div class="row g-2">
                                <div class="col-sm">
                                    <div class="input-group">
                                        <input type="text" name="fp_query" class="form-control" id="findSubaccountInput"
                                            placeholder="' . Tools::trans('search') . '"
                                            onkeyup="return findSubaccountSearch(\'find-subaccount\', \'0\', this);" />
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="button"
                                                onclick="return accEntryFormAction(\'find-subaccount\', \'0\');"
                                                data-loading-text="<span class=\'spinner-border spinner-border-sm\' role=\'status\' aria-hidden=\'true\'></span>">
                                                <i class="fa-solid fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                ' . self::orderControls() . '
                            </div>
                            <div class="table-responsive mt-3" id="findSubaccountList">
                                ' . self::renderSubaccountList($model) . '
                            </div>
                        </div>';

        $modalFooter = '
                    </div>
                </div>
            </div>';

        return $modalHeader . $modalBody . $modalFooter;
    }

    /**
     * Genera los controles de ordenación.
     *
     * @return string
     */
    protected static function orderControls(): string
    {
        return '
            <div class="col-sm">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fa-solid fa-sort-amount-down-alt"></i>
                    </span>
                    <select name="fp_orden" class="form-select"
                        onchange="return accEntryFormAction(\'find-subaccount\', \'0\');">
                        <option value="code_asc">' . Tools::trans('code') . '</option>
                        <option value="desc_asc">' . Tools::trans('description') . '</option>
                        <option value="saldo_desc">' . Tools::trans('balance') . '</option>
                    </select>
                </div>
            </div>';
    }
}