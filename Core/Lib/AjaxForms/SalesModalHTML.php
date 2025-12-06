<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2021-2025 ERPIA Team
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

use ERPIA\Core\Base\ControllerPermissions;
use ERPIA\Core\Base\DataBase;
use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Cache;
use ERPIA\Core\Model\Base\SalesDocument;
use ERPIA\Core\Model\User;
use ERPIA\Core\Session;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\AttributeValue;
use ERPIA\Dinamic\Model\Customer;
use ERPIA\Dinamic\Model\Manufacturer;
use ERPIA\Dinamic\Model\ProductFamily;
use ERPIA\Dinamic\Model\RoleAccess;

/**
 * Description of SalesModalHTML
 *
 * @author ERPIA Team
 */
class SalesModalHTML
{
    /** @var string */
    protected static $warehouseCode;
    
    /** @var string */
    protected static $customerCode;
    
    /** @var string */
    protected static $manufacturerCode;
    
    /** @var string */
    protected static $familyCode;
    
    /** @var array */
    protected static $attributeValueIds = [];
    
    /** @var string */
    protected static $order;
    
    /** @var string */
    protected static $query;
    
    /** @var bool */
    protected static $sold;

    /**
     * Apply form data to modals
     * @param SalesDocument $model
     * @param array $formData
     */
    public static function apply(SalesDocument &$model, array $formData): void
    {
        self::$warehouseCode = $model->warehouseCode;
        self::$customerCode = $model->customerCode;
        self::$manufacturerCode = $formData['fp_manufacturer'] ?? '';
        self::$familyCode = $formData['fp_family'] ?? '';
        self::$order = $formData['fp_order'] ?? 'ref_asc';
        self::$sold = (bool)($formData['fp_sold'] ?? false);
        self::$query = isset($formData['fp_query']) ?
            Tools::noHtml(mb_strtolower($formData['fp_query'], 'UTF8')) : '';
    }

    /**
     * Render modals
     * @param SalesDocument $model
     * @param string $url
     * @return string
     */
    public static function render(SalesDocument $model, string $url): string
    {
        self::$warehouseCode = $model->warehouseCode;
        return $model->editable ? static::modalCustomers($url) . static::modalProducts() : '';
    }

    /**
     * Render product list
     * @return string
     */
    public static function renderProductList(): string
    {
        $tbody = '';
        foreach (static::getProducts() as $row) {
            $cssClass = $row['nostock'] ? 'table-info clickableRow' : ($row['available'] > 0 ? 'clickableRow' : 'table-warning clickableRow');
            $description = Tools::textBreak($row['description'], 120)
                . static::attributeValue($row['attribute1'])
                . static::attributeValue($row['attribute2'])
                . static::attributeValue($row['attribute3'])
                . static::attributeValue($row['attribute4']);
                
            $tbody .= '<tr class="' . $cssClass . '" onclick="$(\'#findProductModal\').modal(\'hide\');'
                . ' return salesFormAction(\'add-product\', \'' . $row['reference'] . '\');">'
                . '<td><b>' . $row['reference'] . '</b> ' . $description . '</td>'
                . '<td class="text-end">' . str_replace(' ', '&nbsp;', Tools::money($row['price'])) . '</td>';

            if (self::$sold) {
                $tbody .= '<td class="text-end">' . str_replace(' ', '&nbsp;', Tools::money($row['last_price'])) . '</td>';
            }

            $tbody .= '<td class="text-end">' . $row['available'] . '</td>'
                . '</tr>';
        }

        if (empty($tbody)) {
            $tbody .= '<tr class="table-warning"><td colspan="4">' . Tools::trans('no-data') . '</td></tr>';
        }

        $extraTh = self::$sold ?
            '<th class="text-end">' . Tools::trans('last-price-sale') . '</th>' :
            '';
        return '<table class="table table-hover mb-0">'
            . '<thead>'
            . '<tr>'
            . '<th>' . Tools::trans('product') . '</th>'
            . '<th class="text-end">' . Tools::trans('price') . '</th>'
            . $extraTh
            . '<th class="text-end">' . Tools::trans('stock') . '</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>' . $tbody . '</tbody>'
            . '</table>';
    }

    /**
     * Render manufacturers select
     * @return string
     */
    public static function manufacturers(): string
    {
        $options = '<option value="">' . Tools::trans('manufacturer') . '</option>'
            . '<option value="">------</option>';
        foreach (Manufacturer::all([], ['name' => 'ASC'], 0, 0) as $man) {
            $options .= '<option value="' . $man->manufacturerCode . '">' . $man->name . '</option>';
        }

        return '<select name="fp_manufacturer" class="form-select" onchange="return salesFormAction(\'find-product\', \'0\');">'
            . $options . '</select>';
    }

    /**
     * Render families select
     * @return string
     */
    protected static function families(): string
    {
        $options = '<option value="">' . Tools::trans('family') . '</option>'
            . '<option value="">------</option>';

        $where = [new DataBaseWhere('parent', null, 'IS')];
        $orderBy = ['description' => 'ASC'];
        foreach (ProductFamily::all($where, $orderBy, 0, 0) as $fam) {
            $options .= '<option value="' . $fam->familyCode . '">' . $fam->description . '</option>';
            $options .= static::subfamilies($fam);
        }

        return '<select name="fp_family" class="form-select" onchange="return salesFormAction(\'find-product\', \'0\');">'
            . $options . '</select>';
    }

    /**
     * Get customers with caching
     * @param User $user
     * @param ControllerPermissions $permissions
     * @return array
     */
    protected static function getCustomers(User $user, ControllerPermissions $permissions): array
    {
        $cacheKey = 'model-Customer-sales-modal-' . $user->nick;
        return Cache::remember($cacheKey, function () use ($user, $permissions) {
            // Check if user can view all customers
            $showAll = false;
            foreach (RoleAccess::allFromUser($user->nick, 'EditCustomer') as $access) {
                if (false === $access->onlyOwnerData) {
                    $showAll = true;
                }
            }

            // Database query
            $where = [new DataBaseWhere('dischargeDate', null, 'IS')];
            if ($permissions->onlyOwnerData && !$showAll) {
                $where[] = new DataBaseWhere('agentCode', $user->agentCode);
                $where[] = new DataBaseWhere('agentCode', null, 'IS NOT');
            }
            return Customer::all($where, ['LOWER(name)' => 'ASC'], 0, 50);
        });
    }

    /**
     * Get filtered products
     * @return array
     */
    protected static function getProducts(): array
    {
        $dataBase = new DataBase();
        $dataBase->connect();

        $sql = 'SELECT v.reference, p.description, v.attribute1, v.attribute2, v.attribute3,'
            . ' v.attribute4, v.price, COALESCE(s.available, 0) as available, p.nostock'
            . ' FROM product_variants v'
            . ' LEFT JOIN products p ON v.productId = p.id'
            . ' LEFT JOIN stocks s ON v.reference = s.reference AND s.warehouseCode = ' . $dataBase->var2str(self::$warehouseCode)
            . ' WHERE p.sellable = true AND p.blocked = false';

        if (self::$manufacturerCode) {
            $sql .= ' AND manufacturerCode = ' . $dataBase->var2str(self::$manufacturerCode);
        }

        if (self::$familyCode) {
            $familyCodes = [$dataBase->var2str(self::$familyCode)];
            $family = new ProductFamily();
            if ($family->load(self::$familyCode)) {
                foreach ($family->getSubfamilies() as $fam) {
                    $familyCodes[] = $dataBase->var2str($fam->familyCode);
                }
            }
            $sql .= ' AND familyCode IN (' . implode(',', $familyCodes) . ')';
        }

        if (self::$sold) {
            $sql .= ' AND v.reference IN (SELECT reference FROM customer_invoice_lines'
                . ' LEFT JOIN customer_invoices ON customer_invoice_lines.invoiceId = customer_invoices.id'
                . ' WHERE customerCode = ' . $dataBase->var2str(self::$customerCode) . ')';
        }

        if (self::$query) {
            $words = explode(' ', self::$query);
            if (count($words) === 1) {
                $sql .= " AND (LOWER(v.barcode) = " . $dataBase->var2str(self::$query)
                    . " OR LOWER(v.reference) LIKE '%" . self::$query . "%'"
                    . " OR LOWER(p.description) LIKE '%" . self::$query . "%')";
            } elseif (count($words) > 1) {
                $sql .= " AND (LOWER(v.reference) LIKE '%" . self::$query . "%' OR (";
                foreach ($words as $wc => $word) {
                    $sql .= $wc > 0 ?
                        " AND LOWER(p.description) LIKE '%" . $word . "%'" :
                        "LOWER(p.description) LIKE '%" . $word . "%'";
                }
                $sql .= "))";
            }
        }

        switch (self::$order) {
            case 'desc_asc':
                $sql .= " ORDER BY 2 ASC";
                break;
            case 'price_desc':
                $sql .= " ORDER BY 7 DESC";
                break;
            case 'ref_asc':
                $sql .= " ORDER BY 1 ASC";
                break;
            case 'stock_desc':
                $sql .= " ORDER BY 8 DESC";
                break;
        }

        $results = $dataBase->selectLimit($sql);
        if (self::$sold) {
            static::setProductsLastPrice($dataBase, $results);
        }

        return $results;
    }

    /**
     * Get attribute value description
     * @param int|null $id
     * @return string
     */
    protected static function attributeValue(?int $id): string
    {
        if (empty($id)) {
            return '';
        }

        if (!isset(self::$attributeValueIds[$id])) {
            $attrValue = new AttributeValue();
            $attrValue->load($id);
            self::$attributeValueIds[$id] = $attrValue->description;
        }

        return ', ' . self::$attributeValueIds[$id];
    }

    /**
     * Render customers modal
     * @param string $url
     * @return string
     */
    protected static function modalCustomers(string $url): string
    {
        $trs = '';
        $user = Session::user();
        $permissions = Session::permissions();

        foreach (static::getCustomers($user, $permissions) as $customer) {
            $name = ($customer->name === $customer->businessName) ? 
                $customer->name : 
                $customer->name . ' <small>(' . $customer->businessName . ')</span>';
                
            $trs .= '<tr class="clickableRow" onclick="document.forms[\'salesForm\'][\'customerCode\'].value = \''
                . $customer->customerCode . '\'; $(\'#findCustomerModal\').modal(\'hide\'); salesFormAction(\'set-customer\', \'0\'); return false;">'
                . '<td><i class="fa-solid fa-user fa-fw"></i> ' . $name . '</td>'
                . '</tr>';
        }

        $linkAgent = '';
        if ($user->agentCode) {
            $linkAgent = '&agentCode=' . $user->agentCode;
        }

        return '<div class="modal" id="findCustomerModal" tabindex="-1" aria-hidden="true">'
            . '<div class="modal-dialog modal-dialog-scrollable">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title"><i class="fa-solid fa-users fa-fw"></i> ' . Tools::trans('customers') . '</h5>'
            . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
            . '</div>'
            . '<div class="modal-body p-0">'
            . '<div class="p-3">'
            . '<div class="input-group">'
            . '<input type="text" id="findCustomerInput" class="form-control" placeholder="' . Tools::trans('search') . '" />'
            . '<div class="input-group-apend">'
            . '<button type="button" class="btn btn-primary"><i class="fa-solid fa-search"></i></button>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '<table class="table table-hover mb-0">' . $trs . '</table></div>'
            . '<div class="modal-footer bg-light">'
            . '<a href="EditCustomer?return=' . urlencode($url) . $linkAgent . '" class="btn w-100 btn-success">'
            . '<i class="fa-solid fa-plus fa-fw"></i> ' . Tools::trans('new')
            . '</a>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render products modal
     * @return string
     */
    protected static function modalProducts(): string
    {
        return '<div class="modal" id="findProductModal" tabindex="-1" aria-hidden="true">'
            . '<div class="modal-dialog modal-xl">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title"><i class="fa-solid fa-cubes fa-fw"></i> ' . Tools::trans('products') . '</h5>'
            . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
            . '</div>'
            . '<div class="modal-body">'
            . '<div class="row g-2">'
            . '<div class="col-sm-6 col-md-12 col-lg mb-2">'
            . '<div class="input-group">'
            . '<input type="text" name="fp_query" class="form-control" id="productModalInput" placeholder="' . Tools::trans('search')
            . '" onkeyup="return salesFormActionWait(\'find-product\', \'0\', event);"/>'
            . '<button class="btn btn-primary btn-spin-action" type="button" onclick="return salesFormAction(\'find-product\', \'0\');">'
            . '<i class="fa-solid fa-search"></i></button>'
            . '</div>'
            . '</div>'
            . '<div class="col-sm-6 col-md-4 col-lg mb-2">' . static::manufacturers() . '</div>'
            . '<div class="col-sm-6 col-md-4 col-lg mb-2">' . static::families() . '</div>'
            . '<div class="col-sm-6 col-md-4 col-lg mb-2">' . static::order() . '</div>'
            . '</div>'
            . '<div class="row g-2">'
            . '<div class="col-sm">'
            . '<div class="form-check">'
            . '<input type="checkbox" name="fp_sold" value="1" class="form-check-input" id="sold" onchange="return salesFormAction(\'find-product\', \'0\');">'
            . '<label class="form-check-label" for="sold">' . Tools::trans('previously-sold-to-customer') . '</label>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '<div class="table-responsive" id="findProductList">' . static::renderProductList() . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render order select
     * @return string
     */
    protected static function order(): string
    {
        return '<div class="input-group">'
            . '<span class="input-group-text"><i class="fa-solid fa-sort-amount-down-alt"></i></span>'
            . '<select name="fp_order" class="form-select" onchange="return salesFormAction(\'find-product\', \'0\');">'
            . '<option value="">' . Tools::trans('sort') . '</option>'
            . '<option value="">------</option>'
            . '<option value="ref_asc">' . Tools::trans('reference') . '</option>'
            . '<option value="desc_asc">' . Tools::trans('description') . '</option>'
            . '<option value="price_desc">' . Tools::trans('price') . '</option>'
            . '<option value="stock_desc">' . Tools::trans('stock') . '</option>'
            . '</select>'
            . '</div>';
    }

    /**
     * Set last sold price for products
     * @param DataBase $db
     * @param array $items
     */
    protected static function setProductsLastPrice(DataBase $db, array &$items): void
    {
        foreach ($items as $key => $item) {
            $sql = 'SELECT unitPrice FROM customer_invoice_lines l'
                . ' LEFT JOIN customer_invoices f ON f.id = l.invoiceId'
                . ' WHERE f.customerCode = ' . $db->var2str(self::$customerCode)
                . ' AND l.reference = ' . $db->var2str($item['reference'])
                . ' ORDER BY f.date DESC';
                
            foreach ($db->selectLimit($sql, 1) as $row) {
                $items[$key]['last_price'] = $row['unitPrice'];
                continue 2;
            }
            $items[$key]['last_price'] = $item['price'];
        }
    }

    /**
     * Get subfamilies recursively
     * @param ProductFamily $family
     * @param int $level
     * @return string
     */
    private static function subfamilies(ProductFamily $family, int $level = 1): string
    {
        $options = '';
        foreach ($family->getSubfamilies() as $subFamily) {
            $options .= '<option value="' . $subFamily->familyCode . '">'
                . str_repeat('-', $level) . ' ' . $subFamily->description
                . '</option>';
            $options .= static::subfamilies($subFamily, $level + 1);
        }
        return $options;
    }
}