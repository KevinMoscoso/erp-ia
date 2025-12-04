<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2017-2024 ERPIA Contributors
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

use ERPIA\Core\Lib\ExtendedController\ListController;

/**
 * Controller to list the items in the Pais model
 *
 * @author ERPIA Contributors
 */
class ListPais extends ListController
{
    /**
     * Returns page configuration data
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'admin';
        $pageData['title'] = 'countries';
        $pageData['icon'] = 'fa-solid fa-globe-americas';
        return $pageData;
    }

    /**
     * Create and configure the views
     */
    protected function createViews()
    {
        $this->createViewsCountries();
        $this->createViewsProvinces();
        $this->createViewsCities();
        $this->createViewsPOIs();
        $this->createViewsZipCodes();
        $this->createViewsDivisas();
    }

    /**
     * Creates and configures the cities view
     *
     * @param string $viewName
     */
    protected function createViewsCities(string $viewName = 'ListCiudad'): void
    {
        // Add cities view
        $this->addView($viewName, 'Ciudad', 'cities', 'fa-solid fa-city');
        
        // Configure default order
        $this->addOrderBy(['ciudad'], 'name');
        $this->addOrderBy(['idprovincia'], 'province');
        
        // Configure search fields
        $this->addSearchFields(['ciudad', 'alias']);
        
        // Province filter (autocomplete)
        $this->addFilterAutocomplete($viewName, 'idprovincia', 'province', 'idprovincia', 'provincias', 'idprovincia', 'provincia');
        
        // Disable new button
        $this->setSettings($viewName, 'btnNew', false);
    }

    /**
     * Creates and configures the countries view
     *
     * @param string $viewName
     */
    protected function createViewsCountries(string $viewName = 'ListPais'): void
    {
        // Add countries view
        $this->addView($viewName, 'Pais', 'countries', 'fa-solid fa-globe-americas');
        
        // Configure default order
        $this->addOrderBy(['codpais'], 'code');
        $this->addOrderBy(['nombre'], 'name', 1);
        $this->addOrderBy(['codiso'], 'codiso');
        
        // Configure search fields
        $this->addSearchFields(['nombre', 'codiso', 'codpais', 'alias']);
    }

    /**
     * Creates and configures the currencies view
     *
     * @param string $viewName
     */
    protected function createViewsDivisas(string $viewName = 'ListDivisa'): void
    {
        // Add currencies view
        $this->addView($viewName, 'Divisa', 'currency', 'fa-solid fa-money-bill-alt');
        
        // Configure default order
        $this->addOrderBy(['coddivisa'], 'code');
        $this->addOrderBy(['descripcion'], 'description', 1);
        $this->addOrderBy(['codiso'], 'codiso');
        
        // Configure search fields
        $this->addSearchFields(['descripcion', 'coddivisa']);
    }

    /**
     * Creates and configures the points of interest view
     *
     * @param string $viewName
     */
    protected function createViewsPOIs(string $viewName = 'ListPuntoInteresCiudad'): void
    {
        // Add points of interest view
        $this->addView($viewName, 'PuntoInteresCiudad', 'points-of-interest', 'fa-solid fa-location-dot');
        
        // Configure default order
        $this->addOrderBy(['name'], 'name');
        $this->addOrderBy(['idciudad'], 'city');
        
        // Configure search fields
        $this->addSearchFields(['name', 'alias']);
        
        // City filter (autocomplete)
        $this->addFilterAutocomplete($viewName, 'idciudad', 'city', 'idciudad', 'ciudades', 'idciudad', 'ciudad');
        
        // Disable new button
        $this->setSettings($viewName, 'btnNew', false);
    }

    /**
     * Creates and configures the provinces view
     *
     * @param string $viewName
     */
    protected function createViewsProvinces(string $viewName = 'ListProvincia'): void
    {
        // Add provinces view
        $this->addView($viewName, 'Provincia', 'provinces', 'fa-solid fa-map-signs');
        
        // Configure default order
        $this->addOrderBy(['provincia'], 'name');
        $this->addOrderBy(['codpais'], 'country');
        
        // Configure search fields
        $this->addSearchFields(['provincia', 'codisoprov', 'alias']);
        
        // Country filter (autocomplete)
        $this->addFilterAutocomplete($viewName, 'codpais', 'country', 'codpais', 'paises', 'codpais', 'nombre');
        
        // Disable new button
        $this->setSettings($viewName, 'btnNew', false);
    }

    /**
     * Creates and configures the zip codes view
     *
     * @param string $viewName
     */
    protected function createViewsZipCodes(string $viewName = 'ListCodigoPostal'): void
    {
        // Add zip codes view
        $this->addView($viewName, 'CodigoPostal', 'zip-codes', 'fa-solid fa-map-pin');
        
        // Configure default order
        $this->addOrderBy(['number'], 'number');
        $this->addOrderBy(['codpais'], 'country');
        $this->addOrderBy(['idprovincia'], 'province');
        $this->addOrderBy(['idciudad'], 'city');
        
        // Configure search fields
        $this->addSearchFields(['number']);
        
        // Country filter (autocomplete)
        $this->addFilterAutocomplete($viewName, 'codpais', 'country', 'codpais', 'paises', 'codpais', 'nombre');
        
        // Province filter (autocomplete)
        $this->addFilterAutocomplete($viewName, 'idprovincia', 'province', 'idprovincia', 'provincias', 'idprovincia', 'provincia');
        
        // City filter (autocomplete)
        $this->addFilterAutocomplete($viewName, 'idciudad', 'city', 'idciudad', 'ciudades', 'idciudad', 'ciudad');
    }
}