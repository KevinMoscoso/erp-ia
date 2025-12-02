<?php
/**
 * Este archivo es parte de ERPIA
 * Copyright (C) 2024-2025 ERPIA Team
 *
 * Este programa es software libre: puede redistribuirlo y/o modificarlo
 * bajo los términos de la Licencia Pública General GNU Affero como
 * publicada por la Free Software Foundation, ya sea la versión 3 de la
 * Licencia, o (a su opción) cualquier versión posterior.
 *
 * Este programa se distribuye con la esperanza de que sea útil,
 * pero SIN NINGUNA GARANTÍA; sin siquiera la garantía implícita de
 * COMERCIABILIDAD o IDONEIDAD PARA UN PROPÓSITO PARTICULAR. Consulte la
 * Licencia Pública General GNU Affero para más detalles.
 *
 * Debería haber recibido una copia de la Licencia Pública General GNU Affero
 * junto con este programa. Si no es así, consulte <http://www.gnu.org/licenses/>.
 */

namespace ERPIA\Controller;

use ERPIA\Core\Lib\ExtendedController\EditController;

/**
 * Controlador para editar notificaciones de correo electrónico
 * 
 * Gestiona la configuración de alertas y notificaciones por email.
 */
class EditEmailNotification extends EditController
{
    /**
     * Obtiene los metadatos de la página
     * 
     * @return array
     */
    public function getPageData(): array
    {
        $pageConfig = parent::getPageData();
        $pageConfig['title'] = 'email-notification';
        $pageConfig['menu'] = 'admin';
        $pageConfig['icon'] = 'fa-solid fa-bell';
        
        return $pageConfig;
    }

    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'EmailNotification';
    }

    /**
     * Configura las vistas del controlador
     */
    protected function createViews()
    {
        parent::createViews();

        $mainView = $this->getMainViewName();
        
        // Desactivar botones específicos
        $this->setSettings($mainView, 'btnNew', false);
        $this->setSettings($mainView, 'btnOptions', false);
        $this->setSettings($mainView, 'btnPrint', false);
    }
}