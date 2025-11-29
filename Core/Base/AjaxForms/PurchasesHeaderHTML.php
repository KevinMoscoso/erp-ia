<?php

namespace ERPIA\Core\Base\AjaxForms;

use ERPIA\Core\Base\Contract\PurchasesModInterface;
use ERPIA\Core\Base\Translator;
use ERPIA\Core\Model\Base\PurchaseDocument;
use ERPIA\Core\Model\User;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Proveedor;

class PurchasesHeaderHTML
{
    use CommonSalesPurchases;

    /** @var PurchasesModInterface[] */
    private static $mods = [];

    /**
     * @param PurchasesModInterface $mod
     */
    public static function addMod(PurchasesModInterface $mod)
    {
        foreach (self::$mods as $m) {
            if ($m === $mod) {
                return;
            }
        }
        self::$mods[] = $mod;
    }

    /**
     * Aplica datos del formulario al modelo.
     * Se permite que los módulos intervengan antes y después de la aplicación.
     * @param PurchaseDocument $model
     * @param array $formData
     * @param User $user
     */
    public static function apply(PurchaseDocument &$model, array $formData, User $user)
    {
        // Módulos: aplicar antes
        foreach (self::$mods as $mod) {
            try {
                $mod->applyBefore($model, $formData, $user);
            } catch (\Throwable $e) {
                Tools::log()->warning('mod-applyBefore-error: ' . $e->getMessage());
            }
        }

        $proveedor = new Proveedor();

        // Si es nuevo, establecer autor y posible sujeto inicial
        if (empty($model->primaryColumnValue())) {
            $model->setAuthor($user);

            if (!empty($formData['codproveedor']) && $proveedor->loadFromCode($formData['codproveedor'])) {
                $model->setSubject($proveedor);

                // Si la acción es solo seleccionar proveedor, salimos para evitar sobreescrituras
                if (empty($formData['action']) || $formData['action'] === 'set-supplier') {
                    return;
                }
            }
        } elseif (isset($formData['action'], $formData['codproveedor'])
            && $formData['action'] === 'set-supplier'
            && $proveedor->loadFromCode($formData['codproveedor'])) {
            // Cambio explícito de sujeto
            $model->setSubject($proveedor);
            return;
        }

        // Aplicación segura de campos con validación mínima
        try {
            if (isset($formData['codalmacen'])) {
                $model->setWarehouse($formData['codalmacen'] ?? $model->codalmacen);
            }

            if (isset($formData['cifnif'])) {
                $model->cifnif = is_string($formData['cifnif']) ? trim($formData['cifnif']) : $model->cifnif;
            }

            if (isset($formData['coddivisa'])) {
                $model->coddivisa = $formData['coddivisa'] ?? $model->coddivisa;
            }

            if (isset($formData['codpago'])) {
                $model->codpago = $formData['codpago'] ?? $model->codpago;
            }

            if (isset($formData['codproveedor'])) {
                $model->codproveedor = $formData['codproveedor'] ?? $model->codproveedor;
            }

            if (isset($formData['codserie'])) {
                $model->codserie = $formData['codserie'] ?? $model->codserie;
            }

            if (isset($formData['fecha'])) {
                $model->fecha = empty($formData['fecha']) ? $model->fecha : Tools::date($formData['fecha']);
            }

            if (isset($formData['femail'])) {
                $model->femail = !empty($formData['femail']) ? $formData['femail'] : $model->femail;
            }

            if (isset($formData['hora'])) {
                $model->hora = $formData['hora'] ?? $model->hora;
            }

            if (isset($formData['nombre'])) {
                $model->nombre = is_string($formData['nombre']) ? trim($formData['nombre']) : $model->nombre;
            }

            if (isset($formData['numproveedor'])) {
                $model->numproveedor = is_string($formData['numproveedor']) ? trim($formData['numproveedor']) : $model->numproveedor;
            }

            if (isset($formData['operacion'])) {
                $model->operacion = $formData['operacion'] ?? $model->operacion;
            }

            if (isset($formData['tasaconv'])) {
                $model->tasaconv = is_numeric($formData['tasaconv']) ? (float)$formData['tasaconv'] : $model->tasaconv;
            }

            // Campos que pueden ser nulos
            foreach (['fechadevengo'] as $key) {
                if (array_key_exists($key, $formData)) {
                    $model->{$key} = empty($formData[$key]) ? null : $formData[$key];
                }
            }
        } catch (\Throwable $e) {
            Tools::log()->warning('apply-fields-error: ' . $e->getMessage());
        }

        // Módulos: aplicar después
        foreach (self::$mods as $mod) {
            try {
                $mod->apply($model, $formData, $user);
            } catch (\Throwable $e) {
                Tools::log()->warning('mod-apply-error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Permite a los módulos registrar assets (CSS/JS).
     */
    public static function assets()
    {
        foreach (self::$mods as $mod) {
            try {
                $mod->assets();
            } catch (\Throwable $e) {
                Tools::log()->warning('mod-assets-error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Renderiza la cabecera completa (HTML).
     *
     * Mantiene la estructura y atributos esperados por la UI.
     *
     * @param PurchaseDocument $model
     * @return string
     */
    public static function render(PurchaseDocument $model): string
    {
        $i18n = new Translator();

        $html = '<div class="container-fluid">'
            . '<div class="form-row align-items-end">'
            . self::renderField($i18n, $model, 'codproveedor')
            . self::renderField($i18n, $model, 'codalmacen')
            . self::renderField($i18n, $model, 'codserie')
            . self::renderField($i18n, $model, 'fecha')
            . self::renderNewFields($i18n, $model)
            . self::renderField($i18n, $model, 'numproveedor')
            . self::renderField($i18n, $model, 'codpago')
            . self::renderField($i18n, $model, 'total')
            . '</div>'
            . '<div class="form-row align-items-end">'
            . self::renderField($i18n, $model, '_detail')
            . self::renderField($i18n, $model, '_parents')
            . self::renderField($i18n, $model, '_children')
            . self::renderField($i18n, $model, '_email')
            . self::renderNewBtnFields($i18n, $model)
            . self::renderField($i18n, $model, '_paid')
            . self::renderField($i18n, $model, 'idestado')
            . '</div>'
            . '</div>';

        return $html;
    }

    /**
     * Renderiza el control de selección del sujeto (proveedor).
     *
     * Conserva los atributos y hooks JS originales para compatibilidad.
     *
     * @param Translator $i18n
     * @param PurchaseDocument $model
     * @return string
     */
    private static function codproveedor(Translator $i18n, PurchaseDocument $model): string
    {
        $proveedor = new Proveedor();

        // Si no hay proveedor seleccionado, mostramos botón para abrir modal de búsqueda
        if (empty($model->codproveedor) || false === $proveedor->loadFromCode($model->codproveedor)) {
            return '<div class="col-sm-3">'
                . '<div class="form-group">' . $i18n->trans('supplier')
                . '<input type="hidden" name="codproveedor" />'
                . '<a href="#" id="btnFindSupplierModal" class="btn btn-block btn-primary" onclick="$(\'#findSupplierModal\').modal();'
                . ' $(\'#findSupplierInput\').focus(); return false;"><i class="fas fa-users fa-fw"></i> '
                . $i18n->trans('select') . '</a>'
                . '</div>'
                . '</div>'
                . self::detailModal($i18n, $model);
        }

        // Botón para editar proveedor si el documento es editable
        $btnProveedor = $model->editable
            ? '<button class="btn btn-outline-secondary" type="button" onclick="$(\'#findSupplierModal\').modal();'
                . ' $(\'#findSupplierInput\').focus(); return false;"><i class="fas fa-pen"></i></button>'
            : '<button class="btn btn-outline-secondary" type="button"><i class="fas fa-lock"></i></button>';

        $html = '<div class="col-sm-3 col-lg">'
            . '<div class="form-group">'
            . '<a href="' . $proveedor->url() . '">' . $i18n->trans('supplier') . '</a>'
            . '<input type="hidden" name="codproveedor" value="' . $model->codproveedor . '" />'
            . '<div class="input-group">'
            . '<input type="text" value="' . Tools::noHtml($proveedor->nombre) . '" class="form-control" readonly />'
            . '<div class="input-group-append">' . $btnProveedor . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';

        // Si es nuevo documento, mostrar detalle inline para completar datos
        if (empty($model->primaryColumnValue())) {
            $html .= self::detail($i18n, $model, true);
        }

        return $html;
    }

    /**
     * Renderiza el botón que abre el modal de detalle y el modal asociado.
     *
     * @param Translator $i18n
     * @param PurchaseDocument $model
     * @param bool $new
     * @return string
     */
    private static function detail(Translator $i18n, PurchaseDocument $model, bool $new = false): string
    {
        if (empty($model->primaryColumnValue()) && $new === false) {
            return '';
        }

        $css = $new ? 'col-sm-auto' : 'col-sm';

        return '<div class="' . $css . '">'
            . '<div class="form-group">'
            . '<button class="btn btn-outline-secondary" type="button" data-toggle="modal" data-target="#headerModal">'
            . '<i class="fas fa-edit fa-fw" aria-hidden="true"></i> ' . $i18n->trans('detail') . ' </button>'
            . '</div>'
            . '</div>'
            . self::detailModal($i18n, $model);
    }

    /**
     * Modal con campos de detalle de la cabecera.
     *
     * Conserva atributos ARIA y estructura esperada por la UI.
     *
     * @param Translator $i18n
     * @param PurchaseDocument $model
     * @return string
     */
    private static function detailModal(Translator $i18n, PurchaseDocument $model): string
    {
        return '<div class="modal fade" id="headerModal" tabindex="-1" aria-labelledby="headerModalLabel" aria-hidden="true">'
            . '<div class="modal-dialog modal-dialog-centered">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title">' . $i18n->trans('detail') . '</h5>'
            . '<button type="button" class="close" data-dismiss="modal" aria-label="Close">'
            . '<span aria-hidden="true">&times;</span>'
            . '</button>'
            . '</div>'
            . '<div class="modal-body">'
            . '<div class="form-row">'
            . self::renderField($i18n, $model, 'nombre')
            . self::renderField($i18n, $model, 'cifnif')
            . self::renderField($i18n, $model, 'fechadevengo')
            . self::renderField($i18n, $model, 'hora')
            . self::renderField($i18n, $model, 'operacion')
            . self::renderField($i18n, $model, 'femail')
            . self::renderField($i18n, $model, 'coddivisa')
            . self::renderField($i18n, $model, 'tasaconv')
            . self::renderField($i18n, $model, 'user')
            . self::renderNewModalFields($i18n, $model)
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

    /**
     * Campo nombre (razón social / nombre del proveedor).
     *
     * @param Translator $i18n
     * @param PurchaseDocument $model
     * @return string
     */
    private static function nombre(Translator $i18n, PurchaseDocument $model): string
    {
        $attributes = $model->editable ? 'name="nombre" required=""' : 'disabled=""';
        return '<div class="col-sm-6">'
            . '<div class="form-group">' . $i18n->trans('business-name')
            . '<input type="text" ' . $attributes . ' value="' . Tools::noHtml($model->nombre) . '" class="form-control" maxlength="100" autocomplete="off" />'
            . '</div>'
            . '</div>';
    }

    /**
     * Campo número de proveedor (opcional).
     *
     * @param Translator $i18n
     * @param PurchaseDocument $model
     * @return string
     */
    private static function numproveedor(Translator $i18n, PurchaseDocument $model): string
    {
        $attributes = $model->editable ? 'name="numproveedor"' : 'disabled=""';
        if (empty($model->codproveedor)) {
            return '';
        }
        return '<div class="col-sm-3 col-md-2 col-lg">'
            . '<div class="form-group">' . $i18n->trans('numsupplier')
            . '<input type="text" ' . $attributes . ' value="' . Tools::noHtml($model->numproveedor) . '" class="form-control" maxlength="50"'
            . ' placeholder="' . $i18n->trans('optional') . '" />'
            . '</div>'
            . '</div>';
    }

    /**
     * Renderiza un campo delegando primero a los módulos registrados.
     *
     * Si ningún módulo devuelve HTML, se usa la implementación por defecto.
     *
     * @param Translator $i18n
     * @param PurchaseDocument $model
     * @param string $field
     * @return string|null
     */
    private static function renderField(Translator $i18n, PurchaseDocument $model, string $field): ?string
    {
        // Delegación a módulos
        foreach (self::$mods as $mod) {
            try {
                $html = $mod->renderField($i18n, $model, $field);
                if ($html !== null) {
                    return $html;
                }
            } catch (\Throwable $e) {
                Tools::log()->warning('mod-renderField-error: ' . $e->getMessage());
            }
        }

        // Fallback a implementación por defecto (métodos del trait CommonSalesPurchases)
        switch ($field) {
            case '_children':
                return self::children($i18n, $model);
            case '_detail':
                return self::detail($i18n, $model);
            case '_email':
                return self::email($i18n, $model);
            case '_fecha':
                return self::fecha($i18n, $model, false);
            case '_paid':
                return self::paid($i18n, $model, 'purchasesFormSave');
            case '_parents':
                return self::parents($i18n, $model);
            case 'cifnif':
                return self::cifnif($i18n, $model);
            case 'codalmacen':
                return self::codalmacen($i18n, $model, 'purchasesFormAction');
            case 'coddivisa':
                return self::coddivisa($i18n, $model);
            case 'codpago':
                return self::codpago($i18n, $model);
            case 'codproveedor':
                return self::codproveedor($i18n, $model);
            case 'codserie':
                return self::codserie($i18n, $model, 'purchasesFormAction');
            case 'fecha':
                return self::fecha($i18n, $model);
            case 'fechadevengo':
                return self::fechadevengo($i18n, $model);
            case 'femail':
                return self::femail($i18n, $model);
            case 'hora':
                return self::hora($i18n, $model);
            case 'idestado':
                return self::idestado($i18n, $model, 'purchasesFormSave');
            case 'nombre':
                return self::nombre($i18n, $model);
            case 'numproveedor':
                return self::numproveedor($i18n, $model);
            case 'operacion':
                return self::operacion($i18n, $model);
            case 'tasaconv':
                return self::tasaconv($i18n, $model);
            case 'total':
                return self::total($i18n, $model, 'purchasesFormSave');
            case 'user':
                return self::user($i18n, $model);
        }

        return null;
    }

    /**
     * Renderiza botones adicionales aportados por módulos (zona de botones).
     *
     * @param Translator $i18n
     * @param PurchaseDocument $model
     * @return string
     */
    private static function renderNewBtnFields(Translator $i18n, PurchaseDocument $model): string
    {
        $newFields = [];
        foreach (self::$mods as $mod) {
            try {
                foreach ($mod->newBtnFields() as $field) {
                    if (false === in_array($field, $newFields)) {
                        $newFields[] = $field;
                    }
                }
            } catch (\Throwable $e) {
                Tools::log()->warning('mod-newBtnFields-error: ' . $e->getMessage());
            }
        }

        $html = '';
        foreach ($newFields as $field) {
            foreach (self::$mods as $mod) {
                try {
                    $fieldHtml = $mod->renderField(new Translator(), $model, $field);
                    if ($fieldHtml !== null) {
                        $html .= $fieldHtml;
                        break;
                    }
                } catch (\Throwable $e) {
                    Tools::log()->warning('mod-renderField-newBtn-error: ' . $e->getMessage());
                }
            }
        }

        return $html;
    }

    /**
     * Renderiza campos adicionales del pie aportados por módulos.
     *
     * @param Translator $i18n
     * @param PurchaseDocument $model
     * @return string
     */
    private static function renderNewFields(Translator $i18n, PurchaseDocument $model): string
    {
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

        $html = '';
        foreach ($newFields as $field) {
            foreach (self::$mods as $mod) {
                try {
                    $fieldHtml = $mod->renderField($i18n, $model, $field);
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

    /**
     * Renderiza campos adicionales que se mostrarán dentro del modal de detalle.
     *
     * @param Translator $i18n
     * @param PurchaseDocument $model
     * @return string
     */
    private static function renderNewModalFields(Translator $i18n, PurchaseDocument $model): string
    {
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

        $html = '';
        foreach ($newFields as $field) {
            foreach (self::$mods as $mod) {
                try {
                    $fieldHtml = $mod->renderField($i18n, $model, $field);
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
}