<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2025 ERPIA Project
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

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\Controller;
use ERPIA\Core\Base\ControllerPermissions;
use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Plugins;
use ERPIA\Core\Response;
use ERPIA\Core\SystemConfig;
use ERPIA\Dinamic\Lib\Accounting\AccountingPlanImport;
use ERPIA\Dinamic\Lib\TaxRegime;
use ERPIA\Dinamic\Model\Warehouse;
use ERPIA\Dinamic\Model\Account;
use ERPIA\Dinamic\Model\FiscalYear;
use ERPIA\Dinamic\Model\Role;
use ERPIA\Dinamic\Model\User;

/**
 * Initial system setup wizard controller
 *
 * @author ERPIA Project
 */
class Wizard extends Controller
{
    const SELECTION_LIMIT = 500;
    const DEFAULT_HOMEPAGE = 'Dashboard';

    /**
     * Get page metadata
     */
    public function getPageData(): array
    {
        $pageInfo = parent::getPageData();
        $pageInfo['menu'] = 'administration';
        $pageInfo['title'] = 'setup-wizard';
        $pageInfo['icon'] = 'fa-solid fa-magic-wand-sparkles';
        $pageInfo['showonmenu'] = false;
        return $pageInfo;
    }

    /**
     * Get available tax regimes
     */
    public function getTaxRegimes(): array
    {
        $regimes = [];
        foreach (TaxRegime::getAll() as $code => $description) {
            $regimes[$code] = $this->translate($description);
        }
        return $regimes;
    }

    /**
     * Get select values for a model
     */
    public function getSelectValues(string $modelClass, bool $includeEmpty = false): array
    {
        $values = $includeEmpty ? ['' => '------'] : [];
        $fullClassName = '\\ERPIA\\Dinamic\\Model\\' . $modelClass;
        
        if (!class_exists($fullClassName)) {
            return $values;
        }
        
        $modelInstance = new $fullClassName();
        $orderField = $modelInstance->getDescriptionColumn();
        
        $ordering = [$orderField => 'ASC'];
        $items = $modelInstance->getAll([], $ordering, 0, self::SELECTION_LIMIT);
        
        foreach ($items as $item) {
            $values[$item->getPrimaryKeyValue()] = $item->getDescription();
        }
        
        return $values;
    }

    /**
     * Main controller logic
     */
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        
        $action = $this->request->get('action', '');
        
        switch ($action) {
            case 'step1':
                $this->processStepOne();
                break;
                
            case 'step2':
                $this->processStepTwo();
                break;
                
            case 'step3':
                $this->processStepThree();
                break;
                
            default:
                // Set company email from user if empty
                if (empty($this->company->email) && !empty($this->user->email)) {
                    $this->company->email = $this->user->email;
                    $this->company->save();
                }
        }
    }

    /**
     * Redirect to final destination
     */
    protected function finalRedirect(): void
    {
        $this->redirect($this->user->homepage ?? 'Dashboard', 2);
    }

    /**
     * Initialize required models
     */
    private function initializeModels(array $modelNames): void
    {
        foreach ($modelNames as $name) {
            $className = '\\ERPIA\\Dinamic\\Model\\' . $name;
            if (class_exists($className)) {
                new $className();
            }
        }
    }

    /**
     * Load default accounting plan for country
     */
    private function loadCountryAccountingPlan(string $countryCode): void
    {
        $planFile = ERPIA_ROOT . '/Dinamic/Data/Countries/' . $countryCode . '/defaultAccounts.csv';
        
        if (!file_exists($planFile)) {
            return;
        }
        
        // Check if old accounts table exists
        if ($this->database->tableExists('co_accounts_legacy')) {
            return;
        }
        
        // Check if accounts already exist
        $account = new Account();
        if ($account->count() > 0) {
            return;
        }
        
        // Import for each fiscal year
        foreach (FiscalYear::getAll() as $fiscalYear) {
            $importer = new AccountingPlanImport();
            $importer->importFromCSV($planFile, $fiscalYear->code);
            break; // Import for first year only
        }
    }

    /**
     * Preset application settings based on country
     */
    private function presetCountrySettings(string $countryCode): void
    {
        $configFile = ERPIA_ROOT . '/Dinamic/Data/Countries/' . $countryCode . '/defaults.json';
        
        if (file_exists($configFile)) {
            $content = file_get_contents($configFile);
            $defaults = json_decode($content, true) ?? [];
            
            foreach ($defaults as $section => $settings) {
                foreach ($settings as $key => $value) {
                    SystemConfig::set($section, $key, $value);
                }
            }
        }
        
        SystemConfig::set('general', 'country', $countryCode);
        SystemConfig::set('general', 'homepage', 'Root');
        SystemConfig::save();
    }

    /**
     * Save company address information
     */
    private function saveCompanyAddress(string $countryCode): void
    {
        $this->company->postOfficeBox = $this->request->get('postbox', '');
        $this->company->taxId = $this->request->get('taxid', '');
        $this->company->city = $this->request->get('city', '');
        $this->company->country = $countryCode;
        $this->company->postalCode = $this->request->get('postcode', '');
        $this->company->address = $this->request->get('address', '');
        $this->company->name = $this->request->get('companyname', '');
        $this->company->shortName = $this->truncateText($this->company->name, 32);
        $this->company->isIndividual = (bool)$this->request->get('isindividual', '0');
        $this->company->state = $this->request->get('state', '');
        $this->company->phone1 = $this->request->get('phone1', '');
        $this->company->phone2 = $this->request->get('phone2', '');
        $this->company->idType = $this->request->get('idtype', '');
        
        if (empty($this->company->idType)) {
            $this->company->idType = SystemConfig::get('general', 'idtype');
        }
        
        $this->company->save();
        
        // Assign or create warehouse
        $conditions = [
            new DataBaseWhere('company_id', $this->company->id),
            new DataBaseWhere('company_id', null, 'IS', 'OR')
        ];
        
        $warehouses = Warehouse::getAll($conditions);
        if (!empty($warehouses)) {
            $this->setupWarehouse($warehouses[0], $countryCode);
            return;
        }
        
        // Create new warehouse
        $newWarehouse = new Warehouse();
        $this->setupWarehouse($newWarehouse, $countryCode);
    }

    /**
     * Save email addresses
     */
    private function saveEmailAddress(string $email): bool
    {
        if (empty($this->company->email)) {
            $this->company->email = $email;
        }
        
        if (empty($this->user->email)) {
            $this->user->email = $email;
        }
        
        return $this->company->save() && $this->user->save();
    }

    /**
     * Update user password
     */
    private function updatePassword(string $newPassword): bool
    {
        $this->user->setNewPassword($newPassword);
        $confirmPassword = $this->request->get('confirmpassword', '');
        $this->user->confirmPassword = $confirmPassword;
        return $this->user->save();
    }

    /**
     * Process step one: basic configuration
     */
    private function processStepOne(): void
    {
        if (!$this->validateFormToken()) {
            return;
        }
        
        $countryCode = $this->request->get('country', $this->company->country);
        $this->presetCountrySettings($countryCode);
        
        $this->initializeModels([
            'Attachment', 'Journal', 'DocumentStatus', 'PaymentMethod',
            'Tax', 'Withholding', 'Series', 'State'
        ]);
        
        $this->saveCompanyAddress($countryCode);
        
        // Update password if provided
        $password = $this->request->get('password', '');
        if (!empty($password) && !$this->updatePassword($password)) {
            return;
        }
        
        // Update email if provided
        $email = $this->request->get('email', '');
        if (!empty($email) && !$this->saveEmailAddress($email)) {
            return;
        }
        
        $this->setTemplate('WizardStep2');
    }

    /**
     * Process step two: fiscal configuration
     */
    private function processStepTwo(): void
    {
        if (!$this->validateFormToken()) {
            return;
        }
        
        $this->company->taxRegime = $this->request->get('taxregime');
        $this->company->save();
        
        // Update system settings
        $settingsToUpdate = ['defaulttax', 'costpolicy'];
        foreach ($settingsToUpdate as $key) {
            $value = $this->request->get($key);
            $finalValue = empty($value) ? null : $value;
            SystemConfig::set('general', $key, $finalValue);
        }
        
        SystemConfig::set('inventory', 'updateprices', (bool)$this->request->get('updateprices', '0'));
        SystemConfig::set('sales', 'instockonly', (bool)$this->request->get('instockonly', '0'));
        SystemConfig::set('general', 'site_url', $this->getSiteUrl());
        SystemConfig::save();
        
        // Load default accounting plan if requested
        if ($this->request->get('loaddefaultplan', '0')) {
            $this->loadCountryAccountingPlan($this->company->country);
        }
        
        $this->setTemplate('WizardStep3');
        $this->redirect($this->getUrl() . '?action=step3', 2);
    }

    /**
     * Process step three: final initialization
     */
    protected function processStepThree(): void
    {
        // Load all model classes
        $modelNames = [];
        $modelsPath = $this->getModelsPath();
        
        if (is_dir($modelsPath)) {
            $files = scandir($modelsPath);
            foreach ($files as $file) {
                if (substr($file, -4) === '.php') {
                    $modelNames[] = substr($file, 0, -4);
                }
            }
        }
        
        // Skip for legacy installations
        if (!$this->database->tableExists('erpia_users')) {
            $this->initializeModels($modelNames);
        }
        
        // Deploy plugins
        Plugins::deploy(true, true);
        
        // Set default role
        $role = new Role();
        if ($role->loadByCode('employee')) {
            SystemConfig::set('general', 'defaultrole', $role->code);
            SystemConfig::save();
        }
        
        // Update user homepage
        $this->user->homepage = $this->database->tableExists('erpia_users') 
            ? 'PluginManager' 
            : self::DEFAULT_HOMEPAGE;
        $this->user->save();
        
        $this->setTemplate('WizardStep3');
        $this->finalRedirect();
    }

    /**
     * Configure warehouse settings
     */
    private function setupWarehouse(Warehouse $warehouse, string $countryCode): void
    {
        $warehouse->city = $this->company->city;
        $warehouse->country = $countryCode;
        $warehouse->postalCode = $this->company->postalCode;
        $warehouse->address = $this->company->address;
        $warehouse->companyId = $this->company->id;
        $warehouse->name = $this->company->shortName;
        $warehouse->state = $this->company->state;
        $warehouse->save();
        
        SystemConfig::set('inventory', 'defaultwarehouse', $warehouse->code);
        SystemConfig::set('general', 'companyid', $this->company->id);
        SystemConfig::save();
    }

    /**
     * Get models directory path
     */
    private function getModelsPath(): string
    {
        return ERPIA_ROOT . '/Dinamic/Model';
    }

    /**
     * Truncate text to specified length
     */
    private function truncateText(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length - 3) . '...';
    }

    /**
     * Get current site URL
     */
    private function getSiteUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }
}