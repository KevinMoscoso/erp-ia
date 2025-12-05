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
use ERPIA\Dinamic\Model\Pais;
use ERPIA\Dinamic\Model\ModeloCodigo;

final class Paises implements InterfazFuenteDatos
{
    const MIEMBROS_UE = [
        'DE', 'AT', 'BE', 'BG', 'CZ', 'CY', 'HR', 'DK', 'SK', 'SI', 'EE', 'FI', 'FR', 'GR', 'HU', 'IE', 'IT', 'LV',
        'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'GB', 'ES'
    ];

    /** @var Pais[] */
    private static $lista;

    /** @return Pais[] */
    public static function todos(): array
    {
        if (!isset(self::$lista)) {
            self::$lista = DataCache::recordar('lista-paises-modelo', function () {
                return Pais::obtenerTodos([], ['nombre' => 'ASC'], 0, 0);
            });
        }

        return self::$lista;
    }

    public static function limpiar(): void
    {
        self::$lista = null;
        DataCache::eliminar('lista-paises-modelo');
    }

    public static function modeloCodigo(bool $agregarVacio = true): array
    {
        $codigos = [];
        foreach (self::todos() as $pais) {
            $codigos[$pais->codigopais] = $pais->nombre;
        }

        return ModeloCodigo::arregloAModeloCodigo($codigos, $agregarVacio);
    }

    public static function predeterminado(): Pais
    {
        $codigo = SystemConfig::obtener('general', 'paispredeterminado', 'ESP');
        return self::obtener($codigo);
    }

    /**
     * @param string $codigo
     *
     * @return Pais
     */
    public static function obtener($codigo): Pais
    {
        foreach (self::todos() as $item) {
            if ($item->obtenerCodigo() === $codigo) {
                return $item;
            }
        }

        return Pais::buscar($codigo) ?? new Pais();
    }

    /**
     * Verifica si un país es miembro de la Unión Europea por su código de país
     *
     * @param string $codigoPais Código del país (ej. 'ESP')
     * @return bool
     */
    public static function esMiembroUE(string $codigoPais): bool
    {
        $pais = self::obtener($codigoPais);
        $iso = $pais->codigoiso ?? '';
        return self::esMiembroUEPorISO($iso);
    }

    /**
     * Verifica si un país es miembro de la Unión Europea por su código ISO
     *
     * @param string $codigoISO Código ISO de dos letras (ej. 'ES')
     * @return bool
     */
    public static function esMiembroUEPorISO(string $codigoISO): bool
    {
        return in_array(strtoupper($codigoISO), self::MIEMBROS_UE, true);
    }

    /**
     * Verifica si existe un país con el código especificado
     */
    public static function existe(string $codigo): bool
    {
        foreach (self::todos() as $pais) {
            if ($pais->obtenerCodigo() === $codigo) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtiene países que están marcados como activos
     */
    public static function activos(): array
    {
        return array_filter(self::todos(), function ($pais) {
            return $pais->activo === true;
        });
    }

    /**
     * Obtiene países por continente específico
     */
    public static function porContinente(string $continente): array
    {
        return array_filter(self::todos(), function ($pais) use ($continente) {
            return $pais->continente === $continente;
        });
    }

    /**
     * Obtiene países que son miembros de la Unión Europea
     */
    public static function miembrosUE(): array
    {
        return array_filter(self::todos(), function ($pais) {
            return self::esMiembroUEPorISO($pais->codigoiso ?? '');
        });
    }

    /**
     * Obtiene el nombre de un país por su código
     */
    public static function nombrePorCodigo(string $codigoPais): string
    {
        $pais = self::obtener($codigoPais);
        return $pais->nombre ?? '';
    }

    /**
     * Obtiene el código ISO de un país por su código interno
     */
    public static function isoPorCodigo(string $codigoPais): string
    {
        $pais = self::obtener($codigoPais);
        return $pais->codigoiso ?? '';
    }
}