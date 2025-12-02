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
 * Controlador para editar un elemento individual del modelo Divisa
 * 
 * Proporciona interfaz para la gestión de monedas en el sistema.
 */
class EditDivisa extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Divisa';
    }

    /**
     * Obtiene los metadatos de configuración de la página
     * 
     * @return array
     */
    public function getPageData(): array
    {
        $pageConfig = parent::getPageData();
        $pageConfig['menu'] = 'admin';
        $pageConfig['title'] = 'currency';
        $pageConfig['icon'] = 'fa-solid fa-money-bill-alt';
        
        return $pageConfig;
    }
}