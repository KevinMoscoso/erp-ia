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

namespace ERPIA\Core\Error;

use ERPIA\Core\Template\ErrorController;
use ERPIA\Core\Traduccion;

class DatabaseError extends ErrorController
{
    /**
     * Ejecuta el controlador de error para errores de base de datos.
     * Limpia el buffer, establece el código HTTP 500 y muestra una página de error.
     */
    public function run(): void
    {
        ob_clean();
        http_response_code(500);

        $titulo = '⚠️ ' . Traduccion::obtener('error-base-datos');
        $contenido = '<div class="card shadow mb-4">'
            . '<div class="card-body">'
            . '<h1 class="h3">' . $titulo . '</h1>'
            . '<p>' . ($this->excepcion ? $this->excepcion->getMessage() : '') . '</p>'
            . '<p>' . Traduccion::obtener('error-base-datos-pc') . '</p>'
            . '<p class="mb-0">' . Traduccion::obtener('error-base-datos-servidor') . '</p>'
            . '</div>'
            . '<div class="card-footer">'
            . '<a href="https://erpia.org/documentacion/error-conexion-base-datos" class="btn btn-secondary" target="_blank" rel="nofollow">'
            . Traduccion::obtener('leer-mas')
            . '</a>'
            . '</div>'
            . '</div>';

        echo $this->generarHtml(
            $titulo,
            $this->contenedorHtml($contenido)
        );
    }
}