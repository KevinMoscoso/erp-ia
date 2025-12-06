<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2021-2024 ERPIA Team
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

namespace ERPIA\Core\Lib\AjaxForms;

use ERPIA\Core\Contract\SalesModInterface;
use ERPIA\Core\DataSrc\Agents;
use ERPIA\Core\DataSrc\Countries;
use ERPIA\Core\Model\TransportAgency;
use ERPIA\Core\Model\Base\SalesDocument;
use ERPIA\Core\Model\Customer;
use ERPIA\Core\Model\Contact;
use ERPIA\Core\Session;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\City;
use ERPIA\Dinamic\Model\Country;
use ERPIA\Dinamic\Model\Province;

/**
 * Description of SalesHeaderHTML
 *
 * @author ERPIA Team
 */
class SalesHeaderHTML
{
    use CommonSalesPurchases;

    /** @var Customer */
    private static $customer;

    /** @var SalesModInterface[] */
    private static $mods = [];

    /**
     * Add a modification module
     * @param SalesModInterface $mod
     */
    public static function addMod(SalesModInterface $mod): void
    {
        self::$mods[] = $mod;
    }

    /**
     * Apply form data to the model
     * @param SalesDocument $model
     * @param array $formData
     */
    public static function apply(SalesDocument &$model, array $formData): void
    {
        // Apply mods before
        foreach (self::$mods as $mod) {
            $mod->applyBefore($model, $formData);
        }

        $customer = new Customer();
        if (empty($model->id())) {
            // New record: set user and customer
            $model->setAuthor(Session::user());
            if (isset($formData['customerCode']) && $formData['customerCode'] && $customer->load($formData['customerCode'])) {
                $model->setSubject($customer);
                if (empty($formData['action']) || $formData['action'] === 'set-customer') {
                    return;
                }
            }

            $contact = new Contact();
            if (isset($formData['billingContactId']) && $contact->load($formData['billingContactId'])) {
                $model->setSubject($contact);
                if (empty($formData['action'])) {
                    return;
                }
            }
        } elseif (isset($formData['action'], $formData['customerCode']) &&
            $formData['action'] === 'set-customer' &&
            $customer->load($formData['customerCode'])) {
            // Existing record: change customer
            $model->setSubject($customer);
            return;
        }

        $model->setWarehouse($formData['warehouseCode'] ?? $model->warehouseCode);
        $model->taxId = $formData['taxId'] ?? $model->taxId;
        $model->customerCode = $formData['customerCode'] ?? $model->customerCode;
        $model->trackingCode = $formData['trackingCode'] ?? $model->trackingCode;
        $model->currencyCode = $formData['currencyCode'] ?? $model->currencyCode;
        $model->paymentMethod = $formData['paymentMethod'] ?? $model->paymentMethod;
        $model->seriesCode = $formData['seriesCode'] ?? $model->seriesCode;
        $model->date = empty($formData['date']) ? $model->date : Tools::date($formData['date']);
        $model->emailDate = isset($formData['emailDate']) && !empty($formData['emailDate']) ? $formData['emailDate'] : $model->emailDate;
        $model->time = $formData['time'] ?? $model->time;
        $model->customerName = $formData['customerName'] ?? $model->customerName;
        $model->number2 = $formData['number2'] ?? $model->number2;
        $model->operation = $formData['operation'] ?? $model->operation;
        $model->conversionRate = (float)($formData['conversionRate'] ?? $model->conversionRate);

        foreach (['agentCode', 'carrierCode', 'accrualDate', 'expirationDate'] as $key) {
            if (isset($formData[$key])) {
                $model->{$key} = empty($formData[$key]) ? null : $formData[$key];
            }
        }

        if (false === isset($formData['billingContactId'], $formData['shippingContactId'])) {
            return;
        }

        // Set billing address
        $dir = new Contact();
        if (empty($formData['billingContactId'])) {
            $model->billingContactId = null;
            $model->address = $formData['address'] ?? $model->address;
            $model->postOfficeBox = $formData['postOfficeBox'] ?? $model->postOfficeBox;
            $model->zipCode = $formData['zipCode'] ?? $model->zipCode;
            $model->city = $formData['city'] ?? $model->city;
            $model->province = $formData['province'] ?? $model->province;
            $model->countryCode = $formData['countryCode'] ?? $model->countryCode;
        } elseif ($dir->load($formData['billingContactId'])) {
            // Update billing address
            $model->billingContactId = $dir->contactId;

            // Is billing address empty?
            if (empty($dir->address)) {
                $model->address = $formData['address'] ?? $model->address;
                $model->postOfficeBox = $formData['postOfficeBox'] ?? $model->postOfficeBox;
                $model->zipCode = $formData['zipCode'] ?? $model->zipCode;
                $model->city = $formData['city'] ?? $model->city;
                $model->province = $formData['province'] ?? $model->province;
                $model->countryCode = $formData['countryCode'] ?? $model->countryCode;
            } else {
                $model->address = $dir->address;
                $model->postOfficeBox = $dir->postOfficeBox;
                $model->zipCode = $dir->zipCode;
                $model->city = $dir->city;
                $model->province = $dir->province;
                $model->countryCode = $dir->countryCode;
            }
        }

        // Set shipping address
        $model->shippingContactId = empty($formData['shippingContactId']) ? null : $formData['shippingContactId'];

        // Apply mods after
        foreach (self::$mods as $mod) {
            $mod->apply($model, $formData);
        }
    }

    /**
     * Load required assets
     */
    public static function assets(): void
    {
        foreach (self::$mods as $mod) {
            $mod->assets();
        }
    }

    /**
     * Render the sales header
     * @param SalesDocument $model
     * @return string
     */
    public static function render(SalesDocument $model): string
    {
        return '<div class="container-fluid">'
            . '<div class="row g-2 align-items-end">'
            . self::renderField($model, 'customerCode')
            . self::renderField($model, 'warehouseCode')
            . self::renderField($model, 'seriesCode')
            . self::renderField($model, 'date')
            . self::renderNewFields($model)
            . self::renderField($model, 'number2')
            . self::renderField($model, 'paymentMethod')
            . self::renderField($model, 'expirationDate')
            . self::renderField($model, 'total')
            . '</div>'
            . '<div class="row g-2 align-items-end">'
            . self::renderField($model, '_detail')
            . self::renderField($model, '_parents')
            . self::renderField($model, '_children')
            . self::renderField($model, '_email')
            . self::renderNewBtnFields($model)
            . self::renderField($model, '_paid')
            . self::renderField($model, 'statusId')
            . '</div>'
            . '</div>';
    }

    /**
     * Render address field
     * @param SalesDocument $model
     * @param string $field
     * @param string $label
     * @param int $size
     * @param int $maxlength
     * @return string
     */
    private static function addressField(SalesDocument $model, string $field, string $label, int $size, int $maxlength): string
    {
        $attributes = $model->editable && (empty($model->billingContactId) || empty($model->address)) ?
            'name="' . $field . '" maxlength="' . $maxlength . '" autocomplete="off"' :
            'disabled=""';

        return '<div class="col-sm-' . $size . '">'
            . '<div class="mb-2">' . Tools::trans($label)
            . '<input type="text" ' . $attributes . ' value="' . Tools::noHtml($model->{$field}) . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render city field
     * @param SalesDocument $model
     * @param int $size
     * @param int $maxlength
     * @return string
     */
    private static function city(SalesDocument $model, int $size, int $maxlength): string
    {
        $list = '';
        $dataList = '';
        $attributes = $model->editable && (empty($model->billingContactId) || empty($model->address)) ?
            'name="city" maxlength="' . $maxlength . '" autocomplete="off"' :
            'disabled=""';

        if ($model->editable) {
            // Pre-load city list
            $list = 'list="cities"';
            $dataList = '<datalist id="cities">';

            foreach (City::all([], ['city' => 'ASC'], 0, 0) as $city) {
                $dataList .= '<option value="' . $city->city . '">' . $city->city . '</option>';
            }
            $dataList .= '</datalist>';
        }

        return '<div class="col-sm-' . $size . '">'
            . '<div class="mb-2">' . Tools::trans('city')
            . '<input type="text" ' . $attributes . ' value="' . Tools::noHtml($model->city) . '" ' . $list . ' class="form-control"/>'
            . $dataList
            . '</div>'
            . '</div>';
    }

    /**
     * Render agent code field
     * @param SalesDocument $model
     * @return string
     */
    private static function agentCode(SalesDocument $model): string
    {
        $agents = Agents::all();
        if (count($agents) === 0) {
            return '';
        }

        $options = ['<option value="">------</option>'];
        foreach ($agents as $row) {
            // Skip inactive agents if not selected
            if ($row->disabled && $row->agentCode != $model->agentCode) {
                continue;
            }

            $options[] = ($row->agentCode === $model->agentCode) ?
                '<option value="' . $row->agentCode . '" selected>' . $row->name . '</option>' :
                '<option value="' . $row->agentCode . '">' . $row->name . '</option>';
        }

        $attributes = $model->editable ? 'name="agentCode"' : 'disabled';
        return empty($model->subjectColumnValue()) ? '' : '<div class="col-sm-6">'
            . '<div class="mb-2">'
            . '<a href="' . Agents::get($model->agentCode)->url() . '">' . Tools::trans('agent') . '</a>'
            . '<select ' . $attributes . ' class="form-select">' . implode('', $options) . '</select>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render customer code field
     * @param SalesDocument $model
     * @return string
     */
    private static function customerCode(SalesDocument $model): string
    {
        self::$customer = new Customer();
        if (empty($model->customerCode) || false === self::$customer->load($model->customerCode)) {
            return '<div class="col-sm-6 col-md-4 col-lg-3">'
                . '<div class="mb-2">' . Tools::trans('customer')
                . '<input type="hidden" name="customerCode"/>'
                . '<a href="#" id="btnFindCustomerModal" class="btn w-100 btn-primary" onclick="$(\'#findCustomerModal\').modal(\'show\');'
                . ' $(\'#findCustomerInput\').focus(); return false;"><i class="fa-solid fa-users fa-fw"></i> '
                . Tools::trans('select') . '</a>'
                . '</div>'
                . '</div>'
                . self::detailModal($model);
        }

        $btnCustomer = $model->editable ?
            '<button class="btn btn-outline-secondary" type="button" onclick="$(\'#findCustomerModal\').modal(\'show\');'
            . ' $(\'#findCustomerInput\').focus(); return false;"><i class="fa-solid fa-pen"></i></button>' :
            '<button class="btn btn-outline-secondary" type="button"><i class="fa-solid fa-lock"></i></button>';

        $html = '<div class="col-sm-6 col-md-4 col-lg">'
            . '<div class="mb-2">'
            . '<a href="' . self::$customer->url() . '">' . Tools::trans('customer') . '</a>'
            . '<input type="hidden" name="customerCode" value="' . $model->customerCode . '"/>'
            . '<div class="input-group">'
            . '<input type="text" value="' . Tools::noHtml(self::$customer->name) . '" class="form-control" readonly/>'
            . '' . $btnCustomer . ''
            . '</div>'
            . '</div>'
            . '</div>';

        if (empty($model->id())) {
            $html .= self::detail($model, true);
        }

        return $html;
    }

    /**
     * Render tracking code field
     * @param SalesDocument $model
     * @return string
     */
    private static function trackingCode(SalesDocument $model): string
    {
        $attributes = $model->editable ? 'name="trackingCode" maxlength="200" autocomplete="off"' : 'disabled=""';
        return '<div class="col-sm-4">'
            . '<div class="mb-2">' . Tools::trans('tracking-code')
            . '<input type="text" ' . $attributes . ' value="' . Tools::noHtml($model->trackingCode) . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render country code field
     * @param SalesDocument $model
     * @return string
     */
    private static function countryCode(SalesDocument $model): string
    {
        $options = [];
        foreach (Countries::all() as $country) {
            $options[] = ($country->countryCode === $model->countryCode) ?
                '<option value="' . $country->countryCode . '" selected>' . $country->name . '</option>' :
                '<option value="' . $country->countryCode . '">' . $country->name . '</option>';
        }

        $countryModel = new Country();
        $attributes = $model->editable && (empty($model->billingContactId) || empty($model->address)) ?
            'name="countryCode"' :
            'disabled=""';
        return '<div class="col-sm-6">'
            . '<div class="mb-2">'
            . '<a href="' . $countryModel->url() . '">' . Tools::trans('country') . '</a>'
            . '<select ' . $attributes . ' class="form-select">' . implode('', $options) . '</select>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render carrier code field
     * @param SalesDocument $model
     * @return string
     */
    private static function carrierCode(SalesDocument $model): string
    {
        $options = ['<option value="">------</option>'];
        $transportAgency = new TransportAgency();
        foreach ($transportAgency->all() as $agency) {
            $options[] = ($agency->carrierCode === $model->carrierCode) ?
                '<option value="' . $agency->carrierCode . '" selected>' . $agency->name . '</option>' :
                '<option value="' . $agency->carrierCode . '">' . $agency->name . '</option>';
        }

        $attributes = $model->editable ? 'name="carrierCode"' : 'disabled=""';
        return '<div class="col-sm-4">'
            . '<div class="mb-2">'
            . '<a href="' . $transportAgency->url() . '">' . Tools::trans('carrier') . '</a>'
            . '<select ' . $attributes . ' class="form-select">' . implode('', $options) . '</select>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render detail button
     * @param SalesDocument $model
     * @param bool $new
     * @return string
     */
    private static function detail(SalesDocument $model, bool $new = false): string
    {
        if (empty($model->id()) && $new === false) {
            // Detail modal already rendered for new records
            return '';
        }

        $css = $new ? 'col-sm-auto' : 'col-sm';
        return '<div class="' . $css . '">'
            . '<div class="mb-2">'
            . '<button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#headerModal">'
            . '<i class="fa-solid fa-edit fa-fw" aria-hidden="true"></i> ' . Tools::trans('detail') . ' </button>'
            . '</div>'
            . '</div>'
            . self::detailModal($model);
    }

    /**
     * Render detail modal
     * @param SalesDocument $model
     * @return string
     */
    private static function detailModal(SalesDocument $model): string
    {
        return '<div class="modal fade" id="headerModal" tabindex="-1" aria-labelledby="headerModalLabel" aria-hidden="true">'
            . '<div class="modal-dialog modal-dialog-centered modal-lg">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title"><i class="fa-solid fa-edit fa-fw" aria-hidden="true"></i> ' . Tools::trans('detail') . '</h5>'
            . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
            . '</div>'
            . '<div class="modal-body">'
            . '<div class="row g-2">'
            . self::renderField($model, 'customerName')
            . self::renderField($model, 'taxId')
            . self::renderField($model, 'billingContactId')
            . self::renderField($model, 'address')
            . self::renderField($model, 'postOfficeBox')
            . self::renderField($model, 'zipCode')
            . self::renderField($model, 'city')
            . self::renderField($model, 'province')
            . self::renderField($model, 'countryCode')
            . self::renderField($model, 'shippingContactId')
            . self::renderField($model, 'carrierCode')
            . self::renderField($model, 'trackingCode')
            . self::renderField($model, 'accrualDate')
            . self::renderField($model, 'time')
            . self::renderField($model, 'operation')
            . self::renderField($model, 'emailDate')
            . self::renderField($model, 'currencyCode')
            . self::renderField($model, 'conversionRate')
            . self::renderField($model, 'user')
            . self::renderField($model, 'agentCode')
            . self::renderNewModalFields($model)
            . '</div>'
            . '</div>'
            . '<div class="modal-footer">'
            . '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Tools::trans('close') . '</button>'
            . '<button type="button" class="btn btn-primary" data-bs-dismiss="modal">' . Tools::trans('accept') . '</button>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render expiration date field
     * @param SalesDocument $model
     * @return string
     */
    private static function expirationDate(SalesDocument $model): string
    {
        if (false === $model->hasColumn('expirationDate') || empty($model->id())) {
            return '';
        }

        $label = empty($model->expirationDate) || strtotime($model->expirationDate) > time() ?
            Tools::trans('expiration') :
            '<span class="text-danger">' . Tools::trans('expiration') . '</span>';

        $attributes = $model->editable ? 'name="expirationDate"' : 'disabled=""';
        $value = empty($model->expirationDate) ? '' : 'value="' . date('Y-m-d', strtotime($model->expirationDate)) . '"';
        return '<div class="col-sm-6 col-md-4 col-lg">'
            . '<div class="mb-2">' . $label
            . '<input type="date" ' . $attributes . ' ' . $value . ' class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    /**
     * Get address options for select
     * @param mixed $selected
     * @param bool $empty
     * @return array
     */
    private static function getAddressOptions($selected, bool $empty): array
    {
        $options = $empty ? ['<option value="">------</option>'] : [];
        foreach (self::$customer->getAddresses() as $contact) {
            $description = empty($contact->description) ? '(' . Tools::trans('empty') . ') ' : '(' . $contact->description . ') ';
            $description .= empty($contact->address) ? '' : $contact->address;
            $options[] = $contact->contactId == $selected ?
                '<option value="' . $contact->contactId . '" selected>' . $description . '</option>' :
                '<option value="' . $contact->contactId . '">' . $description . '</option>';
        }
        return $options;
    }

    /**
     * Render shipping contact field
     * @param SalesDocument $model
     * @return string
     */
    private static function shippingContactId(SalesDocument $model): string
    {
        if (empty($model->customerCode)) {
            return '';
        }

        $attributes = $model->editable ? 'name="shippingContactId"' : 'disabled=""';
        $options = self::getAddressOptions($model->shippingContactId, true);
        return '<div class="col-sm-4">'
            . '<div class="mb-2">'
            . '<a href="' . self::$customer->url() . '&activetab=EditAddressContact" target="_blank">'
            . Tools::trans('shipping-address') . '</a>'
            . '<select ' . $attributes . ' class="form-select">' . implode('', $options) . '</select>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render billing contact field
     * @param SalesDocument $model
     * @return string
     */
    private static function billingContactId(SalesDocument $model): string
    {
        if (empty($model->customerCode)) {
            return '';
        }

        $attributes = $model->editable ? 'name="billingContactId" onchange="return salesFormActionWait(\'recalculate-line\', \'0\', event);"' : 'disabled=""';
        $options = self::getAddressOptions($model->billingContactId, true);
        return '<div class="col-sm-6">'
            . '<div class="mb-2">'
            . '<a href="' . self::$customer->url() . '&activetab=EditAddressContact" target="_blank">' . Tools::trans('billing-address') . '</a>'
            . '<select ' . $attributes . ' class="form-select">' . implode('', $options) . '</select>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render customer name field
     * @param SalesDocument $model
     * @return string
     */
    private static function customerName(SalesDocument $model): string
    {
        $attributes = $model->editable ? 'name="customerName" required="" maxlength="100" autocomplete="off"' : 'disabled=""';
        return '<div class="col-sm-6">'
            . '<div class="mb-2">'
            . Tools::trans('business-name')
            . '<input type="text" ' . $attributes . ' value="' . Tools::noHtml($model->customerName) . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render number2 field
     * @param SalesDocument $model
     * @return string
     */
    private static function number2(SalesDocument $model): string
    {
        $attributes = $model->editable ? 'name="number2" maxlength="50" placeholder="' . Tools::trans('optional') . '"' : 'disabled=""';
        return empty($model->customerCode) ? '' : '<div class="col-sm-6 col-md-4 col-lg">'
            . '<div class="mb-2">'
            . Tools::trans('number2')
            . '<input type="text" ' . $attributes . ' value="' . Tools::noHtml($model->number2) . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render province field
     * @param SalesDocument $model
     * @param int $size
     * @param int $maxlength
     * @return string
     */
    private static function province(SalesDocument $model, int $size, int $maxlength): string
    {
        $list = '';
        $dataList = '';
        $attributes = $model->editable && (empty($model->billingContactId) || empty($model->address)) ?
            'name="province" maxlength="' . $maxlength . '" autocomplete="off"' :
            'disabled=""';

        if ($model->editable) {
            // Pre-load province list
            $list = 'list="provinces"';
            $dataList = '<datalist id="provinces">';

            foreach (Province::all([], ['province' => 'ASC'], 0, 0) as $province) {
                $dataList .= '<option value="' . $province->province . '">' . $province->province . '</option>';
            }
            $dataList .= '</datalist>';
        }

        return '<div class="col-sm-' . $size . '">'
            . '<div class="mb-2">' . Tools::trans('province')
            . '<input type="text" ' . $attributes . ' value="' . Tools::noHtml($model->province) . '" ' . $list . ' class="form-control"/>'
            . $dataList
            . '</div>'
            . '</div>';
    }

    /**
     * Render a specific field
     * @param SalesDocument $model
     * @param string $field
     * @return string|null
     */
    private static function renderField(SalesDocument $model, string $field): ?string
    {
        foreach (self::$mods as $mod) {
            $html = $mod->renderField($model, $field);
            if ($html !== null) {
                return $html;
            }
        }

        switch ($field) {
            case '_children':
                return self::children($model);

            case '_detail':
                return self::detail($model);

            case '_email':
                return self::email($model);

            case '_fecha':
                return self::fecha($model, false);

            case '_paid':
                return self::paid($model);

            case '_parents':
                return self::parents($model);

            case 'postOfficeBox':
                return self::addressField($model, 'postOfficeBox', 'post-office-box', 4, 10);

            case 'taxId':
                return self::taxId($model);

            case 'city':
                return self::city($model, 4, 100);

            case 'agentCode':
                return self::agentCode($model);

            case 'warehouseCode':
                return self::warehouseCode($model, 'salesFormAction');

            case 'customerCode':
                return self::customerCode($model);

            case 'currencyCode':
                return self::currencyCode($model);

            case 'trackingCode':
                return self::trackingCode($model);

            case 'paymentMethod':
                return self::paymentMethod($model);

            case 'countryCode':
                return self::countryCode($model);

            case 'zipCode':
                return self::addressField($model, 'zipCode', 'zip-code', 4, 10);

            case 'seriesCode':
                return self::seriesCode($model, 'salesFormAction');

            case 'carrierCode':
                return self::carrierCode($model);

            case 'address':
                return self::addressField($model, 'address', 'address', 6, 200);

            case 'date':
                return self::fecha($model);

            case 'accrualDate':
                return self::accrualDate($model);

            case 'emailDate':
                return self::emailDate($model);

            case 'expirationDate':
                return self::expirationDate($model);

            case 'time':
                return self::time($model);

            case 'billingContactId':
                return self::billingContactId($model);

            case 'shippingContactId':
                return self::shippingContactId($model);

            case 'statusId':
                return self::statusId($model, 'salesFormSave');

            case 'customerName':
                return self::customerName($model);

            case 'number2':
                return self::number2($model);

            case 'operation':
                return self::operation($model);

            case 'province':
                return self::province($model, 6, 100);

            case 'conversionRate':
                return self::conversionRate($model);

            case 'total':
                return self::total($model, 'salesFormSave');

            case 'user':
                return self::user($model);
        }

        return null;
    }

    /**
     * Render new button fields from mods
     * @param SalesDocument $model
     * @return string
     */
    private static function renderNewBtnFields(SalesDocument $model): string
    {
        $newFields = [];
        foreach (self::$mods as $mod) {
            foreach ($mod->newBtnFields() as $field) {
                if (false === in_array($field, $newFields)) {
                    $newFields[] = $field;
                }
            }
        }

        $html = '';
        foreach ($newFields as $field) {
            foreach (self::$mods as $mod) {
                $fieldHtml = $mod->renderField($model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }

    /**
     * Render new fields from mods
     * @param SalesDocument $model
     * @return string
     */
    private static function renderNewFields(SalesDocument $model): string
    {
        $newFields = [];
        foreach (self::$mods as $mod) {
            foreach ($mod->newFields() as $field) {
                if (false === in_array($field, $newFields)) {
                    $newFields[] = $field;
                }
            }
        }

        $html = '';
        foreach ($newFields as $field) {
            foreach (self::$mods as $mod) {
                $fieldHtml = $mod->renderField($model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }

    /**
     * Render new modal fields from mods
     * @param SalesDocument $model
     * @return string
     */
    private static function renderNewModalFields(SalesDocument $model): string
    {
        $newFields = [];
        foreach (self::$mods as $mod) {
            foreach ($mod->newModalFields() as $field) {
                if (false === in_array($field, $newFields)) {
                    $newFields[] = $field;
                }
            }
        }

        $html = '';
        foreach ($newFields as $field) {
            foreach (self::$mods as $mod) {
                $fieldHtml = $mod->renderField($model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }
}