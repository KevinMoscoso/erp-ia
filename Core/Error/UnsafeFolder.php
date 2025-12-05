<?php
/**
 * Este archivo es parte de ERPIA
 * Copyright (C) 2025 Proyecto ERPIA
 *
 * Este programa es software libre: puedes redistribuirlo y/o modificarlo
 * bajo los términos de la Licencia Pública General Reducida de GNU como
 * publicada por the Free Software Foundation, ya sea la versión 3 de la
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

class UnsafeFolder extends ErrorController
{
    /**
     * Ejecuta el controlador de error para carpeta considerada insegura.
     * Establece el código HTTP 403 y muestra un mensaje de error.
     */
    public function run(): void
    {
        http_response_code(403);

        $titulo = TextManager::get('carpeta_no_permitida');
        $mensaje = $this->excepcion ? $this->excepcion->getMessage() : 'Acceso restringido a esta ubicación';
        
        $contenido = '<div class="alert alert-warning border-start border-5 border-warning" role="alert">'
            . '<h3 class="alert-heading"><i class="fas fa-folder-times me-2"></i>' . $titulo . '</h3>'
            . '<p class="mb-2">' . htmlspecialchars($mensaje) . '</p>'
            . '<hr>'
            . '<p class="mb-0 small">' . TextManager::get('directorio_bloqueado_seguridad') . '</p>'
            . '</div>';

        echo $this->construirPaginaError(
            'Acceso denegado - ' . $titulo,
            $this->envolverContenido(
                $this->generarTarjetaRestriccion($contenido)
            )
        );
    }

    /**
     * Genera una tarjeta de restricción de acceso con contenido personalizado.
     *
     * @param string $contenidoHTML Contenido HTML para la tarjeta.
     * @return string HTML de la tarjeta de restricción.
     */
    private function generarTarjetaRestriccion(string $contenidoHTML): string
    {
        return '<div class="card shadow-lg border-warning">'
            . '<div class="card-header bg-warning text-dark d-flex align-items-center">'
            . '<i class="fas fa-shield-alt me-2"></i>'
            . '<span class="fw-bold">' . TextManager::get('restriccion_seguridad') . '</span>'
            . '</div>'
            . '<div class="card-body">' . $contenidoHTML . '</div>'
            . '<div class="card-footer bg-light text-muted small">'
            . '<i class="fas fa-info-circle me-1"></i>'
            . TextManager::get('consulte_administrador_sistema')
            . '</div>'
            . '</div>';
    }
}