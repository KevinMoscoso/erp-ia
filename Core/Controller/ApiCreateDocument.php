<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Lib\Calculator;
use ERPIA\Core\Model\Base\BusinessDocument;
use ERPIA\Core\Response;
use ERPIA\Core\Template\ApiController;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Cliente;
use ERPIA\Dinamic\Model\Proveedor;

class ApiCreateDocument extends ApiController
{
    protected $modelo_compra;
    protected $modelo_venta;

    protected function ejecutarRecurso(): void
    {
        if (!in_array($this->solicitud->metodo(), ['POST', 'PUT'])) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_METODO_NO_PERMITIDO)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'metodo-no-permitido',
                ]);
            return;
        }

        $this->cargarModelo();

        if (!empty($this->modelo_compra)) {
            $this->crearCompra();
        } elseif (!empty($this->modelo_venta)) {
            $this->crearVenta();
        } else {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_ENTIDAD_NO_PROCESABLE)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'modelo-invalido',
                ]);
        }
    }

    protected function crearCompra(): void
    {
        $codproveedor = $this->solicitud->entrada('codproveedor');
        if (empty($codproveedor)) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_SOLICITUD_INCORRECTA)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'campo-codproveedor-requerido',
                ]);
            return;
        }

        $proveedor = new Proveedor();
        if (!$proveedor->cargar($codproveedor)) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_NO_ENCONTRADO)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'proveedor-no-encontrado',
                ]);
            return;
        }

        $clase = '\\ERPIA\\Dinamic\\Model\\' . $this->modelo_compra;
        $documento = new $clase();

        if ($documento->establecerSujeto($proveedor) === false) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_SERVER_ERROR_INTERNO)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'error-asignar-sujeto',
                ]);
            return;
        }

        $codalmacen = $this->solicitud->entrada('codalmacen');
        if ($codalmacen && $documento->establecerAlmacen($codalmacen) === false) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_NO_ENCONTRADO)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'almacen-no-encontrado',
                ]);
            return;
        }

        $fecha = $this->solicitud->entrada('fecha');
        $hora = $this->solicitud->entrada('hora', $documento->hora);
        if ($fecha && $documento->establecerFecha($fecha, $hora) === false) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_SOLICITUD_INCORRECTA)
                ->json([
                    'estado' => 'error',
                    'mensaje' => Tools::trans('fecha-invalida'),
                ]);
            return;
        }

        $coddivisa = $this->solicitud->entrada('coddivisa');
        if ($coddivisa) {
            $documento->establecerDivisa($coddivisa);
        }

        foreach ($documento->obtenerCamposModelo() as $clave => $campo) {
            if ($this->solicitud->solicitud->tiene($clave)) {
                $documento->{$clave} = $this->solicitud->entrada($clave);
            }
        }

        $this->baseDatos()->iniciarTransaccion();

        if ($documento->guardar() === false) {
            $this->baseDatos()->revertirTransaccion();
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_ENTIDAD_NO_PROCESABLE)
                ->json([
                    'estado' => 'error',
                    'mensaje' => Tools::trans('error-guardar-registro'),
                ]);
            return;
        }

        if ($this->guardarLineas($documento) === false) {
            $this->baseDatos()->revertirTransaccion();
            return;
        }

        $this->procesarFacturaPagada($documento);

        $this->baseDatos()->confirmarTransaccion();

        $this->respuesta
            ->json([
                'documento' => $documento->aArray(),
                'lineas' => $documento->obtenerLineas(),
            ]);
    }

    protected function crearVenta(): void
    {
        $codcliente = $this->solicitud->entrada('codcliente');
        if (empty($codcliente)) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_SOLICITUD_INCORRECTA)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'campo-codcliente-requerido',
                ]);
            return;
        }

        $cliente = new Cliente();
        if (!$cliente->cargar($codcliente)) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_NO_ENCONTRADO)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'cliente-no-encontrado',
                ]);
            return;
        }

        $clase = '\\ERPIA\\Dinamic\\Model\\' . $this->modelo_venta;
        $documento = new $clase();

        if ($documento->establecerSujeto($cliente) === false) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_SERVER_ERROR_INTERNO)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'error-asignar-sujeto',
                ]);
            return;
        }

        $codalmacen = $this->solicitud->entrada('codalmacen');
        if ($codalmacen && $documento->establecerAlmacen($codalmacen) === false) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_NO_ENCONTRADO)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'almacen-no-encontrado',
                ]);
            return;
        }

        $fecha = $this->solicitud->entrada('fecha');
        $hora = $this->solicitud->entrada('hora', $documento->hora);
        if ($fecha && $documento->establecerFecha($fecha, $hora) === false) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_SOLICITUD_INCORRECTA)
                ->json([
                    'estado' => 'error',
                    'mensaje' => Tools::trans('fecha-invalida'),
                ]);
            return;
        }

        $coddivisa = $this->solicitud->entrada('coddivisa');
        if ($coddivisa) {
            $documento->establecerDivisa($coddivisa);
        }

        foreach ($documento->obtenerCamposModelo() as $clave => $campo) {
            if ($this->solicitud->solicitud->tiene($clave)) {
                $documento->{$clave} = $this->solicitud->entrada($clave);
            }
        }

        $this->baseDatos()->iniciarTransaccion();

        if ($documento->guardar() === false) {
            $this->baseDatos()->revertirTransaccion();
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_ENTIDAD_NO_PROCESABLE)
                ->json([
                    'estado' => 'error',
                    'mensaje' => Tools::trans('error-guardar-registro'),
                ]);
            return;
        }

        if ($this->guardarLineas($documento) === false) {
            $this->baseDatos()->revertirTransaccion();
            return;
        }

        $this->procesarFacturaPagada($documento);

        $this->baseDatos()->confirmarTransaccion();

        $this->respuesta
            ->json([
                'documento' => $documento->aArray(),
                'lineas' => $documento->obtenerLineas(),
            ]);
    }

    protected function cargarModelo(): void
    {
        switch ($this->obtenerParametroUri(2)) {
            case 'crearAlbaranCliente':
                $this->modelo_venta = 'AlbaranCliente';
                break;
            case 'crearAlbaranProveedor':
                $this->modelo_compra = 'AlbaranProveedor';
                break;
            case 'crearFacturaCliente':
                $this->modelo_venta = 'FacturaCliente';
                break;
            case 'crearFacturaProveedor':
                $this->modelo_compra = 'FacturaProveedor';
                break;
            case 'crearPedidoCliente':
                $this->modelo_venta = 'PedidoCliente';
                break;
            case 'crearPedidoProveedor':
                $this->modelo_compra = 'PedidoProveedor';
                break;
            case 'crearPresupuestoCliente':
                $this->modelo_venta = 'PresupuestoCliente';
                break;
            case 'crearPresupuestoProveedor':
                $this->modelo_compra = 'PresupuestoProveedor';
                break;
        }
    }

    protected function procesarFacturaPagada(BusinessDocument &$documento): void
    {
        if ($documento->tieneColumna('idfactura') &&
            $documento->tieneColumna('pagada') &&
            $this->solicitud->solicitud->obtenerBool('pagada', false)) {
            foreach ($documento->obtenerRecibos() as $recibo) {
                $recibo->pagado = true;
                $recibo->guardar();
            }
            $documento->recargar();
        }
    }

    protected function guardarLineas(BusinessDocument &$documento): bool
    {
        if (!$this->solicitud->solicitud->tiene('lineas')) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_SOLICITUD_INCORRECTA)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'campo-lineas-requerido',
                ]);
            return false;
        }

        $datosLineas = $this->solicitud->entrada('lineas');
        $lineas = json_decode($datosLineas, true);

        if (!is_array($lineas)) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_SOLICITUD_INCORRECTA)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'lineas-invalidas',
                ]);
            return false;
        }

        $nuevasLineas = [];
        foreach ($lineas as $linea) {
            $nuevaLinea = empty($linea['referencia'] ?? '') ?
                $documento->obtenerNuevaLinea() :
                $documento->obtenerNuevaLineaProducto($linea['referencia']);

            $nuevaLinea->cantidad = (float)($linea['cantidad'] ?? 1);
            $nuevaLinea->descripcion = $linea['descripcion'] ?? $nuevaLinea->descripcion ?? '?';
            $nuevaLinea->pvpunitario = (float)($linea['pvpunitario'] ?? $nuevaLinea->pvpunitario);
            $nuevaLinea->dtopor = (float)($linea['dtopor'] ?? $nuevaLinea->dtopor);
            $nuevaLinea->dtopor2 = (float)($linea['dtopor2'] ?? $nuevaLinea->dtopor2);

            if (isset($linea['excepcioniva'])) {
                $nuevaLinea->excepcioniva = $linea['excepcioniva'] === 'null' ? null : $linea['excepcioniva'];
            }

            if (isset($linea['codimpuesto'])) {
                $nuevoCodimpuesto = $linea['codimpuesto'] === 'null' ? null : $linea['codimpuesto'];
                if ($nuevoCodimpuesto !== $nuevaLinea->codimpuesto) {
                    $nuevaLinea->establecerImpuesto($nuevoCodimpuesto);
                }
            }

            if (!empty($linea['suplido'] ?? '')) {
                $nuevaLinea->suplido = (bool)$linea['suplido'];
            }

            if (!empty($linea['mostrar_cantidad'] ?? '')) {
                $nuevaLinea->mostrar_cantidad = (bool)$linea['mostrar_cantidad'];
            }

            if (!empty($linea['mostrar_precio'] ?? '')) {
                $nuevaLinea->mostrar_precio = (bool)$linea['mostrar_precio'];
            }

            if (!empty($linea['salto_pagina'] ?? '')) {
                $nuevaLinea->salto_pagina = (bool)$linea['salto_pagina'];
            }

            $nuevasLineas[] = $nuevaLinea;
        }

        if (Calculator::calcular($documento, $nuevasLineas, true) === false) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_ENTIDAD_NO_PROCESABLE)
                ->json([
                    'estado' => 'error',
                    'mensaje' => Tools::trans('error-calcular-totales'),
                ]);
            return false;
        }

        return true;
    }
}