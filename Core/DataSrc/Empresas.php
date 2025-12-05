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
use ERPIA\Dinamic\Model\Empresa;
use ERPIA\Dinamic\Model\ModeloCodigo;

final class Empresas implements InterfazFuenteDatos
{
    /** @var Empresa[] */
    private static $lista;

    /**
     * @return Empresa[]
     */
    public static function todos(): array
    {
        if (!isset(self::$lista)) {
            self::$lista = DataCache::recordar('lista-empresas-modelo', function () {
                return Empresa::obtenerTodos([], ['nombre' => 'ASC'], 0, 0);
            });
        }

        return self::$lista;
    }

    public static function limpiar(): void
    {
        self::$lista = null;
        DataCache::eliminar('lista-empresas-modelo');
    }

    public static function modeloCodigo(bool $agregarVacio = true): array
    {
        $codigos = [];
        foreach (self::todos() as $empresa) {
            $codigos[$empresa->idempresa] = $empresa->nombre;
        }

        return ModeloCodigo::arregloAModeloCodigo($codigos, $agregarVacio);
    }

    public static function predeterminado(): Empresa
    {
        $id = SystemConfig::obtener('general', 'idempresa', 1);
        return self::obtener($id);
    }

    /**
     * @param string|int $codigo
     *
     * @return Empresa
     */
    public static function obtener($codigo): Empresa
    {
        // Convertir a entero si es numérico para comparación estricta
        $codigoBuscado = is_numeric($codigo) ? (int)$codigo : $codigo;
        
        foreach (self::todos() as $item) {
            $idItem = $item->obtenerId();
            if ($idItem === $codigoBuscado) {
                return $item;
            }
        }

        return Empresa::buscar($codigo) ?? new Empresa();
    }

    /**
     * Verifica si existe una empresa con el ID especificado
     */
    public static function existe($id): bool
    {
        $idBuscado = is_numeric($id) ? (int)$id : $id;
        
        foreach (self::todos() as $empresa) {
            if ($empresa->obtenerId() === $idBuscado) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtiene empresas que están marcadas como activas
     */
    public static function activas(): array
    {
        return array_filter(self::todos(), function ($empresa) {
            return $empresa->activo === true;
        });
    }

    /**
     * Obtiene empresas por país específico
     */
    public static function porPais(string $codigoPais): array
    {
        return array_filter(self::todos(), function ($empresa) use ($codigoPais) {
            return $empresa->codpais === $codigoPais;
        });
    }

    /**
     * Obtiene la empresa actualmente seleccionada en la sesión
     */
    public static function actual(): Empresa
    {
        // Primero intentamos obtener de la sesión
        if (isset($_SESSION['idempresa'])) {
            $empresaSesion = self::obtener($_SESSION['idempresa']);
            if ($empresaSesion->existe()) {
                return $empresaSesion;
            }
        }
        
        // Si no hay en sesión, retornamos la predeterminada
        return self::predeterminado();
    }

    /**
     * Obtiene el nombre de una empresa por su ID
     */
    public static function nombrePorId($id): string
    {
        $empresa = self::obtener($id);
        return $empresa->nombre ?? '';
    }
}