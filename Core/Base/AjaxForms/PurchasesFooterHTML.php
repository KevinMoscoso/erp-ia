<?php
/**
 * Este archivo es parte de nuestro sistema ERP
 * Desarrollado para gestión de documentos comerciales
 */

namespace ERPIA\Core\Base\FormulariosAjax;

use ERPIA\Core\Base\Contrato\ModuloComprasInterface;
use ERPIA\Core\Base\Traductor;
use ERPIA\Core\Modelo\Base\DocumentoCompra;
use ERPIA\Core\Modelo\Usuario;
use ERPIA\Core\Herramientas;

/**
 * Clase PiePaginaComprasHTML
 * 
 * Genera el pie de página para documentos de compras
 */
class PiePaginaComprasHTML
{
    use CabeceraVentasCompras;

    /** @var ModuloComprasInterface[] */
    private static $modulos = [];

    public static function agregarModulo(ModuloComprasInterface $modulo)
    {
        self::$modulos[] = $modulo;
    }

    public static function aplicar(DocumentoCompra &$modelo, array $datosFormulario, Usuario $usuario)
    {
        // módulos
        foreach (self::$modulos as $modulo) {
            $modulo->aplicarAntes($modelo, $datosFormulario, $usuario);
        }

        self::$vistaColumna = $datosFormulario['vistaColumna'] ?? Herramientas::configuracion('default', 'columnetosubtotal', 'subtotal');

        $modelo->dtopor1 = isset($datosFormulario['dtopor1']) ? (float)$datosFormulario['dtopor1'] : $modelo->dtopor1;
        $modelo->dtopor2 = isset($datosFormulario['dtopor2']) ? (float)$datosFormulario['dtopor2'] : $modelo->dtopor2;
        $modelo->observaciones = $datosFormulario['observaciones'] ?? $modelo->observaciones;

        // módulos
        foreach (self::$modulos as $modulo) {
            $modulo->aplicar($modelo, $datosFormulario, $usuario);
        }
    }

    public static function recursos()
    {
        // módulos
        foreach (self::$modulos as $modulo) {
            $modulo->recursos();
        }
    }

    public static function renderizar(DocumentoCompra $modelo): string
    {
        if (empty(self::$vistaColumna)) {
            self::$vistaColumna = Herramientas::configuracion('default', 'columnetosubtotal', 'subtotal');
        }

        if (empty($modelo->codproveedor)) {
            return '';
        }

        $i18n = new Traductor();
        return '<div class="container-fluid mt-3">'
            . '<div class="form-row">'
            . self::renderizarCampo($i18n, $modelo, '_botonProducto')
            . self::renderizarCampo($i18n, $modelo, '_botonNuevaLinea')
            . self::renderizarCampo($i18n, $modelo, '_botonOrdenar')
            . self::renderizarCampo($i18n, $modelo, '_entradaRapida')
            . self::renderizarCampo($i18n, $modelo, '_botonSubtotalNeto')
            . '</div>'
            . '<div class="form-row">'
            . self::renderizarCampo($i18n, $modelo, 'observaciones')
            . self::renderizarNuevosCampos($i18n, $modelo)
            . self::renderizarCampo($i18n, $modelo, 'netosindto')
            . self::renderizarCampo($i18n, $modelo, 'dtopor1')
            . self::renderizarCampo($i18n, $modelo, 'dtopor2')
            . self::renderizarCampo($i18n, $modelo, 'neto')
            . self::renderizarCampo($i18n, $modelo, 'totaliva')
            . self::renderizarCampo($i18n, $modelo, 'totalrecargo')
            . self::renderizarCampo($i18n, $modelo, 'totalirpf')
            . self::renderizarCampo($i18n, $modelo, 'total')
            . '</div>'
            . '<div class="form-row">'
            . '<div class="col-auto">'
            . self::renderizarCampo($i18n, $modelo, '_botonEliminar')
            . '</div>'
            . '<div class="col text-right">'
            . self::renderizarNuevosCamposBoton($i18n, $modelo)
            . self::renderizarCampo($i18n, $modelo, '_modalPie')
            . self::renderizarCampo($i18n, $modelo, '_botonDeshacer')
            . self::renderizarCampo($i18n, $modelo, '_botonGuardar')
            . '</div>'
            . '</div>'
            . '</div>';
    }

    private static function modalPie(Traductor $i18n, DocumentoCompra $modelo): string
    {
        $htmlModal = self::renderizarNuevosCamposModal($i18n, $modelo);

        if (empty($htmlModal)) {
            return '';
        }

        return '<button class="btn btn-outline-secondary mr-2" type="button" data-toggle="modal" data-target="#modalPie">'
            . '<i class="fas fa-plus fa-fw" aria-hidden="true"></i></button>'
            . self::htmlModalPie($i18n, $htmlModal);
    }

    private static function htmlModalPie(Traductor $i18n, string $htmlModal): string
    {
        return '<div class="modal fade" id="modalPie" tabindex="-1" aria-labelledby="modalPieLabel" aria-hidden="true">'
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
            . $htmlModal
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

    private static function renderizarCampo(Traductor $i18n, DocumentoCompra $modelo, string $campo): ?string
    {
        foreach (self::$modulos as $modulo) {
            $html = $modulo->renderizarCampo($i18n, $modelo, $campo);
            if ($html !== null) {
                return $html;
            }
        }

        switch ($campo) {
            case '_botonEliminar':
                return self::botonEliminar($i18n, $modelo, 'formularioComprasGuardar');

            case '_entradaRapida':
                return self::entradaRapida($i18n, $modelo, 'lineaRapidaCompras');

            case '_modalPie':
                return self::modalPie($i18n, $modelo);

            case '_botonNuevaLinea':
                return self::botonNuevaLinea($i18n, $modelo, 'formularioComprasAccion');

            case '_botonProducto':
                return self::botonProducto($i18n, $modelo);

            case '_botonGuardar':
                return self::botonGuardar($i18n, $modelo, 'formularioComprasGuardar');

            case '_botonOrdenar':
                return self::botonOrdenar($i18n, $modelo);

            case '_botonSubtotalNeto':
                return self::botonSubtotalNeto($i18n);

            case '_botonDeshacer':
                return self::botonDeshacer($i18n, $modelo);

            case 'dtopor1':
                return self::dtopor1($i18n, $modelo, 'formularioComprasAccionEspera');

            case 'dtopor2':
                return self::dtopor2($i18n, $modelo, 'formularioComprasAccionEspera');

            case 'neto':
                return self::columna($i18n, $modelo, 'neto', 'net', true);

            case 'netosindto':
                return self::netosindto($i18n, $modelo);

            case 'observaciones':
                return self::observaciones($i18n, $modelo);

            case 'total':
                return self::columna($i18n, $modelo, 'total', 'total', true);

            case 'totalirpf':
                return self::columna($i18n, $modelo, 'totalirpf', 'irpf', true);

            case 'totaliva':
                return self::columna($i18n, $modelo, 'totaliva', 'taxes', true);

            case 'totalrecargo':
                return self::columna($i18n, $modelo, 'totalrecargo', 're', true);
        }

        return null;
    }

    private static function renderizarNuevosCamposBoton(Traductor $i18n, DocumentoCompra $modelo): string
    {
        $nuevosCampos = [];
        foreach (self::$modulos as $modulo) {
            foreach ($modulo->nuevosCamposBoton() as $campo) {
                if (in_array($campo, $nuevosCampos) === false) {
                    $nuevosCampos[] = $campo;
                }
            }
        }

        $html = '';
        foreach ($nuevosCampos as $campo) {
            foreach (self::$modulos as $modulo) {
                $htmlCampo = $modulo->renderizarCampo($i18n, $modelo, $campo);
                if ($htmlCampo !== null) {
                    $html .= $htmlCampo;
                    break;
                }
            }
        }
        return $html;
    }

    private static function renderizarNuevosCampos(Traductor $i18n, DocumentoCompra $modelo): string
    {
        $nuevosCampos = [];
        foreach (self::$modulos as $modulo) {
            foreach ($modulo->nuevosCampos() as $campo) {
                if (in_array($campo, $nuevosCampos) === false) {
                    $nuevosCampos[] = $campo;
                }
            }
        }

        $html = '';
        foreach ($nuevosCampos as $campo) {
            foreach (self::$modulos as $modulo) {
                $htmlCampo = $modulo->renderizarCampo($i18n, $modelo, $campo);
                if ($htmlCampo !== null) {
                    $html .= $htmlCampo;
                    break;
                }
            }
        }
        return $html;
    }

    private static function renderizarNuevosCamposModal(Traductor $i18n, DocumentoCompra $modelo): string
    {
        $nuevosCampos = [];
        foreach (self::$modulos as $modulo) {
            foreach ($modulo->nuevosCamposModal() as $campo) {
                if (in_array($campo, $nuevosCampos) === false) {
                    $nuevosCampos[] = $campo;
                }
            }
        }

        $html = '';
        foreach ($nuevosCampos as $campo) {
            foreach (self::$modulos as $modulo) {
                $htmlCampo = $modulo->renderizarCampo($i18n, $modelo, $campo);
                if ($htmlCampo !== null) {
                    $html .= $htmlCampo;
                    break;
                }
            }
        }

        return $html;
    }
}