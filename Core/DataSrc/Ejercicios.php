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
use ERPIA\Dinamic\Model\Ejercicio;
use ERPIA\Dinamic\Model\ModeloCodigo;

final class Ejercicios implements InterfazFuenteDatos
{
    /** @var Ejercicio[] */
    private static $lista;

    /** @return Ejercicio[] */
    public static function todos(): array
    {
        if (!isset(self::$lista)) {
            self::$lista = DataCache::recordar('lista-ejercicios-modelo', function () {
                return Ejercicio::obtenerTodos([], ['codigoejercicio' => 'ASC'], 0, 0);
            });
        }

        return self::$lista;
    }

    public static function limpiar(): void
    {
        self::$lista = null;
        DataCache::eliminar('lista-ejercicios-modelo');
    }

    public static function modeloCodigo(bool $agregarVacio = true): array
    {
        $codigos = [];
        foreach (self::todos() as $ejercicio) {
            $codigos[$ejercicio->codigoejercicio] = $ejercicio->nombre;
        }

        return ModeloCodigo::arregloAModeloCodigo($codigos, $agregarVacio);
    }

    /**
     * @param string $codigo
     *
     * @return Ejercicio
     */
    public static function obtener($codigo): Ejercicio
    {
        foreach (self::todos() as $item) {
            if ($item->obtenerCodigo() === $codigo) {
                return $item;
            }
        }

        return Ejercicio::buscar($codigo) ?? new Ejercicio();
    }

    /**
     * Verifica si existe un ejercicio con el código especificado
     */
    public static function existe(string $codigo): bool
    {
        foreach (self::todos() as $ejercicio) {
            if ($ejercicio->obtenerCodigo() === $codigo) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtiene ejercicios que están marcados como activos
     */
    public static function activos(): array
    {
        return array_filter(self::todos(), function ($ejercicio) {
            return $ejercicio->estado === 'abierto' || $ejercicio->activo === true;
        });
    }

    /**
     * Obtiene el ejercicio actual basado en la fecha del sistema
     */
    public static function actual(): ?Ejercicio
    {
        $fechaActual = date('Y-m-d');
        foreach (self::activos() as $ejercicio) {
            if ($ejercicio->fechainicio <= $fechaActual && $ejercicio->fechafin >= $fechaActual) {
                return $ejercicio;
            }
        }
        return null;
    }

    /**
     * Obtiene ejercicios por año específico
     */
    public static function porAnio(int $anio): array
    {
        return array_filter(self::todos(), function ($ejercicio) use ($anio) {
            return date('Y', strtotime($ejercicio->fechainicio)) == $anio;
        });
    }
}