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
use ERPIA\Dinamic\Model\FormaPago;
use ERPIA\Dinamic\Model\ModeloCodigo;

final class FormasPago implements InterfazFuenteDatos
{
    /** @var FormaPago[] */
    private static $lista;

    /** @return FormaPago[] */
    public static function todos(): array
    {
        if (!isset(self::$lista)) {
            self::$lista = DataCache::recordar('lista-formaspago-modelo', function () {
                return FormaPago::obtenerTodos([], ['codigopago' => 'ASC'], 0, 0);
            });
        }

        return self::$lista;
    }

    public static function limpiar(): void
    {
        self::$lista = null;
        DataCache::eliminar('lista-formaspago-modelo');
    }

    public static function modeloCodigo(bool $agregarVacio = true): array
    {
        $codigos = [];
        foreach (self::todos() as $formaPago) {
            $codigos[$formaPago->codigopago] = $formaPago->descripcion;
        }

        return ModeloCodigo::arregloAModeloCodigo($codigos, $agregarVacio);
    }

    public static function predeterminado(): FormaPago
    {
        $codigo = SystemConfig::obtener('general', 'formapagopredeterminada', '');
        return self::obtener($codigo);
    }

    /**
     * @param string $codigo
     *
     * @return FormaPago
     */
    public static function obtener($codigo): FormaPago
    {
        foreach (self::todos() as $item) {
            if ($item->obtenerCodigo() === $codigo) {
                return $item;
            }
        }

        return FormaPago::buscar($codigo) ?? new FormaPago();
    }

    /**
     * Verifica si existe una forma de pago con el código especificado
     */
    public static function existe(string $codigo): bool
    {
        foreach (self::todos() as $formaPago) {
            if ($formaPago->obtenerCodigo() === $codigo) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtiene formas de pago que están marcadas como activas
     */
    public static function activas(): array
    {
        return array_filter(self::todos(), function ($formaPago) {
            return $formaPago->activo === true;
        });
    }

    /**
     * Obtiene formas de pago que son para ventas (no solo compras)
     */
    public static function paraVentas(): array
    {
        return array_filter(self::todos(), function ($formaPago) {
            return $formaPago->paraventas === true;
        });
    }

    /**
     * Obtiene formas de pago que son para compras (no solo ventas)
     */
    public static function paraCompras(): array
    {
        return array_filter(self::todos(), function ($formaPago) {
            return $formaPago->paracompras === true;
        });
    }

    /**
     * Obtiene el vencimiento predeterminado de una forma de pago
     */
    public static function vencimiento(string $codigoFormaPago): int
    {
        $formaPago = self::obtener($codigoFormaPago);
        return $formaPago->vencimiento ?? 0;
    }

    /**
     * Obtiene formas de pago que generan asientos contables automáticamente
     */
    public static function conAsientoAutomatico(): array
    {
        return array_filter(self::todos(), function ($formaPago) {
            return $formaPago->generarasiento === true;
        });
    }
}