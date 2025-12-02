<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Plugins;
use ERPIA\Core\Request;
use ERPIA\Core\Response;
use ERPIA\Core\Template\ApiController;

class ApiPlugins extends ApiController
{
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

        $plugins = Plugins::listar();

        $filtros = $this->solicitud->consulta->obtenerArray('filtro');
        $plugins = $this->aplicarFiltro($plugins, $filtros);

        $orden = $this->solicitud->consulta->obtenerArray('ordenar');
        $plugins = $this->aplicarOrden($plugins, $orden);

        $this->respuesta->json($plugins);
    }

    private function aplicarFiltro(array $plugins, $filtros): array
    {
        if (empty($filtros)) {
            return $plugins;
        }

        return array_filter($plugins, function ($plugin) use ($filtros) {
            foreach ($filtros as $clave => $valor) {
                $operador = '=';
                $campo = $clave;

                switch (substr($clave, -3)) {
                    case '_gt':
                        $campo = substr($clave, 0, -3);
                        $operador = '>';
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

                if (substr($clave, -5) === '_like') {
                    $campo = substr($clave, 0, -5);
                    $operador = 'COMO';
                }

                if (substr($clave, -5) === '_null') {
                    $campo = substr($clave, 0, -5);
                    $operador = 'ES';
                    $valor = null;
                } elseif (substr($clave, -8) === '_notnull') {
                    $campo = substr($clave, 0, -8);
                    $operador = 'NO ES';
                    $valor = null;
                }

                if (!property_exists($plugin, $campo)) {
                    return false;
                }

                $valorPlugin = $plugin->{$campo};
                if (!$this->comparar($valorPlugin, $valor, $operador)) {
                    return false;
                }
            }
            return true;
        });
    }

    private function comparar($a, $b, string $operador): bool
    {
        switch ($operador) {
            case '>':
                return $a > $b;
            case '<':
                return $a < $b;
            case '>=':
                return $a >= $b;
            case '<=':
                return $a <= $b;
            case '!=':
                return $a != $b;
            case 'COMO':
                return stripos((string)$a, (string)$b) !== false;
            case 'ES':
                return $a === null;
            case 'NO ES':
                return $a !== null;
            default:
                return $a == $b;
        }
    }

    private function aplicarOrden($plugins, $orden): array
    {
        if (empty($orden)) {
            return $plugins;
        }

        usort($plugins, function ($a, $b) use ($orden) {
            foreach ($orden as $clave => $valor) {
                if (!property_exists($a, $clave) || !property_exists($b, $clave)) {
                    continue;
                }

                $valorA = $a->{$clave};
                $valorB = $b->{$clave};

                if ($valorA === $valorB) {
                    continue;
                }

                if ($valor === 'DESC') {
                    return ($valorA < $valorB) ? 1 : -1;
                }
                return ($valorA < $valorB) ? -1 : 1;
            }
            return 0;
        });

        return $plugins;
    }
}