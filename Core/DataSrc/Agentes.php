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
use ERPIA\Dinamic\Model\Agente;
use ERPIA\Dinamic\Model\ModeloCodigo;

final class Agentes implements InterfazFuenteDatos
{
    /** @var Agente[] */
    private static $lista;

    /** @return Agente[] */
    public static function todos(): array
    {
        if (!isset(self::$lista)) {
            self::$lista = DataCache::recordar('lista-agentes-modelo', function () {
                return Agente::obtenerTodos([], ['codigoagente' => 'ASC'], 0, 0);
            });
        }

        return self::$lista;
    }

    public static function limpiar(): void
    {
        self::$lista = null;
        DataCache::eliminar('lista-agentes-modelo');
    }

    public static function modeloCodigo(bool $agregarVacio = true): array
    {
        $codigos = [];
        foreach (self::todos() as $agente) {
            $codigos[$agente->codigoagente] = $agente->nombre;
        }

        return ModeloCodigo::arregloAModeloCodigo($codigos, $agregarVacio);
    }

    /**
     * @param string $codigo
     *
     * @return Agente
     */
    public static function obtener($codigo): Agente
    {
        foreach (self::todos() as $item) {
            if ($item->obtenerCodigo() === $codigo) {
                return $item;
            }
        }

        return Agente::buscar($codigo) ?? new Agente();
    }

    /**
     * Verifica si existe un agente con el código especificado
     */
    public static function existe(string $codigo): bool
    {
        foreach (self::todos() as $agente) {
            if ($agente->obtenerCodigo() === $codigo) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtiene agentes activos solamente
     */
    public static function activos(): array
    {
        return array_filter(self::todos(), function ($agente) {
            return $agente->activo === true;
        });
    }
}