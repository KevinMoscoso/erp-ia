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

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Lib\ExtendedController\EditController;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Contacto;

/**
 * Controlador para editar un registro individual de EmailSent
 * 
 * Proporciona funcionalidad para visualizar correos electrónicos enviados,
 * incluyendo su contenido HTML, archivos adjuntos y otros correos del mismo
 * destinatario, con posibilidad de redirigir al contacto asociado.
 */
class EditEmailSent extends EditController
{
    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'EmailSent';
    }

    /**
     * Obtiene los metadatos de la página
     * 
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'admin';
        $pageData['title'] = 'email-sent';
        $pageData['icon'] = 'fa-solid fa-envelope';
        
        return $pageData;
    }

    /**
     * Redirige a la página del contacto asociado al correo
     */
    protected function contactAction(): void
    {
        $contacto = new Contacto();
        $email = $this->getViewModelValue($this->getMainViewName(), 'addressee');
        $filtro = [new DataBaseWhere('email', $email)];
        if ($contacto->loadWhere($filtro)) {
            $this->redirect($contacto->url());
            return;
        }

        Tools::log()->warning('record-not-found');
    }

    /**
     * Configura las vistas del controlador
     */
    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        $this->createViewHtml();
        $this->createViewAttachments();

        // Botón para contacto
        $vistaPrincipal = $this->getMainViewName();
        $this->addButton($vistaPrincipal, [
            'action' => 'contact',
            'color' => 'info',
            'icon' => 'fa-solid fa-address-book',
            'label' => 'contact',
            'type' => 'button'
        ]);

        // Desactivar botón nuevo
        $this->setSettings($vistaPrincipal, 'btnNew', false);

        // Otras vistas
        $this->createViewOtherEmails();
    }

    /**
     * Crea la vista de archivos adjuntos
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewAttachments(string $viewName = 'EmailSentAttachment'): void
    {
        $this->addHtmlView($viewName, 'Tab\EmailSentAttachment', 'EmailSent', 'attached-files', 'fa-solid fa-paperclip');
    }

    /**
     * Crea la vista del contenido HTML
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewHtml(string $viewName = 'EmailSentHtml'): void
    {
        $this->addHtmlView($viewName, 'Tab\EmailSentHtml', 'EmailSent', 'html');
    }

    /**
     * Crea la vista de otros correos del mismo destinatario
     * 
     * @param string $viewName Nombre de la vista
     */
    protected function createViewOtherEmails(string $viewName = 'ListEmailSent'): void
    {
        $this->addListView($viewName, 'EmailSent', 'emails', 'fa-solid fa-paper-plane')
            ->addOrderBy(['date'], 'date', 2)
            ->addSearchFields(['body', 'subject'])
            ->setSettings('btnNew', false);
    }

    /**
     * Ejecuta acciones posteriores
     * 
     * @param string $action Acción a ejecutar
     */
    protected function execAfterAction($action)
    {
        switch ($action) {
            case 'contact':
                $this->contactAction();
                break;

            default:
                parent::execAfterAction($action);
        }
    }

    /**
     * Ejecuta acciones previas
     * 
     * @param string $action Acción a ejecutar
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        if ($action === 'getHtml') {
            $this->getHtmlAction();
            return false;
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Acción para obtener el HTML del correo
     */
    protected function getHtmlAction(): void
    {
        $this->setTemplate(false);

        $modelo = $this->getModel();
        if (false === $modelo->loadFromCode($this->request->queryOrInput('code', ''))) {
            $this->response->json(['getHtml' => false]);
            return;
        }

        $this->response->json([
            'getHtml' => true,
            'html' => empty($modelo->html) ?
                '<h1 style="text-align: center">' . Tools::trans('not-stored-content') . '</h1>' :
                Tools::fixHtml($modelo->html),
        ]);
    }

    /**
     * Carga datos en una vista específica
     * 
     * @param string $viewName Nombre de la vista
     * @param BaseView $view Instancia de la vista
     */
    protected function loadData($viewName, $view)
    {
        $vistaPrincipal = $this->getMainViewName();

        switch ($viewName) {
            case 'EmailSentAttachment':
                $view->cursor = $this->views[$vistaPrincipal]->model->getAttachments();
                $view->count = count($view->cursor);
                break;

            case 'ListEmailSent':
                $destinatario = $this->getViewModelValue($vistaPrincipal, 'addressee');
                $id = $this->getViewModelValue($vistaPrincipal, 'id');
                $filtros = [
                    new DataBaseWhere('addressee', $destinatario),
                    new DataBaseWhere('id', $id, '!=')
                ];
                $view->loadData('', $filtros);
                break;

            default:
                parent::loadData($viewName, $view);

                // Ocultar pestaña si no hay adjuntos
                if (false === $view->model->attachment) {
                    $this->setSettings('EmailSentAttachment', 'active', false);
                }
                break;
        }
    }
}