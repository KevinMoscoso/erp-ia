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

use ERPIA\Core\Response;
use ERPIA\Core\Template\ErrorController;

class DisabledApi extends ErrorController
{
    /**
     * Ejecuta el controlador de error para API deshabilitada.
     * Devuelve una respuesta JSON con código HTTP 409 (Conflict).
     */
    public function run(): void
    {
        $this->response()
            ->setHttpCode(Response::HTTP_CONFLICT)
            ->json([
                'estado' => 'error',
                'mensaje' => $this->excepcion ? $this->excepcion->getMessage() : 'API deshabilitada',
            ]);
    }
}