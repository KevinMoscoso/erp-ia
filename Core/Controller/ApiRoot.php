<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\KernelException;
use ERPIA\Core\Template\ApiController;
use ERPIA\Core\Tools;

class ApiRoot extends ApiController
{
    private static $recursos_personalizados = [
        'archivosadjuntos', 'crearAlbaranCliente', 'crearAlbaranProveedor', 'crearFacturaCliente', 'crearFacturaProveedor',
        'crearFacturaRectificativaCliente', 'crearPedidoCliente', 'crearPedidoProveedor', 'crearPresupuestoCliente',
        'crearPresupuestoProveedor', 'exportarAlbaranCliente', 'exportarAlbaranProveedor', 'exportarFacturaCliente',
        'exportarFacturaProveedor', 'exportarPedidoCliente', 'exportarPedidoProveedor', 'exportarPresupuestoCliente',
        'exportarPresupuestoProveedor', 'pagarFacturaCliente', 'pagarFacturaProveedor', 'plugins', 'imagenesproducto',
        'subirArchivos'
    ];

    public static function agregarRecursoPersonalizado(string $nombre): void
    {
        self::$recursos_personalizados[] = $nombre;
    }

    protected function exponerRecursos(array &$mapa): void
    {
        $json = ['recursos' => self::$recursos_personalizados];
        foreach (array_keys($mapa) as $clave) {
            $json['recursos'][] = $clave;
        }

        sort($json['recursos']);

        $this->respuesta->json($json);
    }

    public static function obtenerRecursosPersonalizados(): array
    {
        return self::$recursos_personalizados;
    }

    protected function obtenerMapaRecursos(): array
    {
        $recursos = [];

        $carpeta = Tools::carpeta('Dinamic', 'Lib', 'API');
        foreach (Tools::explorarCarpeta($carpeta, false) as $recurso) {
            if (substr($recurso, -4) !== '.php') {
                continue;
            }

            $clase = substr('\\ERPIA\\Dinamic\\Lib\\API\\' . $recurso, 0, -4);
            $claseAPI = new $clase($this->respuesta, $this->solicitud, []);
            if (isset($claseAPI) && method_exists($claseAPI, 'obtenerRecursos')) {
                $recursos[] = $claseAPI->obtenerRecursos();
            }
        }

        return array_merge(...$recursos);
    }

    protected function ejecutarRecurso(): void
    {
        $mapa = $this->obtenerMapaRecursos();

        $nombreRecurso = $this->obtenerParametroUri(2);
        if ($nombreRecurso === '') {
            $this->exponerRecursos($mapa);
            return;
        }

        if (!isset($mapa[$nombreRecurso]['API'])) {
            throw new KernelException('RecursoApiInvalido', Tools::trans('recurso-api-invalido'));
        }

        $parametro = 3;
        $parametros = [];
        while (($item = $this->obtenerParametroUri($parametro)) !== '') {
            $parametros[] = $item;
            $parametro++;
        }

        $claseAPI = new $mapa[$nombreRecurso]['API']($this->respuesta, $this->solicitud, $parametros);
        $claseAPI->procesarRecurso($mapa[$nombreRecurso]['Nombre']);
    }
}