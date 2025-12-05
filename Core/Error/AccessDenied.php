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

use ERPIA\Core\Lib\MenuManager;
use ERPIA\Core\Response;
use ERPIA\Core\Template\ErrorController;

class AccessDenied extends ErrorController
{
    /**
     * Ejecuta el controlador de error para acceso denegado.
     * Configura la respuesta HTTP con código 403 y muestra la plantilla de error.
     */
    public function run(): void
    {
        $this->response()
            ->setHttpCode(Response::HTTP_FORBIDDEN)
            ->view('Error/AccessDenied.html.twig', [
                'nombreControlador' => 'AccessDenied',
                'mostrarDepuracion' => false,
                'controlador' => $this,
                'gestorMenu' => MenuManager::iniciar(),
                'plantilla' => 'Error/AccessDenied.html.twig'
            ]);
    }
}