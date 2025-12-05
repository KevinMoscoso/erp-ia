<?php
/**
 * ERPIA - Encabezado para Documentos de Compras
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
use ERPIA\Core\Session;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Proveedor;

/**
 * Clase para generar el encabezado de formularios de documentos de compras.
 * 
 * @author ERPIA
 * @version 1.0
 */
class PurchasesHeaderHTML
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

        $supplier = new Proveedor();
        if (empty($model->id())) {
            $model->setAuthor(Session::user());
            if (!empty($formData['codproveedor']) && $supplier->load($formData['codproveedor'])) {
                $model->setSubject($supplier);
                if (empty($formData['action']) || $formData['action'] === 'set-supplier') {
                    return;
                }
            }
        } elseif (!empty($formData['action']) && !empty($formData['codproveedor']) &&
            $formData['action'] === 'set-supplier' &&
            $supplier->load($formData['codproveedor'])) {
            $model->setSubject($supplier);
            return;
        }

        $model->setWarehouse($formData['codalmacen'] ?? $model->codalmacen);
        $model->cifnif = $formData['cifnif'] ?? $model->cifnif;
        $model->coddivisa = $formData['coddivisa'] ?? $model->coddivisa;
        $model->codpago = $formData['codpago'] ?? $model->codpago;
        $model->codproveedor = $formData['codproveedor'] ?? $model->codproveedor;
        $model->codserie = $formData['codserie'] ?? $model->codserie;
        $model->fecha = empty($formData['fecha']) ? $model->fecha : Tools::date($formData['fecha']);
        $model->femail = !empty($formData['femail']) ? $formData['femail'] : $model->femail;
        $model->hora = $formData['hora'] ?? $model->hora;
        $model->nombre = $formData['nombre'] ?? $model->nombre;
        $model->numproveedor = $formData['numproveedor'] ?? $model->numproveedor;
        $model->operacion = $formData['operacion'] ?? $model->operacion;
        $model->tasaconv = (float)($formData['tasaconv'] ?? $model->tasaconv);

        if (isset($formData['fechadevengo'])) {
            $model->fechadevengo = empty($formData['fechadevengo']) ? null : $formData['fechadevengo'];
        }

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
     * Renderiza el encabezado completo del formulario.
     *
     * @param PurchaseDocument $model
     * @return string
     */
    public static function render(PurchaseDocument $model): string
    {
        return '<div class="container-fluid">'
            . '<div class="row g-2 align-items-end">'
            . self::renderField($model, 'codproveedor')
            . self::renderField($model, 'codalmacen')
            . self::renderField($model, 'codserie')
            . self::renderField($model, 'fecha')
            . self::renderAdditionalFields($model)
            . self::renderField($model, 'numproveedor')
            . self::renderField($model, 'codpago')
            . self::renderField($model, 'total')
            . '</div>'
            . '<div class="row g-2 align-items-end">'
            . self::renderField($model, '_detail')
            . self::renderField($model, '_parents')
            . self::renderField($model, '_children')
            . self::renderField($model, '_email')
            . self::renderAdditionalButtons($model)
            . self::renderField($model, '_paid')
            . self::renderField($model, 'idestado')
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el campo de selección de proveedor.
     *
     * @param PurchaseDocument $model
     * @return string
     */
    private static function codproveedor(PurchaseDocument $model): string
    {
        $supplier = new Proveedor();
        if (empty($model->codproveedor) || !$supplier->load($model->codproveedor)) {
            return '<div class="col-sm-6 col-md-4 col-lg-3">'
                . '<div class="mb-2">' . Tools::trans('supplier')
                . '<input type="hidden" name="codproveedor" />'
                . '<a href="#" id="btnFindSupplierModal" class="btn btn-primary w-100" onclick="$(\'#findSupplierModal\').modal(\'show\');'
                . ' $(\'#findSupplierInput\').focus(); return false;"><i class="fa-solid fa-users fa-fw"></i> '
                . Tools::trans('select') . '</a>'
                . '</div>'
                . '</div>'
                . self::detailModal($model);
        }

        $editButton = $model->editable
            ? '<button class="btn btn-outline-secondary" type="button" onclick="$(\'#findSupplierModal\').modal(\'show\');'
              . ' $(\'#findSupplierInput\').focus(); return false;"><i class="fa-solid fa-pen"></i></button>'
            : '<button class="btn btn-outline-secondary" type="button"><i class="fa-solid fa-lock"></i></button>';

        $html = '<div class="col-sm-6 col-md-4 col-lg">'
            . '<div class="mb-2">'
            . '<a href="' . $supplier->url() . '">' . Tools::trans('supplier') . '</a>'
            . '<input type="hidden" name="codproveedor" value="' . $model->codproveedor . '" />'
            . '<div class="input-group">'
            . '<input type="text" value="' . Tools::noHtml($supplier->nombre) . '" class="form-control" readonly />'
            . $editButton
            . '</div>'
            . '</div>'
            . '</div>';

        if (empty($model->id())) {
            $html .= self::detailSection($model, true);
        }

        return $html;
    }

    /**
     * Genera el botón de detalles del encabezado.
     *
     * @param PurchaseDocument $model
     * @param bool $isNew
     * @return string
     */
    private static function detailSection(PurchaseDocument $model, bool $isNew = false): string
    {
        if (empty($model->id()) && !$isNew) {
            return '';
        }

        $cssClass = $isNew ? 'col-sm-auto' : 'col-sm';
        return '<div class="' . $cssClass . '">'
            . '<div class="mb-2">'
            . '<button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#headerModal">'
            . '<i class="fa-solid fa-edit fa-fw" aria-hidden="true"></i> ' . Tools::trans('detail') . ' </button>'
            . '</div>'
            . '</div>'
            . self::detailModal($model);
    }

    /**
     * Genera el modal de detalles del encabezado.
     *
     * @param PurchaseDocument $model
     * @return string
     */
    private static function detailModal(PurchaseDocument $model): string
    {
        return '<div class="modal fade" id="headerModal" tabindex="-1" aria-labelledby="headerModalLabel" aria-hidden="true">'
            . '<div class="modal-dialog modal-dialog-centered">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title">' . Tools::trans('detail') . '</h5>'
            . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
            . '</div>'
            . '<div class="modal-body">'
            . '<div class="row g-2">'
            . self::renderField($model, 'nombre')
            . self::renderField($model, 'cifnif')
            . self::renderField($model, 'fechadevengo')
            . self::renderField($model, 'hora')
            . self::renderField($model, 'operacion')
            . self::renderField($model, 'femail')
            . self::renderField($model, 'coddivisa')
            . self::renderField($model, 'tasaconv')
            . self::renderField($model, 'user')
            . self::renderModalFields($model)
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
     * Genera el campo de nombre/razón social.
     *
     * @param PurchaseDocument $model
     * @return string
     */
    private static function nombre(PurchaseDocument $model): string
    {
        $attributes = $model->editable ? 'name="nombre" required' : 'disabled';
        return '<div class="col-sm-6">'
            . '<div class="mb-2">' . Tools::trans('business-name')
            . '<input type="text" ' . $attributes . ' value="' . Tools::noHtml($model->nombre) . '" class="form-control" maxlength="100" autocomplete="off"/>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el campo de número de proveedor.
     *
     * @param PurchaseDocument $model
     * @return string
     */
    private static function numproveedor(PurchaseDocument $model): string
    {
        if (empty($model->codproveedor)) {
            return '';
        }

        $attributes = $model->editable ? 'name="numproveedor"' : 'disabled';
        return '<div class="col-sm-6 col-md-4 col-lg">'
            . '<div class="mb-2">' . Tools::trans('numsupplier')
            . '<input type="text" ' . $attributes . ' value="' . Tools::noHtml($model->numproveedor) . '" class="form-control" maxlength="50"'
            . ' placeholder="' . Tools::trans('optional') . '" />'
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
            case '_children':
                return self::children($model);

            case '_detail':
                return self::detailSection($model);

            case '_email':
                return self::email($model);

            case '_fecha':
                return self::fecha($model, false);

            case '_paid':
                return self::paid($model);

            case '_parents':
                return self::parents($model);

            case 'cifnif':
                return self::cifnif($model);

            case 'codalmacen':
                return self::codalmacen($model, 'purchasesFormAction');

            case 'coddivisa':
                return self::coddivisa($model);

            case 'codpago':
                return self::codpago($model);

            case 'codproveedor':
                return self::codproveedor($model);

            case 'codserie':
                return self::codserie($model, 'purchasesFormAction');

            case 'fecha':
                return self::fecha($model);

            case 'fechadevengo':
                return self::fechadevengo($model);

            case 'femail':
                return self::femail($model);

            case 'hora':
                return self::hora($model);

            case 'idestado':
                return self::idestado($model, 'purchasesFormSave');

            case 'nombre':
                return self::nombre($model);

            case 'numproveedor':
                return self::numproveedor($model);

            case 'operacion':
                return self::operacion($model);

            case 'tasaconv':
                return self::tasaconv($model);

            case 'total':
                return self::total($model, 'purchasesFormSave');

            case 'user':
                return self::user($model);
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