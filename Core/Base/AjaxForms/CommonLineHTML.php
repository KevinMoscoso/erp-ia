<?php
/**
 * Este archivo es parte de nuestro sistema ERP
 * Desarrollado para gestión de documentos comerciales
 */

namespace ERPIA\Core\Base\FormulariosAjax;

use ERPIA\Core\Base\Traductor;
use ERPIA\Core\Base\BaseDeDatos\DondeBaseDeDatos;
use ERPIA\Core\FuenteDatos\Impuestos;
use ERPIA\Core\FuenteDatos\Retenciones;
use ERPIA\Core\FuenteDatos\Series;
use ERPIA\Core\Lib\TipoProducto;
use ERPIA\Core\Lib\RegimenIVA;
use ERPIA\Core\Modelo\Base\DocumentoComercial;
use ERPIA\Core\Modelo\Base\LineaDocumentoComercial;
use ERPIA\Core\Modelo\Base\DocumentoTransformador;
use ERPIA\Dinamic\Modelo\Stock;
use ERPIA\Dinamic\Modelo\Variante;

/**
 * Trait LineaHTMLComun
 * 
 * Genera la interfaz visual para las líneas de documentos comerciales
 */
trait LineaHTMLComun
{
    /** @var string */
    protected static $vistaColumna;

    /** @var int */
    protected static $contador = 0;

    /** @var int */
    protected static $totalLineas = 0;

    /** @var string */
    protected static $regimenIva;

    /** @var array */
    private static $variantes = [];

    /** @var array */
    private static $stocks = [];

    private static function cantidadPendiente(Traductor $i18n, LineaDocumentoComercial $linea, DocumentoTransformador $modelo): string
    {
        if ($linea->servido <= 0 || $modelo->editable === false) {
            return '';
        }

        $pendiente = $linea->cantidad - $linea->servido;
        return '<div class="input-group-prepend" title="' . $i18n->trans('quantity-remaining') . '">'
            . '<a href="UnirDocumentos?modelo=' . $modelo->claseModelo() . '&codigos=' . $modelo->valorColumnaPrincipal()
            . '" class="btn btn-outline-secondary" type="button">' . $pendiente . '</a>'
            . '</div>';
    }

    private static function impuesto(Traductor $i18n, string $idLinea, LineaDocumentoComercial $linea, DocumentoTransformador $modelo, string $funcionJS): string
    {
        if (!isset(self::$regimenIva)) {
            self::$regimenIva = $modelo->getSujeto()->regimeniva;
        }

        $opciones = ['<option value="">------</option>'];
        foreach (Impuestos::todos() as $impuesto) {
            if (!$impuesto->activo && $linea->codimpuesto != $impuesto->codimpuesto) {
                continue;
            }

            $opciones[] = $linea->codimpuesto == $impuesto->codimpuesto ?
                '<option value="' . $impuesto->codimpuesto . '" selected>' . $impuesto->descripcion . '</option>' :
                '<option value="' . $impuesto->codimpuesto . '">' . $impuesto->descripcion . '</option>';
        }

        $editable = $modelo->editable && self::$regimenIva != RegimenIVA::SISTEMA_EXENTO
            && Series::obtener($modelo->codserie)->siniva == false && $linea->suplido == false;

        $atributos = $editable ?
            'name="codimpuesto_' . $idLinea . '" onchange="return ' . $funcionJS . '(\'recalcular-linea\', \'0\');"' :
            'disabled=""';
        
        return '<div class="col-sm col-lg-1 order-6">'
            . '<div class="d-lg-none mt-3 small">' . $i18n->trans('tax') . '</div>'
            . '<select ' . $atributos . ' class="form-control form-control-sm border-0">' . implode('', $opciones) . '</select>'
            . '<input type="hidden" name="iva_' . $idLinea . '" value="' . $linea->iva . '"/>'
            . '</div>';
    }

    private static function descripcion(Traductor $i18n, string $idLinea, LineaDocumentoComercial $linea, DocumentoTransformador $modelo): string
    {
        $atributos = $modelo->editable ? 'name="descripcion_' . $idLinea . '"' : 'disabled=""';

        $filas = 0;
        foreach (explode("\n", $linea->descripcion) as $lineaDesc) {
            $filas += mb_strlen($lineaDesc) < 90 ? 1 : ceil(mb_strlen($lineaDesc) / 90);
        }

        $columnaMd = empty($linea->referencia) ? 12 : 8;
        $columnaSm = empty($linea->referencia) ? 10 : 8;
        
        return '<div class="col-sm-' . $columnaSm . ' col-md-' . $columnaMd . ' col-lg order-2">'
            . '<div class="d-lg-none mt-3 small">' . $i18n->trans('description') . '</div>'
            . '<textarea ' . $atributos . ' class="form-control form-control-sm border-0 desc-linea-doc" rows="' . $filas . '">'
            . $linea->descripcion . '</textarea></div>';
    }

    private static function descuentoPorcentaje(Traductor $i18n, string $idLinea, LineaDocumentoComercial $linea, DocumentoTransformador $modelo, string $funcionJS): string
    {
        $atributos = $modelo->editable ?
            'name="dtopor_' . $idLinea . '" min="0" max="100" step="1" onkeyup="return ' . $funcionJS . '(\'recalcular-linea\', \'0\', event);"' :
            'disabled=""';
        return '<div class="col-sm col-lg-1 order-5">'
            . '<div class="d-lg-none mt-3 small">' . $i18n->trans('percentage-discount') . '</div>'
            . '<input type="number" ' . $atributos . ' value="' . $linea->dtopor . '" class="form-control form-control-sm text-lg-center border-0"/>'
            . '</div>';
    }

    private static function descuentoPorcentaje2(Traductor $i18n, string $idLinea, LineaDocumentoComercial $linea, DocumentoTransformador $modelo, string $campo, string $funcionJS): string
    {
        $atributos = $modelo->editable ?
            'name="' . $campo . '_' . $idLinea . '" min="0" max="100" step="1" onkeyup="return ' . $funcionJS . '(\'recalcular-linea\', \'0\', event);"' :
            'disabled=""';
        return '<div class="col-6">'
            . '<div class="mb-2">' . $i18n->trans('percentage-discount') . ' 2'
            . '<input type="number" ' . $atributos . ' value="' . $linea->{$campo} . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    private static function excepcionIVA(Traductor $i18n, string $idLinea, LineaDocumentoComercial $linea, DocumentoTransformador $modelo, string $campo, string $funcionJS): string
    {
        $atributos = $modelo->editable ?
            'name="excepcioniva_' . $idLinea . '" onchange="return ' . $funcionJS . '(\'recalcular-linea\', \'0\');"' :
            'disabled=""';

        $opciones = '<option value="" selected>------</option>';
        $producto = $linea->getProducto();
        $excepcionIva = empty($linea->idlinea) && empty($linea->{$campo}) ? $producto->{$campo} : $linea->{$campo};

        foreach (RegimenIVA::todasExcepciones() as $clave => $valor) {
            $seleccionado = $excepcionIva === $clave ? 'selected' : '';
            $opciones .= '<option value="' . $clave . '" ' . $seleccionado . '>' . $i18n->trans($valor) . '</option>';
        }

        return '<div class="col-6">'
            . '<div class="mb-2">' . $i18n->trans('vat-exception')
            . '<select ' . $atributos . ' class="form-control">' . $opciones . '</select>'
            . '</div>'
            . '</div>';
    }

    private static function booleanoGenerico(Traductor $i18n, string $idLinea, LineaDocumentoComercial $linea, DocumentoTransformador $modelo, string $campo, string $etiqueta): string
    {
        $atributos = $modelo->editable ? 'name="' . $campo . '_' . $idLinea . '"' : 'disabled=""';
        $opciones = $linea->{$campo} ?
            ['<option value="0">' . $i18n->trans('no') . '</option>', '<option value="1" selected>' . $i18n->trans('yes') . '</option>'] :
            ['<option value="0" selected>' . $i18n->trans('no') . '</option>', '<option value="1">' . $i18n->trans('yes') . '</option>'];
        return '<div class="col-6">'
            . '<div class="mb-2">' . $i18n->trans($etiqueta)
            . '<select ' . $atributos . ' class="form-control">' . implode('', $opciones) . '</select>'
            . '</div>'
            . '</div>';
    }

    private static function retencionIRPF(Traductor $i18n, string $idLinea, LineaDocumentoComercial $linea, DocumentoTransformador $modelo, string $funcionJS): string
    {
        $opciones = ['<option value="">------</option>'];
        foreach (Retenciones::todas() as $retencion) {
            if (!$retencion->activa && $linea->irpf != $retencion->porcentaje) {
                continue;
            }

            $opciones[] = $linea->irpf === $retencion->porcentaje ?
                '<option value="' . $retencion->porcentaje . '" selected>' . $retencion->descripcion . '</option>' :
                '<option value="' . $retencion->porcentaje . '">' . $retencion->descripcion . '</option>';
        }

        $atributos = $modelo->editable && $linea->suplido === false ?
            'name="irpf_' . $idLinea . '" onchange="return ' . $funcionJS . '(\'recalcular-linea\', \'0\', event);"' :
            'disabled=""';
        return '<div class="col-6">'
            . '<div class="mb-2"><a href="ListaImpuesto?pestaniaActiva=ListaRetencion">' . $i18n->trans('retention') . '</a>'
            . '<select ' . $atributos . ' class="form-control">' . implode('', $opciones) . '</select>'
            . '</div>'
            . '</div>';
    }

    private static function totalLinea(Traductor $i18n, string $idLinea, LineaDocumentoComercial $linea, DocumentoTransformador $modelo, string $jsSubtotal, string $jsNeto): string
    {
        if ('subtotal' === self::$vistaColumna) {
            $cssSubtotal = '';
            $cssNeto = 'd-none';
        } else {
            $cssSubtotal = 'd-none';
            $cssNeto = '';
        }

        $onclickSubtotal = $modelo->editable ?
            ' onclick="' . $jsSubtotal . '(\'' . $idLinea . '\')"' :
            '';

        $onclickNeto = $modelo->editable ?
            ' onclick="' . $jsNeto . '(\'' . $idLinea . '\')"' :
            '';

        $subtotal = self::valorSubtotal($linea, $modelo);
        return '<div class="col col-lg-1 order-7 columnaSubtotal ' . $cssSubtotal . '">'
            . '<div class="d-lg-none mt-2 small">' . $i18n->trans('subtotal') . '</div>'
            . '<input type="number" name="totalLinea_' . $idLinea . '"  value="' . number_format($subtotal, FS_NF0, '.', '')
            . '" class="form-control form-control-sm text-lg-right border-0"' . $onclickSubtotal . ' readonly/></div>'
            . '<div class="col col-lg-1 order-7 columnaNeto ' . $cssNeto . '">'
            . '<div class="d-lg-none mt-2 small">' . $i18n->trans('net') . '</div>'
            . '<input type="number" name="netoLinea_' . $idLinea . '"  value="' . number_format($linea->pvptotal, FS_NF0, '.', '')
            . '" class="form-control form-control-sm text-lg-right border-0"' . $onclickNeto . ' readonly/></div>';
    }

    private static function cargarProductos(array $lineas, DocumentoComercial $modelo): void
    {
        $referencias = [];
        foreach ($lineas as $linea) {
            if (!empty($linea->referencia)) {
                $referencias[] = $linea->referencia;
            }
        }
        if (empty($referencias)) {
            return;
        }

        $modeloVariante = new Variante();
        $donde = [new DondeBaseDeDatos('referencia', $referencias, 'IN')];
        foreach ($modeloVariante->all($donde, [], 0, 0) as $variante) {
            self::$variantes[$variante->referencia] = $variante;
        }

        $modeloStock = new Stock();
        $donde = [
            new DondeBaseDeDatos('codalmacen', $modelo->codalmacen),
            new DondeBaseDeDatos('referencia', $referencias, 'IN'),
        ];
        foreach ($modeloStock->all($donde, [], 0, 0) as $stock) {
            self::$stocks[$stock->referencia] = $stock;
        }
    }

    private static function recargo(Traductor $i18n, string $idLinea, LineaDocumentoComercial $linea, DocumentoTransformador $modelo, string $funcionJS): string
    {
        if (!isset(self::$regimenIva)) {
            self::$regimenIva = $modelo->getSujeto()->regimeniva;
        }

        $editable = $modelo->editable
            && $linea->suplido === false
            && Series::obtener($modelo->codserie)->siniva === false
            && (self::$regimenIva === RegimenIVA::SISTEMA_RECARGO || $modelo->getEmpresa()->regimeniva === RegimenIVA::SISTEMA_RECARGO);

        $atributos = $editable ?
            'name="recargo_' . $idLinea . '" min="0" max="100" step="1" onkeyup="return ' . $funcionJS . '(\'recalcular-linea\', \'0\', event);"' :
            'disabled=""';
        return '<div class="col-6">'
            . '<div class="mb-2"><a href="ListaImpuesto">' . $i18n->trans('percentage-surcharge') . '</a>'
            . '<input type="number" ' . $atributos . ' value="' . $linea->recargo . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    private static function referencia(Traductor $i18n, string $idLinea, LineaDocumentoComercial $linea, DocumentoTransformador $modelo): string
    {
        $ordenable = $modelo->editable ?
            '<input type="hidden" name="orden_' . $idLinea . '" value="' . $linea->orden . '"/>' :
            '';
        $numeroLinea = self::$totalLineas > 10 ? self::$contador . '. ' : '';

        if (empty($linea->referencia)) {
            return '<div class="col-sm-2 col-lg-1 order-1">' . $ordenable . '<div class="small text-break">' . $numeroLinea . '</div></div>';
        }

        $enlace = isset(self::$variantes[$linea->referencia]) ?
            $numeroLinea . '<a href="' . self::$variantes[$linea->referencia]->url() . '" target="_blank">' . $linea->referencia . '</a>' :
            $linea->referencia;

        return '<div class="col-sm-2 col-lg-1 order-1">'
            . '<div class="small text-break"><div class="d-lg-none mt-2 text-truncate">' . $i18n->trans('reference') . '</div>'
            . $ordenable . $enlace . '<input type="hidden" name="referencia_' . $idLinea . '" value="' . $linea->referencia . '"/>'
            . '</div>'
            . '</div>';
    }

    private static function botonExpandir(Traductor $i18n, string $idLinea, DocumentoTransformador $modelo, string $nombreJS): string
    {
        if ($modelo->editable) {
            return '<div class="col-auto order-9">'
                . '<button type="button" data-toggle="modal" data-target="#modalLinea-' . $idLinea . '" class="btn btn-sm btn-light mr-2" title="'
                . $i18n->trans('more') . '"><i class="fas fa-ellipsis-h"></i></button>'
                . '<button class="btn btn-sm btn-danger btn-spin-action" type="button" title="' . $i18n->trans('delete') . '"'
                . ' onclick="return ' . $nombreJS . '(\'eliminar-linea\', \'' . $idLinea . '\');">'
                . '<i class="fas fa-trash-alt"></i></button>'
                . '</div>';
        }

        return '<div class="col-auto order-9"><button type="button" data-toggle="modal" data-target="#modalLinea-'
            . $idLinea . '" class="btn btn-sm btn-outline-secondary" title="'
            . $i18n->trans('more') . '"><i class="fas fa-ellipsis-h"></i></button></div>';
    }

    private static function valorSubtotal(LineaDocumentoComercial $linea, DocumentoTransformador $modelo): float
    {
        if ($modelo->columnaSujeto() === 'codcliente'
            && $modelo->getEmpresa()->regimeniva === RegimenIVA::SISTEMA_BIENES_USADOS
            && $linea->getProducto()->tipo === TipoProducto::SEGUNDA_MANO) {
            $beneficio = $linea->pvpunitario - $linea->coste;
            $impuesto = $beneficio * ($linea->iva + $linea->recargo - $linea->irpf) / 100;
            return ($linea->coste + $beneficio + $impuesto) * $linea->cantidad;
        }

        return $linea->pvptotal * (100 + $linea->iva + $linea->recargo - $linea->irpf) / 100;
    }

    private static function suplido(Traductor $i18n, string $idLinea, LineaDocumentoComercial $linea, DocumentoTransformador $modelo, string $funcionJS): string
    {
        $atributos = $modelo->editable ?
            'name="suplido_' . $idLinea . '" onchange="return ' . $funcionJS . '(\'recalcular-linea\', \'0\', event);"' :
            'disabled=""';
        $opciones = $linea->suplido ?
            ['<option value="0">' . $i18n->trans('no') . '</option>', '<option value="1" selected>' . $i18n->trans('yes') . '</option>'] :
            ['<option value="0" selected>' . $i18n->trans('no') . '</option>', '<option value="1">' . $i18n->trans('yes') . '</option>'];
        return '<div class="col-6">'
            . '<div class="mb-2">' . $i18n->trans('supplied')
            . '<select ' . $atributos . ' class="form-control">' . implode('', $opciones) . '</select>'
            . '</div>'
            . '</div>';
    }

    private static function tituloBotonAcciones(DocumentoTransformador $modelo): string
    {
        $ancho = $modelo->editable ? 68 : 32;
        return '<div class="col-lg-auto order-8"><div style="min-width: ' . $ancho . 'px;"></div></div>';
    }

    private static function tituloCantidad(Traductor $i18n): string
    {
        return '<div class="col-lg-1 text-right order-3">' . $i18n->trans('quantity') . '</div>';
    }

    private static function tituloImpuesto(Traductor $i18n): string
    {
        return '<div class="col-lg-1 order-6"><a href="ListaImpuesto">' . $i18n->trans('tax') . '</a></div>';
    }

    private static function tituloDescripcion(Traductor $i18n): string
    {
        return '<div class="col-lg order-2">' . $i18n->trans('description') . '</div>';
    }

    private static function tituloDescuentoPorcentaje(Traductor $i18n): string
    {
        return '<div class="col-lg-1 text-center order-5">' . $i18n->trans('percentage-discount') . '</div>';
    }

    private static function tituloPrecio(Traductor $i18n): string
    {
        return '<div class="col-lg-1 text-right order-4">' . $i18n->trans('price') . '</div>';
    }

    private static function tituloReferencia(Traductor $i18n): string
    {
        return '<div class="col-lg-1 order-1">' . $i18n->trans('reference') . '</div>';
    }

    private static function tituloTotal(Traductor $i18n): string
    {
        if ('subtotal' === self::$vistaColumna) {
            $cssSubtotal = '';
            $cssNeto = 'd-none';
        } else {
            $cssSubtotal = 'd-none';
            $cssNeto = '';
        }

        return '<div class="col-lg-1 text-right order-7 columnaSubtotal ' . $cssSubtotal . '">' . $i18n->trans('subtotal') . '</div>'
            . '<div class="col-lg-1 text-right order-7 columnaNeto ' . $cssNeto . '">' . $i18n->trans('net') . '</div>';
    }
}