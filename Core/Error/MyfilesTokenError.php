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

class MyfilesTokenError extends ErrorController
{
    /**
     * Ejecuta el controlador de error para token de archivos inválido.
     * Establece el código HTTP 403 y muestra un mensaje de error.
     */
    public function run(): void
    {
        http_response_code(403);

        $titulo = '🔐 ' . Traduccion::obtener('token-invalido');
        $contenido = '<h1>' . $titulo . '</h1>'
            . '<p>' . Traduccion::obtener('token-invalido-archivo', ['%archivo%' => $this->excepcion ? $this->excepcion->getMessage() : '']) . '</p>';

        echo $this->generarHtml(
            $titulo,
            $this->contenedorHtml(
                $this->tarjetaErrorHtml($contenido)
            )
        );
    }
}