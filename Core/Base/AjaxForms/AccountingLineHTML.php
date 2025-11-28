<?php

namespace FacturaScripts\Core\Base\AjaxForms;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Impuestos;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Translator;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Dinamic\Model\Subcuenta;

/**
 * Description of AccountingLineHTML
 * @deprecated replaced by Core/Lib/AjaxForms/AccountingLineHTML
 */
class AccountingLineHTML
{
    /** @var array */
    protected static $deletedLines = [];

    /** @var int */
    protected static $num = 0;

    /**
     * Aplica los datos del formulario a las líneas y al modelo.
     * @param Asiento $model
     * @param Partida[] $lines
     * @param array $formData
     */
    public static function apply(Asiento &$model, array &$lines, array $formData)
    {
        $rmLineId = ($formData['action'] ?? '') === 'rm-line' ? ($formData['selectedLine'] ?? 0) : 0;

        foreach ($lines as $idx => $line) {
            $lineId = (string)$line->idpartida;

            // Eliminar si se marcó para borrar o si no se envió su subcuenta en el formulario
            if ($line->idpartida === (int)$rmLineId || !isset($formData['codsubcuenta_' . $lineId])) {
                self::$deletedLines[] = $line->idpartida;
                unset($lines[$idx]);
                continue;
            }

            // Actualizar la línea con los datos del formulario
            static::applyToLine($formData, $lines[$idx], $lineId);
        }

        // Líneas nuevas (n1..n999)
        for ($n = 1; $n < 1000; $n++) {
            $newId = 'n' . $n;
            if (isset($formData['codsubcuenta_' . $newId]) && $rmLineId !== $newId) {
                $newLine = $model->getNewLine();
                static::applyToLine($formData, $newLine, $newId);
                $lines[] = $newLine;
            }
        }

        // Recalcular debe/haber/importe del asiento
        static::calculateUnbalance($model, $lines);

        // Agregar nueva línea desde el campo de nueva subcuenta
        if (($formData['action'] ?? '') === 'new-line' && !empty($formData['new_subaccount'])) {
            $subcuenta = static::getSubcuenta($formData['new_subaccount'], $model);
            if (!$subcuenta->exists()) {
                Tools::log()->error('subaccount-not-found', ['%subAccountCode%' => $formData['new_subaccount']]);
                return;
            }

            $newLine = $model->getNewLine();
            $newLine->setAccount($subcuenta);
            $newLine->debe = ($model->debe < $model->haber) ? round($model->haber - $model->debe, FS_NF0) : 0.00;
            $newLine->haber = ($model->debe > $model->haber) ? round($model->debe - $model->haber, FS_NF0) : 0.00;
            $lines[] = $newLine;

            static::calculateUnbalance($model, $lines);
        }
    }

    /**
     * Devuelve las líneas eliminadas en apply().
     * @return array
     */
    public static function getDeletedLines(): array
    {
        return self::$deletedLines;
    }

    /**
     * Renderiza todas las líneas del asiento.
     * @param Partida[] $lines
     * @param Asiento $model
     *
     * @return string
     */
    public static function render(array $lines, Asiento $model): string
    {
        $html = '';
        foreach ($lines as $line) {
            $html .= static::renderLine($line, $model);
        }

        if ($html === '') {
            return '<div class="alert alert-warning border-top mb-0">'
                . Tools::lang()->trans('new-acc-entry-line-p')
                . '</div>';
        }

        return $html;
    }

    /**
     * Renderiza una línea del asiento.
     *
     * @param Partida $line
     * @param Asiento $model
     *
     * @return string
     */
    public static function renderLine(Partida $line, Asiento $model): string
    {
        static::$num++;
        $i18n = new Translator();

        $idlinea = $line->idpartida ?? ('n' . static::$num);
        $rowCss = (static::$num % 2 === 0) ? 'bg-white border-top' : 'bg-light border-top';

        $topRow = implode('', [
            static::subcuenta($i18n, $line, $model),
            static::debe($i18n, $line, $model),
            static::haber($i18n, $line, $model),
            static::renderExpandButton($i18n, (string)$idlinea, $model),
        ]);

        $modal = static::renderLineModal($i18n, $line, (string)$idlinea, $model);

        return '<div class="' . $rowCss . ' line pl-2 pr-2">'
            . '<div class="form-row align-items-end">' . $topRow . '</div>'
            . $modal
            . '</div>';
    }

    /**
     * Modal de detalle de línea (IVA, recargo, base imponible, CIF/NIF, documento).
     */
    private static function renderLineModal(Translator $i18n, Partida $line, string $idlinea, Asiento $model): string
    {
        $header = '<div class="modal-header">'
            . '<h5 class="modal-title">' . Tools::noHtml($line->codsubcuenta) . '</h5>'
            . '<button type="button" class="close" data-dismiss="modal" aria-label="Close">'
            . '<span aria-hidden="true">&times;</span>'
            . '</button>'
            . '</div>';

        $row1 = '<div class="form-row">' . static::iva($i18n, $line, $model) . static::recargo($i18n, $line, $model) . '</div>';
        $row2 = '<div class="form-row">' . static::baseimponible($i18n, $line, $model) . static::cifnif($i18n, $line, $model) . '</div>';
        $row3 = '<div class="form-row">' . static::documento($i18n, $line, $model) . '</div>';

        $body = '<div class="modal-body">' . $row1 . $row2 . $row3 . '</div>';

        $footer = '<div class="modal-footer">'
            . '<button type="button" class="btn btn-secondary" data-dismiss="modal">' . $i18n->trans('close') . '</button>'
            . '<button type="button" class="btn btn-primary" data-dismiss="modal">' . $i18n->trans('accept') . '</button>'
            . '</div>';

        return '<div class="modal fade" id="lineModal-' . $idlinea . '" tabindex="-1" aria-labelledby="lineModal-' . $idlinea . 'Label" aria-hidden="true">'
            . '<div class="modal-dialog modal-dialog-centered">'
            . '<div class="modal-content">' . $header . $body . $footer . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Copia los datos del formulario a una línea concreta.
     *
     * @param array $formData
     * @param Partida $line
     * @param string $id
     */
    protected static function applyToLine(array &$formData, Partida &$line, string $id)
    {
        $line->baseimponible = (float)($formData['baseimponible_' . $id] ?? '0');
        $line->cifnif = $formData['cifnif_' . $id] ?? '';
        $line->concepto = $formData['concepto_' . $id] ?? '';
        $line->codcontrapartida = $formData['codcontrapartida_' . $id] ?? '';
        $line->codsubcuenta = $formData['codsubcuenta_' . $id] ?? '';
        $line->debe = (float)($formData['debe_' . $id] ?? '0');
        $line->documento = $formData['documento_' . $id] ?? '';
        $line->haber = (float)($formData['haber_' . $id] ?? '0');

        // IVA puede ser vacío (null) o numérico (float)
        $line->iva = ($formData['iva_' . $id] ?? '') === '' ? null : (float)$formData['iva_' . $id];

        $line->orden = (int)($formData['orden_' . $id] ?? '0');
        $line->recargo = (float)($formData['recargo_' . $id] ?? '0');
    }

    /**
     * Base imponible para aplicar impuesto.
     */
    protected static function baseimponible(Translator $i18n, Partida $line, Asiento $model): string
    {
        $id = $line->idpartida ?? ('n' . static::$num);
        $attrs = $model->editable ? 'name="baseimponible_' . $id . '"' : 'disabled';

        return '<div class="col pb-2 small">' . $i18n->trans('tax-base')
            . '<input type="number" ' . $attrs . ' value="' . floatval($line->baseimponible) . '" class="form-control" step="any" autocomplete="off">'
            . '</div>';
    }

    /**
     * Recalcula debe/haber/importe del Asiento.
     *
     * @param Asiento $model
     * @param Partida[] $lines
     */
    public static function calculateUnbalance(Asiento &$model, array $lines)
    {
        $model->debe = 0.0;
        $model->haber = 0.0;

        foreach ($lines as $line) {
            $model->debe += $line->debe;
            $model->haber += $line->haber;
        }

        $model->importe = max([$model->debe, $model->haber]);
    }

    /**
     * CIF/NIF.
     */
    protected static function cifnif(Translator $i18n, Partida $line, Asiento $model): string
    {
        $id = $line->idpartida ?? ('n' . static::$num);
        $attrs = $model->editable ? 'name="cifnif_' . $id . '"' : 'disabled';

        return '<div class="col pb-2 small">' . $i18n->trans('cifnif')
            . '<input type="text" ' . $attrs . ' value="' . Tools::noHtml($line->cifnif) . '" class="form-control" maxlength="30" autocomplete="off"/>'
            . '</div>';
    }

    /**
     * Concepto.
     */
    protected static function concepto(Translator $i18n, Partida $line, Asiento $model): string
    {
        $id = $line->idpartida ?? ('n' . static::$num);
        $attrs = $model->editable
            ? 'name="concepto_' . $id . '" onchange="return recalculateLine(\'recalculate\', \'' . $id . '\');"'
            : 'disabled';

        return '<div class="col pb-2 small">' . $i18n->trans('concept')
            . '<input type="text" ' . $attrs . ' class="form-control" value="' . Tools::noHtml($line->concepto) . '">'
            . '</div>';
    }

    /**
     * Contrapartida.
     */
    protected static function contrapartida(Translator $i18n, Partida $line, Asiento $model): string
    {
        $id = $line->idpartida ?? ('n' . static::$num);
        $attrs = $model->editable
            ? 'name="codcontrapartida_' . $id . '" onchange="return recalculateLine(\'recalculate\', \'' . $id . '\');"'
            : 'disabled';

        return '<div class="col pb-2 small">' . $i18n->trans('counterpart')
            . '<input type="text" ' . $attrs . ' value="' . Tools::noHtml($line->codcontrapartida) . '" class="form-control" maxlength="15" autocomplete="off" placeholder="' . $i18n->trans('optional') . '"/>'
            . '</div>';
    }

    /**
     * Debe.
     */
    protected static function debe(Translator $i18n, Partida $line, Asiento $model): string
    {
        $id = $line->idpartida ?? ('n' . static::$num);
        $attrs = $model->editable
            ? 'name="debe_' . $id . '" step="1" onchange="return recalculateLine(\'recalculate\', \'' . $id . '\');"'
            : 'disabled';

        return '<div class="col pb-2 small">' . $i18n->trans('debit')
            . '<input type="number" class="form-control line-debit" ' . $attrs . ' value="' . floatval($line->debe) . '"/>'
            . '</div>';
    }

    /**
     * Documento.
     */
    protected static function documento(Translator $i18n, Partida $line, Asiento $model): string
    {
        $id = $line->idpartida ?? ('n' . static::$num);
        $attrs = $model->editable ? 'name="documento_' . $id . '"' : 'disabled';

        return '<div class="col pb-2 small">' . $i18n->trans('document')
            . '<input type="text" ' . $attrs . ' value="' . Tools::noHtml($line->documento) . '" class="form-control" maxlength="30" autocomplete="off"/>'
            . '</div>';
    }

    /**
     * Obtiene la subcuenta por código + ejercicio.
     *
     * @param string $code
     * @param Asiento $model
     *
     * @return Subcuenta
     */
    protected static function getSubcuenta(string $code, Asiento $model): Subcuenta
    {
        $subcuenta = new Subcuenta();

        if ($code === '' || $model->codejercicio === '') {
            return $subcuenta;
        }

        $where = [
            new DataBaseWhere('codejercicio', $model->codejercicio),
            new DataBaseWhere('codsubcuenta', $subcuenta->transformCodsubcuenta($code, $model->codejercicio)),
        ];

        $subcuenta->loadFromCode('', $where);
        return $subcuenta;
    }

    /**
     * Haber.
     */
    protected static function haber(Translator $i18n, Partida $line, Asiento $model): string
    {
        $id = $line->idpartida ?? ('n' . static::$num);
        $attrs = $model->editable
            ? 'name="haber_' . $id . '" step="1" onchange="return recalculateLine(\'recalculate\', \'' . $id . '\');"'
            : 'disabled';

        return '<div class="col pb-2 small">' . $i18n->trans('credit')
            . '<input type="number" class="form-control" ' . $attrs . ' value="' . round($line->haber, FS_NF0) . '"/>'
            . '</div>';
    }

    /**
     * IVA (select con opciones y preselección).
     */
    protected static function iva(Translator $i18n, Partida $line, Asiento $model): string
    {
        // Determinar el impuesto preseleccionado
        $codimpuesto = null;
        foreach (Impuestos::all() as $imp) {
            if ($imp->codsubcuentarep || $imp->codsubcuentasop) {
                if (in_array($line->codsubcuenta, [$imp->codsubcuentarep, $imp->codsubcuentasop], true)) {
                    $codimpuesto = $imp->codimpuesto;
                    break;
                }
            }
            if ($imp->iva === $line->iva) {
                $codimpuesto = $imp->codimpuesto;
            }
        }

        $options = ['<option value="">------</option>'];
        foreach (Impuestos::all() as $imp) {
            $sel = ($imp->codimpuesto === $codimpuesto) ? ' selected' : '';
            $options[] = '<option value="' . $imp->iva . '"' . $sel . '>' . Tools::noHtml($imp->descripcion) . '</option>';
        }

        $id = $line->idpartida ?? ('n' . static::$num);
        $attrs = $model->editable ? 'name="iva_' . $id . '"' : 'disabled';

        return '<div class="col pb-2 small"><a href="ListImpuesto">' . $i18n->trans('vat') . '</a>'
            . '<select ' . $attrs . ' class="form-control">' . implode('', $options) . '</select>'
            . '</div>';
    }

    /**
     * Recargo de equivalencia.
     */
    protected static function recargo(Translator $i18n, Partida $line, Asiento $model): string
    {
        $id = $line->idpartida ?? ('n' . static::$num);
        $attrs = $model->editable ? 'name="recargo_' . $id . '"' : 'disabled';

        return '<div class="col pb-2 small">' . $i18n->trans('surcharge')
            . '<input type="number" ' . $attrs . ' value="' . floatval($line->recargo) . '" class="form-control" step="any" autocomplete="off">'
            . '</div>';
    }

    /**
     * Botones de expandir/eliminar.
     */
    protected static function renderExpandButton(Translator $i18n, string $idlinea, Asiento $model): string
    {
        $btnMore = '<button type="button" data-toggle="modal" data-target="#lineModal-' . $idlinea . '" class="btn btn-outline-secondary mb-1" title="'
            . $i18n->trans('more') . '"><i class="fas fa-ellipsis-h"></i></button>';

        if ($model->editable) {
            $btnDel = '<button class="btn btn-outline-danger btn-spin-action ml-2 mb-1" type="button" title="' . $i18n->trans('delete') . '"'
                . ' onclick="return accEntryFormAction(\'rm-line\', \'' . $idlinea . '\');">'
                . '<i class="fas fa-trash-alt"></i></button>';

            return '<div class="col-sm-auto pb-1">' . $btnMore . $btnDel . '</div>';
        }

        return '<div class="col-sm-auto pb-1">' . $btnMore . '</div>';
    }

    /**
     * Saldo de una subcuenta (solo lectura).
     */
    protected static function saldo(Translator $i18n, Subcuenta $subcuenta): string
    {
        return '<div class="col pb-2 small">' . $i18n->trans('balance')
            . '<input type="text" class="form-control" value="' . Tools::number($subcuenta->saldo) . '" tabindex="-1" readonly>'
            . '</div>';
    }

    /**
     * Campo de subcuenta con enlace y descripción.
     */
    protected static function subcuenta(Translator $i18n, Partida $line, Asiento $model): string
    {
        $id = $line->idpartida ?? ('n' . static::$num);
        $subcuenta = static::getSubcuenta($line->codsubcuenta, $model);
        $desc = Tools::noHtml($subcuenta->descripcion);

        if (!$model->editable) {
            return '<div class="col pb-2 small">' . $desc
                . '<div class="input-group">'
                . '<input type="text" value="' . Tools::noHtml($line->codsubcuenta) . '" class="form-control" tabindex="-1" readonly>'
                . '<div class="input-group-append"><a href="' . $subcuenta->url() . '" target="_blank" class="btn btn-outline-primary">'
                . '<i class="far fa-eye"></i></a></div>'
                . '</div>'
                . '</div>'
                . static::contrapartida($i18n, $line, $model)
                . static::concepto($i18n, $line, $model);
        }

        $hiddenOrder = '<input type="hidden" name="orden_' . $id . '" value="' . (int)$line->orden . '"/>';

        return '<div class="col pb-2 small">'
            . $hiddenOrder . $desc
            . '<div class="input-group">'
            . '<input type="text" name="codsubcuenta_' . $id . '" value="' . Tools::noHtml($line->codsubcuenta) . '" class="form-control" tabindex="-1" readonly>'
            . '<div class="input-group-append"><a href="' . $subcuenta->url() . '" target="_blank" class="btn btn-outline-primary">'
            . '<i class="far fa-eye"></i></a></div>'
            . '</div>'
            . '</div>'
            . static::contrapartida($i18n, $line, $model)
            . static::concepto($i18n, $line, $model);
    }
}