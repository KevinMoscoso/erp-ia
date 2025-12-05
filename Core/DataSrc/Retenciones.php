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
use ERPIA\Dinamic\Model\Retencion;
use ERPIA\Dinamic\Model\ModeloCodigo;

final class Retenciones implements InterfazFuenteDatos
{
    /** @var Retencion[] */
    private static $lista;

    /** @return Retencion[] */
    public static function todos(): array
    {
        if (!isset(self::$lista)) {
            self::$lista = DataCache::recordar('lista-retenciones-modelo', function () {
                return Retencion::obtenerTodos([], ['codigoretencion' => 'ASC'], 0, 0);
            });
        }

        return self::$lista;
    }

    public static function limpiar(): void
    {
        self::$lista = null;
        DataCache::eliminar('lista-retenciones-modelo');
    }

    public static function modeloCodigo(bool $agregarVacio = true): array
    {
        $codigos = [];
        foreach (self::todos() as $retencion) {
            $codigos[$retencion->codigoretencion] = $retencion->descripcion;
        }

        return ModeloCodigo::arregloAModeloCodigo($codigos, $agregarVacio);
    }

    public static function predeterminado(): Retencion
    {
        $codigo = SystemConfig::obtener('general', 'retencionpredeterminada', '');
        return self::obtener($codigo);
    }

    /**
     * @param string $codigo
     *
     * @return Retencion
     */
    public static function obtener($codigo): Retencion
    {
        foreach (self::todos() as $item) {
            if ($item->obtenerCodigo() === $codigo) {
                return $item;
            }
        }

        return Retencion::buscar($codigo) ?? new Retencion();
    }

    /**
     * Verifica si existe una retención con el código especificado
     */
    public static function existe(string $codigo): bool
    {
        foreach (self::todos() as $retencion) {
            if ($retencion->obtenerCodigo() === $codigo) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtiene retenciones que están marcadas como activas
     */
    public static function activas(): array
    {
        return array_filter(self::todos(), function ($retencion) {
            return $retencion->activo === true;
        });
    }

    /**
     * Obtiene retenciones por tipo (IRPF, etc.)
     */
    public static function porTipo(string $tipo): array
    {
        return array_filter(self::todos(), function ($retencion) use ($tipo) {
            return $retencion->tipo === $tipo;
        });
    }

    /**
     * Obtiene la tasa de retención por código
     */
    public static function tasaPorCodigo(string $codigoRetencion): float
    {
        $retencion = self::obtener($codigoRetencion);
        return $retencion->tasa ?? 0.0;
    }

    /**
     * Obtiene retenciones con una tasa mayor o igual a la especificada
     */
    public static function conTasaMayorOIgual(float $tasaMinima): array
    {
        return array_filter(self::todos(), function ($retencion) use ($tasaMinima) {
            return $retencion->tasa >= $tasaMinima;
        });
    }

    /**
     * Obtiene retenciones que son aplicables a proveedores
     */
    public static function paraProveedores(): array
    {
        return array_filter(self::todos(), function ($retencion) {
            return $retencion->paraproveedores === true;
        });
    }

    /**
     * Obtiene retenciones que son aplicables a clientes
     */
    public static function paraClientes(): array
    {
        return array_filter(self::todos(), function ($retencion) {
            return $retencion->paraclientes === true;
        });
    }

    /**
     * Obtiene retenciones que requieren certificado de retención
     */
    public static function conCertificado(): array
    {
        return array_filter(self::todos(), function ($retencion) {
            return $retencion->requierecertificado === true;
        });
    }
}