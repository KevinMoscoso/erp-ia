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
use ERPIA\Dinamic\Model\GrupoClientes;
use ERPIA\Dinamic\Model\ModeloCodigo;

final class GruposClientes implements InterfazFuenteDatos
{
    /** @var GrupoClientes[] */
    private static $lista;

    /**
     * @return GrupoClientes[]
     */
    public static function todos(): array
    {
        if (!isset(self::$lista)) {
            self::$lista = DataCache::recordar('lista-gruposclientes-modelo', function () {
                return GrupoClientes::obtenerTodos([], ['nombre' => 'ASC'], 0, 0);
            });
        }

        return self::$lista;
    }

    public static function limpiar(): void
    {
        self::$lista = null;
        DataCache::eliminar('lista-gruposclientes-modelo');
    }

    public static function modeloCodigo(bool $agregarVacio = true): array
    {
        $codigos = [];
        foreach (self::todos() as $grupo) {
            $codigos[$grupo->codigogrupo] = $grupo->nombre;
        }

        return ModeloCodigo::arregloAModeloCodigo($codigos, $agregarVacio);
    }

    /**
     * @param string $codigo
     *
     * @return GrupoClientes
     */
    public static function obtener($codigo): GrupoClientes
    {
        foreach (self::todos() as $item) {
            if ($item->obtenerCodigo() === $codigo) {
                return $item;
            }
        }

        return GrupoClientes::buscar($codigo) ?? new GrupoClientes();
    }

    /**
     * Verifica si existe un grupo de clientes con el código especificado
     */
    public static function existe(string $codigo): bool
    {
        foreach (self::todos() as $grupo) {
            if ($grupo->obtenerCodigo() === $codigo) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtiene grupos de clientes que están marcados como activos
     */
    public static function activos(): array
    {
        return array_filter(self::todos(), function ($grupo) {
            return $grupo->activo === true;
        });
    }

    /**
     * Obtiene grupos de clientes con un descuento específico o mayor
     */
    public static function conDescuento(float $descuentoMinimo = 0.0): array
    {
        return array_filter(self::todos(), function ($grupo) use ($descuentoMinimo) {
            return $grupo->descuento >= $descuentoMinimo;
        });
    }

    /**
     * Obtiene el descuento aplicable para un grupo de clientes
     */
    public static function descuentoPorGrupo(string $codigoGrupo): float
    {
        $grupo = self::obtener($codigoGrupo);
        return $grupo->descuento ?? 0.0;
    }

    /**
     * Obtiene grupos de clientes por tipo (empresa, particular, etc.)
     */
    public static function porTipo(string $tipo): array
    {
        return array_filter(self::todos(), function ($grupo) use ($tipo) {
            return $grupo->tipo === $tipo;
        });
    }
}