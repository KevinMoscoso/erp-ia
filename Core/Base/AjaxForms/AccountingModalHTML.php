<?php

namespace FacturaScripts\Core\Base\AjaxForms;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Translator;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Subcuenta;

/**
 * Description of AccountingModalHTML
 *
 * @deprecated replaced by Core/Lib/AjaxForms/AccountingModalHTML
 */
class AccountingModalHTML
{
    /** @var string */
    protected static $orden;

    /** @var string */
    protected static $query;

    /**
     * Carga los parámetros de búsqueda y orden desde el formulario.
     */
    public static function apply(Asiento &$model, array $formData)
    {
        self::$orden = $formData['fp_orden'] ?? 'ref_asc';
        self::$query = isset($formData['fp_query']) ? Tools::noHtml(mb_strtolower($formData['fp_query'], 'UTF8')) : '';
    }

    /**
     * Renderiza el modal completo.
     */
    public static function render(Asiento $model): string
    {
        $i18n = new Translator();
        return static::modalSubaccount($i18n, $model);
    }

    /**
     * Renderiza la tabla de subcuentas filtradas y ordenadas.
     */
    public static function renderSubaccountList(Asiento $model): string
    {
        $i18n = new Translator();
        $rows = '';

        foreach (static::getSubaccounts($model) as $subaccount) {
            $rowClass = ($subaccount->saldo > 0) ? 'table-success clickableRow' : 'clickableRow';
            $onClick = '$(\'#findSubaccountModal\').modal(\'hide\'); return newLineAction(\'' . $subaccount->codsubcuenta . '\');';

            $rows .= '<tr class="' . $rowClass . '" onclick="' . $onClick . '">'
                . '<td><b>' . $subaccount->codsubcuenta . '</b> ' . Tools::noHtml($subaccount->descripcion) . '</td>'
                . '<td class="text-right">' . Tools::money($subaccount->saldo) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr class="table-warning"><td colspan="3">' . $i18n->trans('no-data') . '</td></tr>';
        }

        return '<table class="table table-hover mb-0">'
            . '<thead>'
            . '<tr>'
            . '<th>' . $i18n->trans('subaccount') . '</th>'
            . '<th class="text-right">' . $i18n->trans('balance') . '</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>';
    }

    /**
     * Devuelve el listado de subcuentas según búsqueda y orden.
     *
     * @return Subcuenta[]
     */
    protected static function getSubaccounts(Asiento $model): array
    {
        $subcuenta = new Subcuenta();

        // Asegura el ejercicio si no está establecido
        if (empty($model->codejercicio)) {
            $model->setDate($model->fecha);
        }

        $where = [new DataBaseWhere('codejercicio', $model->codejercicio)];
        if (!empty(self::$query)) {
            $where[] = new DataBaseWhere('descripcion|codsubcuenta', self::$query, 'XLIKE');
        }

        // Orden configurable
        switch (self::$orden) {
            case 'desc_asc':
                $order = ['descripcion' => 'ASC'];
                break;
            case 'saldo_desc':
                $order = ['saldo' => 'DESC'];
                break;
            default:
                $order = ['codsubcuenta' => 'ASC'];
                break;
        }

        return $subcuenta->all($where, $order);
    }

    /**
     * Estructura del modal de búsqueda/selección de subcuentas.
     */
    protected static function modalSubaccount(Translator $i18n, Asiento $model): string
    {
        $header = '<div class="modal-header">'
            . '<h5 class="modal-title"><i class="fas fa-book fa-fw"></i> ' . $i18n->trans('subaccounts') . '</h5>'
            . '<button type="button" class="close" data-dismiss="modal" aria-label="Close">'
            . '<span aria-hidden="true">&times;</span>'
            . '</button>'
            . '</div>';

        $searchBlock = '<div class="col-sm">'
            . '<div class="input-group">'
            . '<input type="text" name="fp_query" class="form-control" id="findSubaccountInput" placeholder="' . $i18n->trans('search') . '"'
            . ' onkeyup="return findSubaccountSearch(\'find-subaccount\', \'0\', this);"/>'
            . '<div class="input-group-apend">'
            . '<button class="btn btn-primary" type="button" onclick="return accEntryFormAction(\'find-subaccount\', \'0\');"'
            . ' data-loading-text="<span class=\'spinner-border spinner-border-sm\' role=\'status\' aria-hidden=\'true\'></span>">'
            . '<i class="fas fa-search"></i></button>'
            . '</div>'
            . '</div>'
            . '</div>';

        $controls = '<div class="form-row">' . $searchBlock . static::orden($i18n) . '</div>';

        $body = '<div class="modal-body">' . $controls . '</div>'
            . '<div class="table-responsive" id="findSubaccountList">' . static::renderSubaccountList($model) . '</div>';

        return '<div class="modal" id="findSubaccountModal" tabindex="-1" aria-hidden="true">'
            . '<div class="modal-dialog modal-xl">'
            . '<div class="modal-content">' . $header . $body . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Selector de orden para la lista del modal.
     */
    protected static function orden(Translator $i18n): string
    {
        return '<div class="col-sm">'
            . '<div class="input-group">'
            . '<div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-sort-amount-down-alt"></i></span></div>'
            . '<select name="fp_orden" class="form-control" onchange="return accEntryFormAction(\'find-subaccount\', \'0\');">'
            . '<option value="code_asc">' . $i18n->trans('code') . '</option>'
            . '<option value="desc_asc">' . $i18n->trans('description') . '</option>'
            . '<option value="saldo_desc">' . $i18n->trans('balance') . '</option>'
            . '</select>'
            . '</div>'
            . '</div>';
    }
}