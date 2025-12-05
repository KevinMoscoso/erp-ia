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
use ERPIA\Dinamic\Model\Impuesto;
use ERPIA\Dinamic\Model\ModeloCodigo;

final class Impuestos implements InterfazFuenteDatos
{
    /** @var Impuesto[] */
    private static $lista;

    /** @return Impuesto[] */
    public static function todos(): array
    {
        if (!isset(self::$lista)) {
            self::$lista = DataCache::recordar('lista-impuestos-modelo', function () {
                return Impuesto::obtenerTodos([], ['codigoimpuesto' => 'ASC'], 0, 0);
            });
        }

        return self::$lista;
    }

    public static function limpiar(): void
    {
        self::$lista = null;
        DataCache::eliminar('lista-impuestos-modelo');
    }

    public static function modeloCodigo(bool $agregarVacio = true): array
    {
        $codigos = [];
        foreach (self::todos() as $impuesto) {
            $codigos[$impuesto->codigoimpuesto] = $impuesto->descripcion;
        }

        return ModeloCodigo::arregloAModeloCodigo($codigos, $agregarVacio);
    }

    public static function predeterminado(): Impuesto
    {
        $codigo = SystemConfig::obtener('general', 'impuestopredeterminado', 'IVA21');
        return self::obtener($codigo);
    }

    /**
     * @param string $codigo
     *
     * @return Impuesto
     */
    public static function obtener($codigo): Impuesto
    {
        foreach (self::todos() as $item) {
            if ($item->obtenerCodigo() === $codigo) {
                return $item;
            }
        }

        return Impuesto::buscar($codigo) ?? new Impuesto();
    }

    /**
     * Verifica si existe un impuesto con el código especificado
     */
    public static function existe(string $codigo): bool
    {
        foreach (self::todos() as $impuesto) {
            if ($impuesto->obtenerCodigo() === $codigo) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtiene impuestos que están marcados como activos
     */
    public static function activos(): array
    {
        return array_filter(self::todos(), function ($impuesto) {
            return $impuesto->activo === true;
        });
    }

    /**
     * Obtiene impuestos por tipo (IVA, IRPF, etc.)
     */
    public static function porTipo(string $tipo): array
    {
        return array_filter(self::todos(), function ($impuesto) use ($tipo) {
            return $impuesto->tipo === $tipo;
        });
    }

    /**
     * Obtiene la tasa de impuesto por código
     */
    public static function tasaPorCodigo(string $codigoImpuesto): float
    {
        $impuesto = self::obtener($codigoImpuesto);
        return $impuesto->tasa ?? 0.0;
    }

    /**
     * Obtiene impuestos con una tasa mayor o igual a la especificada
     */
    public static function conTasaMayorOIgual(float $tasaMinima): array
    {
        return array_filter(self::todos(), function ($impuesto) use ($tasaMinima) {
            return $impuesto->tasa >= $tasaMinima;
        });
    }

    /**
     * Obtiene impuestos que son aplicables a ventas
     */
    public static function paraVentas(): array
    {
        return array_filter(self::todos(), function ($impuesto) {
            return $impuesto->paraventas === true;
        });
    }

    /**
     * Obtiene impuestos que son aplicables a compras
     */
    public static function paraCompras(): array
    {
        return array_filter(self::todos(), function ($impuesto) {
            return $impuesto->paracompras === true;
        });
    }
}