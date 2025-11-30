<?php
/**
 * Este archivo es parte de nuestro sistema ERP
 * Desarrollado para gestión de documentos comerciales
 */

namespace ERPIA\Core\Base\AjaxForms;

use ERPIA\Core\Base\Traductor;
use ERPIA\Core\Base\Sesion;
use ERPIA\Core\Base\Herramientas;
use ERPIA\Core\FuenteDatos\Almacenes;
use ERPIA\Core\FuenteDatos\Divisas;
use ERPIA\Core\FuenteDatos\Empresas;
use ERPIA\Core\FuenteDatos\FormasPago;
use ERPIA\Core\FuenteDatos\Series;
use ERPIA\Core\Lib\OperacionFactura;
use ERPIA\Core\Modelo\Base\DocumentoComercial;
use ERPIA\Core\Modelo\Base\DocumentoTransformador;
use ERPIA\Dinamic\Modelo\EstadoDocumento;

/**
 * Trait CabeceraVentasCompras
 * 
 * Genera la interfaz visual para las cabeceras de documentos comerciales
 */
trait CabeceraVentasCompras
{
    /** @var string */
    protected static $vistaColumna;

    public static function verificarNivel(int $nivel): bool
    {
        $usuario = Sesion::usuario();

        if ($usuario->existe() === false) {
            return false;
        }

        if ($usuario->admin) {
            return true;
        }

        return $nivel <= $usuario->nivel;
    }

    protected static function cifnif(Traductor $i18n, DocumentoComercial $modelo): string
    {
        $atributos = $modelo->editable ? 'name="cifnif" maxlength="30" autocomplete="off"' : 'disabled';
        return '<div class="col-sm-6">'
            . '<div class="form-group">' . $i18n->trans('cifnif')
            . '<input type="text" ' . $atributos . ' value="' . Herramientas::sinHtml($modelo->cifnif) . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    protected static function documentosHijos(Traductor $i18n, DocumentoTransformador $modelo): string
    {
        if (empty($modelo->valorColumnaPrincipal())) {
            return '';
        }

        $hijos = $modelo->documentosHijos();
        switch (count($hijos)) {
            case 0:
                return '';

            case 1:
                return '<div class="col-sm-auto">'
                    . '<div class="form-group">'
                    . '<a href="' . $hijos[0]->url() . '" class="btn btn-block btn-info">'
                    . '<i class="fas fa-forward fa-fw" aria-hidden="true"></i> ' . $hijos[0]->descripcionPrincipal()
                    . '</a>'
                    . '</div>'
                    . '</div>';
        }

        return '<div class="col-sm-auto">'
            . '<div class="form-group">'
            . '<button class="btn btn-block btn-info" type="button" title="' . $i18n->trans('documents-generated')
            . '" data-toggle="modal" data-target="#modalHijos"><i class="fas fa-forward fa-fw" aria-hidden="true"></i> '
            . count($hijos) . ' </button>'
            . '</div>'
            . '</div>'
            . self::modalListaDocumentos($i18n, $hijos, 'documents-generated', 'modalHijos');
    }

    protected static function codalmacen(Traductor $i18n, DocumentoComercial $modelo, string $funcionJS): string
    {
        $almacenes = 0;
        $opciones = [];
        foreach (Empresas::todas() as $empresa) {
            if ($empresa->idempresa != $modelo->idempresa && $modelo->existe()) {
                continue;
            }

            $opcion = '';
            foreach ($empresa->getAlmacenes() as $fila) {
                if ($fila->codalmacen != $modelo->codalmacen && !$fila->activo) {
                    continue;
                }

                $opcion .= ($fila->codalmacen === $modelo->codalmacen) ?
                    '<option value="' . $fila->codalmacen . '" selected>' . $fila->nombre . '</option>' :
                    '<option value="' . $fila->codalmacen . '">' . $fila->nombre . '</option>';
                $almacenes++;
            }
            $opciones[] = '<optgroup label="' . $empresa->nombrecorto . '">' . $opcion . '</optgroup>';
        }

        $atributos = $modelo->editable ?
            'name="codalmacen" onchange="return ' . $funcionJS . '(\'recalcular\', \'0\');" required' :
            'disabled';

        return empty($modelo->valorColumnaSujeto()) || $almacenes <= 1 ? '' : '<div class="col-sm-2 col-lg">'
            . '<div class="form-group">'
            . '<a href="' . Almacenes::obtener($modelo->codalmacen)->url() . '">' . $i18n->trans('company-warehouse') . '</a>'
            . '<select ' . $atributos . ' class="form-control">' . implode('', $opciones) . '</select>'
            . '</div>'
            . '</div>';
    }

    protected static function coddivisa(Traductor $i18n, DocumentoComercial $modelo): string
    {
        $opciones = [];
        foreach (Divisas::todas() as $fila) {
            $opciones[] = ($fila->coddivisa === $modelo->coddivisa) ?
                '<option value="' . $fila->coddivisa . '" selected>' . $fila->descripcion . '</option>' :
                '<option value="' . $fila->coddivisa . '">' . $fila->descripcion . '</option>';
        }

        $atributos = $modelo->editable ? 'name="coddivisa" required' : 'disabled';
        return empty($modelo->valorColumnaSujeto()) ? '' : '<div class="col-sm-6">'
            . '<div class="form-group">'
            . '<a href="' . Divisas::obtener($modelo->coddivisa)->url() . '">' . $i18n->trans('currency') . '</a>'
            . '<select ' . $atributos . ' class="form-control">'
            . implode('', $opciones) . '</select>'
            . '</div>'
            . '</div>';
    }

    protected static function codpago(Traductor $i18n, DocumentoComercial $modelo): string
    {
        $opciones = [];
        foreach (FormasPago::todas() as $fila) {
            if ($fila->idempresa != $modelo->idempresa) {
                continue;
            }

            if ($fila->codpago != $modelo->codpago && !$fila->activa) {
                continue;
            }

            $opciones[] = ($fila->codpago === $modelo->codpago) ?
                '<option value="' . $fila->codpago . '" selected>' . $fila->descripcion . '</option>' :
                '<option value="' . $fila->codpago . '">' . $fila->descripcion . '</option>';
        }

        $atributos = $modelo->editable ? 'name="codpago" required' : 'disabled';
        return empty($modelo->valorColumnaSujeto()) ? '' : '<div class="col-sm-3 col-md-2 col-lg">'
            . '<div id="metodos-pago" class="form-group">'
            . '<a href="' . FormasPago::obtener($modelo->codpago)->url() . '">' . $i18n->trans('payment-method') . '</a>'
            . '<select ' . $atributos . ' class="form-control">' . implode('', $opciones) . '</select>'
            . '</div>'
            . '</div>';
    }

    protected static function codserie(Traductor $i18n, DocumentoComercial $modelo, string $funcionJS): string
    {
        $rectificativa = property_exists($modelo, 'idfacturarect') && $modelo->idfacturarect;

        $opciones = [];
        foreach (Series::todas() as $fila) {
            if ($fila->codserie === $modelo->codserie) {
                $opciones[] = '<option value="' . $fila->codserie . '" selected>' . $fila->descripcion . '</option>';
                continue;
            }

            if ($rectificativa && $fila->tipo === 'R') {
                $opciones[] = '<option value="' . $fila->codserie . '">' . $fila->descripcion . '</option>';
                continue;
            }

            if ($rectificativa === false && $fila->tipo !== 'R') {
                $opciones[] = '<option value="' . $fila->codserie . '">' . $fila->descripcion . '</option>';
            }
        }

        $atributos = $modelo->editable ?
            'name="codserie" onchange="return ' . $funcionJS . '(\'recalcular\', \'0\');" required' :
            'disabled';
        return empty($modelo->valorColumnaSujeto()) ? '' : '<div class="col-sm-3 col-md-2 col-lg">'
            . '<div class="form-group">'
            . '<a href="' . Series::obtener($modelo->codserie)->url() . '">' . $i18n->trans('serie') . '</a>'
            . '<select ' . $atributos . ' class="form-control">' . implode('', $opciones) . '</select>'
            . '</div>'
            . '</div>';
    }

    protected static function columna(Traductor $i18n, DocumentoComercial $modelo, string $nombreCol, string $etiqueta, bool $autoOcultar = false, int $nivel = 0): string
    {
        if (self::verificarNivel($nivel) === false) {
            return '';
        }

        return empty($modelo->{$nombreCol}) && $autoOcultar ? '' : '<div class="col-sm"><div class="form-group">' . $i18n->trans($etiqueta)
            . '<input type="text" value="' . number_format($modelo->{$nombreCol}, FS_NF0, FS_NF1, '')
            . '" class="form-control" disabled/></div></div>';
    }

    protected static function botonEliminar(Traductor $i18n, DocumentoComercial $modelo, string $nombreJS): string
    {
        return $modelo->valorColumnaPrincipal() && $modelo->editable ?
            '<button type="button" class="btn btn-spin-action btn-danger mb-3" data-toggle="modal" data-target="#modalEliminarDoc">'
            . '<i class="fas fa-trash-alt fa-fw"></i> ' . $i18n->trans('delete')
            . '</button>'
            . '<div class="modal fade" id="modalEliminarDoc" tabindex="-1" aria-hidden="true">'
            . '<div class="modal-dialog">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title"></h5>'
            . '<button type="button" class="close" data-dismiss="modal" aria-label="Close">'
            . '<span aria-hidden="true">&times;</span>'
            . '</button>'
            . '</div>'
            . '<div class="modal-body text-center">'
            . '<i class="fas fa-trash-alt fa-3x"></i>'
            . '<h5 class="mt-3 mb-1">' . $i18n->trans('confirm-delete') . '</h5>'
            . '<p class="mb-0">' . $i18n->trans('are-you-sure') . '</p>'
            . '</div>'
            . '<div class="modal-footer">'
            . '<button type="button" class="btn btn-spin-action btn-secondary" data-dismiss="modal">' . $i18n->trans('cancel') . '</button>'
            . '<button type="button" class="btn btn-spin-action btn-danger" onclick="return ' . $nombreJS . '(\'eliminar-doc\', \'0\');">'
            . $i18n->trans('delete') . '</button>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>' : '';
    }

    protected static function dtopor1(Traductor $i18n, DocumentoComercial $modelo, string $nombreJS): string
    {
        if (empty($modelo->netosindto) && empty($modelo->dtopor1)) {
            return '<input type="hidden" name="dtopor1" value="0"/>';
        }

        $atributos = $modelo->editable ?
            'max="100" min="0" name="dtopor1" required step="any" onkeyup="return ' . $nombreJS . '(\'recalcular\', \'0\', event);"' :
            'disabled';
        return '<div class="col-sm"><div class="form-group">' . $i18n->trans('global-dto')
            . '<div class="input-group">'
            . '<div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-percentage"></i></span></div>'
            . '<input type="number" ' . $atributos . ' value="' . floatval($modelo->dtopor1) . '" class="form-control"/>'
            . '</div></div></div>';
    }

    protected static function dtopor2(Traductor $i18n, DocumentoComercial $modelo, string $nombreJS): string
    {
        if (empty($modelo->dtopor1) && empty($modelo->dtopor2)) {
            return '<input type="hidden" name="dtopor2" value="0"/>';
        }

        $atributos = $modelo->editable ?
            'max="100" min="0" name="dtopor2" required step="any" onkeyup="return ' . $nombreJS . '(\'recalcular\', \'0\', event);"' :
            'disabled';
        return '<div class="col-sm-2 col-md"><div class="form-group">' . $i18n->trans('global-dto-2')
            . '<div class="input-group">'
            . '<div class="input-group-prepend">'
            . '<span class="input-group-text"><i class="fas fa-percentage"></i></span>'
            . '</div>'
            . '<input type="number" ' . $atributos . ' value="' . floatval($modelo->dtopor2) . '" class="form-control"/>'
            . '</div></div></div>';
    }

    private static function email(Traductor $i18n, DocumentoComercial $modelo): string
    {
        return empty($modelo->femail) ? '' : '<div class="col-sm-auto">'
            . '<div class="form-group">'
            . '<button class="btn btn-outline-info" type="button" title="' . $i18n->trans('email-sent')
            . '" data-toggle="modal" data-target="#modalCabecera"><i class="fas fa-envelope fa-fw" aria-hidden="true"></i> '
            . $modelo->femail . ' </button></div></div>';
    }

    protected static function entradaRapida(Traductor $i18n, DocumentoComercial $modelo, string $nombreJS): string
    {
        return $modelo->editable ? '<div class="col-8 col-md">'
            . '<div class="input-group mb-3">'
            . '<div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-barcode"></i></span></div>'
            . '<input type="text" name="entradarapida" class="form-control" placeholder="' . $i18n->trans('barcode')
            . '" onkeyup="' . $nombreJS . '(event)"/>'
            . '</div></div>' : '<div class="col"></div>';
    }

    protected static function fecha(Traductor $i18n, DocumentoComercial $modelo, bool $habilitado = true): string
    {
        $atributos = $modelo->editable && $habilitado ? 'name="fecha" required' : 'disabled';
        return empty($modelo->valorColumnaSujeto()) ? '' : '<div class="col-sm">'
            . '<div id="fecha-documento" class="form-group">' . $i18n->trans('date')
            . '<input type="date" ' . $atributos . ' value="' . date('Y-m-d', strtotime($modelo->fecha)) . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    protected static function fechadevengo(Traductor $i18n, DocumentoComercial $modelo): string
    {
        if (property_exists($modelo, 'fechadevengo') === false) {
            return '';
        }

        $atributos = $modelo->editable ? 'name="fechadevengo" required' : 'disabled';
        $valor = empty($modelo->fechadevengo) ? '' : date('Y-m-d', strtotime($modelo->fechadevengo));
        return empty($modelo->valorColumnaSujeto()) ? '' : '<div class="col-sm">'
            . '<div class="form-group">' . $i18n->trans('accrual-date')
            . '<input type="date" ' . $atributos . ' value="' . $valor . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    protected static function femail(Traductor $i18n, DocumentoComercial $modelo): string
    {
        if (empty($modelo->valorColumnaPrincipal())) {
            return '';
        }

        $atributos = empty($modelo->femail) && $modelo->editable ? 'name="femail" ' : 'disabled';
        $valor = empty($modelo->femail) ? '' : date('Y-m-d', strtotime($modelo->femail));
        return '<div class="col-sm-6">'
            . '<div class="form-group">' . $i18n->trans('email-sent')
            . '<input type="date" ' . $atributos . ' value="' . $valor . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    protected static function hora(Traductor $i18n, DocumentoComercial $modelo): string
    {
        $atributos = $modelo->editable ? 'name="hora" required' : 'disabled';
        return empty($modelo->valorColumnaSujeto()) ? '' : '<div class="col-sm-6">'
            . '<div class="form-group">' . $i18n->trans('hour')
            . '<input type="time" ' . $atributos . ' value="' . date('H:i:s', strtotime($modelo->hora)) . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    protected static function idestado(Traductor $i18n, DocumentoTransformador $modelo, string $nombreJS): string
    {
        if (empty($modelo->valorColumnaPrincipal())) {
            return '';
        }

        $estado = $modelo->getEstado();
        $claseBtn = 'btn btn-block btn-secondary btn-spin-action';
        if ($estado->editable === false && empty($estado->generadoc) && empty($estado->actualizastock)) {
            $claseBtn = 'btn btn-block btn-danger btn-spin-action';
        }

        if ($estado->generadoc) {
            return '<div class="col-sm-auto">'
                . '<div class="form-group">'
                . '<button type="button" class="' . $claseBtn . '">'
                . '<i class="' . static::iconoEstado($estado) . ' fa-fw"></i> ' . $estado->nombre
                . '</button>'
                . '</div>'
                . '</div>';
        }

        $opciones = [];
        foreach ($modelo->getEstadosDisponibles() as $est) {
            if ($est->idestado === $modelo->idestado || $est->activo === false) {
                continue;
            }

            $opciones[] = '<a class="dropdown-item' . static::colorTextoEstado($est) . '"'
                . ' href="#" onclick="return ' . $nombreJS . '(\'guardar-estado\', \'' . $est->idestado . '\', this);">'
                . '<i class="' . static::iconoEstado($est, true) . ' fa-fw"></i> ' . $est->nombre . '</a>';
        }

        if ($modelo->editable && in_array($modelo->claseModelo(), ['FacturaCliente', 'FacturaProveedor']) === false) {
            $opciones[] = '<div class="dropdown-divider"></div>'
                . '<a class="dropdown-item" href="UnirDocumentos?modelo=' . $modelo->claseModelo() . '&codigos=' . $modelo->valorColumnaPrincipal() . '">'
                . '<i class="fas fa-magic fa-fw" aria-hidden="true"></i> ' . $i18n->trans('group-or-split')
                . '</a>';
        }

        return '<div class="col-sm-auto">'
            . '<div class="form-group botonEstado">'
            . '<div class="dropdown">'
            . '<button class="' . $claseBtn . ' dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'
            . '<i class="' . static::iconoEstado($estado) . ' fa-fw"></i> ' . $estado->nombre
            . '</button>'
            . '<div class="dropdown-menu dropdown-menu-right">' . implode('', $opciones) . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    protected static function iconoEstado(EstadoDocumento $estado, bool $alternativo = false): string
    {
        if ($estado->icono) {
            return $estado->icono;
        } elseif ($estado->generadoc && $alternativo) {
            return 'fas fa-forward';
        }

        return $estado->editable ? 'fas fa-pen' : 'fas fa-lock';
    }

    protected static function colorTextoEstado(EstadoDocumento $estado): string
    {
        if ($estado->generadoc) {
            return ' text-success';
        }

        return $estado->editable === false && empty($estado->actualizastock) ? ' text-danger' : '';
    }

    public static function modalListaDocumentos(Traductor $i18n, array $documentos, string $titulo, string $id): string
    {
        $lista = '';
        $suma = 0;
        foreach ($documentos as $doc) {
            $lista .= '<tr>'
                . '<td><a href="' . $doc->url() . '">' . $i18n->trans($doc->claseModelo()) . ' ' . $doc->codigo . '</a></td>'
                . '<td>' . $doc->observaciones . '</td>'
                . '<td class="text-right text-nowrap">' . Herramientas::dinero($doc->total) . '</td>'
                . '<td class="text-right text-nowrap">' . $doc->fecha . ' ' . $doc->hora . '</td>'
                . '</tr>';
            $suma += $doc->total;
        }

        $lista .= '<tr class="table-warning">'
            . '<td class="text-right text-nowrap" colspan="3">'
            . $i18n->trans('total') . ' <b>' . Herramientas::dinero($suma) . '</b></td>'
            . '<td></td>'
            . '</tr>';

        return '<div class="modal fade" tabindex="-1" id="' . $id . '">'
            . '<div class="modal-dialog modal-xl">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title"><i class="fas fa-copy fa-fw" aria-hidden="true"></i> ' . $i18n->trans($titulo) . '</h5>'
            . '<button type="button" class="close" data-dismiss="modal" aria-label="' . $i18n->trans('close') . '">'
            . '<span aria-hidden="true">&times;</span>'
            . '</button>'
            . '</div>'
            . '<div class="table-responsive">'
            . '<table class="table table-hover mb-0">'
            . '<thead>'
            . '<tr>'
            . '<th>' . $i18n->trans('document') . '</th>'
            . '<th>' . $i18n->trans('observations') . '</th>'
            . '<th class="text-right">' . $i18n->trans('total') . '</th>'
            . '<th class="text-right">' . $i18n->trans('date') . '</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>' . $lista . '</tbody>'
            . '</table>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    protected static function netosindto(Traductor $i18n, DocumentoComercial $modelo): string
    {
        return empty($modelo->dtopor1) && empty($modelo->dtopor2) ? '' : '<div class="col-sm-2"><div class="form-group">' . $i18n->trans('subtotal')
            . '<input type="text" value="' . number_format($modelo->netosindto, FS_NF0, FS_NF1, '')
            . '" class="form-control" disabled/></div></div>';
    }

    protected static function botonNuevaLinea(Traductor $i18n, DocumentoComercial $modelo, string $nombreJS): string
    {
        return $modelo->editable ? '<div class="col-3 col-md-auto">'
            . '<a href="#" class="btn btn-success btn-block btn-spin-action mb-3" onclick="return ' . $nombreJS . '(\'nueva-linea\', \'0\');">'
            . '<i class="fas fa-plus fa-fw"></i> ' . $i18n->trans('line') . '</a></div>' : '';
    }

    protected static function observaciones(Traductor $i18n, DocumentoComercial $modelo): string
    {
        $atributos = $modelo->editable ? 'name="observaciones"' : 'disabled';
        $filas = 1;
        foreach (explode("\n", $modelo->observaciones ?? '') as $lineaDesc) {
            $filas += mb_strlen($lineaDesc) < 140 ? 1 : ceil(mb_strlen($lineaDesc) / 140);
        }

        return '<div class="col-sm-12"><div class="form-group">' . $i18n->trans('observations')
            . '<textarea ' . $atributos . ' class="form-control" placeholder="' . $i18n->trans('observations')
            . '" rows="' . $filas . '">' . Herramientas::sinHtml($modelo->observaciones) . '</textarea>'
            . '</div></div>';
    }

    protected static function operacion(Traductor $i18n, DocumentoComercial $modelo): string
    {
        $opciones = ['<option value="">------</option>'];
        foreach (OperacionFactura::todas() as $clave => $valor) {
            $opciones[] = ($clave === $modelo->operacion) ?
                '<option value="' . $clave . '" selected>' . $i18n->trans($valor) . '</option>' :
                '<option value="' . $clave . '">' . $i18n->trans($valor) . '</option>';
        }

        $atributos = $modelo->editable ? ' name="operacion"' : ' disabled';
        return '<div class="col-sm-6">'
            . '<div class="form-group">' . $i18n->trans('operation')
            . '<select' . $atributos . ' class="form-control">' . implode('', $opciones) . '</select>'
            . '</div>'
            . '</div>';
    }

    protected static function pagado(Traductor $i18n, DocumentoComercial $modelo, string $nombreJS): string
    {
        if (empty($modelo->valorColumnaPrincipal()) || method_exists($modelo, 'getRecibos') === false) {
            return '';
        }

        if ($modelo->pagado()) {
            return '<div class="col-sm-auto">'
                . '<div class="form-group">'
                . '<button class="btn btn-outline-success dropdown-toggle" type="button" data-toggle="dropdown" aria-expanded="false">'
                . '<i class="fas fa-check-square fa-fw"></i> ' . $i18n->trans('paid') . '</button>'
                . '<div class="dropdown-menu"><a class="dropdown-item text-danger" href="#" onclick="return ' . $nombreJS . '(\'guardar-pagado\', \'0\');">'
                . '<i class="fas fa-times fa-fw"></i> ' . $i18n->trans('unpaid') . '</a></div>'
                . '</div>'
                . '</div>';
        }

        return '<div class="col-sm-auto">'
            . '<div class="form-group">'
            . '<button class="btn btn-spin-action btn-outline-danger dropdown-toggle" type="button" data-toggle="dropdown" aria-expanded="false">'
            . '<i class="fas fa-times fa-fw"></i> ' . $i18n->trans('unpaid') . '</button>'
            . '<div class="dropdown-menu"><a class="dropdown-item text-success" href="#" onclick="mostrarModalCondicionesPago(' . $nombreJS . ')">'
            . '<i class="fas fa-check-square fa-fw"></i> ' . $i18n->trans('paid') . '</a></div>'
            . '</div>'
            . '</div>';
    }

    protected static function documentosPadres(Traductor $i18n, DocumentoTransformador $modelo): string
    {
        if (empty($modelo->valorColumnaPrincipal())) {
            return '';
        }

        $padres = $modelo->documentosPadres();
        switch (count($padres)) {
            case 0:
                return '';

            case 1:
                return '<div class="col-sm-auto">'
                    . '<div class="form-group">'
                    . '<a href="' . $padres[0]->url() . '" class="btn btn-block btn-warning">'
                    . '<i class="fas fa-backward fa-fw" aria-hidden="true"></i> ' . $padres[0]->descripcionPrincipal()
                    . '</a>'
                    . '</div>'
                    . '</div>';
        }

        return '<div class="col-sm-auto">'
            . '<div class="form-group">'
            . '<button class="btn btn-block btn-warning" type="button" title="' . $i18n->trans('previous-documents')
            . '" data-toggle="modal" data-target="#modalPadres"><i class="fas fa-backward fa-fw" aria-hidden="true"></i> '
            . count($padres) . ' </button>'
            . '</div>'
            . '</div>'
            . self::modalListaDocumentos($i18n, $padres, 'previous-documents', 'modalPadres');
    }

    protected static function botonProducto(Traductor $i18n, DocumentoComercial $modelo): string
    {
        return $modelo->editable ? '<div class="col-9 col-md col-lg-2">'
            . '<div class="input-group mb-3">'
            . '<input type="text" id="entradaBuscarProducto" class="form-control" placeholder="' . $i18n->trans('reference') . '"/>'
            . '<div class="input-group-append"><button class="btn btn-info" type="button" onclick="$(\'#modalBuscarProducto\').modal();'
            . ' $(\'#entradaModalProducto\').select();"><i class="fas fa-book fa-fw"></i></button></div>'
            . '</div>'
            . '</div>' : '';
    }

    protected static function botonGuardar(Traductor $i18n, DocumentoComercial $modelo, string $nombreJS): string
    {
        return $modelo->valorColumnaSujeto() && $modelo->editable ? '<button type="button" class="btn btn-primary btn-spin-action"'
            . ' load-after="true" onclick="return ' . $nombreJS . '(\'guardar-doc\', \'0\');">'
            . '<i class="fas fa-save fa-fw"></i> ' . $i18n->trans('save')
            . '</button>' : '';
    }

    protected static function botonOrdenar(Traductor $i18n, DocumentoComercial $modelo): string
    {
        return $modelo->editable ? '<div class="col-4 col-md-auto">'
            . '<button type="button" class="btn btn-block btn-light mb-3" id="botonOrdenar">'
            . '<i class="fas fa-arrows-alt-v fa-fw"></i> ' . $i18n->trans('move-lines')
            . '</button>'
            . '</div>' : '';
    }

    protected static function botonSubtotalNeto(Traductor $i18n): string
    {
        $html = '<div class="col-12 col-md-auto mb-3">'
            . '<div id="vistaColumna" class="btn-group btn-block" role="group">';

        if ('subtotal' === self::$vistaColumna) {
            $html .= '<button type="button" class="btn btn-light" data-column="neto" onclick="cambiarColumna(this)">'
                . $i18n->trans('net') . '</button>'
                . '<button type="button" class="btn btn-light active" data-column="subtotal" onclick="cambiarColumna(this)">'
                . $i18n->trans('subtotal') . '</button>';
        } else {
            $html .= '<button type="button" class="btn btn-light active" data-column="neto" onclick="cambiarColumna(this)">'
                . $i18n->trans('net') . '</button>'
                . '<button type="button" class="btn btn-light" data-column="subtotal" onclick="cambiarColumna(this)">'
                . $i18n->trans('subtotal') . '</button>';
        }

        $html .= '</div></div>';
        return $html;
    }

    protected static function tasaconv(Traductor $i18n, DocumentoComercial $modelo): string
    {
        $atributos = $modelo->editable ? 'name="tasaconv" step="any" autocomplete="off"' : 'disabled';
        return '<div class="col-sm-6">'
            . '<div class="form-group">' . $i18n->trans('conversion-rate')
            . '<input type="number" ' . $atributos . ' value="' . floatval($modelo->tasaconv) . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    protected static function total(Traductor $i18n, DocumentoComercial $modelo, string $nombreJS): string
    {
        return empty($modelo->total) ? '' : '<div class="col-sm"><div class="form-group">' . $i18n->trans('total')
            . '<div class="input-group">'
            . '<input type="text" value="' . number_format($modelo->total, FS_NF0, FS_NF1, '')
            . '" class="form-control" disabled/>'
            . '<div class="input-group-append"><button class="btn btn-primary btn-spin-action" onclick="return ' . $nombreJS
            . '(\'guardar-doc\', \'0\');" title="' . $i18n->trans('save') . '" type="button">'
            . '<i class="fas fa-save fa-fw"></i></button></div>'
            . '</div></div></div>';
    }

    protected static function botonDeshacer(Traductor $i18n, DocumentoComercial $modelo): string
    {
        return $modelo->valorColumnaSujeto() && $modelo->editable ? '<a href="' . $modelo->url() . '" class="btn btn-secondary mr-2">'
            . '<i class="fas fa-undo fa-fw"></i> ' . $i18n->trans('undo')
            . '</a>' : '';
    }

    protected static function usuario(Traductor $i18n, DocumentoComercial $modelo): string
    {
        $atributos = 'disabled';
        return empty($modelo->valorColumnaSujeto()) ? '' : '<div class="col-sm-6">'
            . '<div class="form-group">' . $i18n->trans('user')
            . '<input type="text" ' . $atributos . ' value="' . Herramientas::sinHtml($modelo->nick) . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }
}