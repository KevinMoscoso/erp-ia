<?php
/**
 * ERPIA - Trait para Líneas Comunes de Documentos HTML
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
use ERPIA\Core\DataSrc\Impuestos;
use ERPIA\Core\DataSrc\Retenciones;
use ERPIA\Core\DataSrc\Series;
use ERPIA\Core\Lib\ProductType;
use ERPIA\Core\Lib\RegimenIVA;
use ERPIA\Core\Model\Base\BusinessDocument;
use ERPIA\Core\Model\Base\BusinessDocumentLine;
use ERPIA\Core\Model\Base\TransformerDocument;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Stock;
use ERPIA\Dinamic\Model\Variante;

trait CommonLineHTML
{
    /** @var string */
    protected static $viewMode;

    /** @var int */
    protected static $counter = 0;

    /** @var int */
    protected static $lineCount = 0;

    /** @var string */
    protected static $taxRegime;

    /** @var array */
    private static $productVariants = [];

    /** @var array */
    private static $productStocks = [];

    /**
     * Muestra la cantidad restante de una línea.
     *
     * @param BusinessDocumentLine $line
     * @param TransformerDocument $model
     * @return string
     */
    private static function remainingQuantity(BusinessDocumentLine $line, TransformerDocument $model): string
    {
        if ($line->servido <= 0 || false === $model->editable) {
            return '';
        }

        $remaining = $line->cantidad - $line->servido;
        return '<div class="input-group-prepend" title="' . Tools::trans('quantity-remaining') . '">'
            . '<a href="DocumentStitcher?model=' . $model->modelClassName() . '&codes=' . $model->id()
            . '" class="btn btn-outline-secondary" type="button">' . $remaining . '</a>'
            . '</div>';
    }

    /**
     * Genera el selector de impuestos.
     *
     * @param string $lineId
     * @param BusinessDocumentLine $line
     * @param TransformerDocument $model
     * @param string $jsFunction
     * @return string
     */
    private static function taxSelector(string $lineId, BusinessDocumentLine $line, TransformerDocument $model, string $jsFunction): string
    {
        if (!isset(self::$taxRegime)) {
            self::$taxRegime = $model->getSubject()->regimeniva;
        }

        $options = ['<option value="">------</option>'];
        $allTaxes = Impuestos::all();

        foreach ($allTaxes as $tax) {
            if (!$tax->activo && $line->codimpuesto != $tax->codimpuesto) {
                continue;
            }

            $selected = $line->codimpuesto == $tax->codimpuesto ? ' selected' : '';
            $options[] = '<option value="' . $tax->codimpuesto . '"' . $selected . '>' . $tax->descripcion . '</option>';
        }

        $editable = $model->editable 
            && self::$taxRegime != RegimenIVA::TAX_SYSTEM_EXEMPT
            && false == Series::get($model->codserie)->siniva 
            && false == $line->suplido;

        $attributes = $editable 
            ? 'name="codimpuesto_' . $lineId . '" onchange="return ' . $jsFunction . '(\'recalculate-line\', \'0\');"'
            : 'disabled=""';

        return '<div class="col-sm col-lg-1 order-6">'
            . '<div class="d-lg-none mt-3 small">' . Tools::trans('tax') . '</div>'
            . '<select ' . $attributes . ' class="form-select form-select-sm border-0">' . implode('', $options) . '</select>'
            . '<input type="hidden" name="iva_' . $lineId . '" value="' . $line->iva . '"/>'
            . '</div>';
    }

    /**
     * Genera el campo de descripción.
     *
     * @param string $lineId
     * @param BusinessDocumentLine $line
     * @param TransformerDocument $model
     * @return string
     */
    private static function descriptionField(string $lineId, BusinessDocumentLine $line, TransformerDocument $model): string
    {
        $attributes = $model->editable ? 'name="descripcion_' . $lineId . '"' : 'disabled=""';

        $rows = 0;
        $descriptionLines = explode("\n", $line->descripcion);
        foreach ($descriptionLines as $descLine) {
            $rows += mb_strlen($descLine) < 90 ? 1 : ceil(mb_strlen($descLine) / 90);
        }

        $colMd = empty($line->referencia) ? 12 : 8;
        $colSm = empty($line->referencia) ? 10 : 8;

        return '<div class="col-sm-' . $colSm . ' col-md-' . $colMd . ' col-lg order-2">'
            . '<div class="d-lg-none mt-3 small">' . Tools::trans('description') . '</div>'
            . '<textarea ' . $attributes . ' class="form-control form-control-sm border-0 doc-line-desc" rows="' . $rows . '">'
            . $line->descripcion . '</textarea></div>';
    }

    /**
     * Genera el campo de porcentaje de descuento.
     *
     * @param string $lineId
     * @param BusinessDocumentLine $line
     * @param TransformerDocument $model
     * @param string $jsFunction
     * @return string
     */
    private static function discountPercentage(string $lineId, BusinessDocumentLine $line, TransformerDocument $model, string $jsFunction): string
    {
        $attributes = $model->editable
            ? 'name="dtopor_' . $lineId . '" min="0" max="100" step="1" onkeyup="return ' . $jsFunction . '(\'recalculate-line\', \'0\', event);"'
            : 'disabled=""';

        return '<div class="col-sm col-lg-1 order-5">'
            . '<div class="d-lg-none mt-3 small">' . Tools::trans('percentage-discount') . '</div>'
            . '<input type="number" ' . $attributes . ' value="' . $line->dtopor . '" class="form-control form-control-sm text-lg-center border-0"/>'
            . '</div>';
    }

    /**
     * Genera el campo de segundo porcentaje de descuento.
     *
     * @param string $lineId
     * @param BusinessDocumentLine $line
     * @param TransformerDocument $model
     * @param string $field
     * @param string $jsFunction
     * @return string
     */
    private static function secondDiscount(string $lineId, BusinessDocumentLine $line, TransformerDocument $model, string $field, string $jsFunction): string
    {
        $attributes = $model->editable
            ? 'name="' . $field . '_' . $lineId . '" min="0" max="100" step="1" onkeyup="return ' . $jsFunction . '(\'recalculate-line\', \'0\', event);"'
            : 'disabled=""';

        return '<div class="col-6">'
            . '<div class="mb-2">' . Tools::trans('percentage-discount') . ' 2'
            . '<input type="number" ' . $attributes . ' value="' . $line->{$field} . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el selector de excepción de IVA.
     *
     * @param string $lineId
     * @param BusinessDocumentLine $line
     * @param TransformerDocument $model
     * @param string $field
     * @param string $jsFunction
     * @return string
     */
    private static function vatException(string $lineId, BusinessDocumentLine $line, TransformerDocument $model, string $field, string $jsFunction): string
    {
        $attributes = $model->editable
            ? 'name="excepcioniva_' . $lineId . '" onchange="return ' . $jsFunction . '(\'recalculate-line\', \'0\');"'
            : 'disabled=""';

        $options = '<option value="" selected>------</option>';
        $product = $line->getProducto();
        $vatException = empty($line->idlinea) && empty($line->{$field}) ? $product->{$field} : $line->{$field};

        foreach (RegimenIVA::allExceptions() as $key => $value) {
            $selected = $vatException === $key ? 'selected' : '';
            $options .= '<option value="' . $key . '" ' . $selected . '>' . Tools::trans($value) . '</option>';
        }

        return '<div class="col-6">'
            . '<div class="mb-2">' . Tools::trans('vat-exception')
            . '<select ' . $attributes . ' class="form-select">' . $options . '</select>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera un selector booleano genérico.
     *
     * @param string $lineId
     * @param BusinessDocumentLine $line
     * @param TransformerDocument $model
     * @param string $field
     * @param string $label
     * @return string
     */
    private static function genericBoolean(string $lineId, BusinessDocumentLine $line, TransformerDocument $model, string $field, string $label): string
    {
        $attributes = $model->editable ? 'name="' . $field . '_' . $lineId . '"' : 'disabled=""';
        
        if ($line->{$field}) {
            $options = [
                '<option value="0">' . Tools::trans('no') . '</option>',
                '<option value="1" selected>' . Tools::trans('yes') . '</option>'
            ];
        } else {
            $options = [
                '<option value="0" selected>' . Tools::trans('no') . '</option>',
                '<option value="1">' . Tools::trans('yes') . '</option>'
            ];
        }

        return '<div class="col-6">'
            . '<div class="mb-2">' . Tools::trans($label)
            . '<select ' . $attributes . ' class="form-select">' . implode('', $options) . '</select>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el selector de retención (IRPF).
     *
     * @param string $lineId
     * @param BusinessDocumentLine $line
     * @param TransformerDocument $model
     * @param string $jsFunction
     * @return string
     */
    private static function retentionSelector(string $lineId, BusinessDocumentLine $line, TransformerDocument $model, string $jsFunction): string
    {
        $options = ['<option value="">------</option>'];
        $allRetentions = Retenciones::all();

        foreach ($allRetentions as $ret) {
            if (!$ret->activa && $line->irpf != $ret->porcentaje) {
                continue;
            }

            $selected = $line->irpf === $ret->porcentaje ? 'selected' : '';
            $options[] = '<option value="' . $ret->porcentaje . '" ' . $selected . '>' . $ret->descripcion . '</option>';
        }

        $attributes = $model->editable && false === $line->suplido
            ? 'name="irpf_' . $lineId . '" onchange="return ' . $jsFunction . '(\'recalculate-line\', \'0\', event);"'
            : 'disabled=""';

        return '<div class="col-6">'
            . '<div class="mb-2"><a href="ListImpuesto?activetab=ListRetencion">' . Tools::trans('retention') . '</a>'
            . '<select ' . $attributes . ' class="form-select">' . implode('', $options) . '</select>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el campo de total de línea.
     *
     * @param string $lineId
     * @param BusinessDocumentLine $line
     * @param TransformerDocument $model
     * @param string $jsSubtotal
     * @param string $jsNet
     * @return string
     */
    private static function lineTotalField(string $lineId, BusinessDocumentLine $line, TransformerDocument $model, string $jsSubtotal, string $jsNet): string
    {
        $subtotalClass = self::$viewMode === 'subtotal' ? '' : 'd-none';
        $netClass = self::$viewMode === 'subtotal' ? 'd-none' : '';

        $subtotalClick = $model->editable ? ' onclick="' . $jsSubtotal . '(\'' . $lineId . '\')"' : '';
        $netClick = $model->editable ? ' onclick="' . $jsNet . '(\'' . $lineId . '\')"' : '';

        $decimalPlaces = Tools::settings('default', 'decimals', 2);
        $subtotalValue = self::calculateSubtotal($line, $model);

        return '<div class="col col-lg-1 order-7 columSubtotal ' . $subtotalClass . '">'
            . '<div class="d-lg-none mt-2 small">' . Tools::trans('subtotal') . '</div>'
            . '<input type="number" name="linetotal_' . $lineId . '"  value="' . number_format($subtotalValue, $decimalPlaces, '.', '')
            . '" class="form-control form-control-sm text-lg-end border-0"' . $subtotalClick . ' readonly/></div>'
            . '<div class="col col-lg-1 order-7 columNeto ' . $netClass . '">'
            . '<div class="d-lg-none mt-2 small">' . Tools::trans('net') . '</div>'
            . '<input type="number" name="lineneto_' . $lineId . '"  value="' . number_format($line->pvptotal, $decimalPlaces, '.', '')
            . '" class="form-control form-control-sm text-lg-end border-0"' . $netClick . ' readonly/></div>';
    }

    /**
     * Carga productos, variantes y stocks.
     *
     * @param array $lines
     * @param BusinessDocument $model
     */
    private static function loadProductData(array $lines, BusinessDocument $model): void
    {
        $references = [];
        foreach ($lines as $line) {
            if (!empty($line->referencia)) {
                $references[] = $line->referencia;
            }
        }

        if (empty($references)) {
            return;
        }

        // Cargar variantes
        $variantWhere = [new DataBaseWhere('referencia', $references, 'IN')];
        $variants = Variante::all($variantWhere, [], 0, 0);
        foreach ($variants as $variant) {
            self::$productVariants[$variant->referencia] = $variant;
        }

        // Cargar stocks
        $stockWhere = [
            new DataBaseWhere('codalmacen', $model->codalmacen),
            new DataBaseWhere('referencia', $references, 'IN'),
        ];
        $stocks = Stock::all($stockWhere, [], 0, 0);
        foreach ($stocks as $stock) {
            self::$productStocks[$stock->referencia] = $stock;
        }
    }

    /**
     * Genera el campo de recargo.
     *
     * @param string $lineId
     * @param BusinessDocumentLine $line
     * @param TransformerDocument $model
     * @param string $jsFunction
     * @return string
     */
    private static function surchargeField(string $lineId, BusinessDocumentLine $line, TransformerDocument $model, string $jsFunction): string
    {
        if (!isset(self::$taxRegime)) {
            self::$taxRegime = $model->getSubject()->regimeniva;
        }

        $editable = $model->editable
            && false === $line->suplido
            && false === Series::get($model->codserie)->siniva
            && (self::$taxRegime === RegimenIVA::TAX_SYSTEM_SURCHARGE || $model->getCompany()->regimeniva === RegimenIVA::TAX_SYSTEM_SURCHARGE);

        $attributes = $editable
            ? 'name="recargo_' . $lineId . '" min="0" max="100" step="1" onkeyup="return ' . $jsFunction . '(\'recalculate-line\', \'0\', event);"'
            : 'disabled=""';

        return '<div class="col-6">'
            . '<div class="mb-2"><a href="ListImpuesto">' . Tools::trans('percentage-surcharge') . '</a>'
            . '<input type="number" ' . $attributes . ' value="' . $line->recargo . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el campo de referencia.
     *
     * @param string $lineId
     * @param BusinessDocumentLine $line
     * @param TransformerDocument $model
     * @return string
     */
    private static function referenceField(string $lineId, BusinessDocumentLine $line, TransformerDocument $model): string
    {
        $sortable = $model->editable 
            ? '<input type="hidden" name="orden_' . $lineId . '" value="' . $line->orden . '"/>' 
            : '';
        
        $lineNumber = self::$lineCount > 10 ? self::$counter . '. ' : '';

        if (empty($line->referencia)) {
            return '<div class="col-sm-2 col-lg-1 order-1">' . $sortable . '<div class="small text-break">' . $lineNumber . '</div></div>';
        }

        $link = isset(self::$productVariants[$line->referencia])
            ? $lineNumber . '<a href="' . self::$productVariants[$line->referencia]->url() . '" target="_blank">' . $line->referencia . '</a>'
            : $line->referencia;

        return '<div class="col-sm-2 col-lg-1 order-1">'
            . '<div class="small text-break"><div class="d-lg-none mt-2 text-truncate">' . Tools::trans('reference') . '</div>'
            . $sortable . $link . '<input type="hidden" name="referencia_' . $lineId . '" value="' . $line->referencia . '"/>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el botón de expandir/eliminar.
     *
     * @param string $lineId
     * @param TransformerDocument $model
     * @param string $jsName
     * @return string
     */
    private static function expandButton(string $lineId, TransformerDocument $model, string $jsName): string
    {
        if ($model->editable) {
            return '<div class="col-auto order-9">'
                . '<button type="button" data-bs-toggle="modal" data-bs-target="#lineModal-' . $lineId . '" class="btn btn-sm btn-light me-2" title="'
                . Tools::trans('more') . '"><i class="fa-solid fa-ellipsis-h"></i></button>'
                . '<button class="btn btn-sm btn-danger btn-spin-action" type="button" title="' . Tools::trans('delete') . '"'
                . ' onclick="return ' . $jsName . '(\'rm-line\', \'' . $lineId . '\');">'
                . '<i class="fa-solid fa-trash-alt"></i></button>'
                . '</div>';
        }

        return '<div class="col-auto order-9"><button type="button" data-bs-toggle="modal" data-bs-target="#lineModal-'
            . $lineId . '" class="btn btn-sm btn-outline-secondary" title="'
            . Tools::trans('more') . '"><i class="fa-solid fa-ellipsis-h"></i></button></div>';
    }

    /**
     * Calcula el subtotal de una línea.
     *
     * @param BusinessDocumentLine $line
     * @param TransformerDocument $model
     * @return float
     */
    private static function calculateSubtotal(BusinessDocumentLine $line, TransformerDocument $model): float
    {
        if ($model->subjectColumn() === 'codcliente'
            && $model->getCompany()->regimeniva === RegimenIVA::TAX_SYSTEM_USED_GOODS
            && $line->getProducto()->tipo === ProductType::SECOND_HAND) {
            
            $profit = $line->pvpunitario - $line->coste;
            $taxAmount = $profit * ($line->iva + $line->recargo - $line->irpf) / 100;
            return ($line->coste + $profit + $taxAmount) * $line->cantidad;
        }

        return $line->pvptotal * (100 + $line->iva + $line->recargo - $line->irpf) / 100;
    }

    /**
     * Genera el campo de suplido.
     *
     * @param string $lineId
     * @param BusinessDocumentLine $line
     * @param TransformerDocument $model
     * @param string $jsFunction
     * @return string
     */
    private static function suppliedField(string $lineId, BusinessDocumentLine $line, TransformerDocument $model, string $jsFunction): string
    {
        $attributes = $model->editable
            ? 'name="suplido_' . $lineId . '" onchange="return ' . $jsFunction . '(\'recalculate-line\', \'0\', event);"'
            : 'disabled=""';

        if ($line->suplido) {
            $options = [
                '<option value="0">' . Tools::trans('no') . '</option>',
                '<option value="1" selected>' . Tools::trans('yes') . '</option>'
            ];
        } else {
            $options = [
                '<option value="0" selected>' . Tools::trans('no') . '</option>',
                '<option value="1">' . Tools::trans('yes') . '</option>'
            ];
        }

        return '<div class="col-6">'
            . '<div class="mb-2">' . Tools::trans('supplied')
            . '<select ' . $attributes . ' class="form-select">' . implode('', $options) . '</select>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el espacio para botones de acción en el encabezado.
     *
     * @param TransformerDocument $model
     * @return string
     */
    private static function actionButtonsSpace(TransformerDocument $model): string
    {
        $width = $model->editable ? 68 : 32;
        return '<div class="col-lg-auto order-8"><div style="min-width: ' . $width . 'px;"></div></div>';
    }

    /**
     * Genera el título de la columna de cantidad.
     *
     * @return string
     */
    private static function quantityTitle(): string
    {
        return '<div class="col-lg-1 text-end order-3">' . Tools::trans('quantity') . '</div>';
    }

    /**
     * Genera el título de la columna de impuesto.
     *
     * @return string
     */
    private static function taxTitle(): string
    {
        return '<div class="col-lg-1 order-6"><a href="ListImpuesto">' . Tools::trans('tax') . '</a></div>';
    }

    /**
     * Genera el título de la columna de descripción.
     *
     * @return string
     */
    private static function descriptionTitle(): string
    {
        return '<div class="col-lg order-2">' . Tools::trans('description') . '</div>';
    }

    /**
     * Genera el título de la columna de descuento.
     *
     * @return string
     */
    private static function discountTitle(): string
    {
        return '<div class="col-lg-1 text-center order-5">' . Tools::trans('percentage-discount') . '</div>';
    }

    /**
     * Genera el título de la columna de precio.
     *
     * @return string
     */
    private static function priceTitle(): string
    {
        return '<div class="col-lg-1 text-end order-4">' . Tools::trans('price') . '</div>';
    }

    /**
     * Genera el título de la columna de referencia.
     *
     * @return string
     */
    private static function referenceTitle(): string
    {
        return '<div class="col-lg-1 order-1">' . Tools::trans('reference') . '</div>';
    }

    /**
     * Genera los títulos de las columnas de total.
     *
     * @return string
     */
    private static function totalTitle(): string
    {
        $subtotalClass = self::$viewMode === 'subtotal' ? '' : 'd-none';
        $netClass = self::$viewMode === 'subtotal' ? 'd-none' : '';

        return '<div class="col-lg-1 text-end order-7 columSubtotal ' . $subtotalClass . '">' . Tools::trans('subtotal') . '</div>'
            . '<div class="col-lg-1 text-end order-7 columNeto ' . $netClass . '">' . Tools::trans('net') . '</div>';
    }
}