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

/**
 * Interfaz que define el contrato para las fuentes de datos del sistema.
 * Todas las clases que implementen esta interfaz deben proporcionar
 * métodos estáticos para acceder a listas de modelos con caché.
 */
interface InterfazFuenteDatos
{
    /**
     * Obtiene todos los elementos del modelo, utilizando caché para mejorar el rendimiento.
     *
     * @return array Lista de todos los elementos.
     */
    public static function todos(): array;

    /**
     * Limpia la caché de elementos, forzando una nueva carga en la siguiente solicitud.
     *
     * @return void
     */
    public static function limpiar(): void;

    /**
     * Genera un modelo de códigos para su uso en listas desplegables.
     *
     * @param bool $agregarVacio Si es true, agrega una opción vacía al principio.
     *
     * @return array Arreglo en formato de modelo de código.
     */
    public static function modeloCodigo(bool $agregarVacio = true): array;

    /**
     * Obtiene un elemento específico por su código.
     * Si no se encuentra, retorna una nueva instancia vacía del modelo.
     *
     * @param string $codigo Código del elemento a buscar.
     *
     * @return mixed Instancia del modelo encontrado o nueva instancia vacía.
     */
    public static function obtener($codigo);
}