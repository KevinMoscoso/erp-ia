<?php
namespace ERPIA\Core\Base\AjaxForms;

use ERPIA\Core\Base\Contract\SalesModInterface;
use ERPIA\Core\Base\Translator;
use ERPIA\Core\DataSrc\Agentes;
use ERPIA\Core\DataSrc\Paises;
use ERPIA\Core\Model\AgenciaTransporte;
use ERPIA\Core\Model\Base\SalesDocument;
use ERPIA\Core\Model\Cliente;
use ERPIA\Core\Model\Contacto;
use ERPIA\Core\Model\User;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\Ciudad;
use ERPIA\Dinamic\Model\Pais;
use ERPIA\Dinamic\Model\Provincia;

class SalesHeaderHTML
{
    use CommonSalesPurchases;

    /** @var Cliente */
    private static $cliente;

    /** @var SalesModInterface[] */
    private static $mods = [];

    public static function addMod(SalesModInterface $mod)
    {
        self::$mods[] = $mod;
    }

    public static function apply(SalesDocument &$model, array $formData, User $user)
    {
        foreach (self::$mods as $modifier) {
            $modifier->applyBefore($model, $formData, $user);
        }

        $customer = new Cliente();
        $isNewRecord = empty($model->primaryColumnValue());
        
        if ($isNewRecord) {
            $model->setAuthor($user);
            
            if (!empty($formData['codcliente']) && $customer->loadFromCode($formData['codcliente'])) {
                $model->setSubject($customer);
                if (empty($formData['action']) || $formData['action'] === 'set-customer') {
                    return;
                }
            }

            $contact = new Contacto();
            if (!empty($formData['idcontactofact']) && $contact->loadFromCode($formData['idcontactofact'])) {
                $model->setSubject($contact);
                if (empty($formData['action'])) {
                    return;
                }
            }
        } elseif (!empty($formData['action']) && !empty($formData['codcliente']) && 
                 $formData['action'] === 'set-customer' && $customer->loadFromCode($formData['codcliente'])) {
            $model->setSubject($customer);
            return;
        }

        $model->setWarehouse($formData['codalmacen'] ?? $model->codalmacen);
        $model->cifnif = $formData['cifnif'] ?? $model->cifnif;
        $model->codcliente = $formData['codcliente'] ?? $model->codcliente;
        $model->codigoenv = $formData['codigoenv'] ?? $model->codigoenv;
        $model->coddivisa = $formData['coddivisa'] ?? $model->coddivisa;
        $model->codpago = $formData['codpago'] ?? $model->codpago;
        $model->codserie = $formData['codserie'] ?? $model->codserie;
        
        if (!empty($formData['fecha'])) {
            $model->fecha = Tools::date($formData['fecha']);
        }
        
        $model->femail = !empty($formData['femail']) ? $formData['femail'] : $model->femail;
        $model->hora = $formData['hora'] ?? $model->hora;
        $model->nombrecliente = $formData['nombrecliente'] ?? $model->nombrecliente;
        $model->numero2 = $formData['numero2'] ?? $model->numero2;
        $model->operacion = $formData['operacion'] ?? $model->operacion;
        $model->tasaconv = (float)($formData['tasaconv'] ?? $model->tasaconv);

        $optionalFields = ['codagente', 'codtrans', 'fechadevengo', 'finoferta'];
        foreach ($optionalFields as $field) {
            if (array_key_exists($field, $formData)) {
                $model->{$field} = empty($formData[$field]) ? null : $formData[$field];
            }
        }

        if (!isset($formData['idcontactofact'], $formData['idcontactoenv'])) {
            return;
        }

        $billingContact = new Contacto();
        if (empty($formData['idcontactofact'])) {
            $model->idcontactofact = null;
            $model->direccion = $formData['direccion'] ?? $model->direccion;
            $model->apartado = $formData['apartado'] ?? $model->apartado;
            $model->codpostal = $formData['codpostal'] ?? $model->codpostal;
            $model->ciudad = $formData['ciudad'] ?? $model->ciudad;
            $model->provincia = $formData['provincia'] ?? $model->provincia;
            $model->codpais = $formData['codpais'] ?? $model->codpais;
        } elseif ($billingContact->loadFromCode($formData['idcontactofact'])) {
            $model->idcontactofact = $billingContact->idcontacto;

            if (empty($billingContact->direccion)) {
                $model->direccion = $formData['direccion'] ?? $model->direccion;
                $model->apartado = $formData['apartado'] ?? $model->apartado;
                $model->codpostal = $formData['codpostal'] ?? $model->codpostal;
                $model->ciudad = $formData['ciudad'] ?? $model->ciudad;
                $model->provincia = $formData['provincia'] ?? $model->provincia;
                $model->codpais = $formData['codpais'] ?? $model->codpais;
            } else {
                $model->direccion = $billingContact->direccion;
                $model->apartado = $billingContact->apartado;
                $model->codpostal = $billingContact->codpostal;
                $model->ciudad = $billingContact->ciudad;
                $model->provincia = $billingContact->provincia;
                $model->codpais = $billingContact->codpais;
            }
        }

        $model->idcontactoenv = empty($formData['idcontactoenv']) ? null : $formData['idcontactoenv'];

        foreach (self::$mods as $modifier) {
            $modifier->apply($model, $formData, $user);
        }
    }

    public static function assets()
    {
        foreach (self::$mods as $modifier) {
            $modifier->assets();
        }
    }

    public static function render(SalesDocument $model): string
    {
        $translator = new Translator();
        $html = '<div class="container-fluid">';
        
        $html .= '<div class="form-row align-items-end">';
        $html .= self::renderField($translator, $model, 'codcliente');
        $html .= self::renderField($translator, $model, 'codalmacen');
        $html .= self::renderField($translator, $model, 'codserie');
        $html .= self::renderField($translator, $model, 'fecha');
        $html .= self::renderNewFields($translator, $model);
        $html .= self::renderField($translator, $model, 'numero2');
        $html .= self::renderField($translator, $model, 'codpago');
        $html .= self::renderField($translator, $model, 'finoferta');
        $html .= self::renderField($translator, $model, 'total');
        $html .= '</div>';
        
        $html .= '<div class="form-row align-items-end">';
        $html .= self::renderField($translator, $model, '_detail');
        $html .= self::renderField($translator, $model, '_parents');
        $html .= self::renderField($translator, $model, '_children');
        $html .= self::renderField($translator, $model, '_email');
        $html .= self::renderNewBtnFields($translator, $model);
        $html .= self::renderField($translator, $model, '_paid');
        $html .= self::renderField($translator, $model, 'idestado');
        $html .= '</div>';
        
        $html .= '</div>';
        return $html;
    }

    private static function addressField(Translator $i18n, SalesDocument $model, string $field, string $label, int $size, int $maxlength): string
    {
        $isEditable = $model->editable && (empty($model->idcontactofact) || empty($model->direccion));
        $attributes = $isEditable ? 
            'name="' . $field . '" maxlength="' . $maxlength . '" autocomplete="off"' : 
            'disabled=""';

        return '<div class="col-sm-' . $size . '">'
            . '<div class="form-group">' . $i18n->trans($label)
            . '<input type="text" ' . $attributes . ' value="' . Tools::noHtml($model->{$field}) . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    private static function ciudad(Translator $i18n, SalesDocument $model, int $size, int $maxlength): string
    {
        $attributes = $model->editable && (empty($model->idcontactofact) || empty($model->direccion)) ?
            'name="ciudad" maxlength="' . $maxlength . '" autocomplete="off"' :
            'disabled=""';

        $datalist = '';
        if ($model->editable) {
            $datalist = '<datalist id="ciudades">';
            $cityModel = new Ciudad();
            $cities = $cityModel->all([], ['ciudad' => 'ASC'], 0, 0);
            foreach ($cities as $city) {
                $datalist .= '<option value="' . $city->ciudad . '">' . $city->ciudad . '</option>';
            }
            $datalist .= '</datalist>';
        }

        return '<div class="col-sm-' . $size . '">'
            . '<div class="form-group">' . $i18n->trans('city')
            . '<input type="text" ' . $attributes . ' value="' . Tools::noHtml($model->ciudad) . '" list="ciudades" class="form-control"/>'
            . $datalist
            . '</div>'
            . '</div>';
    }

    private static function codagente(Translator $i18n, SalesDocument $model): string
    {
        $agents = Agentes::all();
        if (count($agents) === 0) {
            return '';
        }

        $options = ['<option value="">------</option>'];
        foreach ($agents as $agent) {
            if ($agent->debaja && $agent->codagente != $model->codagente) {
                continue;
            }

            $selected = $agent->codagente === $model->codagente ? ' selected' : '';
            $options[] = '<option value="' . $agent->codagente . '"' . $selected . '>' . $agent->nombre . '</option>';
        }

        $attributes = $model->editable ? 'name="codagente"' : 'disabled';
        return empty($model->subjectColumnValue()) ? '' : '<div class="col-sm-6">'
            . '<div class="form-group">'
            . '<a href="' . Agentes::get($model->codagente)->url() . '">' . $i18n->trans('agent') . '</a>'
            . '<select ' . $attributes . ' class="form-control">' . implode('', $options) . '</select>'
            . '</div>'
            . '</div>';
    }

    private static function codcliente(Translator $i18n, SalesDocument $model): string
    {
        self::$cliente = new Cliente();
        if (empty($model->codcliente) || !self::$cliente->loadFromCode($model->codcliente)) {
            $html = '<div class="col-sm-3">'
                . '<div class="form-group">' . $i18n->trans('customer')
                . '<input type="hidden" name="codcliente"/>'
                . '<a href="#" id="btnFindCustomerModal" class="btn btn-block btn-primary" onclick="$(\'#findCustomerModal\').modal();'
                . ' $(\'#findCustomerInput\').focus(); return false;"><i class="fas fa-users fa-fw"></i> '
                . $i18n->trans('select') . '</a>'
                . '</div>'
                . '</div>'
                . self::detailModal($i18n, $model);
            return $html;
        }

        $customerButton = $model->editable ?
            '<button class="btn btn-outline-secondary" type="button" onclick="$(\'#findCustomerModal\').modal();'
            . ' $(\'#findCustomerInput\').focus(); return false;"><i class="fas fa-pen"></i></button>' :
            '<button class="btn btn-outline-secondary" type="button"><i class="fas fa-lock"></i></button>';

        $html = '<div class="col-sm-3 col-lg">'
            . '<div class="form-group">'
            . '<a href="' . self::$cliente->url() . '">' . $i18n->trans('customer') . '</a>'
            . '<input type="hidden" name="codcliente" value="' . $model->codcliente . '"/>'
            . '<div class="input-group">'
            . '<input type="text" value="' . Tools::noHtml(self::$cliente->nombre) . '" class="form-control" readonly/>'
            . '<div class="input-group-append">' . $customerButton . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';

        if (empty($model->primaryColumnValue())) {
            $html .= self::detail($i18n, $model, true);
        }

        return $html;
    }

    private static function codigoenv(Translator $i18n, SalesDocument $model): string
    {
        $attributes = $model->editable ? 'name="codigoenv" maxlength="200" autocomplete="off"' : 'disabled=""';
        return '<div class="col-sm-4">'
            . '<div class="form-group">' . $i18n->trans('tracking-code')
            . '<input type="text" ' . $attributes . ' value="' . Tools::noHtml($model->codigoenv) . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    private static function codpais(Translator $i18n, SalesDocument $model): string
    {
        $options = [];
        foreach (Paises::all() as $country) {
            $selected = $country->codpais === $model->codpais ? ' selected' : '';
            $options[] = '<option value="' . $country->codpais . '"' . $selected . '>' . $country->nombre . '</option>';
        }

        $countryModel = new Pais();
        $attributes = $model->editable && (empty($model->idcontactofact) || empty($model->direccion)) ?
            'name="codpais"' : 'disabled=""';
            
        return '<div class="col-sm-6">'
            . '<div class="form-group">'
            . '<a href="' . $countryModel->url() . '">' . $i18n->trans('country') . '</a>'
            . '<select ' . $attributes . ' class="form-control">' . implode('', $options) . '</select>'
            . '</div>'
            . '</div>';
    }

    private static function codtrans(Translator $i18n, SalesDocument $model): string
    {
        $options = ['<option value="">------</option>'];
        $shippingAgency = new AgenciaTransporte();
        $agencies = $shippingAgency->all();
        
        foreach ($agencies as $agency) {
            $selected = $agency->codtrans === $model->codtrans ? ' selected' : '';
            $options[] = '<option value="' . $agency->codtrans . '"' . $selected . '>' . $agency->nombre . '</option>';
        }

        $attributes = $model->editable ? 'name="codtrans"' : 'disabled=""';
        return '<div class="col-sm-4">'
            . '<div class="form-group">'
            . '<a href="' . $shippingAgency->url() . '">' . $i18n->trans('carrier') . '</a>'
            . '<select ' . $attributes . ' class="form-control">' . implode('', $options) . '</select>'
            . '</div>'
            . '</div>';
    }

    private static function detail(Translator $i18n, SalesDocument $model, bool $new = false): string
    {
        if (empty($model->primaryColumnValue()) && !$new) {
            return '';
        }

        $cssClass = $new ? 'col-sm-auto' : 'col-sm';
        return '<div class="' . $cssClass . '">'
            . '<div class="form-group">'
            . '<button class="btn btn-outline-secondary" type="button" data-toggle="modal" data-target="#headerModal">'
            . '<i class="fas fa-edit fa-fw" aria-hidden="true"></i> ' . $i18n->trans('detail') . ' </button>'
            . '</div>'
            . '</div>'
            . self::detailModal($i18n, $model);
    }

    private static function detailModal(Translator $i18n, SalesDocument $model): string
    {
        $modalFields = [
            'nombrecliente', 'cifnif', 'idcontactofact', 'direccion', 'apartado', 'codpostal',
            'ciudad', 'provincia', 'codpais', 'idcontactoenv', 'codtrans', 'codigoenv',
            'fechadevengo', 'hora', 'operacion', 'femail', 'coddivisa', 'tasaconv',
            'user', 'codagente'
        ];

        $fieldsHtml = '';
        foreach ($modalFields as $field) {
            $fieldsHtml .= self::renderField($i18n, $model, $field);
        }
        $fieldsHtml .= self::renderNewModalFields($i18n, $model);

        return '<div class="modal fade" id="headerModal" tabindex="-1" aria-labelledby="headerModalLabel" aria-hidden="true">'
            . '<div class="modal-dialog modal-dialog-centered modal-lg">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title"><i class="fas fa-edit fa-fw" aria-hidden="true"></i> ' . $i18n->trans('detail') . '</h5>'
            . '<button type="button" class="close" data-dismiss="modal" aria-label="Close">'
            . '<span aria-hidden="true">&times;</span>'
            . '</button>'
            . '</div>'
            . '<div class="modal-body">'
            . '<div class="form-row">'
            . $fieldsHtml
            . '</div>'
            . '</div>'
            . '<div class="modal-footer">'
            . '<button type="button" class="btn btn-secondary" data-dismiss="modal">' . $i18n->trans('close') . '</button>'
            . '<button type="button" class="btn btn-primary" data-dismiss="modal">' . $i18n->trans('accept') . '</button>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    private static function finoferta(Translator $i18n, SalesDocument $model): string
    {
        if (!property_exists($model, 'finoferta') || empty($model->primaryColumnValue())) {
            return '';
        }

        $isExpired = !empty($model->finoferta) && strtotime($model->finoferta) <= time();
        $label = $isExpired ? 
            '<span class="text-danger">' . $i18n->trans('expiration') . '</span>' :
            $i18n->trans('expiration');

        $attributes = $model->editable ? 'name="finoferta"' : 'disabled=""';
        $value = empty($model->finoferta) ? '' : 'value="' . date('Y-m-d', strtotime($model->finoferta)) . '"';
        
        return '<div class="col-sm">'
            . '<div class="form-group">' . $label
            . '<input type="date" ' . $attributes . ' ' . $value . ' class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    private static function getAddressOptions(Translator $i18n, $selected, bool $empty): array
    {
        $options = $empty ? ['<option value="">------</option>'] : [];
        $addresses = self::$cliente->getAddresses();
        
        foreach ($addresses as $contact) {
            $description = empty($contact->descripcion) ? 
                '(' . $i18n->trans('empty') . ') ' : 
                '(' . $contact->descripcion . ') ';
            $description .= empty($contact->direccion) ? '' : $contact->direccion;
            
            $isSelected = $contact->idcontacto == $selected ? ' selected' : '';
            $options[] = '<option value="' . $contact->idcontacto . '"' . $isSelected . '>' . $description . '</option>';
        }
        
        return $options;
    }

    private static function idcontactoenv(Translator $i18n, SalesDocument $model): string
    {
        if (empty($model->codcliente)) {
            return '';
        }

        $attributes = $model->editable ? 'name="idcontactoenv"' : 'disabled=""';
        $options = self::getAddressOptions($i18n, $model->idcontactoenv, true);
        
        return '<div class="col-sm-4">'
            . '<div class="form-group">'
            . '<a href="' . self::$cliente->url() . '&activetab=EditDireccionContacto" target="_blank">'
            . $i18n->trans('shipping-address') . '</a>'
            . '<select ' . $attributes . ' class="form-control">' . implode('', $options) . '</select>'
            . '</div>'
            . '</div>';
    }

    private static function idcontactofact(Translator $i18n, SalesDocument $model): string
    {
        if (empty($model->codcliente)) {
            return '';
        }

        $attributes = $model->editable ? 
            'name="idcontactofact" onchange="return salesFormActionWait(\'recalculate-line\', \'0\', event);"' : 
            'disabled=""';
        $options = self::getAddressOptions($i18n, $model->idcontactofact, true);
        
        return '<div class="col-sm-6">'
            . '<div class="form-group">'
            . '<a href="' . self::$cliente->url() . '&activetab=EditDireccionContacto" target="_blank">' . $i18n->trans('billing-address') . '</a>'
            . '<select ' . $attributes . ' class="form-control">' . implode('', $options) . '</select>'
            . '</div>'
            . '</div>';
    }

    private static function nombrecliente(Translator $i18n, SalesDocument $model): string
    {
        $attributes = $model->editable ? 
            'name="nombrecliente" required="" maxlength="100" autocomplete="off"' : 
            'disabled=""';
            
        return '<div class="col-sm-6">'
            . '<div class="form-group">'
            . $i18n->trans('business-name')
            . '<input type="text" ' . $attributes . ' value="' . Tools::noHtml($model->nombrecliente) . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    private static function numero2(Translator $i18n, SalesDocument $model): string
    {
        if (empty($model->codcliente)) {
            return '';
        }

        $attributes = $model->editable ? 
            'name="numero2" maxlength="50" placeholder="' . $i18n->trans('optional') . '"' : 
            'disabled=""';
            
        return '<div class="col-sm">'
            . '<div class="form-group">'
            . $i18n->trans('number2')
            . '<input type="text" ' . $attributes . ' value="' . Tools::noHtml($model->numero2) . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    private static function provincia(Translator $i18n, SalesDocument $model, int $size, int $maxlength): string
    {
        $attributes = $model->editable && (empty($model->idcontactofact) || empty($model->direccion)) ?
            'name="provincia" maxlength="' . $maxlength . '" autocomplete="off"' :
            'disabled=""';

        $datalist = '';
        if ($model->editable) {
            $datalist = '<datalist id="provincias">';
            $provinceModel = new Provincia();
            $provinces = $provinceModel->all([], ['provincia' => 'ASC'], 0, 0);
            foreach ($provinces as $province) {
                $datalist .= '<option value="' . $province->provincia . '">' . $province->provincia . '</option>';
            }
            $datalist .= '</datalist>';
        }

        return '<div class="col-sm-' . $size . '">'
            . '<div class="form-group">' . $i18n->trans('province')
            . '<input type="text" ' . $attributes . ' value="' . Tools::noHtml($model->provincia) . '" list="provincias" class="form-control"/>'
            . $datalist
            . '</div>'
            . '</div>';
    }

    private static function renderField(Translator $i18n, SalesDocument $model, string $field): ?string
    {
        foreach (self::$mods as $modifier) {
            $html = $modifier->renderField($i18n, $model, $field);
            if ($html !== null) {
                return $html;
            }
        }

        return self::renderCoreField($i18n, $model, $field);
    }

    private static function renderCoreField(Translator $i18n, SalesDocument $model, string $field): ?string
    {
        switch ($field) {
            case '_children':
                return self::children($i18n, $model);
            case '_detail':
                return self::detail($i18n, $model);
            case '_email':
                return self::email($i18n, $model);
            case '_fecha':
                return self::fecha($i18n, $model, false);
            case '_paid':
                return self::paid($i18n, $model, 'salesFormSave');
            case '_parents':
                return self::parents($i18n, $model);
            case 'apartado':
                return self::addressField($i18n, $model, 'apartado', 'post-office-box', 4, 10);
            case 'cifnif':
                return self::cifnif($i18n, $model);
            case 'ciudad':
                return self::ciudad($i18n, $model, 4, 100);
            case 'codagente':
                return self::codagente($i18n, $model);
            case 'codalmacen':
                return self::codalmacen($i18n, $model, 'salesFormAction');
            case 'codcliente':
                return self::codcliente($i18n, $model);
            case 'coddivisa':
                return self::coddivisa($i18n, $model);
            case 'codigoenv':
                return self::codigoenv($i18n, $model);
            case 'codpago':
                return self::codpago($i18n, $model);
            case 'codpais':
                return self::codpais($i18n, $model);
            case 'codpostal':
                return self::addressField($i18n, $model, 'codpostal', 'zip-code', 4, 10);
            case 'codserie':
                return self::codserie($i18n, $model, 'salesFormAction');
            case 'codtrans':
                return self::codtrans($i18n, $model);
            case 'direccion':
                return self::addressField($i18n, $model, 'direccion', 'address', 6, 200);
            case 'fecha':
                return self::fecha($i18n, $model);
            case 'fechadevengo':
                return self::fechadevengo($i18n, $model);
            case 'femail':
                return self::femail($i18n, $model);
            case 'finoferta':
                return self::finoferta($i18n, $model);
            case 'hora':
                return self::hora($i18n, $model);
            case 'idcontactofact':
                return self::idcontactofact($i18n, $model);
            case 'idcontactoenv':
                return self::idcontactoenv($i18n, $model);
            case 'idestado':
                return self::idestado($i18n, $model, 'salesFormSave');
            case 'nombrecliente':
                return self::nombrecliente($i18n, $model);
            case 'numero2':
                return self::numero2($i18n, $model);
            case 'operacion':
                return self::operacion($i18n, $model);
            case 'provincia':
                return self::provincia($i18n, $model, 6, 100);
            case 'tasaconv':
                return self::tasaconv($i18n, $model);
            case 'total':
                return self::total($i18n, $model, 'salesFormSave');
            case 'user':
                return self::user($i18n, $model);
        }

        return null;
    }

    private static function renderNewBtnFields(Translator $i18n, SalesDocument $model): string
    {
        $buttonFields = [];
        foreach (self::$mods as $modifier) {
            $newFields = $modifier->newBtnFields();
            foreach ($newFields as $field) {
                if (!in_array($field, $buttonFields)) {
                    $buttonFields[] = $field;
                }
            }
        }

        $html = '';
        foreach ($buttonFields as $field) {
            foreach (self::$mods as $modifier) {
                $fieldHtml = $modifier->renderField($i18n, $model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }

    private static function renderNewFields(Translator $i18n, SalesDocument $model): string
    {
        $customFields = [];
        foreach (self::$mods as $modifier) {
            $newFields = $modifier->newFields();
            foreach ($newFields as $field) {
                if (!in_array($field, $customFields)) {
                    $customFields[] = $field;
                }
            }
        }

        $html = '';
        foreach ($customFields as $field) {
            foreach (self::$mods as $modifier) {
                $fieldHtml = $modifier->renderField($i18n, $model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }

    private static function renderNewModalFields(Translator $i18n, SalesDocument $model): string
    {
        $modalFields = [];
        foreach (self::$mods as $modifier) {
            $newFields = $modifier->newModalFields();
            foreach ($newFields as $field) {
                if (!in_array($field, $modalFields)) {
                    $modalFields[] = $field;
                }
            }
        }

        $html = '';
        foreach ($modalFields as $field) {
            foreach (self::$mods as $modifier) {
                $fieldHtml = $modifier->renderField($i18n, $model, $field);
                if ($fieldHtml !== null) {
                    $html .= $fieldHtml;
                    break;
                }
            }
        }
        return $html;
    }
}