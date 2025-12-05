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
use ERPIA\Core\Language\TextManager;

class UnsafeFile extends ErrorController
{
    /**
     * Ejecuta el controlador de error para archivo considerado inseguro.
     * Establece el código HTTP 403 y muestra un mensaje de error.
     */
    public function run(): void
    {
        http_response_code(403);

        $titulo = '⛔ ' . TextManager::get('archivo_no_permitido');
        $nombreArchivo = $this->excepcion ? $this->excepcion->getMessage() : 'archivo desconocido';
        $contenido = '<div class="alert alert-danger" role="alert">'
            . '<h2 class="alert-heading">' . $titulo . '</h2>'
            . '<p>' . TextManager::get('archivo_bloqueado_por_seguridad', ['archivo' => htmlspecialchars($nombreArchivo)]) . '</p>'
            . '<hr>'
            . '<p class="mb-0">' . TextManager::get('contacte_administrador_para_ayuda') . '</p>'
            . '</div>';

        echo $this->construirPaginaError(
            $titulo,
            $this->envolverContenido(
                $this->crearPanelError($contenido, 'danger')
            )
        );
    }

    /**
     * Crea un panel de error con el contenido especificado.
     *
     * @param string $contenido HTML del contenido del panel.
     * @param string $tipo Tipo de panel (danger, warning, etc.).
     * @return string HTML del panel.
     */
    private function crearPanelError(string $contenido, string $tipo = 'danger'): string
    {
        return '<div class="card border-' . $tipo . ' mb-4">'
            . '<div class="card-header bg-' . $tipo . ' text-white">'
            . '<i class="fas fa-exclamation-triangle me-2"></i>'
            . TextManager::get('error_seguridad')
            . '</div>'
            . '<div class="card-body">' . $contenido . '</div>'
            . '</div>';
    }
}