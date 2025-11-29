<?php

namespace ERPIA\Core\Base\AjaxForms;

use ERPIA\Core\Base\Contract\PurchasesLineModInterface;
use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Base\Translator;
use ERPIA\Core\DataSrc\Impuestos;
use ERPIA\Core\Model\Base\PurchaseDocument;
use ERPIA\Core\Model\Base\PurchaseDocumentLine;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Variante;

class PurchasesLineHTML
{
    use CommonLineHTML;

    /** @var array */
    private static $deletedLines = [];

    /** @var PurchasesLineModInterface[] */
    private static $mods = [];

    public static function addMod(PurchasesLineModInterface $mod)
    {
        foreach (self::$mods as $m) {
            if ($m === $mod) {
                return;
            }
        }
        self::$mods[] = $mod;
    }

    /**
     * Aplica datos del formulario al documento y sus líneas.
     *
     * @param PurchaseDocument $model
     * @param PurchaseDocumentLine[] $lines
     * @param array $formData
     */
    public static function apply(PurchaseDocument &$model, array &$lines, array $formData)
    {
        self::$columnView = $formData['columnView'] ?? Tools::settings('default', 'columnetosubtotal', 'subtotal');

        // update or remove lines
        $rmLineId = ($formData['action'] ?? '') === 'rm-line' ? ($formData['selectedLine'] ?? 0) : 0;
        foreach ($lines as $key => $value) {
            $idlinea = $value->idlinea ?? null;
            if ($idlinea !== null && ((string)$idlinea === (string)$rmLineId || false === isset($formData['cantidad_' . $idlinea]))) {
                self::$deletedLines[] = $idlinea;
                unset($lines[$key]);
                continue;
            }

            self::applyToLine($formData, $value, $value->idlinea);
        }

        // new lines
        for ($num = 1; $num < 1000; $num++) {
            if (isset($formData['cantidad_n' . $num]) && $rmLineId !== 'n' . $num) {
                $newLine = isset($formData['referencia_n' . $num]) && $formData['referencia_n' . $num] !== ''
                    ? $model->getNewProductLine($formData['referencia_n' . $num])
                    : $model->getNewLine();
                $idNewLine = 'n' . $num;
                self::applyToLine($formData, $newLine, $idNewLine);
                $lines[] = $newLine;
            }
        }

        // add new line actions
        $action = $formData['action'] ?? '';
        if ($action === 'add-product' || $action === 'fast-product') {
            if (isset($formData['selectedLine'])) {
                $lines[] = $model->getNewProductLine($formData['selectedLine']);
            }
        } elseif ($action === 'fast-line') {
            $newLine = static::getFastLine($model, $formData);
            if ($newLine) {
                $lines[] = $newLine;
            }
        } elseif ($action === 'new-line') {
            $lines[] = $model->getNewLine();
        }

        // mods
        foreach (self::$mods as $mod) {
            try {
                $mod->apply($model, $lines, $formData);
            } catch (\Throwable $e) {
                Tools::log()->warning('mod-apply-error: ' . $e->getMessage());
            }
        }
    }

    public static function assets()
    {
        // mods
        foreach (self::$mods as $mod) {
            try {
                $mod->assets();
            } catch (\Throwable $e) {
                Tools::log()->warning('mod-assets-error: ' . $e->getMessage());
            }
        }
    }

    public static function getDeletedLines(): array
    {
        return self::$deletedLines;
    }

    /**
     * @param PurchaseDocumentLine[] $lines
     * @param PurchaseDocument $model
     *
     * @return array
     */
    public static function map(array $lines, PurchaseDocument $model): array
    {
        $map = [];
        foreach ($lines as $line) {
            self::$num++;
            $idlinea = $line->idlinea ?? 'n' . self::$num;

            // codimpuesto
            $map['iva_' . $idlinea] = $line->iva ?? 0;

            // total
            $map['linetotal_' . $idlinea] = self::subtotalValue($line, $model);

            // neto
            $map['lineneto_' . $idlinea] = $line->pvptotal ?? 0;
        }

        // mods
        foreach (self::$mods as $mod) {
            try {
                foreach ($mod->map($lines, $model) as $key => $value) {
                    $map[$key] = $value;
                }
            } catch (\Throwable $e) {
                Tools::log()->warning('mod-map-error: ' . $e->getMessage());
            }
        }

        return $map;
    }

    /**
     * @param PurchaseDocumentLine[] $lines
     * @param PurchaseDocument $model
     *
     * @return string
     */
    public static function render(array $lines, PurchaseDocument $model): string
    {
        if (empty(self::$columnView)) {
            self::$columnView = Tools::settings('default', 'columnetosubtotal', 'subtotal');
        }

        self::$numlines = count($lines);
        self::loadProducts($lines, $model);

        $i18n = new Translator();
        $html = '';
        foreach ($lines as $line) {
            $html .= self::renderLine($i18n, $line, $model);
        }
        if (empty($html)) {
            $html .= '<div class="container-fluid"><div class="form-row table-warning"><div class="col p-3 text-center">'
                . $i18n->trans('new-invoice-line-p') . '</div></div></div>';
        }
        return empty($model->codproveedor) ? '' : self::renderTitles($i18n, $model) . $html;
    }

    public static function renderLine(Translator $i18n, PurchaseDocumentLine $line, PurchaseDocument $model): string
    {
        self::$num++;
        $idlinea = $line->idlinea ?? 'n' . self::$num;
        return '<div class="container-fluid"><div class="form-row align-items-center border-bottom pb-3 pb-lg-0">'
            . self::renderField($i18n, $idlinea, $line, $model, 'referencia')
            . self::renderField($i18n, $idlinea, $line, $model, 'descripcion')
            . self::renderField($i18n, $idlinea, $line, $model, 'cantidad')
            . self::renderNewFields($i18n, $idlinea, $line, $model)
            . self::renderField($i18n, $idlinea, $line, $model, 'pvpunitario')
            . self::renderField($i18n, $idlinea, $line, $model, 'dtopor')
            . self::renderField($i18n, $idlinea, $line, $model, 'codimpuesto')
            . self::renderField($i18n, $idlinea, $line, $model, '_total')
            . self::renderExpandButton($i18n, $idlinea, $model, 'purchasesFormAction')
            . '</div>' . self::renderLineModal($i18n, $line, $idlinea, $model) . '</div>';
    }

    private static function applyToLine(array $formData, PurchaseDocumentLine &$line, string $id)
    {
        // Casts y validación mínima
        $line->orden = isset($formData['orden_' . $id]) ? (int)$formData['orden_' . $id] : ($line->orden ?? 0);
        $line->cantidad = isset($formData['cantidad_' . $id]) ? (float)$formData['cantidad_' . $id] : ($line->cantidad ?? 0.0);
        $line->dtopor = isset($formData['dtopor_' . $id]) ? (float)$formData['dtopor_' . $id] : ($line->dtopor ?? 0.0);
        $line->dtopor2 = isset($formData['dtopor2_' . $id]) ? (float)$formData['dtopor2_' . $id] : ($line->dtopor2 ?? 0.0);
        $line->descripcion = isset($formData['descripcion_' . $id]) && is_string($formData['descripcion_' . $id])
            ? trim($formData['descripcion_' . $id]) : ($line->descripcion ?? '');
        $line->excepcioniva = $formData['excepcioniva_' . $id] ?? ($line->excepcioniva ?? null);
        $line->irpf = isset($formData['irpf_' . $id]) ? (float)$formData['irpf_' . $id] : ($line->irpf ?? 0.0);
        $line->suplido = isset($formData['suplido_' . $id]) ? (bool)$formData['suplido_' . $id] : ($line->suplido ?? false);
        $line->pvpunitario = isset($formData['pvpunitario_' . $id]) ? (float)$formData['pvpunitario_' . $id] : ($line->pvpunitario ?? 0.0);

        // ¿Cambio de impuesto?
        if (isset($formData['codimpuesto_' . $id]) && $formData['codimpuesto_' . $id] !== ($line->codimpuesto ?? null)) {
            try {
                $impuesto = Impuestos::get($formData['codimpuesto_' . $id]);
                if ($impuesto) {
                    $line->codimpuesto = $impuesto->codimpuesto;
                    $line->iva = $impuesto->iva;
                    if (!empty($line->recargo)) {
                        $line->recargo = $impuesto->recargo;
                    }
                }
            } catch (\Throwable $e) {
                Tools::log()->warning('impuesto-get-error: ' . $e->getMessage());
            }
        } else {
            $line->recargo = isset($formData['recargo_' . $id]) ? (float)$formData['recargo_' . $id] : ($line->recargo ?? 0.0);
        }

        // mods
        foreach (self::$mods as $mod) {
            try {
                $mod->applyToLine($formData, $line, $id);
            } catch (\Throwable $e) {
                Tools::log()->warning('mod-applyToLine-error: ' . $e->getMessage());
            }
        }
    }

    private static function cantidad(Translator $i18n, string $idlinea, PurchaseDocumentLine $line, PurchaseDocument $model, string $jsFunc): string
    {
        if (false === $model->editable) {
            return '<div class="col-sm-2 col-lg-1 order-3">'
                . '<div class="d-lg-none mt-2 small">' . $i18n->trans('quantity') . '</div>'
                . '<div class="input-group input-group-sm">'
                . self::cantidadRestante($i18n, $line, $model)
                . '<input type="number" class="form-control form-control-sm text-lg-right border-0" value="' . $line->cantidad . '" disabled=""/>'
                . '</div>'
                . '</div>';
        }

        return '<div class="col-sm-2 col-lg-1 order-3">'
            . '<div class="d-lg-none mt-2 small">' . $i18n->trans('quantity') . '</div>'
            . '<div class="input-group input-group-sm">'
            . self::cantidadRestante($i18n, $line, $model)
            . '<input type="number" name="cantidad_' . $idlinea . '" value="' . $line->cantidad
            . '" class="form-control form-control-sm text-lg-right border-0 doc-line-qty" onkeyup="return ' . $jsFunc . '(\'recalculate-line\', \'0\', event);"/>'
            . '</div>'
            . '</div>';
    }

    private static function getFastLine(PurchaseDocument $model, array $formData): ?PurchaseDocumentLine
    {
        if (empty($formData['fastli'])) {
            return $model->getNewLine();
        }

        // buscamos el código de barras en las variantes
        $variantModel = new Variante();
        $whereBarcode = [new DataBaseWhere('codbarras', $formData['fastli'])];
        try {
            foreach ($variantModel->all($whereBarcode) as $variante) {
                return $model->getNewProductLine($variante->referencia);
            }
        } catch (\Throwable $e) {
            Tools::log()->warning('variant-search-error: ' . $e->getMessage());
        }

        // buscamos el código de barras con los mods
        foreach (self::$mods as $mod) {
            try {
                $line = $mod->getFastLine($model, $formData);
                if ($line) {
                    return $line;
                }
            } catch (\Throwable $e) {
                Tools::log()->warning('mod-getFastLine-error: ' . $e->getMessage());
            }
        }

        Tools::log()->warning('product-not-found', ['%ref%' => $formData['fastli']]);
        return null;
    }

    private static function precio(Translator $i18n, string $idlinea, PurchaseDocumentLine $line, PurchaseDocument $model, string $jsFunc): string
    {
        if (false === $model->editable) {
            return '<div class="col-sm col-lg-1 order-4">'
                . '<div class="d-lg-none mt-2 small">' . $i18n->trans('price') . '</div>'
                . '<input type="number" value="' . $line->pvpunitario . '" class="form-control form-control-sm text-lg-right border-0" disabled=""/>'
                . '</div>';
        }

        $attributes = 'name="pvpunitario_' . $idlinea . '" onkeyup="return ' . $jsFunc . '(\'recalculate-line\', \'0\', event);"';
        return '<div class="col-sm col-lg-1 order-4">'
            . '<div class="d-lg-none mt-2 small">' . $i18n->trans('price') . '</div>'
            . '<input type="number" ' . $attributes . ' value="' . $line->pvpunitario . '" class="form-control form-control-sm text-lg-right border-0"/>'
            . '</div>';
    }

    private static function renderField(Translator $i18n, string $idlinea, PurchaseDocumentLine $line, PurchaseDocument $model, string $field): ?string
    {
        foreach (self::$mods as $mod) {
            try {
                $html = $mod->renderField($i18n, $idlinea, $line, $model, $field);
                if ($html !== null) {
                    return $html;
                }
            } catch (\Throwable $e) {
                Tools::log()->warning('mod-renderField-error: ' . $e->getMessage());
            }
        }

        switch ($field) {
            case '_total':
                return self::lineTotal($i18n, $idlinea, $line, $model, 'purchasesLineTotalWithTaxes', 'purchasesLineTotalWithoutTaxes');

            case 'cantidad':
                return self::cantidad($i18n, $idlinea, $line, $model, 'purchasesFormActionWait');

            case 'codimpuesto':
                return self::codimpuesto($i18n, $idlinea, $line, $model, 'purchasesFormAction');

            case 'descripcion':
                return self::descripcion($i18n, $idlinea, $line, $model);

            case 'dtopor':
                return self::dtopor($i18n, $idlinea, $line, $model, 'purchasesFormActionWait');

            case 'dtopor2':
                return self::dtopor2($i18n, $idlinea, $line, $model, 'dtopor2', 'purchasesFormActionWait');

            case 'excepcioniva':
                return self::excepcioniva($i18n, $idlinea, $line, $model, 'excepcioniva', 'purchasesFormActionWait');

            case 'irpf':
                return self::irpf($i18n, $idlinea, $line, $model, 'purchasesFormAction');

            case 'pvpunitario':
                return self::precio($i18n, $idlinea, $line, $model, 'purchasesFormActionWait');

            case 'recargo':
                return self::recargo($i18n, $idlinea, $line, $model, 'purchasesFormActionWait');

            case 'referencia':
                return self::referencia($i18n, $idlinea, $line, $model);

            case 'suplido':
                return self::suplido($i18n, $idlinea, $line, $model, 'purchasesFormAction');
        }

        return null;
    }

    private static function renderLineModal(Translator $i18n, PurchaseDocumentLine $line, string $idlinea, PurchaseDocument $model): string
    {
        return '<div class="modal fade" id="lineModal-' . $idlinea . '" tabindex="-1" aria-labelledby="lineModal-' . $idlinea . 'Label" aria-hidden="true">'
            . '<div class="modal-dialog modal-dialog-centered">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title"><i class="fas fa-edit fa-fw" aria-hidden="true"></i> ' . Tools::noHtml($line->referencia ?? '') . '</h5>'
            . '<button type="button" class="close" data-dismiss="modal" aria-label="Close">'
            . '<span aria-hidden="true">&times;</span>'
            . '</button>'
            . '</div>'
            . '<div class="modal-body">'
            . '<div class="form-row">'
            . self::renderField($i18n, $idlinea, $line, $model, 'dtopor2')
            . self::renderField($i18n, $idlinea, $line, $model, 'recargo')
            . self::renderField($i18n, $idlinea, $line, $model, 'irpf')
            . self::renderField($i18n, $idlinea, $line, $model, 'excepcioniva')
            . self::renderField($i18n, $idlinea, $line, $model, 'suplido')
            . '</div>'
            . '<div class="form-row">'
            . self::renderNewModalFields($i18n, $idlinea, $line, $model)
            . '</div>'
            . '</div>'
            . '<div class="modal-footer">'
            . '<button type="button" class="btn btn-secondary" data-dismiss="modal">'
            . $i18n->trans('close')
            . '</button>'
            . '<button type="button" class="btn btn-primary" data-dismiss="modal">'
            . $i18n->trans('accept')
            . '</button>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    private static function renderNewModalFields(Translator $i18n, string $idlinea, PurchaseDocumentLine $line, PurchaseDocument $model): string
    {
        // cargamos los nuevos campos
        $newFields = [];
        foreach (self::$mods as $mod) {
            try {
                foreach ($mod->newModalFields() as $field) {
                    if (false === in_array($field, $newFields)) {
                        $newFields[] = $field;
                    }
                }
            } catch (\Throwable $e) {
                Tools::log()->warning('mod-newModalFields-error: ' . $e->getMessage());
            }
        }

        // renderizamos los campos
        $html = '';
        foreach ($newFields as $field) {
            foreach (self::$mods as $mod) {
                try {
                    $fieldHtml = $mod->renderField($i18n, $idlinea, $line, $model, $field);
                    if ($fieldHtml !== null) {
                        $html .= $fieldHtml;
                        break;
                    }
                } catch (\Throwable $e) {
                    Tools::log()->warning('mod-renderField-newModal-error: ' . $e->getMessage());
                }
            }
        }
        return $html;
    }

    private static function renderNewFields(Translator $i18n, string $idlinea, PurchaseDocumentLine $line, PurchaseDocument $model): string
    {
        // cargamos los nuevos campos
        $newFields = [];
        foreach (self::$mods as $mod) {
            try {
                foreach ($mod->newFields() as $field) {
                    if (false === in_array($field, $newFields)) {
                        $newFields[] = $field;
                    }
                }
            } catch (\Throwable $e) {
                Tools::log()->warning('mod-newFields-error: ' . $e->getMessage());
            }
        }

        // renderizamos los campos
        $html = '';
        foreach ($newFields as $field) {
            foreach (self::$mods as $mod) {
                try {
                    $fieldHtml = $mod->renderField($i18n, $idlinea, $line, $model, $field);
                    if ($fieldHtml !== null) {
                        $html .= $fieldHtml;
                        break;
                    }
                } catch (\Throwable $e) {
                    Tools::log()->warning('mod-renderField-newField-error: ' . $e->getMessage());
                }
            }
        }
        return $html;
    }

    private static function renderNewTitles(Translator $i18n, PurchaseDocument $model): string
    {
        // cargamos los nuevos títulos
        $newFields = [];
        foreach (self::$mods as $mod) {
            try {
                foreach ($mod->newTitles() as $field) {
                    if (false === in_array($field, $newFields)) {
                        $newFields[] = $field;
                    }
                }
            } catch (\Throwable $e) {
                Tools::log()->warning('mod-newTitles-error: ' . $e->getMessage());
            }
        }

        // renderizamos los títulos
        $html = '';
        foreach ($newFields as $field) {
            foreach (self::$mods as $mod) {
                try {
                    $fieldHtml = $mod->renderTitle($i18n, $model, $field);
                    if ($fieldHtml !== null) {
                        $html .= $fieldHtml;
                        break;
                    }
                } catch (\Throwable $e) {
                    Tools::log()->warning('mod-renderTitle-newTitle-error: ' . $e->getMessage());
                }
            }
        }
        return $html;
    }

    private static function renderTitle(Translator $i18n, PurchaseDocument $model, string $field): ?string
    {
        foreach (self::$mods as $mod) {
            try {
                $html = $mod->renderTitle($i18n, $model, $field);
                if ($html !== null) {
                    return $html;
                }
            } catch (\Throwable $e) {
                Tools::log()->warning('mod-renderTitle-error: ' . $e->getMessage());
            }
        }

        switch ($field) {
            case '_actionsButton':
                return self::titleActionsButton($model);

            case '_total':
                return self::titleTotal($i18n);

            case 'cantidad':
                return self::titleCantidad($i18n);

            case 'codimpuesto':
                return self::titleCodimpuesto($i18n);

            case 'descripcion':
                return self::titleDescripcion($i18n);

            case 'dtopor':
                return self::titleDtopor($i18n);

            case 'pvpunitario':
                return self::titlePrecio($i18n);

            case 'referencia':
                return self::titleReferencia($i18n);
        }

        return null;
    }

    private static function renderTitles(Translator $i18n, PurchaseDocument $model): string
    {
        return '<div class="container-fluid d-none d-lg-block"><div class="form-row border-bottom">'
            . self::renderTitle($i18n, $model, 'referencia')
            . self::renderTitle($i18n, $model, 'descripcion')
            . self::renderTitle($i18n, $model, 'cantidad')
            . self::renderNewTitles($i18n, $model)
            . self::renderTitle($i18n, $model, 'pvpunitario')
            . self::renderTitle($i18n, $model, 'dtopor')
            . self::renderTitle($i18n, $model, 'codimpuesto')
            . self::renderTitle($i18n, $model, '_total')
            . self::renderTitle($i18n, $model, '_actionsButton')
            . '</div></div>';
    }
}