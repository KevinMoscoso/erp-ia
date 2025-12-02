<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\Controller;
use ERPIA\Core\Base\ControllerPermissions;
use ERPIA\Core\Lib\Calculator;
use ERPIA\Core\Model\Base\BusinessDocument;
use ERPIA\Core\Model\Producto;
use ERPIA\Core\Model\Variante;
use ERPIA\Core\Response;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Asiento;
use ERPIA\Dinamic\Model\Cliente;
use ERPIA\Dinamic\Model\CodeModel;
use ERPIA\Dinamic\Model\Proveedor;
use ERPIA\Dinamic\Model\User;

class CopyModel extends Controller
{
    const ESPACIO_MODELOS = '\\ERPIA\\Dinamic\\Model\\';
    const PLANTILLA_ASIENTO = 'CopiarAsiento';
    const PLANTILLA_PRODUCTO = 'CopiarProducto';

    public $modeloCodigo;
    public $modelo;
    public $claseModelo;
    public $codigoModelo;

    public function obtenerDatosPagina(): array
    {
        $datos = parent::obtenerDatosPagina();
        $datos['menu'] = 'ventas';
        $datos['titulo'] = 'copiar';
        $datos['icono'] = 'fas fa-copy';
        $datos['mostrarEnMenu'] = false;
        return $datos;
    }

    public function nucleoPrivado(&$respuesta, $usuario, $permisos)
    {
        parent::nucleoPrivado($respuesta, $usuario, $permisos);
        $this->modeloCodigo = new CodeModel();
        $accion = $this->solicitud->entradaOConsulta('accion');
        if ($accion === 'autocompletar') {
            $this->accionAutocompletar();
            return;
        } elseif (false === $this->pipeFalse('ejecutarAccion', $accion, $this->modeloCodigo)) {
            return;
        } elseif (false === $this->cargarModelo()) {
            Tools::registro()->advertencia('registro-no-encontrado');
            return;
        }

        $this->titulo .= ' ' . $this->modelo->descripcionPrincipal();

        switch ($this->claseModelo) {
            case 'Asiento':
                $this->establecerPlantilla(self::PLANTILLA_ASIENTO);
                break;
            case 'Producto':
                $this->establecerPlantilla(self::PLANTILLA_PRODUCTO);
                break;
            default:
                $this->pipe('antes', $this->modelo);
                break;
        }

        if ($accion === 'guardar') {
            switch ($this->claseModelo) {
                case 'AlbaranCliente':
                case 'FacturaCliente':
                case 'PedidoCliente':
                case 'PresupuestoCliente':
                    $this->guardarDocumentoVenta();
                    break;
                case 'AlbaranProveedor':
                case 'FacturaProveedor':
                case 'PedidoProveedor':
                case 'PresupuestoProveedor':
                    $this->guardarDocumentoCompra();
                    break;
                case 'Asiento':
                    $this->guardarAsientoContable();
                    break;
                case 'Producto':
                    $this->guardarProducto();
                    break;
                default:
                    $this->pipe('accionGuardar', $this->modelo, $this->modeloCodigo);
                    break;
            }
        }
    }

    protected function accionAutocompletar(): void
    {
        $this->establecerPlantilla(false);
        $resultados = [];
        $datos = $this->solicitud->solicitud->todo();
        foreach ($this->modeloCodigo->buscar($datos['fuente'], $datos['campoCodigo'], $datos['campoTitulo'], $datos['termino']) as $valor) {
            $resultados[] = [
                'clave' => Tools::corregirHtml($valor->code),
                'valor' => Tools::corregirHtml($valor->description)
            ];
        }
        $this->respuesta->json($resultados);
    }

    protected function cargarModelo(): bool
    {
        $this->claseModelo = $this->solicitud->consultaOEntrada('modelo');
        $this->codigoModelo = $this->solicitud->consultaOEntrada('codigo');
        if (empty($this->claseModelo) || empty($this->codigoModelo)) {
            return false;
        }
        $nombreClase = self::ESPACIO_MODELOS . $this->claseModelo;
        $this->modelo = new $nombreClase();
        return $this->modelo->cargarDesdeCodigo($this->codigoModelo);
    }

    protected function finalizarGuardadoDocumento(BusinessDocument $nuevoDoc): void
    {
        $lineas = $nuevoDoc->obtenerLineas();
        if (false === Calculator::calcular($nuevoDoc, $lineas, true)) {
            Tools::registro()->advertencia('error-guardar-registro');
            $this->baseDatos->revertirTransaccion();
            return;
        }
        $this->baseDatos->confirmarTransaccion();
        Tools::registro()->noticia('registro-actualizado-correctamente');
        $this->redirigir($nuevoDoc->url() . '&accion=guardar-ok');
    }

    protected function guardarAsientoContable(): void
    {
        if (false === $this->validarTokenFormulario()) {
            return;
        }
        $this->baseDatos->iniciarTransaccion();
        $nuevoAsiento = new Asiento();
        $nuevoAsiento->canal = $this->solicitud->entrada('canal');
        $nuevoAsiento->concepto = $this->solicitud->entrada('concepto');
        $empresa = $this->solicitud->entrada('idempresa');
        $nuevoAsiento->idempresa = empty($empresa) ? $nuevoAsiento->idempresa : $empresa;
        $diario = $this->solicitud->entrada('iddiario');
        $nuevoAsiento->iddiario = empty($diario) ? null : $diario;
        $nuevoAsiento->importe = $this->modelo->importe;
        $fecha = $this->solicitud->entrada('fecha');
        if (false === $nuevoAsiento->establecerFecha($fecha)) {
            Tools::registro()->advertencia('error-establecer-fecha');
            $this->baseDatos->revertirTransaccion();
            return;
        }
        if (false === $this->pipeFalse('antesGuardarAsiento', $nuevoAsiento)) {
            $this->baseDatos->revertirTransaccion();
            return;
        }
        if (false === $nuevoAsiento->guardar()) {
            Tools::registro()->advertencia('error-guardar-registro');
            $this->baseDatos->revertirTransaccion();
            return;
        }
        foreach ($this->modelo->obtenerLineas() as $linea) {
            $nuevaLinea = $nuevoAsiento->obtenerNuevaLinea();
            $nuevaLinea->cargarDesdeArray($linea->aArray(), ['idasiento', 'idpartida', 'idsubcuenta']);
            if (false === $this->pipeFalse('antesGuardarLineaAsiento', $nuevaLinea)) {
                $this->baseDatos->revertirTransaccion();
                return;
            }
            if (false === $nuevaLinea->guardar()) {
                Tools::registro()->advertencia('error-guardar-registro');
                $this->baseDatos->revertirTransaccion();
                return;
            }
        }
        $this->baseDatos->confirmarTransaccion();
        Tools::registro()->noticia('registro-actualizado-correctamente');
        $this->redirigir($nuevoAsiento->url() . '&accion=guardar-ok');
    }

    protected function guardarDocumentoCompra(): void
    {
        if (false === $this->validarTokenFormulario()) {
            return;
        }
        $sujeto = new Proveedor();
        if (false === $sujeto->cargar($this->solicitud->entrada('codproveedor'))) {
            Tools::registro()->advertencia('registro-no-encontrado');
            return;
        }
        $this->baseDatos->iniciarTransaccion();
        $nombreClase = self::ESPACIO_MODELOS . $this->claseModelo;
        $nuevoDoc = new $nombreClase();
        $nuevoDoc->establecerAutor($this->usuario);
        $nuevoDoc->establecerSujeto($sujeto);
        $nuevoDoc->codalmacen = $this->solicitud->entrada('codalmacen');
        $nuevoDoc->establecerDivisa($this->modelo->coddivisa);
        $nuevoDoc->codpago = $this->solicitud->entrada('codpago');
        $nuevoDoc->codserie = $this->solicitud->entrada('codserie');
        $nuevoDoc->dtopor1 = (float)$this->solicitud->entrada('dtopor1', 0);
        $nuevoDoc->dtopor2 = (float)$this->solicitud->entrada('dtopor2', 0);
        $nuevoDoc->establecerFecha($this->solicitud->entrada('fecha'), $this->solicitud->entrada('hora'));
        $nuevoDoc->numproveedor = $this->solicitud->entrada('numproveedor');
        $nuevoDoc->observaciones = $this->solicitud->entrada('observaciones');
        if (false === $this->pipeFalse('antesGuardarCompra', $nuevoDoc)) {
            $this->baseDatos->revertirTransaccion();
            return;
        }
        if (false === $nuevoDoc->guardar()) {
            Tools::registro()->advertencia('error-guardar-registro');
            $this->baseDatos->revertirTransaccion();
            return;
        }
        foreach ($this->modelo->obtenerLineas() as $linea) {
            $nuevaLinea = $nuevoDoc->obtenerNuevaLinea($linea->aArray());
            if (false === $this->pipeFalse('antesGuardarLineaCompra', $nuevaLinea)) {
                $this->baseDatos->revertirTransaccion();
                return;
            }
            if (false === $nuevaLinea->guardar()) {
                Tools::registro()->advertencia('error-guardar-registro');
                $this->baseDatos->revertirTransaccion();
                return;
            }
        }
        $this->finalizarGuardadoDocumento($nuevoDoc);
    }

    protected function guardarDocumentoVenta(): void
    {
        if (false === $this->validarTokenFormulario()) {
            return;
        }
        $sujeto = new Cliente();
        if (false === $sujeto->cargar($this->solicitud->entrada('codcliente'))) {
            Tools::registro()->advertencia('registro-no-encontrado');
            return;
        }
        $this->baseDatos->iniciarTransaccion();
        $nombreClase = self::ESPACIO_MODELOS . $this->claseModelo;
        $nuevoDoc = new $nombreClase();
        $nuevoDoc->establecerAutor($this->usuario);
        $nuevoDoc->establecerSujeto($sujeto);
        $nuevoDoc->codalmacen = $this->solicitud->entrada('codalmacen');
        $nuevoDoc->establecerDivisa($this->modelo->coddivisa);
        $nuevoDoc->codpago = $this->solicitud->entrada('codpago');
        $nuevoDoc->codserie = $this->solicitud->entrada('codserie');
        $nuevoDoc->dtopor1 = (float)$this->solicitud->entrada('dtopor1', 0);
        $nuevoDoc->dtopor2 = (float)$this->solicitud->entrada('dtopor2', 0);
        $nuevoDoc->establecerFecha($this->solicitud->entrada('fecha'), $this->solicitud->entrada('hora'));
        $nuevoDoc->numero2 = $this->solicitud->entrada('numero2');
        $nuevoDoc->observaciones = $this->solicitud->entrada('observaciones');
        if (false === $this->pipeFalse('antesGuardarVenta', $nuevoDoc)) {
            $this->baseDatos->revertirTransaccion();
            return;
        }
        if (false === $nuevoDoc->guardar()) {
            Tools::registro()->advertencia('error-guardar-registro');
            $this->baseDatos->revertirTransaccion();
            return;
        }
        foreach ($this->modelo->obtenerLineas() as $linea) {
            $nuevaLinea = $nuevoDoc->obtenerNuevaLinea($linea->aArray());
            if (false === $this->pipeFalse('antesGuardarLineaVenta', $nuevaLinea)) {
                $this->baseDatos->revertirTransaccion();
                return;
            }
            if (false === $nuevaLinea->guardar()) {
                Tools::registro()->advertencia('error-guardar-registro');
                $this->baseDatos->revertirTransaccion();
                return;
            }
        }
        $this->finalizarGuardadoDocumento($nuevoDoc);
    }

    protected function guardarProducto(): void
    {
        if (false === $this->validarTokenFormulario()) {
            return;
        }
        $this->baseDatos->iniciarTransaccion();
        $productoOrigen = $this->modelo;
        $variantesOrigen = $productoOrigen->obtenerVariantes();
        $productoDestino = new Producto();
        $camposProducto = array_keys((new Producto())->obtenerCamposModelo());
        $camposExcluidos = ['actualizado', 'descripcion', 'fechaalta', 'idproducto', 'referencia', 'stockfis'];
        foreach ($camposProducto as $campo) {
            if (false === in_array($campo, $camposExcluidos)) {
                $productoDestino->{$campo} = $productoOrigen->{$campo};
            }
        }
        $productoDestino->descripcion = $this->solicitud->entrada('descripcion');
        $productoDestino->referencia = $this->solicitud->entrada('referencia');
        if (false === $this->pipeFalse('antesGuardarProducto', $productoDestino)) {
            $this->baseDatos->revertirTransaccion();
            return;
        }
        if (false === $productoDestino->guardar()) {
            Tools::registro()->advertencia('error-guardar-registro');
            $this->baseDatos->revertirTransaccion();
            return;
        }
        $camposVariante = array_keys((new Variante())->obtenerCamposModelo());
        $camposExcluidos = ['idvariante', 'idproducto', 'referencia', 'stockfis'];
        foreach ($variantesOrigen as $variante) {
            if ($variante === reset($variantesOrigen)) {
                $varianteDestino = $productoDestino->obtenerVariantes()[0];
            } else {
                $varianteDestino = new Variante();
            }
            foreach ($camposVariante as $campo) {
                if (false === in_array($campo, $camposExcluidos)) {
                    $varianteDestino->{$campo} = $variante->{$campo};
                }
            }
            $varianteDestino->idproducto = $productoDestino->idproducto;
            if (false === $this->pipeFalse('antesGuardarVariante', $varianteDestino)) {
                $this->baseDatos->revertirTransaccion();
                return;
            }
            if (false === $varianteDestino->guardar()) {
                Tools::registro()->advertencia('error-guardar-registro');
                $this->baseDatos->revertirTransaccion();
                return;
            }
        }
        $this->baseDatos->confirmarTransaccion();
        Tools::registro()->noticia('registro-actualizado-correctamente');
        $this->redirigir($productoDestino->url() . '&accion=guardar-ok');
    }
}