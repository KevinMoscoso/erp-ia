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
use ERPIA\Dinamic\Model\Usuario;
use ERPIA\Dinamic\Model\ModeloCodigo;

final class Usuarios implements InterfazFuenteDatos
{
    /** @var Usuario[] */
    private static $lista;

    /** @return Usuario[] */
    public static function todos(): array
    {
        if (!isset(self::$lista)) {
            self::$lista = DataCache::recordar('lista-usuarios-modelo', function () {
                return Usuario::obtenerTodos([], ['nombreusuario' => 'ASC'], 0, 0);
            });
        }

        return self::$lista;
    }

    public static function limpiar(): void
    {
        self::$lista = null;
        DataCache::eliminar('lista-usuarios-modelo');
    }

    public static function modeloCodigo(bool $agregarVacio = true): array
    {
        $codigos = [];
        foreach (self::todos() as $usuario) {
            $codigos[$usuario->nombreusuario] = $usuario->nombreusuario;
        }

        return ModeloCodigo::arregloAModeloCodigo($codigos, $agregarVacio);
    }

    /**
     * @param string $codigo
     *
     * @return Usuario
     */
    public static function obtener($codigo): Usuario
    {
        foreach (self::todos() as $usuario) {
            if ($usuario->nombreusuario === $codigo) {
                return $usuario;
            }
        }

        return Usuario::buscar($codigo) ?? new Usuario();
    }

    /**
     * Verifica si existe un usuario con el nombre de usuario especificado
     */
    public static function existe(string $nombreUsuario): bool
    {
        foreach (self::todos() as $usuario) {
            if ($usuario->nombreusuario === $nombreUsuario) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtiene usuarios que están marcados como activos
     */
    public static function activos(): array
    {
        return array_filter(self::todos(), function ($usuario) {
            return $usuario->activo === true;
        });
    }

    /**
     * Obtiene usuarios por rol específico
     */
    public static function porRol(string $codigoRol): array
    {
        return array_filter(self::todos(), function ($usuario) use ($codigoRol) {
            return $usuario->codigorol === $codigoRol;
        });
    }

    /**
     * Obtiene usuarios por empresa específica
     */
    public static function porEmpresa(int $idEmpresa): array
    {
        return array_filter(self::todos(), function ($usuario) use ($idEmpresa) {
            return $usuario->idempresa === $idEmpresa;
        });
    }

    /**
     * Obtiene el usuario actualmente autenticado en la sesión
     */
    public static function actual(): ?Usuario
    {
        if (isset($_SESSION['usuario'])) {
            return self::obtener($_SESSION['usuario']);
        }
        return null;
    }

    /**
     * Obtiene usuarios que tienen un email verificado
     */
    public static function conEmailVerificado(): array
    {
        return array_filter(self::todos(), function ($usuario) {
            return !empty($usuario->email) && $usuario->emailverificado === true;
        });
    }

    /**
     * Obtiene usuarios por fecha de última conexión
     */
    public static function porUltimaConexion(int $dias = 30): array
    {
        $fechaLimite = date('Y-m-d', strtotime("-$dias days"));
        return array_filter(self::todos(), function ($usuario) use ($fechaLimite) {
            return $usuario->ultimaconexion >= $fechaLimite;
        });
    }
}