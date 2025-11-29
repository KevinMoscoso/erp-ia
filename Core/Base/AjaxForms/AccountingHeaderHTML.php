<?php

namespace ERPIA\Core\Base\AjaxForms;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\DataSrc\Empresas;
use ERPIA\Core\Lib\CodePatterns;
use ERPIA\Core\Tools;
use ERPIA\Core\Translator;
use ERPIA\Dinamic\Model\Asiento;
use ERPIA\Dinamic\Model\ConceptoPartida;
use ERPIA\Dinamic\Model\Diario;
use ERPIA\Dinamic\Model\FacturaCliente;
use ERPIA\Dinamic\Model\FacturaProveedor;

/**
 * Description of AccountingHeaderHTML
 *
 * @deprecated replaced by Core/Lib/AjaxForms/AccountingHeaderHTML
 */
class AccountingHeaderHTML
{
    /**
     * Aplica los datos del formulario al modelo (manteniendo la firma).
     */
    public static function apply(Asiento &$model, array $formData)
    {
        $model->idempresa = $formData['idempresa'] ?? $model->idempresa;
        $model->setDate($formData['fecha'] ?? $model->fecha);
        $model->canal = $formData['canal'] ?? $model->canal;
        $model->concepto = $formData['concepto'] ?? $model->concepto;
        $model->iddiario = !empty($formData['iddiario']) ? $formData['iddiario'] : null;
        $model->documento = $formData['documento'] ?? $model->documento;
        $model->operacion = !empty($formData['operacion']) ? $formData['operacion'] : null;
    }

    /**
     * Renderiza el bloque superior (cabecera) del formulario contable.
     */
    public static function render(Asiento $model): string
    {
        $i18n = new Translator();

        $topRow = implode('', [
            static::idempresa($i18n, $model),
            static::fecha($i18n, $model),
            static::concepto($i18n, $model),
            static::documento($i18n, $model),
            static::diario($i18n, $model),
            static::canal($i18n, $model),
            static::operacion($i18n, $model),
        ]);

        return '<div class="container-fluid">'
            . '<div class="form-row">' . $topRow . '</div>'
            . '</div><br/>';
    }

    protected static function canal(Translator $i18n, Asiento $model): string
    {
        $attrs = $model->editable ? 'name="canal"' : 'disabled';
        return '<div class="col-sm-2 col-md">'
            . '<div class="form-group">' . $i18n->trans('channel')
            . '<input type="number" ' . $attrs . ' value="' . $model->canal . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    protected static function concepto(Translator $i18n, Asiento $model): string
    {
        $attrs = $model->editable ? 'name="concepto" autocomplete="off" required' : 'disabled';
        $value = Tools::noHtml($model->concepto);

        return '<div class="col-sm-6 col-md">'
            . '<div class="form-group">' . $i18n->trans('concept')
            . '<input type="text" list="concept-items" ' . $attrs . ' value="' . $value . '" class="form-control"/>'
            . '<datalist id="concept-items">' . static::getConceptItems($model) . '</datalist>'
            . '</div>'
            . '</div>';
    }

    protected static function documento(Translator $i18n, Asiento $model): string
    {
        if (empty($model->documento)) {
            return '';
        }

        $link = static::getDocumentLink($model);
        $docText = Tools::noHtml($model->documento);

        if ($link) {
            return '<div class="col-sm-3 col-md-2">'
                . '<div class="form-group">' . $i18n->trans('document')
                . '<div class="input-group">'
                . '<div class="input-group-prepend">'
                . '<a class="btn btn-outline-primary" href="' . $link . '"><i class="far fa-eye"></i></a>'
                . '</div>'
                . '<input type="text" value="' . $docText . '" class="form-control" readonly/>'
                . '</div>'
                . '</div>'
                . '</div>';
        }

        return '<div class="col-sm-3 col-md-2 mb-2">'
            . '<div class="form-group">' . $i18n->trans('document')
            . '<input type="text" value="' . $docText . '" class="form-control" readonly/>'
            . '</div></div>';
    }

    protected static function diario(Translator $i18n, Asiento $model): string
    {
        $options = '<option value="">------</option>';
        $modelDiario = new Diario();
        foreach ($modelDiario->all([], [], 0, 0) as $diario) {
            $selected = ($diario->iddiario === $model->iddiario) ? 'selected' : '';
            $options .= '<option value="' . $diario->iddiario . '" ' . $selected . '>' . $diario->descripcion . '</option>';
        }

        $attrs = $model->editable ? 'name="iddiario"' : 'disabled';
        return '<div class="col-sm-2 col-md">'
            . '<div class="form-group">' . $i18n->trans('daily')
            . '<select ' . $attrs . ' class="form-control">' . $options . '</select>'
            . '</div>'
            . '</div>';
    }

    protected static function fecha(Translator $i18n, Asiento $model): string
    {
        $attrs = $model->editable ? 'name="fecha" required' : 'disabled';
        $value = date('Y-m-d', strtotime($model->fecha));

        return '<div class="col-sm-3 col-md-2">'
            . '<div class="form-group">' . $i18n->trans('date')
            . '<input type="date" ' . $attrs . ' value="' . $value . '" class="form-control" />'
            . '</div>'
            . '</div>';
    }

    /**
     * Lista de sugerencias de concepto para el datalist.
     */
    private static function getConceptItems(Asiento $model): string
    {
        $result = '';
        $conceptModel = new ConceptoPartida();
        foreach ($conceptModel->all([], ['descripcion' => 'ASC']) as $concept) {
            $result .= '<option value="' . CodePatterns::trans($concept->descripcion, $model) . '">';
        }
        return $result;
    }

    /**
     * Genera las opciones de un select a partir de una lista.
     *
     * @param array $options
     * @param string $key
     * @param string $name
     * @param mixed $value
     */
    private static function getItems(array &$options, string $key, string $name, $value): string
    {
        $html = '';
        foreach ($options as $item) {
            $selected = ($item->{$key} == $value) ? ' selected ' : '';
            $html .= '<option value="' . $item->{$key} . '"' . $selected . '>' . $item->{$name} . '</option>';
        }
        return $html;
    }

    protected static function idempresa(Translator $i18n, Asiento $model): string
    {
        $companyList = Empresas::all();
        if (count($companyList) < 2) {
            return '<input type="hidden" name="idempresa" value=' . $model->idempresa . ' />';
        }

        $attrs = $model->primaryColumnValue() ? 'readonly' : 'required';

        return '<div class="col-sm-3 col-md-2">'
            . '<div class="form-group">' . $i18n->trans('company')
            . '<select name="idempresa" class="form-control" ' . $attrs . '>'
            . static::getItems($companyList, 'idempresa', 'nombre', $model->idempresa)
            . '</select>'
            . '</div>'
            . '</div>';
    }

    protected static function operacion(Translator $i18n, Asiento $model): string
    {
        $attrs = $model->editable ? 'name="operacion"' : 'disabled';
        return '<div class="col-sm-2 col-md">'
            . '<div class="form-group">' . $i18n->trans('operation')
            . '<select ' . $attrs . ' class="form-control">'
            . '<option value="">------</option>'
            . '<option value="A" ' . ($model->operacion === 'A' ? 'selected' : '') . '>' . $i18n->trans('opening-operation') . '</option>'
            . '<option value="C" ' . ($model->operacion === 'C' ? 'selected' : '') . '>' . $i18n->trans('closing-operation') . '</option>'
            . '<option value="R" ' . ($model->operacion === 'R' ? 'selected' : '') . '>' . $i18n->trans('regularization-operation') . '</option>'
            . '</select>'
            . '</div>'
            . '</div>';
    }

    /**
     * Devuelve el enlace a la factura de cliente o proveedor, si existe.
     */
    private static function getDocumentLink(Asiento $model): string
    {
        $where = [
            new DataBaseWhere('codigo', $model->documento),
            new DataBaseWhere('idasiento', $model->idasiento),
        ];

        $fc = new FacturaCliente();
        if ($fc->loadFromCode('', $where)) {
            return $fc->url();
        }

        $fp = new FacturaProveedor();
        if ($fp->loadFromCode('', $where)) {
            return $fp->url();
        }

        return '';
    }
}