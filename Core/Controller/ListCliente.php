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

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\DataSrc\FormasPago;
use ERPIA\Core\DataSrc\Paises;
use ERPIA\Core\DataSrc\Retenciones;
use ERPIA\Core\DataSrc\Series;
use ERPIA\Core\Lib\ExtendedController\ListController;
use ERPIA\Core\App\Translator;
use ERPIA\Core\Model\CodeModel;

/**
 * Controller to list the items in the Cliente model
 *
 * @author ERPIA Contributors
 */
class ListCliente extends ListController
{
    /**
     * Returns page configuration data
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'sales';
        $pageData['title'] = 'customers';
        $pageData['icon'] = 'fa-solid fa-users';
        return $pageData;
    }

    /**
     * Create and configure the views
     */
    protected function createViews()
    {
        $this->createViewCustomers();
        
        // Only show additional views if user can see all data
        if ($this->permissions->onlyOwnerData === false) {
            $this->createViewContacts();
            $this->createViewBankAccounts();
            $this->createViewGroups();
        }
    }

    /**
     * Creates and configures the customers view
     *
     * @param string $viewName
     */
    protected function createViewCustomers(string $viewName = 'ListCliente'): void
    {
        // Add the main customers view
        $this->addView($viewName, 'Cliente', 'customers', 'fa-solid fa-users');
        
        // Configure default order
        $this->addOrderBy(['codcliente'], 'code');
        $this->addOrderBy(['LOWER(nombre)'], 'name', 1);
        $this->addOrderBy(['cifnif'], 'fiscal-number');
        $this->addOrderBy(['fechaalta', 'codcliente'], 'creation-date');
        $this->addOrderBy(['riesgoalcanzado'], 'current-risk');
        
        // Configure search fields
        $this->addSearchFields([
            'cifnif', 'codcliente', 'codsubcuenta', 'email', 'nombre', 'observaciones', 'razonsocial',
            'telefono1', 'telefono2'
        ]);

        // Status filter (active/suspended/all)
        $this->addFilterSelectWhere($viewName, 'status', [
            ['label' => Translator::trans('only-active'), 'where' => [new DataBaseWhere('debaja', false)]],
            ['label' => Translator::trans('only-suspended'), 'where' => [new DataBaseWhere('debaja', true)]],
            ['label' => Translator::trans('all'), 'where' => []]
        ]);
        
        // Type filter (person/company)
        $this->addFilterSelectWhere($viewName, 'type', [
            ['label' => Translator::trans('all'), 'where' => []],
            ['label' => Translator::trans('is-person'), 'where' => [new DataBaseWhere('personafisica', true)]],
            ['label' => Translator::trans('company'), 'where' => [new DataBaseWhere('personafisica', false)]]
        ]);

        // Fiscal ID type filter
        $fiscalIds = $this->codeModel->getAll('clientes', 'tipoidfiscal', 'tipoidfiscal');
        $this->addFilterSelect($viewName, 'tipoidfiscal', 'fiscal-id', 'tipoidfiscal', $fiscalIds);

        // Customer group filter
        $groupValues = $this->codeModel->getAll('gruposclientes', 'codgrupo', 'nombre');
        $this->addFilterSelect($viewName, 'codgrupo', 'group', 'codgrupo', $groupValues);

        // Series filter
        $this->addFilterSelect($viewName, 'codserie', 'series', 'codserie', Series::codeModel());
        
        // Retention filter
        $this->addFilterSelect($viewName, 'codretencion', 'retentions', 'codretencion', Retenciones::codeModel());
        
        // Payment method filter
        $this->addFilterSelect($viewName, 'codpago', 'payment-methods', 'codpago', FormasPago::codeModel());

        // VAT regime filter
        $vatRegimes = $this->codeModel->getAll('clientes', 'regimeniva', 'regimeniva');
        $this->addFilterSelect($viewName, 'regimeniva', 'vat-regime', 'regimeniva', $vatRegimes);

        // Risk filter
        $this->addFilterNumber($viewName, 'riesgoalcanzado', 'current-risk', 'riesgoalcanzado');
    }

    /**
     * Creates and configures the contacts view
     *
     * @param string $viewName
     */
    protected function createViewContacts(string $viewName = 'ListContacto'): void
    {
        // Add contacts view
        $this->addView($viewName, 'Contacto', 'addresses-and-contacts', 'fa-solid fa-address-book');
        
        // Configure default order
        $this->addOrderBy(['descripcion'], 'description');
        $this->addOrderBy(['direccion'], 'address');
        $this->addOrderBy(['nombre'], 'name');
        $this->addOrderBy(['fechaalta'], 'creation-date', 2);
        
        // Configure search fields
        $this->addSearchFields([
            'apartado', 'apellidos', 'codpostal', 'descripcion', 'direccion', 'email', 'empresa',
            'nombre', 'observaciones', 'telefono1', 'telefono2'
        ]);

        // Type filter (customers/all)
        $typeValues = [
            [
                'label' => Translator::trans('customers'),
                'where' => [new DataBaseWhere('codcliente', null, 'IS NOT')]
            ],
            [
                'label' => Translator::trans('all'),
                'where' => []
            ]
        ];
        $this->addFilterSelectWhere($viewName, 'type', $typeValues);

        // Country filter
        $this->addFilterSelect($viewName, 'codpais', 'country', 'codpais', Paises::codeModel());

        // Province filter - use autocomplete if too many values
        $provinces = $this->codeModel->getAll('contactos', 'provincia', 'provincia');
        $provinceLimit = CodeModel::getLimit();
        if (count($provinces) >= $provinceLimit) {
            $this->addFilterAutocomplete($viewName, 'provincia', 'province', 'provincia', 'contactos', 'provincia');
        } else {
            $this->addFilterSelect($viewName, 'provincia', 'province', 'provincia', $provinces);
        }

        // City filter - use autocomplete if too many values
        $cities = $this->codeModel->getAll('contactos', 'ciudad', 'ciudad');
        if (count($cities) >= $provinceLimit) {
            $this->addFilterAutocomplete($viewName, 'ciudad', 'city', 'ciudad', 'contactos', 'ciudad');
        } else {
            $this->addFilterSelect($viewName, 'ciudad', 'city', 'ciudad', $cities);
        }

        // Postal code filter (always autocomplete)
        $this->addFilterAutocomplete($viewName, 'codpostal', 'zip-code', 'codpostal', 'contactos', 'codpostal');

        // Verified filter
        $this->addFilterCheckbox($viewName, 'verificado', 'verified', 'verificado');
    }

    /**
     * Creates and configures the bank accounts view
     *
     * @param string $viewName
     */
    protected function createViewBankAccounts(string $viewName = 'ListCuentaBancoCliente'): void
    {
        // Add bank accounts view
        $this->addView($viewName, 'CuentaBancoCliente', 'bank-accounts', 'fa-solid fa-piggy-bank');
        
        // Configure search fields
        $this->addSearchFields(['codcuenta', 'descripcion', 'iban', 'swift']);
        
        // Configure default order
        $this->addOrderBy(['codcuenta'], 'bank-mandate');
        $this->addOrderBy(['descripcion'], 'description');
        $this->addOrderBy(['iban'], 'iban');
        $this->addOrderBy(['fmandato', 'codcuenta'], 'bank-mandate-date', 2);

        // Disable buttons and checkboxes
        $this->tab($viewName)
            ->setSettings('btnDelete', false)
            ->setSettings('btnNew', false)
            ->setSettings('checkBoxes', false);
    }

    /**
     * Creates and configures the customer groups view
     *
     * @param string $viewName
     */
    protected function createViewGroups(string $viewName = 'ListGrupoClientes'): void
    {
        // Add customer groups view
        $this->addView($viewName, 'GrupoClientes', 'groups', 'fa-solid fa-users-cog');
        
        // Configure search fields
        $this->addSearchFields(['nombre', 'codgrupo']);
        
        // Configure default order
        $this->addOrderBy(['codgrupo'], 'code');
        $this->addOrderBy(['nombre'], 'name', 1);
    }
}