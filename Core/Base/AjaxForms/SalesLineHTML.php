<?php
namespace ERPIA\Core\Base\AjaxForms;

use ERPIA\Core\Base\Contract\SalesLineModInterface;
use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Base\Translator;
use ERPIA\Core\DataSrc\Impuestos;
use ERPIA\Core\Model\Base\SalesDocument;
use ERPIA\Core\Model\Base\SalesDocumentLine;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Stock;
use ERPIA\Dinamic\Model\Variante;

class SalesLineHTML
{
    use CommonLineHTML;

    /** @var array */
    private static $deletedLines = [];

    /** @var SalesLineModInterface[] */
    private static $mods = [];

    public static function addMod(SalesLineModInterface $mod)
    {
        self::$mods[] = $mod;
    }

    public static function apply(SalesDocument &$model, array &$lines, array $formData)
    {
        self::$columnView = $formData['columnView'] ?? Tools::settings('default', 'columnetosubtotal', 'subtotal');

        $removeLineId = $formData['action'] === 'rm-line' ? $formData['selectedLine'] : 0;
        
        foreach ($lines as $index => $line) {
            if ($line->idlinea === (int)$removeLineId || !isset($formData['cantidad_' . $line->idlinea])) {
                self::$deletedLines[] = $line->idlinea;
                unset($lines[$index]);
                continue;
            }
            self::processLineData($formData, $line, $line->idlinea);
        }

        for ($counter = 1; $counter < 1000; $counter++) {
            $newLineId = 'n' . $counter;
            if (isset($formData['cantidad_' . $newLineId]) && $removeLineId !== $newLineId) {
                $newLine = isset($formData['referencia_' . $newLineId]) ? 
                    $model->getNewProductLine($formData['referencia_' . $newLineId]) : $model->getNewLine();
                self::processLineData($formData, $newLine, $newLineId);
                $lines[] = $newLine;
            }
        }

        $action = $formData['action'] ?? '';
        if ($action === 'add-product' || $action === 'fast-product') {
            $lines[] = $model->getNewProductLine($formData['selectedLine']);
        } elseif ($action === 'fast-line') {
            $fastLine = self::createFastLine($model, $formData);
            if ($fastLine) {
                $lines[] = $fastLine;
            }
        } elseif ($action === 'new-line') {
            $lines[] = $model->getNewLine();
        }

        foreach (self::$mods as $modifier) {
            $modifier->apply($model, $lines, $formData);
        }
    }

    public static function assets()
    {
        foreach (self::$mods as $modifier) {
            $modifier->assets();
        }
    }

    public static function getDeletedLines(): array
    {
        return self::$deletedLines;
    }

    public static function map(array $lines, SalesDocument $model): array
    {
        $mapping = [];
        self::$num = 0;
        
        foreach ($lines as $line) {
            self::$num++;
            $lineId = $line->idlinea ?? 'n' . self::$num;
            
            $mapping['iva_' . $lineId] = $line->iva;
            $mapping['linetotal_' . $lineId] = self::calculateSubtotal($line, $model);
            $mapping['lineneto_' . $lineId] = $line->pvptotal;
        }

        foreach (self::$mods as $modifier) {
            $modMapping = $modifier->map($lines, $model);
            foreach ($modMapping as $key => $value) {
                $mapping[$key] = $value;
            }
        }

        return $mapping;
    }

    public static function render(array $lines, SalesDocument $model): string
    {
        if (empty(self::$columnView)) {
            self::$columnView = Tools::settings('default', 'columnetosubtotal', 'subtotal');
        }

        self::$numlines = count($lines);
        self::loadProducts($lines, $model);

        $translator = new Translator();
        $content = '';
        
        foreach ($lines as $line) {
            $content .= self::renderLine($translator, $line, $model);
        }

        if (empty($content)) {
            $content = '<div class="container-fluid"><div class="form-row table-warning"><div class="col p-3 text-center">'
                . $translator->trans('new-invoice-line-p') . '</div></div></div>';
        }

        return empty($model->codcliente) ? '' : self::renderColumnTitles($translator, $model) . $content;
    }

    public static function renderLine(Translator $i18n, SalesDocumentLine $line, SalesDocument $model): string
    {
        self::$num++;
        $lineId = $line->idlinea ?? 'n' . self::$num;
        
        return '<div class="container-fluid fs-line"><div class="form-row align-items-center border-bottom pb-3 pb-lg-0">'
            . self::renderField($i18n, $lineId, $line, $model, 'referencia')
            . self::renderField($i18n, $lineId, $line, $model, 'descripcion')
            . self::renderField($i18n, $lineId, $line, $model, 'cantidad')
            . self::renderDynamicFields($i18n, $lineId, $line, $model)
            . self::renderField($i18n, $lineId, $line, $model, 'pvpunitario')
            . self::renderField($i18n, $lineId, $line, $model, 'dtopor')
            . self::renderField($i18n, $lineId, $line, $model, 'codimpuesto')
            . self::renderField($i18n, $lineId, $line, $model, '_total')
            . self::renderExpandButton($i18n, $lineId, $model, 'salesFormAction')
            . '</div>'
            . self::renderLineModal($i18n, $line, $lineId, $model) . '</div>';
    }

    private static function processLineData(array $formData, SalesDocumentLine &$line, string $lineId)
    {
        $line->orden = (int)$formData['orden_' . $lineId];
        $line->cantidad = (float)$formData['cantidad_' . $lineId];
        $line->coste = floatval($formData['coste_' . $lineId] ?? $line->coste);
        $line->dtopor = (float)$formData['dtopor_' . $lineId];
        $line->dtopor2 = (float)$formData['dtopor2_' . $lineId];
        $line->descripcion = $formData['descripcion_' . $lineId];
        $line->excepcioniva = $formData['excepcioniva_' . $lineId] ?? null;
        $line->irpf = (float)($formData['irpf_' . $lineId] ?? '0');
        $line->mostrar_cantidad = (bool)($formData['mostrar_cantidad_' . $lineId] ?? '0');
        $line->mostrar_precio = (bool)($formData['mostrar_precio_' . $lineId] ?? '0');
        $line->salto_pagina = (bool)($formData['salto_pagina_' . $lineId] ?? '0');
        $line->suplido = (bool)($formData['suplido_' . $lineId] ?? '0');
        $line->pvpunitario = (float)$formData['pvpunitario_' . $lineId];

        if (isset($formData['codimpuesto_' . $lineId]) && $formData['codimpuesto_' . $lineId] !== $line->codimpuesto) {
            $tax = Impuestos::get($formData['codimpuesto_' . $lineId]);
            $line->codimpuesto = $tax->codimpuesto;
            $line->iva = $tax->iva;
            if ($line->recargo) {
                $line->recargo = $tax->recargo;
            }
        } else {
            $line->recargo = (float)($formData['recargo_' . $lineId] ?? '0');
        }

        foreach (self::$mods as $modifier) {
            $modifier->applyToLine($formData, $line, $lineId);
        }
    }

    private static function renderQuantityField(Translator $i18n, string $lineId, SalesDocumentLine $line, SalesDocument $model, string $jsFunction): string
    {
        if (!$model->editable) {
            return '<div class="col-sm-2 col-lg-1 order-3">'
                . '<div class="d-lg-none mt-2 small">' . $i18n->trans('quantity') . '</div>'
                . '<div class="input-group input-group-sm">'
                . self::renderRemainingQuantity($i18n, $line, $model)
                . '<input type="number" class="form-control text-lg-right border-0" value="' . $line->cantidad . '" disabled=""/>'
                . '</div>'
                . '</div>';
        }

        return '<div class="col-sm-2 col-lg-1 order-3">'
            . '<div class="d-lg-none mt-2 small">' . $i18n->trans('quantity') . '</div>'
            . '<div class="input-group input-group-sm">'
            . self::renderRemainingQuantity($i18n, $line, $model)
            . '<input type="number" name="cantidad_' . $lineId . '" value="' . $line->cantidad
            . '" class="form-control text-lg-right border-0 doc-line-qty" onkeyup="return ' . $jsFunction . '(\'recalculate-line\', \'0\', event);"/>'
            . self::renderStockInfo($i18n, $line, $model)
            . '</div>'
            . '</div>';
    }

    private static function renderStockInfo(Translator $i18n, SalesDocumentLine $line, SalesDocument $model): string
    {
        if (empty($line->referencia) || $line->modelClassName() === 'LineaFacturaCliente' || !$model->editable) {
            return '';
        }

        $product = $line->getProducto();
        if ($product->nostock) {
            return '';
        }

        $stock = self::$stocks[$line->referencia] ?? new Stock();
        $stockHtml = '';

        switch ($line->actualizastock) {
            case -1:
            case -2:
                $stockHtml = $stock->disponible > 0 ? 
                    '<a href="' . $stock->url() . '" target="_blank" class="btn btn-outline-success">' . $stock->disponible . '</a>' :
                    '<a href="' . $stock->url() . '" target="_blank" class="btn btn-outline-danger">' . $stock->disponible . '</a>';
                break;

            default:
                $stockHtml = $line->cantidad <= $stock->cantidad ?
                    '<a href="' . $stock->url() . '" target="_blank" class="btn btn-outline-success">' . $stock->cantidad . '</a>' :
                    '<a href="' . $stock->url() . '" target="_blank" class="btn btn-outline-danger">' . $stock->cantidad . '</a>';
                break;
        }

        return empty($stockHtml) ? '' : 
            '<div class="input-group-prepend" title="' . $i18n->trans('stock') . '">' . $stockHtml . '</div>';
    }

    private static function renderCostField(Translator $i18n, string $lineId, SalesDocumentLine $line, SalesDocument $model, string $field): string
    {
        if (!SalesHeaderHTML::checkLevel(Tools::settings('default', 'levelcostsales', 0))) {
            return '';
        }

        $attributes = $model->editable ?
            'name="' . $field . '_' . $lineId . '" min="0" step="any"' :
            'disabled=""';

        return '<div class="col-6">'
            . '<div class="mb-2">' . $i18n->trans('cost')
            . '<input type="number" ' . $attributes . ' value="' . $line->{$field} . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    private static function createFastLine(SalesDocument $model, array $formData): ?SalesDocumentLine
    {
        if (empty($formData['fastli'])) {
            return $model->getNewLine();
        }

        $variant = new Variante();
        $barcodeCondition = [new DataBaseWhere('codbarras', $formData['fastli'])];
        foreach ($variant->all($barcodeCondition) as $variante) {
            return $model->getNewProductLine($variante->referencia);
        }

        foreach (self::$mods as $modifier) {
            $fastLine = $modifier->getFastLine($model, $formData);
            if ($fastLine) {
                return $fastLine;
            }
        }

        Tools::log()->warning('product-not-found', ['%ref%' => $formData['fastli']]);
        return null;
    }

    private static function renderPriceField(Translator $i18n, string $lineId, SalesDocumentLine $line, SalesDocument $model, string $jsFunction): string
    {
        if (!$model->editable) {
            return '<div class="col-sm col-lg-1 order-4">'
                . '<span class="d-lg-none small">' . $i18n->trans('price') . '</span>'
                . '<input type="number" value="' . $line->pvpunitario . '" class="form-control form-control-sm text-lg-right border-0" disabled/>'
                . '</div>';
        }

        $attributes = 'name="pvpunitario_' . $lineId . '" onkeyup="return ' . $jsFunction . '(\'recalculate-line\', \'0\', event);"';
        return '<div class="col-sm col-lg-1 order-4">'
            . '<span class="d-lg-none small">' . $i18n->trans('price') . '</span>'
            . '<input type="number" ' . $attributes . ' value="' . $line->pvpunitario . '" class="form-control form-control-sm text-lg-right border-0"/>'
            . '</div>';
    }

    private static function renderField(Translator $i18n, string $lineId, SalesDocumentLine $line, SalesDocument $model, string $field): ?string
    {
        foreach (self::$mods as $modifier) {
            $fieldHtml = $modifier->renderField($i18n, $lineId, $line, $model, $field);
            if ($fieldHtml !== null) {
                return $fieldHtml;
            }
        }

        return self::renderCoreField($i18n, $lineId, $line, $model, $field);
    }

    private static function renderCoreField(Translator $i18n, string $lineId, SalesDocumentLine $line, SalesDocument $model, string $field): ?string
    {
        switch ($field) {
            case '_total':
                return self::renderLineTotal($i18n, $lineId, $line, $model, 'salesLineTotalWithTaxes', 'salesLineTotalWithoutTaxes');

            case 'cantidad':
                return self::renderQuantityField($i18n, $lineId, $line, $model, 'salesFormActionWait');

            case 'codimpuesto':
                return self::renderTaxField($i18n, $lineId, $line, $model, 'salesFormAction');

            case 'coste':
                return self::renderCostField($i18n, $lineId, $line, $model, 'coste');

            case 'descripcion':
                return self::renderDescriptionField($i18n, $lineId, $line, $model);

            case 'dtopor':
                return self::renderDiscountField($i18n, $lineId, $line, $model, 'salesFormActionWait');

            case 'dtopor2':
                return self::renderSecondDiscount($i18n, $lineId, $line, $model, 'dtopor2', 'salesFormActionWait');

            case 'excepcioniva':
                return self::renderTaxException($i18n, $lineId, $line, $model, 'excepcioniva', 'salesFormActionWait');

            case 'irpf':
                return self::renderIrpfField($i18n, $lineId, $line, $model, 'salesFormAction');

            case 'mostrar_cantidad':
                return self::renderBooleanField($i18n, $lineId, $line, $model, 'mostrar_cantidad', 'print-quantity');

            case 'mostrar_precio':
                return self::renderBooleanField($i18n, $lineId, $line, $model, 'mostrar_precio', 'print-price');

            case 'pvpunitario':
                return self::renderPriceField($i18n, $lineId, $line, $model, 'salesFormActionWait');

            case 'recargo':
                return self::renderSurchargeField($i18n, $lineId, $line, $model, 'salesFormActionWait');

            case 'referencia':
                return self::renderReferenceField($i18n, $lineId, $line, $model);

            case 'salto_pagina':
                return self::renderBooleanField($i18n, $lineId, $line, $model, 'salto_pagina', 'page-break');

            case 'suplido':
                return self::renderSupplidoField($i18n, $lineId, $line, $model, 'salesFormAction');
        }

        return null;
    }

    private static function renderLineModal(Translator $i18n, SalesDocumentLine $line, string $lineId, SalesDocument $model): string
    {
        $modalFields = [
            'dtopor2', 'recargo', 'irpf', 'excepcioniva', 'suplido', 'coste',
            'mostrar_cantidad', 'mostrar_precio', 'salto_pagina'
        ];

        $modalContent = '';
        foreach ($modalFields as $field) {
            $modalContent .= self::renderField($i18n, $lineId, $line, $model, $field);
        }
        $modalContent .= self::renderDynamicModalFields($i18n, $lineId, $line, $model);

        return '<div class="modal fade" id="lineModal-' . $lineId . '" tabindex="-1" aria-labelledby="lineModal-' . $lineId . 'Label" aria-hidden="true">'
            . '<div class="modal-dialog modal-dialog-centered">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title"><i class="fas fa-edit fa-fw" aria-hidden="true"></i> ' . $line->referencia . '</h5>'
            . '<button type="button" class="close" data-dismiss="modal" aria-label="Close">'
            . '<span aria-hidden="true">&times;</span>'
            . '</button>'
            . '</div>'
            . '<div class="modal-body">'
            . '<div class="form-row">'
            . $modalContent
            . '</div>'
            . '</div>'
            . '<div class="modal-footer">'
            . '<button type="button" class="btn btn-secondary" data-dismiss="modal">' . $i18n->trans('close') . '</button>'
            . '<button type="button" class="btn btn-primary" data-dismiss="modal">' . $i18n->trans('accept') . '</button>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    private static function renderDynamicModalFields(Translator $i18n, string $lineId, SalesDocumentLine $line, SalesDocument $model): string
    {
        $fields = [];
        foreach (self::$mods as $modifier) {
            $modFields = $modifier->newModalFields();
            foreach ($modFields as $field) {
                if (!in_array($field, $fields)) {
                    $fields[] = $field;
                }
            }
        }

        $html = '';
        foreach ($fields as $field) {
            foreach (self::$mods as $modifier) {
                $fieldHtml = $modifier->renderField($i18n, $lineId, $line, $model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }

    private static function renderDynamicFields(Translator $i18n, string $lineId, SalesDocumentLine $line, SalesDocument $model): string
    {
        $fields = [];
        foreach (self::$mods as $modifier) {
            $modFields = $modifier->newFields();
            foreach ($modFields as $field) {
                if (!in_array($field, $fields)) {
                    $fields[] = $field;
                }
            }
        }

        $html = '';
        foreach ($fields as $field) {
            foreach (self::$mods as $modifier) {
                $fieldHtml = $modifier->renderField($i18n, $lineId, $line, $model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }

    private static function renderDynamicTitles(Translator $i18n, SalesDocument $model): string
    {
        $fields = [];
        foreach (self::$mods as $modifier) {
            $modFields = $modifier->newTitles();
            foreach ($modFields as $field) {
                if (!in_array($field, $fields)) {
                    $fields[] = $field;
                }
            }
        }

        $html = '';
        foreach ($fields as $field) {
            foreach (self::$mods as $modifier) {
                $fieldHtml = $modifier->renderTitle($i18n, $model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }

    private static function renderTitleField(Translator $i18n, SalesDocument $model, string $field): ?string
    {
        foreach (self::$mods as $modifier) {
            $titleHtml = $modifier->renderTitle($i18n, $model, $field);
            if ($titleHtml !== null) {
                return $titleHtml;
            }
        }

        switch ($field) {
            case '_actionsButton':
                return self::renderActionsButtonTitle($model);
            case '_total':
                return self::renderTotalTitle($i18n);
            case 'cantidad':
                return self::renderQuantityTitle($i18n);
            case 'codimpuesto':
                return self::renderTaxTitle($i18n);
            case 'descripcion':
                return self::renderDescriptionTitle($i18n);
            case 'dtopor':
                return self::renderDiscountTitle($i18n);
            case 'pvpunitario':
                return self::renderPriceTitle($i18n);
            case 'referencia':
                return self::renderReferenceTitle($i18n);
        }

        return null;
    }

    private static function renderColumnTitles(Translator $i18n, SalesDocument $model): string
    {
        return '<div class="container-fluid d-none d-lg-block titles"><div class="form-row border-bottom">'
            . self::renderTitleField($i18n, $model, 'referencia')
            . self::renderTitleField($i18n, $model, 'descripcion')
            . self::renderTitleField($i18n, $model, 'cantidad')
            . self::renderDynamicTitles($i18n, $model)
            . self::renderTitleField($i18n, $model, 'pvpunitario')
            . self::renderTitleField($i18n, $model, 'dtopor')
            . self::renderTitleField($i18n, $model, 'codimpuesto')
            . self::renderTitleField($i18n, $model, '_total')
            . self::renderTitleField($i18n, $model, '_actionsButton')
            . '</div></div>';
    }
}