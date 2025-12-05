<?php
/**
 * ERPIA - Líneas para Documentos de Compras
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
use ERPIA\Core\Contract\PurchasesLineModInterface;
use ERPIA\Core\DataSrc\Impuestos;
use ERPIA\Core\Model\Base\BusinessDocumentLine;
use ERPIA\Core\Model\Base\PurchaseDocument;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Variante;

/**
 * Clase para generar las líneas de formularios de documentos de compras.
 * 
 * @author ERPIA
 * @version 1.0
 */
class PurchasesLineHTML
{
    use CommonLineHTML;

    /** @var array */
    private static $deletedLineIds = [];

    /** @var PurchasesLineModInterface[] */
    private static $modifiers = [];

    /**
     * Añade un modificador al sistema.
     *
     * @param PurchasesLineModInterface $modifier
     */
    public static function addModifier(PurchasesLineModInterface $modifier): void
    {
        self::$modifiers[] = $modifier;
    }

    /**
     * Aplica los datos del formulario al modelo y líneas.
     *
     * @param PurchaseDocument $model
     * @param BusinessDocumentLine[] $lines
     * @param array $formData
     */
    public static function apply(PurchaseDocument &$model, array &$lines, array $formData): void
    {
        self::$columnView = $formData['columnView'] ?? Tools::settings('default', 'columnetosubtotal', 'subtotal');
        $removeLineId = $formData['action'] === 'rm-line' ? $formData['selectedLine'] : 0;

        foreach ($lines as $key => $line) {
            if ($line->idlinea === (int)$removeLineId || !isset($formData['cantidad_' . $line->idlinea])) {
                self::$deletedLineIds[] = $line->idlinea;
                unset($lines[$key]);
                continue;
            }
            self::applyFormDataToLine($formData, $line, $line->idlinea);
        }

        for ($counter = 1; $counter < 1000; $counter++) {
            $newLineId = 'n' . $counter;
            if (isset($formData['cantidad_' . $newLineId]) && $removeLineId !== $newLineId) {
                $newLine = isset($formData['referencia_' . $newLineId])
                    ? $model->getNewProductLine($formData['referencia_' . $newLineId])
                    : $model->getNewLine();
                self::applyFormDataToLine($formData, $newLine, $newLineId);
                $lines[] = $newLine;
            }
        }

        if ($formData['action'] === 'add-product' || $formData['action'] === 'fast-product') {
            $lines[] = $model->getNewProductLine($formData['selectedLine']);
        } elseif ($formData['action'] === 'fast-line') {
            $fastLine = self::getQuickLine($model, $formData);
            if ($fastLine) {
                $lines[] = $fastLine;
            }
        } elseif ($formData['action'] === 'new-line') {
            $lines[] = $model->getNewLine();
        }

        foreach (self::$modifiers as $modifier) {
            $modifier->apply($model, $lines, $formData);
        }
    }

    /**
     * Añade recursos CSS/JS necesarios.
     */
    public static function assets(): void
    {
        foreach (self::$modifiers as $modifier) {
            $modifier->assets();
        }
    }

    /**
     * Obtiene los IDs de las líneas eliminadas.
     *
     * @return array
     */
    public static function getDeletedLines(): array
    {
        return self::$deletedLineIds;
    }

    /**
     * Genera un mapa de valores para las líneas.
     *
     * @param BusinessDocumentLine[] $lines
     * @param PurchaseDocument $model
     * @return array
     */
    public static function map(array $lines, PurchaseDocument $model): array
    {
        $valueMap = [];
        foreach ($lines as $line) {
            self::$counter++;
            $lineId = $line->idlinea ?? 'n' . self::$counter;

            $valueMap['iva_' . $lineId] = $line->iva;
            $valueMap['linetotal_' . $lineId] = self::calculateSubtotal($line, $model);
            $valueMap['lineneto_' . $lineId] = $line->pvptotal;
        }

        foreach (self::$modifiers as $modifier) {
            foreach ($modifier->map($lines, $model) as $key => $value) {
                $valueMap[$key] = $value;
            }
        }

        return $valueMap;
    }

    /**
     * Renderiza todas las líneas del documento.
     *
     * @param BusinessDocumentLine[] $lines
     * @param PurchaseDocument $model
     * @return string
     */
    public static function render(array $lines, PurchaseDocument $model): string
    {
        if (empty(self::$columnView)) {
            self::$columnView = Tools::settings('default', 'columnetosubtotal', 'subtotal');
        }

        self::$lineCount = count($lines);
        self::loadProductData($lines, $model);

        $html = '';
        foreach ($lines as $line) {
            $html .= self::renderLine($line, $model);
        }

        if (empty($html)) {
            $html = '<div class="container-fluid"><div class="row g-2"><div class="col p-3 table-warning text-center">'
                . Tools::trans('new-invoice-line-p') . '</div></div></div>';
        }

        return empty($model->codproveedor) ? '' : self::renderColumnTitles($model) . $html;
    }

    /**
     * Renderiza una línea individual del documento.
     *
     * @param BusinessDocumentLine $line
     * @param PurchaseDocument $model
     * @return string
     */
    public static function renderLine(BusinessDocumentLine $line, PurchaseDocument $model): string
    {
        self::$counter++;
        $lineId = $line->idlinea ?? 'n' . self::$counter;

        return '<div class="container-fluid"><div class="row g-2 align-items-center border-bottom pb-3 pb-lg-0">'
            . self::renderField($lineId, $line, $model, 'referencia')
            . self::renderField($lineId, $line, $model, 'descripcion')
            . self::renderField($lineId, $line, $model, 'cantidad')
            . self::renderAdditionalFields($lineId, $line, $model)
            . self::renderField($lineId, $line, $model, 'pvpunitario')
            . self::renderField($lineId, $line, $model, 'dtopor')
            . self::renderField($lineId, $line, $model, 'codimpuesto')
            . self::renderField($lineId, $line, $model, '_total')
            . self::expandButton($lineId, $model, 'purchasesFormAction')
            . '</div>' . self::renderLineModal($line, $lineId, $model) . '</div>';
    }

    /**
     * Aplica datos del formulario a una línea específica.
     *
     * @param array $formData
     * @param BusinessDocumentLine $line
     * @param string $lineId
     */
    private static function applyFormDataToLine(array $formData, BusinessDocumentLine &$line, string $lineId): void
    {
        $line->orden = (int)($formData['orden_' . $lineId] ?? 0);
        $line->cantidad = (float)($formData['cantidad_' . $lineId] ?? 0);
        $line->dtopor = (float)($formData['dtopor_' . $lineId] ?? 0);
        $line->dtopor2 = (float)($formData['dtopor2_' . $lineId] ?? 0);
        $line->descripcion = $formData['descripcion_' . $lineId] ?? '';
        $line->excepcioniva = $formData['excepcioniva_' . $lineId] ?? null;
        $line->irpf = (float)($formData['irpf_' . $lineId] ?? 0);
        $line->suplido = (bool)($formData['suplido_' . $lineId] ?? false);
        $line->pvpunitario = (float)($formData['pvpunitario_' . $lineId] ?? 0);

        if (isset($formData['codimpuesto_' . $lineId]) && $formData['codimpuesto_' . $lineId] !== $line->codimpuesto) {
            $tax = Impuestos::get($formData['codimpuesto_' . $lineId]);
            $line->codimpuesto = $tax->codimpuesto;
            $line->iva = $tax->iva;
            if ($line->recargo) {
                $line->recargo = $tax->recargo;
            }
        } else {
            $line->recargo = (float)($formData['recargo_' . $lineId] ?? 0);
        }

        foreach (self::$modifiers as $modifier) {
            $modifier->applyToLine($formData, $line, $lineId);
        }
    }

    /**
     * Genera el campo de cantidad.
     *
     * @param string $lineId
     * @param BusinessDocumentLine $line
     * @param PurchaseDocument $model
     * @param string $jsFunction
     * @return string
     */
    private static function cantidad(string $lineId, BusinessDocumentLine $line, PurchaseDocument $model, string $jsFunction): string
    {
        if (!$model->editable) {
            return '<div class="col-sm-2 col-lg-1 order-3">'
                . '<div class="d-lg-none mt-2 small">' . Tools::trans('quantity') . '</div>'
                . '<div class="input-group input-group-sm">'
                . self::remainingQuantity($line, $model)
                . '<input type="number" class="form-control form-control-sm text-lg-end border-0" value="' . $line->cantidad . '" disabled/>'
                . '</div>'
                . '</div>';
        }

        return '<div class="col-sm-2 col-lg-1 order-3">'
            . '<div class="d-lg-none mt-2 small">' . Tools::trans('quantity') . '</div>'
            . '<div class="input-group input-group-sm">'
            . self::remainingQuantity($line, $model)
            . '<input type="number" name="cantidad_' . $lineId . '" value="' . $line->cantidad
            . '" class="form-control form-control-sm text-lg-end border-0 doc-line-qty" onkeyup="return ' . $jsFunction . '(\'recalculate-line\', \'0\', event);"/>'
            . '</div>'
            . '</div>';
    }

    /**
     * Obtiene una línea rápida basada en código de barras.
     *
     * @param PurchaseDocument $model
     * @param array $formData
     * @return BusinessDocumentLine|null
     */
    private static function getQuickLine(PurchaseDocument $model, array $formData): ?BusinessDocumentLine
    {
        if (empty($formData['fastli'])) {
            return $model->getNewLine();
        }

        $barcodeCondition = [new DataBaseWhere('codbarras', $formData['fastli'])];
        $variants = Variante::all($barcodeCondition, [], 0, 5);

        foreach ($variants as $variant) {
            $product = $variant->getProducto();
            if (!$product->bloqueado && $product->secompra) {
                return $model->getNewProductLine($variant->referencia);
            }
        }

        foreach (self::$modifiers as $modifier) {
            $line = $modifier->getFastLine($model, $formData);
            if ($line) {
                return $line;
            }
        }

        Tools::log()->warning('product-not-found', ['%ref%' => $formData['fastli']]);
        return null;
    }

    /**
     * Genera el campo de precio.
     *
     * @param string $lineId
     * @param BusinessDocumentLine $line
     * @param PurchaseDocument $model
     * @param string $jsFunction
     * @return string
     */
    private static function precio(string $lineId, BusinessDocumentLine $line, PurchaseDocument $model, string $jsFunction): string
    {
        if (!$model->editable) {
            return '<div class="col-sm col-lg-1 order-4">'
                . '<div class="d-lg-none mt-2 small">' . Tools::trans('price') . '</div>'
                . '<input type="number" value="' . $line->pvpunitario . '" class="form-control form-control-sm text-lg-end border-0" disabled/>'
                . '</div>';
        }

        $attributes = 'name="pvpunitario_' . $lineId . '" onkeyup="return ' . $jsFunction . '(\'recalculate-line\', \'0\', event);"';
        return '<div class="col-sm col-lg-1 order-4">'
            . '<div class="d-lg-none mt-2 small">' . Tools::trans('price') . '</div>'
            . '<input type="number" ' . $attributes . ' value="' . $line->pvpunitario . '" class="form-control form-control-sm text-lg-end border-0"/>'
            . '</div>';
    }

    /**
     * Renderiza un campo específico de la línea.
     *
     * @param string $lineId
     * @param BusinessDocumentLine $line
     * @param PurchaseDocument $model
     * @param string $fieldName
     * @return string|null
     */
    private static function renderField(string $lineId, BusinessDocumentLine $line, PurchaseDocument $model, string $fieldName): ?string
    {
        foreach (self::$modifiers as $modifier) {
            $html = $modifier->renderField($lineId, $line, $model, $fieldName);
            if ($html !== null) {
                return $html;
            }
        }

        switch ($fieldName) {
            case '_total':
                return self::lineTotalField($lineId, $line, $model, 'purchasesLineTotalWithTaxes', 'purchasesLineTotalWithoutTaxes');
            case 'cantidad':
                return self::cantidad($lineId, $line, $model, 'purchasesFormActionWait');
            case 'codimpuesto':
                return self::taxSelector($lineId, $line, $model, 'purchasesFormAction');
            case 'descripcion':
                return self::descriptionField($lineId, $line, $model);
            case 'dtopor':
                return self::discountPercentage($lineId, $line, $model, 'purchasesFormActionWait');
            case 'dtopor2':
                return self::secondDiscount($lineId, $line, $model, 'dtopor2', 'purchasesFormActionWait');
            case 'excepcioniva':
                return self::vatException($lineId, $line, $model, 'excepcioniva', 'purchasesFormActionWait');
            case 'irpf':
                return self::retentionSelector($lineId, $line, $model, 'purchasesFormAction');
            case 'pvpunitario':
                return self::precio($lineId, $line, $model, 'purchasesFormActionWait');
            case 'recargo':
                return self::surchargeField($lineId, $line, $model, 'purchasesFormActionWait');
            case 'referencia':
                return self::referenceField($lineId, $line, $model);
            case 'suplido':
                return self::suppliedField($lineId, $line, $model, 'purchasesFormAction');
        }

        return null;
    }

    /**
     * Renderiza el modal de detalles de una línea.
     *
     * @param BusinessDocumentLine $line
     * @param string $lineId
     * @param PurchaseDocument $model
     * @return string
     */
    private static function renderLineModal(BusinessDocumentLine $line, string $lineId, PurchaseDocument $model): string
    {
        return '<div class="modal fade" id="lineModal-' . $lineId . '" tabindex="-1" aria-labelledby="lineModal-' . $lineId . 'Label" aria-hidden="true">'
            . '<div class="modal-dialog modal-dialog-centered">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title"><i class="fa-solid fa-edit fa-fw" aria-hidden="true"></i> ' . $line->referencia . '</h5>'
            . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
            . '</div>'
            . '<div class="modal-body">'
            . '<div class="row g-2">'
            . self::renderField($lineId, $line, $model, 'dtopor2')
            . self::renderField($lineId, $line, $model, 'recargo')
            . self::renderField($lineId, $line, $model, 'irpf')
            . self::renderField($lineId, $line, $model, 'excepcioniva')
            . self::renderField($lineId, $line, $model, 'suplido')
            . '</div>'
            . '<div class="row g-2">'
            . self::renderModalFields($lineId, $line, $model)
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

    /**
     * Renderiza campos modales adicionales de los modificadores.
     *
     * @param string $lineId
     * @param BusinessDocumentLine $line
     * @param PurchaseDocument $model
     * @return string
     */
    private static function renderModalFields(string $lineId, BusinessDocumentLine $line, PurchaseDocument $model): string
    {
        $modalFields = [];
        foreach (self::$modifiers as $modifier) {
            foreach ($modifier->newModalFields() as $field) {
                if (!in_array($field, $modalFields)) {
                    $modalFields[] = $field;
                }
            }
        }

        $html = '';
        foreach ($modalFields as $field) {
            foreach (self::$modifiers as $modifier) {
                $fieldHtml = $modifier->renderField($lineId, $line, $model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }

    /**
     * Renderiza campos adicionales de los modificadores.
     *
     * @param string $lineId
     * @param BusinessDocumentLine $line
     * @param PurchaseDocument $model
     * @return string
     */
    private static function renderAdditionalFields(string $lineId, BusinessDocumentLine $line, PurchaseDocument $model): string
    {
        $additionalFields = [];
        foreach (self::$modifiers as $modifier) {
            foreach ($modifier->newFields() as $field) {
                if (!in_array($field, $additionalFields)) {
                    $additionalFields[] = $field;
                }
            }
        }

        $html = '';
        foreach ($additionalFields as $field) {
            foreach (self::$modifiers as $modifier) {
                $fieldHtml = $modifier->renderField($lineId, $line, $model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }

    /**
     * Renderiza títulos adicionales de los modificadores.
     *
     * @param PurchaseDocument $model
     * @return string
     */
    private static function renderAdditionalTitles(PurchaseDocument $model): string
    {
        $additionalTitles = [];
        foreach (self::$modifiers as $modifier) {
            foreach ($modifier->newTitles() as $field) {
                if (!in_array($field, $additionalTitles)) {
                    $additionalTitles[] = $field;
                }
            }
        }

        $html = '';
        foreach ($additionalTitles as $field) {
            foreach (self::$modifiers as $modifier) {
                $fieldHtml = $modifier->renderTitle($model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }

    /**
     * Renderiza un título de columna específico.
     *
     * @param PurchaseDocument $model
     * @param string $fieldName
     * @return string|null
     */
    private static function renderTitle(PurchaseDocument $model, string $fieldName): ?string
    {
        foreach (self::$modifiers as $modifier) {
            $html = $modifier->renderTitle($model, $fieldName);
            if ($html !== null) {
                return $html;
            }
        }

        switch ($fieldName) {
            case '_actionsButton':
                return self::actionButtonsSpace($model);
            case '_total':
                return self::totalTitle();
            case 'cantidad':
                return self::quantityTitle();
            case 'codimpuesto':
                return self::taxTitle();
            case 'descripcion':
                return self::descriptionTitle();
            case 'dtopor':
                return self::discountTitle();
            case 'pvpunitario':
                return self::priceTitle();
            case 'referencia':
                return self::referenceTitle();
        }

        return null;
    }

    /**
     * Renderiza los títulos de las columnas.
     *
     * @param PurchaseDocument $model
     * @return string
     */
    private static function renderColumnTitles(PurchaseDocument $model): string
    {
        return '<div class="container-fluid d-none d-lg-block pt-3"><div class="row g-2 border-bottom">'
            . self::renderTitle($model, 'referencia')
            . self::renderTitle($model, 'descripcion')
            . self::renderTitle($model, 'cantidad')
            . self::renderAdditionalTitles($model)
            . self::renderTitle($model, 'pvpunitario')
            . self::renderTitle($model, 'dtopor')
            . self::renderTitle($model, 'codimpuesto')
            . self::renderTitle($model, '_total')
            . self::renderTitle($model, '_actionsButton')
            . '</div></div>';
    }
}