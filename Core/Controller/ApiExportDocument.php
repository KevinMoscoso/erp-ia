<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Request;
use ERPIA\Core\Response;
use ERPIA\Core\Template\ApiController;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Lib\ExportManager;

class ApiExportDocument extends ApiController
{
    protected $modelo;

    protected function ejecutarRecurso(): void
    {
        if ($this->solicitud->esMetodo(Request::METODO_GET) === false) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_METODO_NO_PERMITIDO)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'metodo-no-permitido',
                ]);
            return;
        }

        if ($this->cargarModelo() === false) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_NO_ENCONTRADO)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'recurso-no-encontrado',
                ]);
            return;
        }

        $codigo = $this->obtenerParametroUri(3);
        if (empty($codigo)) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_SOLICITUD_INCORRECTA)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'registro-no-especificado',
                ]);
            return;
        }

        $clase = '\\ERPIA\\Dinamic\\Model\\' . $this->modelo;
        $documento = new $clase();
        if ($documento->cargar($codigo) === false) {
            $this->respuesta
                ->establecerCodigoHttp(Response::HTTP_NO_ENCONTRADO)
                ->json([
                    'estado' => 'error',
                    'mensaje' => 'registro-no-encontrado',
                ]);
            return;
        }

        $tipo = $this->solicitud->consulta('tipo', 'PDF');
        $formato = (int)$this->solicitud->consulta('formato', 0);
        $idioma = $this->solicitud->consulta('idioma', $documento->obtenerSujeto()->codidioma) ?? '';
        $titulo = Tools::idioma($idioma)->trans('factura') . ' ' . $documento->id();

        $gestorExportacion = new ExportManager();
        $gestorExportacion->nuevoDocumento($tipo, $titulo, $formato, $idioma);
        $gestorExportacion->agregarPaginaDocumentoComercial($documento);

        $gestorExportacion->mostrar($this->respuesta);
    }

    protected function cargarModelo(): bool
    {
        switch ($this->obtenerParametroUri(2)) {
            case 'exportarAlbaranCliente':
                $this->modelo = 'AlbaranCliente';
                return true;
            case 'exportarAlbaranProveedor':
                $this->modelo = 'AlbaranProveedor';
                return true;
            case 'exportarFacturaCliente':
                $this->modelo = 'FacturaCliente';
                return true;
            case 'exportarFacturaProveedor':
                $this->modelo = 'FacturaProveedor';
                return true;
            case 'exportarPedidoCliente':
                $this->modelo = 'PedidoCliente';
                return true;
            case 'exportarPedidoProveedor':
                $this->modelo = 'PedidoProveedor';
                return true;
            case 'exportarPresupuestoCliente':
                $this->modelo = 'PresupuestoCliente';
                return true;
            case 'exportarPresupuestoProveedor':
                $this->modelo = 'PresupuestoProveedor';
                return true;
        }

        return false;
    }
}