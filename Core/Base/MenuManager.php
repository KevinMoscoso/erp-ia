<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2017-2025 ERPIA Contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace ERPIA\Core\Base;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Translator;
use ERPIA\Dinamic\Lib\MenuItem;
use ERPIA\Dinamic\Model\Page;
use ERPIA\Dinamic\Model\RoleAccess;
use ERPIA\Dinamic\Model\RoleUser;
use ERPIA\Dinamic\Model\User;

/**
 * Manages the ERPIA application menu system
 *
 * @author ERPIA Contributors
 */
class MenuManager
{
    /**
     * Menu structure for the current user
     * @var MenuItem[]
     */
    private static $menuStructure;

    /**
     * Menu activation status flag
     * @var bool
     */
    private static $isMenuActive;

    /**
     * Currently active page in menu
     * @var Page
     */
    private static $activePage;

    /**
     * Page model instance
     * @var Page
     */
    private static $pageModel;

    /**
     * Current user for menu generation
     * @var User|false
     */
    private static $currentUser = false;

    /**
     * Get user menu structure
     */
    public function getMenu()
    {
        return self::$menuStructure;
    }

    /**
     * Initialize menu system after database connection
     */
    public function initialize()
    {
        if (self::$pageModel === null) {
            self::$pageModel = new Page();
        }

        if (self::$currentUser !== false && self::$menuStructure === null) {
            self::$menuStructure = $this->loadUserMenu();
        }
    }

    /**
     * Reload menu from database
     */
    public function reload()
    {
        self::$menuStructure = $this->loadUserMenu();
        if (self::$activePage !== null) {
            $this->activateMenuPage(self::$activePage);
        }
    }

    /**
     * Remove pages not present in current page list
     */
    public function removeObsoletePages($currentPageNames)
    {
        foreach (self::$pageModel->all([], [], 0, 0) as $page) {
            if (!in_array($page->name, $currentPageNames, true)) {
                $page->delete();
            }
        }
    }

    /**
     * Select and activate page in menu
     */
    public function selectPage($pageData)
    {
        if (empty($pageData)) {
            return;
        }

        $page = new Page();
        if (!$page->loadFromCode($pageData['name'])) {
            $pageData['ordernum'] = 100;
            $page = new Page($pageData);
            $page->save();
        } elseif ($this->shouldUpdatePage($page, $pageData)) {
            $page->loadFromData($pageData);
            $page->save();
        }

        if (self::$menuStructure !== null && self::$isMenuActive !== true) {
            $this->activateMenuPage($page);
            self::$isMenuActive = true;
        }
    }

    /**
     * Set current user for menu generation
     */
    public function setUser($user)
    {
        self::$currentUser = $user;
        self::$menuStructure = null;
        $this->initialize();
    }

    /**
     * Get user role access permissions
     */
    private function getUserPermissions($user)
    {
        if (empty($user)) {
            return [];
        }

        $permissions = [];
        $roleUserModel = new RoleUser();
        $where = [new DataBaseWhere('username', $user->username)];
        
        foreach ($roleUserModel->all($where, [], 0, 0) as $roleUser) {
            foreach ($roleUser->getRolePermissions() as $access) {
                $permissions[] = $access;
            }
        }

        return $permissions;
    }

    /**
     * Load accessible pages for current user
     */
    private function loadAccessiblePages()
    {
        $where = [new DataBaseWhere('show_in_menu', true)];
        $order = [
            'lower(menu)' => 'ASC',
            'lower(submenu)' => 'ASC',
            'order_number' => 'ASC',
            'lower(title)' => 'ASC'
        ];

        $pages = self::$pageModel->all($where, $order, 0, 0);
        if (self::$currentUser && self::$currentUser->is_admin) {
            return $pages;
        }

        $accessiblePages = [];
        $userPermissions = $this->getUserPermissions(self::$currentUser);
        
        foreach ($pages as $page) {
            foreach ($userPermissions as $permission) {
                if ($page->name === $permission->page_name) {
                    $accessiblePages[] = $page;
                    break;
                }
            }
        }

        return $accessiblePages;
    }

    /**
     * Load and build user menu structure
     */
    private function loadUserMenu()
    {
        $menu = [];
        $currentMenu = null;
        $currentSubmenu = null;
        $menuContainer = null;
        $translator = new Translator();

        $pages = $this->loadAccessiblePages();
        foreach ($pages as $page) {
            if (empty($page->menu)) {
                continue;
            }

            // Handle menu changes
            if ($currentMenu !== $page->menu) {
                $currentMenu = $page->menu;
                $currentSubmenu = null;
                $menu[$currentMenu] = new MenuItem($currentMenu, $translator->trans($currentMenu), '#');
                $menuContainer = &$menu[$currentMenu]->children;
            }

            // Handle submenu changes
            if ($currentSubmenu !== $page->submenu) {
                $currentSubmenu = $page->submenu;
                $menuContainer = &$menu[$currentMenu]->children;
                
                if (!empty($currentSubmenu)) {
                    $menuContainer[$currentSubmenu] = new MenuItem($currentSubmenu, $translator->trans($currentSubmenu), '#');
                    $menuContainer = &$menuContainer[$currentSubmenu]->children;
                }
            }

            $menuContainer[$page->name] = new MenuItem(
                $page->name, 
                $translator->trans($page->title), 
                $page->getUrl(), 
                $page->icon
            );
        }

        return $this->sortMenuItems($menu);
    }

    /**
     * Check if page needs update
     */
    private function shouldUpdatePage($page, $pageData)
    {
        return $page->menu !== $pageData['menu'] ||
            $page->submenu !== $pageData['submenu'] ||
            $page->title !== $pageData['title'] ||
            $page->icon !== $pageData['icon'] ||
            $page->show_in_menu !== $pageData['showonmenu'];
    }

    /**
     * Activate menu page
     */
    private function activateMenuPage($page)
    {
        foreach (self::$menuStructure as $key => $menuItem) {
            if ($menuItem->name === $page->menu) {
                self::$menuStructure[$key]->active = true;
                $this->activateMenuItem(self::$menuStructure[$key]->children, $page);
                break;
            }
        }
    }

    /**
     * Activate specific menu item
     */
    private function activateMenuItem(&$menuItems, $page)
    {
        foreach ($menuItems as $key => $menuItem) {
            if ($menuItem->name === $page->name) {
                $menuItems[$key]->active = true;
                self::$activePage = $page;
                break;
            } elseif (!empty($page->submenu) && !empty($menuItem->children) && $menuItem->name === $page->submenu) {
                $menuItems[$key]->active = true;
                $this->activateMenuItem($menuItem->children, $page);
                break;
            }
        }
    }

    /**
     * Sort menu items alphabetically
     */
    private function sortMenuItems(array &$menu): array
    {
        uasort($menu, function ($item1, $item2) {
            return strcasecmp($item1->title, $item2->title);
        });

        foreach ($menu as $key => $item) {
            if (!empty($item->children)) {
                $menu[$key]->children = $this->sortMenuItems($item->children);
            }
        }

        return $menu;
    }
}