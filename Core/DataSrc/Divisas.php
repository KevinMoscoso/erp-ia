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
use ERPIA\Dinamic\Model\Divisa;
use ERPIA\Dinamic\Model\ModeloCodigo;

final class Divisas implements InterfazFuenteDatos
{
    /** @var Divisa[] */
    private static $lista;

    /** @return Divisa[] */
    public static function todos(): array
    {
        if (!isset(self::$lista)) {
            self::$lista = DataCache::recordar('lista-divisas-modelo', function () {
                return Divisa::obtenerTodos([], ['codigodivisa' => 'ASC'], 0, 0);
            });
        }

        return self::$lista;
    }

    public static function limpiar(): void
    {
        self::$lista = null;
        DataCache::eliminar('lista-divisas-modelo');
    }

    public static function modeloCodigo(bool $agregarVacio = true): array
    {
        $codigos = [];
        foreach (self::todos() as $divisa) {
            $codigos[$divisa->codigodivisa] = $divisa->descripcion;
        }

        return ModeloCodigo::arregloAModeloCodigo($codigos, $agregarVacio);
    }

    public static function predeterminado(): Divisa
    {
        $codigo = SystemConfig::obtener('general', 'divisapredeterminada', 'EUR');
        return self::obtener($codigo);
    }

    /**
     * @param string $codigo
     *
     * @return Divisa
     */
    public static function obtener($codigo): Divisa
    {
        foreach (self::todos() as $item) {
            if ($item->obtenerCodigo() === $codigo) {
                return $item;
            }
        }

        return Divisa::buscar($codigo) ?? new Divisa();
    }

    /**
     * Verifica si existe una divisa con el código especificado
     */
    public static function existe(string $codigo): bool
    {
        foreach (self::todos() as $divisa) {
            if ($divisa->obtenerCodigo() === $codigo) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtiene divisas que están marcadas como activas
     */
    public static function activas(): array
    {
        return array_filter(self::todos(), function ($divisa) {
            return $divisa->activa === true;
        });
    }

    /**
     * Obtiene el símbolo de una divisa por su código
     */
    public static function simbolo(string $codigoDivisa): string
    {
        $divisa = self::obtener($codigoDivisa);
        return $divisa->simbolo ?? '';
    }

    /**
     * Obtiene la tasa de cambio predeterminada de una divisa
     */
    public static function tasaCambio(string $codigoDivisa): float
    {
        $divisa = self::obtener($codigoDivisa);
        return $divisa->tasacambio ?? 1.0;
    }
}