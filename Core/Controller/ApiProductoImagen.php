<?php

namespace ERPIA\Core\Controller;

use Exception;
use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Response;
use ERPIA\Core\Template\ApiController;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\ProductoImagen;

class ApiProductoImagen extends ApiController
{
    protected $modelo;

    protected function ejecutarRecurso(): void
    {
        $this->modelo = new ProductoImagen();

        try {
            switch ($this->solicitud->metodo()) {
                case 'DELETE':
                    $this->realizarDELETE();
                    return;
                case 'GET':
                    $this->realizarGET();
                    return;
                case 'PATCH':
                case 'PUT':
                    $this->realizarPUT();
                    return;
                case 'POST':
                    $this->realizarPOST();
                    return;
            }
        } catch (Exception $excepcion) {
            $this->establecerError('ERROR-API: ' . $excepcion->getMessage(), null, Response::HTTP_SERVER_ERROR_INTERNO);
        }

        $this->respuesta
            ->establecerCodigoHttp(Response::HTTP_METODO_NO_PERMITIDO)
            ->json([
                'estado' => 'error',
                'mensaje' => 'metodo-no-permitido',
            ]);
    }

    public function realizarDELETE(): void
    {
        if (empty($this->obtenerParametroUri(3)) || $this->modelo->cargar($this->obtenerParametroUri(3)) === false) {
            $this->establecerError(Tools::idioma()->trans('registro-no-encontrado'), null, Response::HTTP_NO_ENCONTRADO);
            return;
        }

        if ($this->modelo->eliminar() === false) {
            $this->establecerError(Tools::idioma()->trans('error-eliminar-registro'));
            return;
        }

        $this->establecerExito(Tools::idioma()->trans('registro-eliminado-correctamente'), $this->modelo->aArray());
    }

    public function realizarGET(): void
    {
        if (empty($this->obtenerParametroUri(3))) {
            $this->listarTodos();
            return;
        }

        if ($this->obtenerParametroUri(3) === 'esquema') {
            $datos = [];
            foreach ($this->modelo->obtenerCamposModelo() as $clave => $valor) {
                $datos[$clave] = [
                    'tipo' => $valor['tipo'],
                    'predeterminado' => $valor['predeterminado'],
                    'nulable' => $valor['nulable']
                ];
            }
            $this->devolverResultado($datos);
            return;
        }

        if ($this->modelo->cargar($this->obtenerParametroUri(3)) === false) {
            $this->establecerError(Tools::idioma()->trans('registro-no-encontrado'), null, Response::HTTP_NO_ENCONTRADO);
            return;
        }

        $datos = $this->modelo->aArray();
        $datos['descargar'] = $this->modelo->url('descargar');
        $datos['descargar-permanente'] = $this->modelo->url('descargar-permanente');
        $this->devolverResultado($datos);
    }

    public function realizarPOST(): void
    {
        $campoClave = $this->modelo->columnaPrincipal();
        $valores = $this->solicitud->solicitud->todo();
        $archivos = $this->solicitud->archivos->todo();

        $parametro0 = empty($this->obtenerParametroUri(3)) ? '' : $this->obtenerParametroUri(3);
        $codigo = $valores[$campoClave] ?? $parametro0;
        if ($this->modelo->cargar($codigo)) {
            $this->establecerError(Tools::idioma()->trans('registro-duplicado'), $this->modelo->aArray());
            return;
        } elseif (empty($valores) && empty($archivos)) {
            $this->establecerError(Tools::idioma()->trans('sin-datos-recibidos-formulario'));
            return;
        }

        foreach ($this->solicitud->archivos->todo() as $archivo) {
            if (!$archivo->esValido()) {
                continue;
            }
            if ($archivo->extension() === 'php') {
                continue;
            }
            $archivo->mover('MisArchivos', $archivo->obtenerNombreOriginal());
            $this->modelo->ruta = $archivo->obtenerNombreOriginal();
        }
        foreach ($valores as $clave => $valor) {
            $this->modelo->{$clave} = $valor;
        }

        $this->guardarRecurso();
    }

    public function realizarPUT(): void
    {
        $campoClave = $this->modelo->columnaPrincipal();
        $valores = $this->solicitud->solicitud->todo();

        $parametro0 = empty($this->obtenerParametroUri(3)) ? '' : $this->obtenerParametroUri(3);
        $codigo = $valores[$campoClave] ?? $parametro0;
        if ($this->modelo->cargar($codigo) === false) {
            $this->establecerError(Tools::idioma()->trans('registro-no-encontrado'), null, Response::HTTP_NO_ENCONTRADO);
            return;
        } elseif (empty($valores)) {
            $this->establecerError(Tools::idioma()->trans('sin-datos-recibidos-formulario'));
            return;
        }

        foreach ($valores as $clave => $valor) {
            $this->modelo->{$clave} = $valor;
        }

        $this->guardarRecurso();
    }

    private function obtenerValoresWhere($filtro, $operacion, $operacionPredeterminada = 'Y'): array
    {
        $where = [];
        foreach ($filtro as $clave => $valor) {
            $campo = $clave;
            $operador = '=';

            switch (substr($clave, -3)) {
                case '_gt':
                    $campo = substr($clave, 0, -3);
                    $operador = '>';
                    break;
                case '_is':
                    $campo = substr($clave, 0, -3);
                    $operador = 'ES';
                    break;
                case '_lt':
                    $campo = substr($clave, 0, -3);
                    $operador = '<';
                    break;
            }

            switch (substr($clave, -4)) {
                case '_gte':
                    $campo = substr($clave, 0, -4);
                    $operador = '>=';
                    break;
                case '_lte':
                    $campo = substr($clave, 0, -4);
                    $operador = '<=';
                    break;
                case '_neq':
                    $campo = substr($clave, 0, -4);
                    $operador = '!=';
                    break;
            }

            if (substr($clave, -5) == '_null') {
                $campo = substr($clave, 0, -5);
                $operador = 'ES';
                $valor = null;
            } elseif (substr($clave, -8) == '_notnull') {
                $campo = substr($clave, 0, -8);
                $operador = 'NO ES';
                $valor = null;
            }

            if (substr($clave, -5) == '_like') {
                $campo = substr($clave, 0, -5);
                $operador = 'COMO';
            } elseif (substr($clave, -6) == '_isnot') {
                $campo = substr($clave, 0, -6);
                $operador = 'NO ES';
            }

            if (!isset($operacion[$clave])) {
                $operacion[$clave] = $operacionPredeterminada;
            }

            $where[] = new DataBaseWhere($campo, $valor, $operador, $operacion[$clave]);
        }

        return $where;
    }

    protected function listarTodos(): void
    {
        $filtro = $this->solicitud->consulta->obtenerArray('filtro');
        $limite = $this->solicitud->consulta->obtenerInt('limite', 50);
        $desplazamiento = $this->solicitud->consulta->obtenerInt('desplazamiento', 0);
        $operacion = $this->solicitud->consulta->obtenerArray('operacion');
        $orden = $this->solicitud->consulta->obtenerArray('ordenar');

        $where = $this->obtenerValoresWhere($filtro, $operacion);
        $datos = [];
        foreach ($this->modelo->todo($where, $orden, $desplazamiento, $limite) as $item) {
            $crudo = $item->aArray();
            $crudo['descargar'] = $item->url('descargar');
            $crudo['descargar-permanente'] = $item->url('descargar-permanente');
            $datos[] = $crudo;
        }

        $total = $this->modelo->contar($where);
        $this->respuesta->encabezado('X-Total-Count', $total);

        $this->devolverResultado($datos);
    }

    protected function devolverResultado(array $datos): void
    {
        $this->respuesta
            ->establecerCodigoHttp(Response::HTTP_OK)
            ->json($datos);
    }

    private function guardarRecurso(): void
    {
        if ($this->modelo->guardar()) {
            $this->establecerExito(Tools::idioma()->trans('registro-actualizado-correctamente'), $this->modelo->aArray());
            return;
        }

        $mensaje = Tools::idioma()->trans('error-guardar-registro');
        foreach (Tools::registro()->leer('', ['critico', 'error', 'info', 'noticia', 'advertencia']) as $log) {
            $mensaje .= ' - ' . $log['mensaje'];
        }

        $this->establecerError($mensaje, $this->modelo->aArray());
    }

    protected function establecerError(string $mensaje, ?array $datos = null, int $estado = Response::HTTP_SOLICITUD_INCORRECTA): void
    {
        Tools::registro('api')->error($mensaje);

        $respuesta = ['error' => $mensaje];
        if ($datos !== null) {
            $respuesta['datos'] = $datos;
        }

        $this->respuesta
            ->establecerCodigoHttp($estado)
            ->json($respuesta);
    }

    protected function establecerExito(string $mensaje, ?array $datos = null): void
    {
        Tools::registro('api')->noticia($mensaje);

        $respuesta = ['exito' => $mensaje];
        if ($datos !== null) {
            $respuesta['datos'] = $datos;
        }

        $this->respuesta
            ->establecerCodigoHttp(Response::HTTP_OK)
            ->json($respuesta);
    }
}