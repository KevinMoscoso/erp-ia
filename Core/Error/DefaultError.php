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
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class DefaultError extends ErrorController
{
    /**
     * Ejecuta el controlador de error por defecto.
     * Guarda la información del error, establece el código HTTP 500 y muestra una página de error detallada.
     */
    public function run(): void
    {
        $this->guardar();

        http_response_code(500);

        $titulo = '🚨 Error ' . $this->informacion['hash'];

        if ($this->excepcion instanceof SyntaxError) {
            $contenido = '<h2>Error de sintaxis Twig</h2>'
                . '<p>' . $this->excepcion->getRawMessage() . '</p>'
                . '<p><b>Archivo</b>: ' . $this->informacion['archivo']
                . ', <b>línea</b>: ' . $this->informacion['linea'] . '</p>';
        } elseif ($this->excepcion instanceof RuntimeError) {
            $contenido = '<h2>Error de tiempo de ejecución Twig</h2>'
                . '<p>' . $this->excepcion->getRawMessage() . '</p>'
                . '<p><b>Archivo</b>: ' . $this->informacion['archivo']
                . ', <b>línea</b>: ' . $this->informacion['linea'] . '</p>';
        } elseif ($this->excepcion instanceof LoaderError) {
            $contenido = '<h2>Error de carga Twig</h2>'
                . '<p>' . $this->excepcion->getRawMessage() . '</p>'
                . '<p><b>Archivo</b>: ' . $this->informacion['archivo']
                . ', <b>línea</b>: ' . $this->informacion['linea'] . '</p>';
        } else {
            $contenido = '<p>' . $this->excepcion->getMessage() . '</p>'
                . '<p><b>Archivo</b>: ' . $this->informacion['archivo']
                . ', <b>línea</b>: ' . $this->informacion['linea'] . '</p>';
        }

        echo $this->generarHtml(
            $titulo,
            $this->contenedorHtml(
                '<h1 class="h3 text-white mb-4">' . $titulo . '</h1>'
                . $this->tarjetaErrorHtml($contenido, true, $this->puedeMostrarBotonesDespliegue())
                . $this->tarjetaFragmentoCodigoHtml()
                . $this->tarjetaRegistroHtml()
            )
        );
    }
}