<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Lib\Calculator;
use ERPIA\Core\Response;
use ERPIA\Core\Template\ApiController;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\FacturaCliente;

class ApiCreateFacturaRectificativaCliente extends ApiController
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

        $camposObligatorios = ['idfactura', 'fecha'];
        foreach ($camposObligatorios as $campo) {
            $valor = $this->solicitud->entrada($campo);
            if (empty($valor)) {
                $this->respuesta
                    ->establecerCodigoHttp(Response::HTTP_SOLICITUD_INCORRECTA)
                    ->json([
                        'estado' => 'error',
                        'mensaje' => 'El campo ' . $campo . ' es obligatorio',
                    ]);
                return;
            }
        }

        $facturaRectificativa = $this->accionNuevaRectificativa();
        if ($facturaRectificativa) {
            $this->respuesta->json([
                'documento' => $facturaRectificativa->aArray(),
                'lineas' => $facturaRectificativa->obtenerLineas(),
            ]);
        }
    }

    protected function accionNuevaRectificativa(): ?FacturaCliente
    {
        $facturaOriginal = new FacturaCliente();
        $codigo = $this->solicitud->entrada('idfactura');
        if (empty($codigo) || $facturaOriginal->cargar($codigo) === false) {
            $this->enviarError('registro-no-encontrado', Response::HTTP_NO_ENCONTRADO);
            return null;
        }

        $lineasARectificar = [];
        $lineasOriginales = $facturaOriginal->obtenerLineas();
        foreach ($lineasOriginales as $linea) {
            $cantidad = (float)$this->solicitud->entrada('devolucion_' . $linea->idlinea, '0');
            if (!empty($cantidad)) {
                $lineasARectificar[] = $linea;
            }
        }

        if (empty($lineasARectificar)) {
            $lineasARectificar = $lineasOriginales;
        }

        $this->baseDatos()->iniciarTransaccion();

        if ($facturaOriginal->editable) {
            foreach ($facturaOriginal->obtenerEstadosDisponibles() as $estado) {
                if ($estado->editable || !$estado->activo) {
                    continue;
                }

                $facturaOriginal->idestado = $estado->idestado;
                if ($facturaOriginal->guardar() === false) {
                    $this->enviarError('error-guardar-registro', Response::HTTP_SERVER_ERROR_INTERNO);
                    $this->baseDatos()->revertirTransaccion();
                    return null;
                }
            }
        }

        $nuevaRectificativa = new FacturaCliente();
        $nuevaRectificativa->cargarDesdeArray($facturaOriginal->aArray(), $facturaOriginal::camposNoCopiar());
        $nuevaRectificativa->codigorect = $facturaOriginal->codigo;
        $nuevaRectificativa->codserie = $this->solicitud->entrada('codserie') ?? $facturaOriginal->codserie;
        $nuevaRectificativa->idfacturarect = $facturaOriginal->idfactura;
        $nuevaRectificativa->nick = $this->solicitud->entrada('nick');
        $nuevaRectificativa->observaciones = $this->solicitud->entrada('observaciones');

        $fecha = $this->solicitud->entrada('fecha');
        $hora = $this->solicitud->entrada('hora');
        if ($nuevaRectificativa->establecerFecha($fecha, $hora) === false) {
            $this->enviarError('error-establecer-fecha', Response::HTTP_SOLICITUD_INCORRECTA);
            $this->baseDatos()->revertirTransaccion();
            return null;
        }

        if ($nuevaRectificativa->guardar() === false) {
            $this->enviarError('error-guardar-registro', Response::HTTP_SERVER_ERROR_INTERNO);
            $this->baseDatos()->revertirTransaccion();
            return null;
        }

        foreach ($lineasARectificar as $linea) {
            $nuevaLinea = $nuevaRectificativa->obtenerNuevaLinea($linea->aArray());
            $nuevaLinea->cantidad = 0 - (float)$this->solicitud->entrada('devolucion_' . $linea->idlinea, $linea->cantidad);
            $nuevaLinea->idlinearect = $linea->idlinea;
            if ($nuevaLinea->guardar() === false) {
                $this->enviarError('error-guardar-registro', Response::HTTP_SERVER_ERROR_INTERNO);
                $this->baseDatos()->revertirTransaccion();
                return null;
            }
        }

        $nuevasLineas = $nuevaRectificativa->obtenerLineas();
        $nuevaRectificativa->idestado = $facturaOriginal->idestado;
        if (Calculator::calcular($nuevaRectificativa, $nuevasLineas, true) === false) {
            $this->enviarError('error-guardar-registro', Response::HTTP_SERVER_ERROR_INTERNO);
            $this->baseDatos()->revertirTransaccion();
            return null;
        }

        if ($facturaOriginal->pagada) {
            foreach ($nuevaRectificativa->obtenerRecibos() as $recibo) {
                $recibo->pagado = true;
                $recibo->guardar();
            }
        }

        $nuevaRectificativa->idestado = $this->solicitud->entrada('idestado');
        if ($nuevaRectificativa->guardar() === false) {
            $this->enviarError('error-guardar-registro', Response::HTTP_SERVER_ERROR_INTERNO);
            $this->baseDatos()->revertirTransaccion();
            return null;
        }

        $this->baseDatos()->confirmarTransaccion();
        Tools::registro()->noticia('registro-actualizado-correctamente');

        return $nuevaRectificativa;
    }

    private function enviarError(string $mensaje, int $codigo_http): void
    {
        $this->respuesta
            ->establecerCodigoHttp($codigo_http)
            ->json([
                'estado' => 'error',
                'mensaje' => Tools::trans($mensaje),
            ]);
    }
}