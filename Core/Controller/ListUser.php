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

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\DataSrc\Agentes;
use ERPIA\Core\DataSrc\Almacenes;
use ERPIA\Core\DataSrc\Empresas;
use ERPIA\Core\DataSrc\Series;
use ERPIA\Core\Lib\ExtendedController\ListController;
use ERPIA\Core\Translator;

/**
 * Controlador para listar los elementos del modelo User
 * 
 * Gestiona la visualización de usuarios y roles del sistema
 * con filtros avanzados y permisos de administrador.
 */
class ListUser extends ListController
{
    /**
     * Obtiene los metadatos de la página
     * 
     * @return array Configuración de menú, título e icono
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'admin';
        $pageData['title'] = 'users';
        $pageData['icon'] = 'fa-solid fa-users';
        
        return $pageData;
    }

    /**
     * Crea las vistas del controlador
     * 
     * Inicializa las vistas de usuarios y roles
     */
    protected function createViews(): void
    {
        $this->createViewsUsers();
        $this->createViewsRoles();
    }

    /**
     * Crea la vista de roles del sistema
     * 
     * @param string $viewName Nombre de la vista (por defecto: ListRole)
     */
    protected function createViewsRoles(string $viewName = 'ListRole'): void
    {
        $this->addView($viewName, 'Role', 'roles', 'fa-solid fa-address-card')
            ->addSearchFields(['codrole', 'descripcion'])
            ->addOrderBy(['descripcion'], 'description')
            ->addOrderBy(['codrole'], 'code');
    }

    /**
     * Crea la vista principal de usuarios
     * 
     * @param string $viewName Nombre de la vista (por defecto: ListUser)
     */
    protected function createViewsUsers(string $viewName = 'ListUser'): void
    {
        $this->addView($viewName, 'User', 'users', 'fa-solid fa-users')
            ->addSearchFields(['nick', 'email'])
            ->addOrderBy(['nick'], 'nick', 1)
            ->addOrderBy(['email'], 'email')
            ->addOrderBy(['creationdate'], 'creation-date')
            ->addOrderBy(['lastactivity'], 'last-activity')
            ->setSettings('btnPrint', false);

        // Ordenación por nivel solo para administradores
        if ($this->user->admin) {
            $this->addOrderBy($viewName, ['level'], 'level');
        }

        $translator = Translator::getInstance();

        // Filtros condicionales basados en cantidad de opciones
        $companies = Empresas::codeModel();
        if (count($companies) > 2) {
            $this->addFilterSelect($viewName, 'idempresa', 'company', 'idempresa', $companies);
        }

        $warehouses = Almacenes::codeModel();
        if (count($warehouses) > 2) {
            $this->addFilterSelect($viewName, 'codalmacen', 'warehouse', 'codalmacen', $warehouses);
        }

        $series = Series::codeModel();
        if (count($series) > 2) {
            $this->addFilterSelect($viewName, 'codserie', 'series', 'codserie', $series);
        }

        $agents = Agentes::codeModel();
        if (count($agents) > 2) {
            $this->addFilterSelect($viewName, 'codagente', 'agent', 'codagente', $agents);
        }

        // Filtros select where usando listView
        $this->listView($viewName)
            ->addFilterSelectWhere('type', [
                [
                    'label' => $translator->trans('all'),
                    'where' => []
                ],
                [
                    'label' => '------',
                    'where' => []
                ],
                [
                    'label' => $translator->trans('admin'),
                    'where' => [new DataBaseWhere('admin', true)]
                ],
                [
                    'label' => $translator->trans('no-admin'),
                    'where' => [new DataBaseWhere('admin', false)]
                ]
            ])
            ->addFilterSelectWhere('2fa', [
                [
                    'label' => $translator->trans('two-factor-auth'),
                    'where' => []
                ],
                [
                    'label' => '------',
                    'where' => []
                ],
                [
                    'label' => $translator->trans('two-factor-auth-enabled'),
                    'where' => [new DataBaseWhere('two_factor_enabled', true)]
                ],
                [
                    'label' => $translator->trans('two-factor-auth-disabled'),
                    'where' => [new DataBaseWhere('two_factor_enabled', false)]
                ]
            ]);

        // Filtro de nivel solo para administradores
        if ($this->user->admin) {
            $levels = $this->codeModel->all('users', 'level', 'level');
            $this->addFilterSelect($viewName, 'level', 'level', 'level', $levels);
        }

        // Filtro de idioma condicional
        $languages = $this->codeModel->all('users', 'langcode', 'langcode');
        if (count($languages) > 2) {
            $this->addFilterSelect($viewName, 'langcode', 'language', 'langcode', $languages);
        }
    }
}