<?php
namespace ERPIA\Core\Base\AjaxForms;

use ERPIA\Core\Base\Contract\SalesModInterface;
use ERPIA\Core\Base\Translator;
use ERPIA\Core\Model\Base\SalesDocument;
use ERPIA\Core\Model\User;
use ERPIA\Core\Tools;

class SalesFooterHTML
{
    use CommonSalesPurchases;

    /** @var SalesModInterface[] */
    private static $mods = [];

    public static function addMod(SalesModInterface $mod)
    {
        self::$mods[] = $mod;
    }

    public static function apply(SalesDocument &$model, array $formData, User $user)
    {
        // Ejecutar modificadores previos
        foreach (self::$mods as $modifier) {
            $modifier->applyBefore($model, $formData, $user);
        }

        // Configurar vista de columnas
        self::$columnView = $formData['columnView'] ?? Tools::settings('default', 'columnetosubtotal', 'subtotal');

        // Procesar campos del formulario
        if (isset($formData['dtopor1'])) {
            $model->dtopor1 = (float)$formData['dtopor1'];
        }
        if (isset($formData['dtopor2'])) {
            $model->dtopor2 = (float)$formData['dtopor2'];
        }
        if (!empty($formData['observaciones'])) {
            $model->observaciones = $formData['observaciones'];
        }

        // Ejecutar modificadores principales
        foreach (self::$mods as $modifier) {
            $modifier->apply($model, $formData, $user);
        }
    }

    public static function assets()
    {
        foreach (self::$mods as $modifier) {
            $modifier->assets();
        }
    }

    public static function render(SalesDocument $model): string
    {
        if (empty(self::$columnView)) {
            self::$columnView = Tools::settings('default', 'columnetosubtotal', 'subtotal');
        }

        if (empty($model->codcliente)) {
            return '';
        }

        $translator = new Translator();
        $output = '<div class="container-fluid mt-3">';
        
        // Primera fila de botones
        $output .= '<div class="form-row">';
        $output .= self::renderField($translator, $model, '_productBtn');
        $output .= self::renderField($translator, $model, '_newLineBtn');
        $output .= self::renderField($translator, $model, '_sortableBtn');
        $output .= self::renderField($translator, $model, '_fastLineInput');
        $output .= self::renderField($translator, $model, '_subtotalNetoBtn');
        $output .= '</div>';
        
        // Segunda fila de campos
        $output .= '<div class="form-row">';
        $output .= self::renderField($translator, $model, 'observaciones');
        $output .= self::renderNewFields($translator, $model);
        $output .= self::renderField($translator, $model, 'netosindto');
        $output .= self::renderField($translator, $model, 'dtopor1');
        $output .= self::renderField($translator, $model, 'dtopor2');
        $output .= self::renderField($translator, $model, 'neto');
        $output .= self::renderField($translator, $model, 'totaliva');
        $output .= self::renderField($translator, $model, 'totalrecargo');
        $output .= self::renderField($translator, $model, 'totalirpf');
        $output .= self::renderField($translator, $model, 'totalsuplidos');
        $output .= self::renderField($translator, $model, 'totalcoste');
        $output .= self::renderField($translator, $model, 'totalbeneficio');
        $output .= self::renderField($translator, $model, 'total');
        $output .= '</div>';
        
        // Tercera fila de acciones
        $output .= '<div class="form-row">';
        $output .= '<div class="col-auto">';
        $output .= self::renderField($translator, $model, '_deleteBtn');
        $output .= '</div>';
        $output .= '<div class="col text-right">';
        $output .= self::renderNewBtnFields($translator, $model);
        $output .= self::renderField($translator, $model, '_modalFooter');
        $output .= self::renderField($translator, $model, '_undoBtn');
        $output .= self::renderField($translator, $model, '_saveBtn');
        $output .= '</div>';
        $output .= '</div>';
        
        $output .= '</div>';
        return $output;
    }

    private static function modalFooter(Translator $i18n, SalesDocument $model): string
    {
        $modalFields = self::renderNewModalFields($i18n, $model);
        if (empty($modalFields)) {
            return '';
        }

        $button = '<button class="btn btn-outline-secondary mr-2" type="button" data-toggle="modal" data-target="#footerModal">';
        $button .= '<i class="fas fa-plus fa-fw" aria-hidden="true"></i>';
        $button .= '</button>';
        
        return $button . self::createModalHtml($i18n, $modalFields);
    }

    private static function createModalHtml(Translator $i18n, string $content): string
    {
        return '<div class="modal fade" id="footerModal" tabindex="-1" aria-labelledby="footerModalLabel" aria-hidden="true">'
            . '<div class="modal-dialog modal-dialog-centered modal-lg">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title">' . $i18n->trans('detail') . ' ' . $i18n->trans('footer') . '</h5>'
            . '<button type="button" class="close" data-dismiss="modal" aria-label="Close">'
            . '<span aria-hidden="true">&times;</span>'
            . '</button>'
            . '</div>'
            . '<div class="modal-body">'
            . '<div class="form-row">'
            . $content
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

    private static function renderField(Translator $i18n, SalesDocument $model, string $field): ?string
    {
        // Consultar módulos externos primero
        foreach (self::$mods as $modifier) {
            $fieldHtml = $modifier->renderField($i18n, $model, $field);
            if ($fieldHtml !== null) {
                return $fieldHtml;
            }
        }

        // Renderizar campos del core
        return self::renderCoreField($i18n, $model, $field);
    }

    private static function renderCoreField(Translator $i18n, SalesDocument $model, string $field): ?string
    {
        switch ($field) {
            case '_deleteBtn':
                return self::deleteBtn($i18n, $model, 'salesFormSave');

            case '_fastLineInput':
                return self::fastLineInput($i18n, $model, 'salesFastLine');

            case '_modalFooter':
                return self::modalFooter($i18n, $model);

            case '_newLineBtn':
                return self::newLineBtn($i18n, $model, 'salesFormAction');

            case '_productBtn':
                return self::productBtn($i18n, $model);

            case '_saveBtn':
                return self::saveBtn($i18n, $model, 'salesFormSave');

            case '_sortableBtn':
                return self::sortableBtn($i18n, $model);

            case '_subtotalNetoBtn':
                return self::subtotalNetoBtn($i18n);

            case '_undoBtn':
                return self::undoBtn($i18n, $model);

            case 'dtopor1':
                return self::dtopor1($i18n, $model, 'salesFormActionWait');

            case 'dtopor2':
                return self::dtopor2($i18n, $model, 'salesFormActionWait');

            case 'neto':
                return self::column($i18n, $model, 'neto', 'net', true);

            case 'netosindto':
                return self::netosindto($i18n, $model);

            case 'observaciones':
                return self::observaciones($i18n, $model);

            case 'total':
                return self::column($i18n, $model, 'total', 'total', true);

            case 'totalbeneficio':
                $level = Tools::settings('default', 'levelbenefitsales', 0);
                return self::column($i18n, $model, 'totalbeneficio', 'profits', true, $level);

            case 'totalcoste':
                $level = Tools::settings('default', 'levelcostsales', 0);
                return self::column($i18n, $model, 'totalcoste', 'total-cost', true, $level);

            case 'totalirpf':
                return self::column($i18n, $model, 'totalirpf', 'irpf', true);

            case 'totaliva':
                return self::column($i18n, $model, 'totaliva', 'taxes', true);

            case 'totalrecargo':
                return self::column($i18n, $model, 'totalrecargo', 're', true);

            case 'totalsuplidos':
                return self::column($i18n, $model, 'totalsuplidos', 'supplied-amount', true);
        }

        return null;
    }

    private static function renderNewBtnFields(Translator $i18n, SalesDocument $model): string
    {
        $buttonFields = [];
        foreach (self::$mods as $modifier) {
            $newFields = $modifier->newBtnFields();
            foreach ($newFields as $field) {
                if (!in_array($field, $buttonFields)) {
                    $buttonFields[] = $field;
                }
            }
        }

        $html = '';
        foreach ($buttonFields as $field) {
            foreach (self::$mods as $modifier) {
                $fieldHtml = $modifier->renderField($i18n, $model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }

    private static function renderNewFields(Translator $i18n, SalesDocument $model): string
    {
        $customFields = [];
        foreach (self::$mods as $modifier) {
            $newFields = $modifier->newFields();
            foreach ($newFields as $field) {
                if (!in_array($field, $customFields)) {
                    $customFields[] = $field;
                }
            }
        }

        $html = '';
        foreach ($customFields as $field) {
            foreach (self::$mods as $modifier) {
                $fieldHtml = $modifier->renderField($i18n, $model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }

    private static function renderNewModalFields(Translator $i18n, SalesDocument $model): string
    {
        $modalFields = [];
        foreach (self::$mods as $modifier) {
            $newFields = $modifier->newModalFields();
            foreach ($newFields as $field) {
                if (!in_array($field, $modalFields)) {
                    $modalFields[] = $field;
                }
            }
        }

        $html = '';
        foreach ($modalFields as $field) {
            foreach (self::$mods as $modifier) {
                $fieldHtml = $modifier->renderField($i18n, $model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }
}