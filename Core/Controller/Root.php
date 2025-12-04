<?php
/**
 * ERPIA - Sistema de Gestión Empresarial
 * Este archivo es parte de ERPIA, software libre bajo licencia GPL.
 * 
 * @package    ERPIA\Core\Controller
 * @author     Equipo de Desarrollo ERPIA
 * @copyright  2023-2025 ERPIA
 * @license    GNU Lesser General Public License v3.0
 */

namespace ERPIA\Core\Controller;

use ERPIA\Core\DataSrc\Empresas;
use ERPIA\Core\Template\Controller;

/**
 * Controlador raíz del sistema ERPIA
 * 
 * Gestiona la redirección inicial a la página de inicio
 * personalizada del usuario o al Dashboard por defecto.
 */
class Root extends Controller
{
    /**
     * Obtiene los metadatos de la página
     * 
     * @return array Configuración de menú, título e icono
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'reports';
        $pageData['title'] = Empresas::default()->nombrecorto ?? 'ERPIA';
        $pageData['icon'] = 'fa-solid fa-home';
        $pageData['showonmenu'] = false;
        
        return $pageData;
    }

    /**
     * Ejecuta la lógica principal del controlador
     * 
     * Redirige al usuario a su página de inicio personalizada
     * o al Dashboard por defecto.
     */
    public function run(): void
    {
        parent::run();

        // Si el usuario tiene homepage y es diferente de Root, redirigir
        if (!empty($this->user->homepage) && $this->user->homepage !== 'Root') {
            $this->response()->redirect($this->user->homepage)->send();
            return;
        }

        // Redirigir al Dashboard por defecto
        $this->response()->redirect('Dashboard')->send();
    }
}