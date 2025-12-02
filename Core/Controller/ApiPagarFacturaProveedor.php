<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Response;
use ERPIA\Core\Template\ApiController;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\FacturaProveedor;

class ApiPagarFacturaProveedor extends ApiController
{
    protected function ejecutarRecurso(): void
    {
        if (!in_array($this->solicitud->metodo(), ['POST', 'PUT'])) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_METODO_NO_PERMITIDO)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'Metodo no permitido',
                ]);
            return;
        }

        if ($this->obtenerParametroUri(3) === null) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_SOLICITUD_INCORRECTA)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'parametro-id-faltante',
                ]);
            return;
        } elseif (!$this->solicitud->solicitud->tiene('pagada')) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_SOLICITUD_INCORRECTA)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'parametro-pagada-faltante',
                ]);
            return;
        }

        $factura = new FacturaProveedor();
        $id = $this->obtenerParametroUri(3);
        if (!$factura->cargar($id)) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_NO_ENCONTRADO)
                ->json([
                    'estado' => 'error',
                    'mensaje' => Tools::trans('factura-no-encontrada'),
                ]);
            return;
        }

        $pagada = $this->solicitud->solicitud->obtenerBool('pagada');
        if ($factura->pagada == $pagada) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_OK)
                ->json([
                    'exito' => Tools::trans('sin-cambios'),
                    'datos' => $factura->aArray(),
                ]);
            return;
        } elseif ($pagada) {
            $this->marcarRecibosPagados($factura);
            return;
        }

        $this->marcarRecibosNoPagados($factura);
    }

    protected function marcarRecibosPagados(FacturaProveedor &$factura): void
    {
        foreach ($factura->obtenerRecibos() as $recibo) {
            $recibo->pagado = true;
            $recibo->fechapago = $this->solicitud->solicitud->obtener('fechapago', $recibo->fechapago);
            $recibo->codpago = $this->solicitud->solicitud->obtener('codpago', $recibo->codpago);
            if ($recibo->guardar() === false) {
                $this->respuesta
                    ->establecerCodigoHttp(Response::HTTP_SERVER_ERROR_INTERNO)
                    ->json([
                        'estado' => 'error',
                        'mensaje' => Tools::trans('error-guardar-registro'),
                    ]);
                return;
            }
        }

        $factura->recargar();

        $this->respuesta
            ->establecerCodigoHttp(Response::HTTP_OK)
            ->json([
                'exito' => Tools::trans('registro-actualizado-correctamente'),
                'datos' => $factura->aArray(),
            ]);
    }

    protected function marcarRecibosNoPagados(FacturaProveedor &$factura): void
    {
        foreach ($factura->obtenerRecibos() as $recibo) {
            $recibo->pagado = false;
            $recibo->fechapago = null;
            if ($recibo->guardar() === false) {
                $this->respuesta
                    ->establecerCodigoHttp(Response::HTTP_SERVER_ERROR_INTERNO)
                    ->json([
                        'estado' => 'error',
                        'mensaje' => Tools::trans('error-guardar-registro'),
                    ]);
                return;
            }
        }

        $factura->recargar();

        $this->respuesta
            ->establecerCodigoHttp(Response::HTTP_OK)
            ->json([
                'exito' => Tools::trans('registro-actualizado-correctamente'),
                'datos' => $factura->aArray(),
            ]);
    }
}