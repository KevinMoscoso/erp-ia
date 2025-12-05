<?php
/**
 * ERPIA - Pie de Formulario para Documentos de Compras
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

use ERPIA\Core\Contract\PurchasesModInterface;
use ERPIA\Core\Model\Base\PurchaseDocument;
use ERPIA\Core\Tools;

/**
 * Clase para generar el pie de formulario de documentos de compras.
 * 
 * @author ERPIA
 * @version 1.0
 */
class PurchasesFooterHTML
{
    use CommonSalesPurchases;

    /** @var PurchasesModInterface[] */
    private static $modifiers = [];

    /**
     * Añade un modificador al sistema.
     *
     * @param PurchasesModInterface $modifier
     */
    public static function addModifier(PurchasesModInterface $modifier): void
    {
        self::$modifiers[] = $modifier;
    }

    /**
     * Aplica los datos del formulario al modelo.
     *
     * @param PurchaseDocument $model
     * @param array $formData
     */
    public static function apply(PurchaseDocument &$model, array $formData): void
    {
        foreach (self::$modifiers as $modifier) {
            $modifier->applyBefore($model, $formData);
        }

        self::$columnView = $formData['columnView'] ?? Tools::settings('default', 'columnetosubtotal', 'subtotal');

        $model->dtopor1 = isset($formData['dtopor1']) ? (float)$formData['dtopor1'] : $model->dtopor1;
        $model->dtopor2 = isset($formData['dtopor2']) ? (float)$formData['dtopor2'] : $model->dtopor2;
        $model->observaciones = $formData['observaciones'] ?? $model->observaciones;

        foreach (self::$modifiers as $modifier) {
            $modifier->apply($model, $formData);
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
     * Renderiza el pie de formulario completo.
     *
     * @param PurchaseDocument $model
     * @return string
     */
    public static function render(PurchaseDocument $model): string
    {
        if (empty(self::$columnView)) {
            self::$columnView = Tools::settings('default', 'columnetosubtotal', 'subtotal');
        }

        if (empty($model->codproveedor)) {
            return '';
        }

        return '<div class="container-fluid mt-3">'
            . '<div class="row g-2">'
            . self::renderField($model, '_productBtn')
            . self::renderField($model, '_newLineBtn')
            . self::renderField($model, '_sortableBtn')
            . self::renderField($model, '_fastLineInput')
            . self::renderField($model, '_subtotalNetoBtn')
            . '</div>'
            . '<div class="row g-2">'
            . self::renderField($model, 'observaciones')
            . self::renderAdditionalFields($model)
            . self::renderField($model, 'netosindto')
            . self::renderField($model, 'dtopor1')
            . self::renderField($model, 'dtopor2')
            . self::renderField($model, 'neto')
            . self::renderField($model, 'totaliva')
            . self::renderField($model, 'totalrecargo')
            . self::renderField($model, 'totalirpf')
            . self::renderField($model, 'total')
            . '</div>'
            . '<div class="row g-2">'
            . '<div class="col-auto">'
            . self::renderField($model, '_deleteBtn')
            . '</div>'
            . '<div class="col text-end">'
            . self::renderAdditionalButtons($model)
            . self::renderField($model, '_modalFooter')
            . self::renderField($model, '_undoBtn')
            . self::renderField($model, '_saveBtn')
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el botón y modal para campos adicionales del pie.
     *
     * @param PurchaseDocument $model
     * @return string
     */
    protected static function modalFooter(PurchaseDocument $model): string
    {
        $modalContent = self::renderModalFields($model);

        if (empty($modalContent)) {
            return '';
        }

        return '<button class="btn btn-outline-secondary me-2" type="button" data-bs-toggle="modal" data-bs-target="#footerModal">'
            . '<i class="fa-solid fa-plus fa-fw" aria-hidden="true"></i></button>'
            . self::modalFooterHtml($modalContent);
    }

    /**
     * Genera el HTML del modal del pie.
     *
     * @param string $modalContent
     * @return string
     */
    private static function modalFooterHtml(string $modalContent): string
    {
        return '<div class="modal fade" id="footerModal" tabindex="-1" aria-labelledby="footerModalLabel" aria-hidden="true">'
            . '<div class="modal-dialog modal-dialog-centered modal-lg">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title">' . Tools::trans('detail') . ' ' . Tools::trans('footer') . '</h5>'
            . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
            . '</div>'
            . '<div class="modal-body">'
            . '<div class="row g-2">'
            . $modalContent
            . '</div>'
            . '</div>'
            . '<div class="modal-footer">'
            . '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Tools::trans('close') . '</button>'
            . '<button type="button" class="btn btn-primary" data-bs-dismiss="modal">' . Tools::trans('accept') . '</button>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Renderiza un campo específico.
     *
     * @param PurchaseDocument $model
     * @param string $fieldName
     * @return string|null
     */
    private static function renderField(PurchaseDocument $model, string $fieldName): ?string
    {
        foreach (self::$modifiers as $modifier) {
            $html = $modifier->renderField($model, $fieldName);
            if ($html !== null) {
                return $html;
            }
        }

        switch ($fieldName) {
            case '_deleteBtn':
                return self::deleteBtn($model, 'purchasesFormSave');

            case '_fastLineInput':
                return self::fastLineInput($model, 'purchasesFastLine');

            case '_modalFooter':
                return self::modalFooter($model);

            case '_newLineBtn':
                return self::newLineBtn($model, 'purchasesFormAction');

            case '_productBtn':
                return self::productBtn($model);

            case '_saveBtn':
                return self::saveBtn($model, 'purchasesFormSave');

            case '_sortableBtn':
                return self::sortableBtn($model);

            case '_subtotalNetoBtn':
                return self::subtotalNetoBtn();

            case '_undoBtn':
                return self::undoBtn($model);

            case 'dtopor1':
                return self::dtopor1($model, 'purchasesFormActionWait');

            case 'dtopor2':
                return self::dtopor2($model, 'purchasesFormActionWait');

            case 'neto':
                return self::column($model, 'neto', 'net', true);

            case 'netosindto':
                return self::netosindto($model);

            case 'observaciones':
                return self::observaciones($model);

            case 'total':
                return self::column($model, 'total', 'total');

            case 'totalirpf':
                return self::column($model, 'totalirpf', 'irpf', true);

            case 'totaliva':
                return self::column($model, 'totaliva', 'taxes', true);

            case 'totalrecargo':
                return self::column($model, 'totalrecargo', 're', true);
        }

        return null;
    }

    /**
     * Renderiza botones adicionales de los modificadores.
     *
     * @param PurchaseDocument $model
     * @return string
     */
    private static function renderAdditionalButtons(PurchaseDocument $model): string
    {
        $buttonFields = [];
        foreach (self::$modifiers as $modifier) {
            foreach ($modifier->newBtnFields() as $field) {
                if (!in_array($field, $buttonFields)) {
                    $buttonFields[] = $field;
                }
            }
        }

        $html = '';
        foreach ($buttonFields as $field) {
            foreach (self::$modifiers as $modifier) {
                $fieldHtml = $modifier->renderField($model, $field);
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
     * @param PurchaseDocument $model
     * @return string
     */
    private static function renderAdditionalFields(PurchaseDocument $model): string
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
                $fieldHtml = $modifier->renderField($model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }

    /**
     * Renderiza campos modales de los modificadores.
     *
     * @param PurchaseDocument $model
     * @return string
     */
    private static function renderModalFields(PurchaseDocument $model): string
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
                $fieldHtml = $modifier->renderField($model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }

        return $html;
    }
}