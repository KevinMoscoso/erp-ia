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

use ERPIA\Core\Base\Controller;
use ERPIA\Core\Base\ControllerPermissions;
use ERPIA\Core\Response;
use ERPIA\Core\Translator;
use ERPIA\Dinamic\Model\Page;
use ERPIA\Dinamic\Model\User;

/**
 * Controlador para realizar búsquedas globales en el sistema
 * 
 * Implementa una mega-búsqueda que busca coincidencias en títulos de páginas,
 * menús y submenús, y prepara enlaces para búsquedas específicas en listados.
 */
class MegaSearch extends Controller
{
    /**
     * Contiene el texto de búsqueda procesado
     * 
     * @var string|false
     */
    public $query;

    /**
     * Resultados organizados por secciones
     * 
     * @var array
     */
    public $results = [];

    /**
     * Secciones adicionales para búsquedas específicas
     * 
     * @var array
     */
    public $sections = [];

    /**
     * Obtiene los metadatos de la página
     * 
     * @return array Configuración de menú, título y visibilidad
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'reports';
        $pageData['title'] = 'mega-search';
        $pageData['showonmenu'] = false;
        
        return $pageData;
    }

    /**
     * Ejecuta la lógica privada del controlador
     * 
     * @param Response $response
     * @param User $user
     * @param ControllerPermissions $permissions
     */
    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);

        $this->results = [];
        $this->sections = [];

        $query = $this->request->input('query', '');
        $this->query = $this->sanitizeQuery($query);
        
        if ($this->query !== '') {
            $this->search();
        }
    }

    /**
     * Realiza la búsqueda en todas las secciones disponibles
     */
    protected function search(): void
    {
        $this->pageSearch();
    }

    /**
     * Realiza la búsqueda en las páginas del sistema
     */
    protected function pageSearch(): void
    {
        $results = [];
        $translator = Translator::getInstance();

        foreach (Page::all() as $page) {
            if (!$page->showonmenu) {
                continue;
            }

            // ¿Coincide el título de la página con la consulta?
            $pageTitle = $page->title ?? '';
            $translatedTitle = mb_strtolower($translator->trans($pageTitle), 'UTF8');
            $searchTitle = mb_strtolower($pageTitle, 'UTF8');
            
            if (stripos($searchTitle, $this->query) !== false || 
                stripos($translatedTitle, $this->query) !== false) {
                $results[] = [
                    'icon' => $page->icon,
                    'link' => $page->url(),
                    'menu' => $translator->trans($page->menu ?? ''),
                    'submenu' => $translator->trans($page->submenu ?? ''),
                    'title' => $translator->trans($pageTitle)
                ];
            }

            // ¿Es un ListController que podría devolver más resultados?
            if (strpos($page->name, 'List') === 0) {
                $this->sections[$page->name] = $page->url() . '?action=megasearch&query=' . $this->query;
            }
        }

        if (!empty($results)) {
            $this->results['pages'] = [
                'columns' => [
                    'icon' => 'icon',
                    'menu' => 'menu', 
                    'submenu' => 'submenu',
                    'title' => 'title'
                ],
                'icon' => 'fa-solid fa-mouse-pointer',
                'title' => 'pages',
                'results' => $results
            ];
        }
    }

    /**
     * Sanitiza la consulta de búsqueda
     * 
     * @param string $query Consulta original
     * @return string Consulta sanitizada
     */
    private function sanitizeQuery(string $query): string
    {
        // Eliminar etiquetas HTML y convertir a minúsculas
        $cleanQuery = strip_tags($query);
        return mb_strtolower($cleanQuery, 'UTF8');
    }
}