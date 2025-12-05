<?php
/**
 * Este archivo es parte de ERPIA
 * Copyright (C) 2025 Proyecto ERPIA
 *
 * Este programa es software libre: puedes redistribuirlo y/o modificarlo
 * bajo los términos de la Licencia Pública General Reducida de GNU como
 * publicada por la Free Software Foundation, ya sea la versión 3 de la
 * Licencia, o (a tu elección) cualquier versión posterior.
 *
 * Este programa se distribuye con la esperanza de que sea útil,
 * pero SIN NINGUNA GARANTÍA; sin siquiera la garantía implícita de
 * COMERCIALIZACIÓN o IDONEIDAD PARA UN PROPÓSITO PARTICULAR. Consulta la
 * Licencia Pública General Reducida de GNU para más detalles.
 *
 * Deberías haber recibido una copia de la Licencia Pública General Reducida de GNU
 * junto con este programa. Si no es así, consulta <http://www.gnu.org/licenses/>.
 */

namespace ERPIA\Core\DataSrc;

use ERPIA\Core\Cache\DataCache;
use ERPIA\Core\Config\SystemConfig;
use ERPIA\Dinamic\Model\Serie;
use ERPIA\Dinamic\Model\ModeloCodigo;

final class Series implements InterfazFuenteDatos
{
    /** @var Serie[] */
    private static $lista;

    /** @return Serie[] */
    public static function todos(): array
    {
        if (!isset(self::$lista)) {
            self::$lista = DataCache::recordar('lista-series-modelo', function () {
                return Serie::obtenerTodos([], ['codigoserie' => 'ASC'], 0, 0);
            });
        }

        return self::$lista;
    }

    public static function limpiar(): void
    {
        self::$lista = null;
        DataCache::eliminar('lista-series-modelo');
    }

    public static function modeloCodigo(bool $agregarVacio = true): array
    {
        $codigos = [];
        foreach (self::todos() as $serie) {
            $codigos[$serie->codigoserie] = $serie->descripcion;
        }

        return ModeloCodigo::arregloAModeloCodigo($codigos, $agregarVacio);
    }

    public static function predeterminado(): Serie
    {
        $codigo = SystemConfig::obtener('general', 'seriepredeterminada', 'A');
        return self::obtener($codigo);
    }

    /**
     * @param string $codigo
     *
     * @return Serie
     */
    public static function obtener($codigo): Serie
    {
        foreach (self::todos() as $item) {
            if ($item->obtenerCodigo() === $codigo) {
                return $item;
            }
        }

        return Serie::buscar($codigo) ?? new Serie();
    }

    /**
     * Verifica si existe una serie con el código especificado
     */
    public static function existe(string $codigo): bool
    {
        foreach (self::todos() as $serie) {
            if ($serie->obtenerCodigo() === $codigo) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtiene series que están marcadas como activas
     */
    public static function activas(): array
    {
        return array_filter(self::todos(), function ($serie) {
            return $serie->activo === true;
        });
    }

    /**
     * Obtiene series por tipo (facturas, albaranes, pedidos, etc.)
     */
    public static function porTipo(string $tipo): array
    {
        return array_filter(self::todos(), function ($serie) use ($tipo) {
            return $serie->tipo === $tipo;
        });
    }

    /**
     * Obtiene series que son para ventas (facturas de cliente)
     */
    public static function paraVentas(): array
    {
        return array_filter(self::todos(), function ($serie) {
            return $serie->paraventas === true;
        });
    }

    /**
     * Obtiene series que son para compras (facturas de proveedor)
     */
    public static function paraCompras(): array
    {
        return array_filter(self::todos(), function ($serie) {
            return $serie->paracompras === true;
        });
    }

    /**
     * Obtiene el número inicial para una serie
     */
    public static function numeroInicial(string $codigoSerie): int
    {
        $serie = self::obtener($codigoSerie);
        return $serie->numeroinicial ?? 1;
    }

    /**
     * Obtiene series que generan asientos contables automáticamente
     */
    public static function conAsientoAutomatico(): array
    {
        return array_filter(self::todos(), function ($serie) {
            return $serie->generarasiento === true;
        });
    }
}