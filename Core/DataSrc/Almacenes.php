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
use ERPIA\Dinamic\Model\Almacen;
use ERPIA\Dinamic\Model\ModeloCodigo;

final class Almacenes implements InterfazFuenteDatos
{
    /** @var Almacen[] */
    private static $lista;

    /** @return Almacen[] */
    public static function todos(): array
    {
        if (!isset(self::$lista)) {
            self::$lista = DataCache::recordar('lista-almacenes-modelo', function () {
                return Almacen::obtenerTodos([], ['nombre' => 'ASC'], 0, 0);
            });
        }

        return self::$lista;
    }

    public static function limpiar(): void
    {
        self::$lista = null;
        DataCache::eliminar('lista-almacenes-modelo');
    }

    public static function modeloCodigo(bool $agregarVacio = true): array
    {
        $codigos = [];
        foreach (self::todos() as $almacen) {
            $codigos[$almacen->codigoalmacen] = $almacen->nombre;
        }

        return ModeloCodigo::arregloAModeloCodigo($codigos, $agregarVacio);
    }

    public static function predeterminado(): Almacen
    {
        $codigo = SystemConfig::obtener('inventario', 'almacenpredeterminado', '');
        return self::obtener($codigo);
    }

    /**
     * @param string $codigo
     *
     * @return Almacen
     */
    public static function obtener($codigo): Almacen
    {
        foreach (self::todos() as $item) {
            if ($item->obtenerCodigo() === $codigo) {
                return $item;
            }
        }

        return Almacen::buscar($codigo) ?? new Almacen();
    }

    /**
     * Verifica si existe un almacén con el código especificado
     */
    public static function existe(string $codigo): bool
    {
        foreach (self::todos() as $almacen) {
            if ($almacen->obtenerCodigo() === $codigo) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtiene almacenes activos solamente
     */
    public static function activos(): array
    {
        return array_filter(self::todos(), function ($almacen) {
            return $almacen->activo === true;
        });
    }

    /**
     * Obtiene almacenes asociados a una empresa específica
     */
    public static function porEmpresa(int $idEmpresa): array
    {
        return array_filter(self::todos(), function ($almacen) use ($idEmpresa) {
            return $almacen->idempresa === $idEmpresa;
        });
    }
}