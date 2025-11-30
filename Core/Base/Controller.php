<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2013-2025 ERPIA Contributors
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
 */

namespace ERPIA\Core\Base;

use ERPIA\Core\Contract\ControllerInterface;
use ERPIA\Core\DataSrc\Companies;
use ERPIA\Core\Kernel;
use ERPIA\Core\AppException;
use ERPIA\Core\Lib\MenuManager;
use ERPIA\Core\Model\Company;
use ERPIA\Core\Model\User;
use ERPIA\Core\Request;
use ERPIA\Core\Response;
use ERPIA\Core\Session;
use ERPIA\Core\Config;
use ERPIA\Core\Logger;
use ERPIA\Dinamic\Lib\AssetManager;
use ERPIA\Dinamic\Lib\MultiRequestProtection;
use ERPIA\Dinamic\Model\User as DynamicUser;

/**
 * Base class for all ERPIA controllers
 *
 * @author ERPIA Contributors
 */
class Controller implements ControllerInterface
{
    /**
     * Controller class name
     * @var string
     */
    private $controllerClass;

    /**
     * Database connection
     * @var DataBase
     */
    protected $database;

    /**
     * Selected company
     * @var Company
     */
    public $company;

    /**
     * Multi-request protection
     * @var MultiRequestProtection
     */
    public $requestProtection;

    /**
     * Controller permissions
     * @var ControllerPermissions
     */
    public $permissions;

    /**
     * HTTP request
     * @var Request
     */
    public $request;

    /**
     * HTTP response
     * @var Response
     */
    protected $response;

    /**
     * Template file name
     * @var string|false
     */
    private $template;

    /**
     * Page title
     * @var string
     */
    public $title;

    /**
     * Request URI
     * @var string
     */
    public $uri;

    /**
     * Current user
     * @var User|false
     */
    public $user = false;

    /**
     * Initialize controller
     */
    public function __construct(string $className, string $uri = '')
    {
        $this->controllerClass = $className;
        Session::set('controllerName', $this->controllerClass);
        Session::set('pageName', $this->controllerClass);
        Session::set('uri', $uri);
        
        $this->database = new DataBase();
        $this->company = Companies::getDefault();
        $this->requestProtection = new MultiRequestProtection();
        $this->request = Request::createFromGlobals();
        $this->template = $this->controllerClass . '.html.twig';
        $this->uri = $uri;
        
        $pageInfo = $this->getPageInfo();
        $this->title = empty($pageInfo) ? $this->controllerClass : Config::trans($pageInfo['title']);
        
        AssetManager::clear();
        AssetManager::setPageAssets($className);
        $this->verifyPhpVersion(8.0);
    }

    /**
     * Add extension (stub for compatibility)
     */
    public static function addExtension($extension)
    {
        Logger::error('extensions-not-supported', ['%class%' => static::class]);
    }

    /**
     * Get page metadata
     */
    public function getPageInfo(): array
    {
        return [
            'name' => $this->controllerClass,
            'title' => $this->controllerClass,
            'icon' => 'fa-solid fa-circle',
            'menu' => 'new',
            'submenu' => null,
            'showonmenu' => true,
            'order' => 100
        ];
    }

    /**
     * Get template name
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Pipe method for extensions (stub)
     */
    public function pipe($name, ...$arguments)
    {
        Logger::error('extensions-not-supported', ['%class%' => static::class]);
        return null;
    }

    /**
     * Pipe method returning false (stub)
     */
    public function pipeFalse($name, ...$arguments): bool
    {
        Logger::error('extensions-not-supported', ['%class%' => static::class]);
        return true;
    }

    /**
     * Private core logic
     */
    public function privateCore(&$response, $user, $permissions)
    {
        $this->permissions = $permissions;
        Session::set('permissions', $this->permissions);
        $this->response = &$response;
        $this->user = $user;

        if (!$this->permissions->accessAllowed) {
            throw new AppException('AccessDenied', Config::lang()->trans('access-denied'));
        }

        // Set user's default company
        $this->company = Companies::get($this->user->company_id);
        
        // Add user to token seed
        $this->requestProtection->addSeed($user->username);
        
        // Handle default page setting
        $this->handleDefaultPage();
    }

    /**
     * Public core logic
     */
    public function publicCore(&$response)
    {
        $this->permissions = new ControllerPermissions();
        Session::set('permissions', $this->permissions);
        $this->response = &$response;
        $this->template = 'Auth/Login.html.twig';
        $this->company = Companies::getDefault();
    }

    /**
     * Redirect to URL
     */
    public function redirect(string $url, int $delay = 0)
    {
        $this->response->setHeader('Refresh', $delay . '; ' . $url);
        if ($delay === 0) {
            $this->setTemplate(false);
        }
    }

    /**
     * Get request object
     */
    public function request(): Request
    {
        return $this->request;
    }

    /**
     * Run controller
     */
    public function run(): void
    {
        $response = new Response();
        
        if ($this->authenticate()) {
            $permissions = new ControllerPermissions(Session::user(), $this->controllerClass);
            $this->privateCore($response, Session::user(), $permissions);
            
            if ($this->template) {
                Kernel::startTimer('Controller::render');
                $response->renderView($this->template, [
                    'controllerName' => $this->controllerClass,
                    'controller' => $this,
                    'menuManager' => MenuManager::initialize()->selectPage($this->getPageInfo()),
                    'template' => $this->template,
                ]);
                Kernel::stopTimer('Controller::render');
            }
            $response->send();
            return;
        }
        
        $this->publicCore($response);
        
        if ($this->template) {
            Kernel::startTimer('Controller::render');
            $response->renderView($this->template, [
                'controllerName' => $this->controllerClass,
                'controller' => $this,
                'template' => $this->template,
            ]);
            Kernel::stopTimer('Controller::render');
        }
        $response->send();
    }

    /**
     * Set template
     */
    public function setTemplate($template): bool
    {
        $this->template = ($template === false) ? false : $template . '.html.twig';
        return true;
    }

    /**
     * Get controller URL
     */
    public function url(): string
    {
        return $this->controllerClass;
    }

    /**
     * Authenticate user
     */
    private function authenticate(): bool
    {
        $username = $this->request->cookie('erpiaUser', '');
        if (empty($username)) {
            return false;
        }

        $user = new DynamicUser();
        if (!$user->loadFromUsername($username)) {
            Logger::warning('user-not-found', ['%user%' => $username]);
            return false;
        }

        $cookieExpire = time() + Config::get('cookie_expire');
        if (!$user->isActive()) {
            Logger::warning('user-disabled', ['%user%' => $username]);
            setcookie('erpiaUser', '', $cookieExpire, '/');
            return false;
        }

        $authToken = $this->request->cookie('erpiaToken', '') ?? '';
        if (!$user->validateAuthToken($authToken)) {
            Logger::warning('invalid-auth-token');
            setcookie('erpiaUser', '', $cookieExpire, '/');
            return false;
        }

        if (time() - strtotime($user->lastActivity) > User::ACTIVITY_UPDATE_INTERVAL) {
            $clientIP = Session::getClientIP();
            $userAgent = $this->request->header('User-Agent');
            $user->updateLastActivity($clientIP, $userAgent);
            $user->save();
        }

        Session::set('user', $user);
        return true;
    }

    /**
     * Verify PHP version
     */
    private function verifyPhpVersion(float $minimum): void
    {
        $current = (float) substr(phpversion(), 0, 3);
        if ($current < $minimum) {
            Logger::warning('php-version-deprecated', [
                '%current%' => $current, 
                '%required%' => $minimum
            ]);
        }
    }

    /**
     * Get database connection
     */
    protected function db(): DataBase
    {
        return $this->database;
    }

    /**
     * Get controller class name
     */
    protected function getControllerClass(): string
    {
        return $this->controllerClass;
    }

    /**
     * Get response object
     */
    protected function response(): Response
    {
        return $this->response;
    }

    /**
     * Validate form token
     */
    protected function validateFormToken(): bool
    {
        $token = $this->request->get('multireqtoken', '');
        if (empty($token) || !$this->requestProtection->validate($token)) {
            Logger::warning('invalid-request-token');
            return false;
        }

        if ($this->requestProtection->tokenExists($token)) {
            Logger::warning('duplicated-request');
            return false;
        }

        return true;
    }

    /**
     * Handle default page setting
     */
    private function handleDefaultPage(): void
    {
        $cookieExpire = time() + Config::get('cookie_expire');
        $defaultPage = $this->request->query('setDefaultPage', '');
        
        if ($defaultPage === 'true') {
            $this->user->homePage = $this->controllerClass;
            $this->response->setCookie('erpiaHomepage', $this->user->homePage, $cookieExpire);
            $this->user->save();
        } elseif ($defaultPage === 'false') {
            $this->user->homePage = null;
            $this->response->setCookie('erpiaHomepage', '', $cookieExpire);
            $this->user->save();
        }
    }
}