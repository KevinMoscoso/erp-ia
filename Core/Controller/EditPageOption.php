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

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\Controller;
use ERPIA\Core\Base\ControllerPermissions;
use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\Widget\VisualItemLoadEngine;
use ERPIA\Core\Response;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\CodeModel;
use ERPIA\Dinamic\Model\Page;
use ERPIA\Dinamic\Model\PageOption;
use ERPIA\Dinamic\Model\User;

/**
 * Edita las opciones de cualquier página.
 * 
 * Permite configurar vistas para usuarios específicos o para todos los usuarios,
 * cargando configuraciones desde la base de datos o desde archivos XML por defecto.
 */
class EditPageOption extends Controller
{
    /**
     * Contiene la URL para volver.
     *
     * @var string
     */
    public $backPage;

    /**
     * @var array
     */
    public $columns = [];

    /**
     * @var array
     */
    public $modals = [];

    /**
     * Detalles de la configuración de la vista
     *
     * @var PageOption
     */
    public $model;

    /**
     * @var array
     */
    public $rows = [];

    /**
     * Usuario seleccionado, para el cual se crean o modifican las columnas del controlador
     *
     * @var string
     */
    public $selectedUser;

    /**
     * Vista seleccionada, para la cual se crean o modifican las columnas
     *
     * @var string
     */
    public $selectedViewName;

    /**
     * Obtiene los metadatos de la página
     * 
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'admin';
        $pageData['title'] = 'options';
        $pageData['icon'] = 'fa-solid fa-wrench';
        $pageData['showonmenu'] = false;
        
        return $pageData;
    }

    /**
     * Obtiene la lista de usuarios, excluyendo al usuario admin
     * 
     * @return array
     */
    public function getUserList(): array
    {
        $resultado = [];
        $usuarios = CodeModel::all(User::tableName(), 'nick', 'nick', false);
        foreach ($usuarios as $modeloCodigo) {
            if ($modeloCodigo->code != 'admin') {
                $resultado[$modeloCodigo->code] = $modeloCodigo->description;
            }
        }

        return $resultado;
    }

    /**
     * Ejecuta la lógica privada del controlador.
     *
     * @param Response $response
     * @param User $user
     * @param ControllerPermissions $permissions
     */
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $this->model = new PageOption();
        $this->loadSelectedViewName();
        $this->setBackPage();
        $this->selectedUser = $this->user->admin ?
            $this->request->queryOrInput('nick') :
            $this->user->nick;
        $this->loadPageOptions();

        $accion = $this->request->inputOrQuery('action', '');
        switch ($accion) {
            case 'delete':
                $this->deleteAction();
                break;

            case 'save':
                $this->saveAction();
                break;
        }
    }

    /**
     * Elimina la configuración de la vista
     */
    protected function deleteAction(): void
    {
        if (false === $this->permissions->allowDelete) {
            Tools::log()->warning('not-allowed-delete');
            return;
        } elseif (false === $this->validateFormToken()) {
            return;
        }

        if ($this->model->delete()) {
            Tools::log()->notice('record-deleted-correctly');
            $this->loadPageOptions();
            return;
        }

        Tools::log()->warning('default-not-deletable');
    }

    /**
     * Carga las opciones de visualización para editar.
     * Si no las encuentra en la base de datos,
     * carga las opciones por defecto de la vista XML.
     */
    protected function loadPageOptions(): void
    {
        if ($this->selectedUser && false === $this->loadPageOptionsForUser()) {
            VisualItemLoadEngine::installXML($this->selectedViewName, $this->model);
        }

        if (empty($this->selectedUser) && false === $this->loadPageOptionsForAll()) {
            VisualItemLoadEngine::installXML($this->selectedViewName, $this->model);
        }

        VisualItemLoadEngine::loadArray($this->columns, $this->modals, $this->rows, $this->model);
    }

    /**
     * Carga el nombre de la vista seleccionada
     */
    protected function loadSelectedViewName(): void
    {
        $codigo = $this->request->queryOrInput('code', '');
        if (false === strpos($codigo, '-')) {
            $this->selectedViewName = $codigo;
            return;
        }

        $partes = explode('-', $codigo);
        $this->selectedViewName = empty($partes) ? $codigo : $partes[0];
    }

    /**
     * Guarda la nueva configuración de la vista
     */
    protected function saveAction(): void
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return;
        } elseif (false === $this->validateFormToken()) {
            return;
        }

        foreach ($this->model->columns as $clave1 => $grupo) {
            if ($grupo['tag'] === 'column') {
                $nombre = $grupo['name'];
                $this->setColumnOption($this->model->columns[$clave1], $nombre, 'title', false, false);
                $this->setColumnOption($this->model->columns[$clave1], $nombre, 'display', false, false);
                $this->setColumnOption($this->model->columns[$clave1], $nombre, 'level', false, true);
                $this->setColumnOption($this->model->columns[$clave1], $nombre, 'readonly', true, true);
                $this->setColumnOption($this->model->columns[$clave1], $nombre, 'decimal', true, true);
                $this->setColumnOption($this->model->columns[$clave1], $nombre, 'numcolumns', false, true);
                $this->setColumnOption($this->model->columns[$clave1], $nombre, 'order', false, true);
                continue;
            }

            foreach ($grupo['children'] as $clave2 => $columna) {
                $nombre = $columna['name'];
                $this->setColumnOption($this->model->columns[$clave1]['children'][$clave2], $nombre, 'title', false, false);
                $this->setColumnOption($this->model->columns[$clave1]['children'][$clave2], $nombre, 'display', false, false);
                $this->setColumnOption($this->model->columns[$clave1]['children'][$clave2], $nombre, 'level', false, true);
                $this->setColumnOption($this->model->columns[$clave1]['children'][$clave2], $nombre, 'readonly', true, true);
                $this->setColumnOption($this->model->columns[$clave1]['children'][$clave2], $nombre, 'decimal', true, true);
                $this->setColumnOption($this->model->columns[$clave1]['children'][$clave2], $nombre, 'numcolumns', false, true);
                $this->setColumnOption($this->model->columns[$clave1]['children'][$clave2], $nombre, 'order', false, true);
            }
        }

        if ($this->model->save()) {
            Tools::log()->notice('record-updated-correctly');
            $this->loadPageOptions();
            return;
        }

        Tools::log()->error('record-save-error');
    }

    /**
     * Carga las opciones de visualización generales para todos los usuarios.
     * 
     * @return bool
     */
    private function loadPageOptionsForAll(): bool
    {
        $filtros = [
            new DataBaseWhere('name', $this->selectedViewName),
            new DataBaseWhere('nick', null, 'IS'),
        ];
        return $this->model->loadWhere($filtros);
    }

    /**
     * Carga las opciones de visualización específicas del usuario.
     * Si no existen, busca las opciones comunes a todos los usuarios.
     * En cualquier caso, indica si ha encontrado una configuración.
     * 
     * @return bool
     */
    private function loadPageOptionsForUser(): bool
    {
        $filtros = [
            new DataBaseWhere('name', $this->selectedViewName),
            new DataBaseWhere('nick', $this->selectedUser),
        ];
        if ($this->model->loadWhere($filtros)) {
            // Existen opciones para el usuario.
            return true;
        }

        if (false === $this->loadPageOptionsForAll()) {
            // No existen opciones generales. Asignamos las opciones por defecto de la vista XML al usuario.
            $this->model->nick = $this->selectedUser;
            return false;
        }

        // No existen opciones para el usuario. Clonamos las generales.
        $this->model->id = null;
        $this->model->nick = $this->selectedUser;
        return true;
    }

    /**
     * Establece la página de retorno.
     */
    private function setBackPage(): void
    {
        // Verificar si la URL es un nombre de controlador real
        $url = $this->request->queryOrInput('url', '');
        foreach (Page::all() as $pagina) {
            if (substr($url, 0, strlen($pagina->name)) === $pagina->name) {
                $this->backPage = $url;
                return;
            }
        }

        // Establecer la página de retorno por defecto
        $this->backPage = $this->selectedViewName;
    }

    /**
     * Establece una opción de columna.
     * 
     * @param array $columna
     * @param string $nombre
     * @param string $clave
     * @param bool $esWidget
     * @param bool $permiteVacio
     */
    private function setColumnOption(&$columna, string $nombre, string $clave, bool $esWidget, bool $permiteVacio): void
    {
        $nuevoValor = Tools::noHtml($this->request->input($nombre . '-' . $clave));
        if ($esWidget) {
            if (!empty($nuevoValor) || $permiteVacio) {
                $columna['children'][0][$clave] = $nuevoValor;
            }
            return;
        }

        if (!empty($nuevoValor) || $permiteVacio) {
            $columna[$clave] = $nuevoValor;
        }
    }
}