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

use ERPIA\Core\Config\SystemConfig;
use ERPIA\Core\Template\ErrorController;
use ERPIA\Core\Traduccion;

class PageNotFound extends ErrorController
{
    /**
     * Ejecuta el controlador de error para página no encontrada.
     * Establece el código HTTP 404 y muestra una tarjeta de error con botones de acción.
     */
    public function run(): void
    {
        http_response_code(404);

        $titulo = '🔍 ' . Traduccion::obtener('pagina-no-encontrada');
        $rutaBase = SystemConfig::obtener('ruta', '/');
        
        $tarjeta = '<div class="card shadow mt-5 mb-5">'
            . '<div class="card-body text-center">'
            . '<div class="display-1 text-info">404</div>'
            . '<h1 class="card-title">' . $titulo . '</h1>'
            . '<p class="mb-0">' . Traduccion::obtener('pagina-no-encontrada-descripcion') . '</p>'
            . '</div>'
            . '<div class="card-footer">'
            . '<div class="row">'
            . '<div class="col">'
            . '<a href="' . $rutaBase . '/" class="btn btn-secondary">'
            . Traduccion::obtener('pagina-inicio') . '</a>'
            . '</div>';

        if ($this->puedeMostrarBotonesDespliegue()) {
            $tarjeta .= '<div class="col-auto">'
                . '<a href="' . $rutaBase . '/AdminPlugins" class="btn btn-warning">' . Traduccion::obtener('plugins') . '</a>'
                . '</div>';
        }

        $tarjeta .= '</div>'
            . '</div>'
            . '</div>';

        echo $this->generarHtml(
            $titulo,
            $this->contenedorHtml($tarjeta)
        );
    }
}