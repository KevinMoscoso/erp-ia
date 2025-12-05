<?php
/**
 * ERPIA - Trait para Formularios Comunes de Ventas y Compras
 * Este archivo es parte de ERPIA, un sistema ERP de código abierto.
 * 
 * Copyright (C) 2025 ERPIA
 *
 * Este programa es software libre: puede redistribuirlo y/o modificarlo
 * bajo los términos de la Licencia Pública General GNU como publicada por
 * la Free Software Foundation, ya sea la versión 3 de la Licencia, o
 * (a su elección) cualquier versión posterior.
 *
 * Este programa se distribuye con la esperanza de que sea útil,
 * pero SIN NINGUNA GARANTÍA; sin siquiera la garantía implícita de
 * COMERCIALIZACIÓN o IDONEIDAD PARA UN PROPÓSITO PARTICULAR. Consulte la
 * Licencia Pública General GNU para obtener más detalles.
 *
 * Debería haber recibido una copia de la Licencia Pública General GNU
 * junto con este programa. Si no es así, consulte <http://www.gnu.org/licenses/>.
 */

namespace ERPIA\Core\Lib\AjaxForms;

use ERPIA\Core\DataSrc\Almacenes;
use ERPIA\Core\DataSrc\Divisas;
use ERPIA\Core\DataSrc\Empresas;
use ERPIA\Core\DataSrc\FormasPago;
use ERPIA\Core\DataSrc\Series;
use ERPIA\Core\Lib\InvoiceOperation;
use ERPIA\Core\Model\Base\BusinessDocument;
use ERPIA\Core\Model\Base\TransformerDocument;
use ERPIA\Core\Session;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Model\EstadoDocumento;

/**
 * Trait con métodos comunes para formularios de ventas y compras.
 * 
 * @author ERPIA
 * @version 1.0
 */
trait CommonSalesPurchases
{
    /** @var string */
    protected static $columnView;

    /**
     * Verifica el nivel de acceso del usuario.
     *
     * @param int $level
     * @return bool
     */
    public static function checkLevel(int $level): bool
    {
        $user = Session::user();

        if (!$user->exists()) {
            return false;
        }

        if ($user->admin) {
            return true;
        }

        return $level <= $user->level;
    }

    /**
     * Genera el campo CIF/NIF.
     *
     * @param BusinessDocument $model
     * @return string
     */
    protected static function cifnif(BusinessDocument $model): string
    {
        $attributes = $model->editable ? 'name="cifnif" maxlength="30" autocomplete="off"' : 'disabled';
        return '<div class="col-sm-6">'
            . '<div class="mb-2">' . Tools::trans('cifnif')
            . '<input type="text" ' . $attributes . ' value="' . Tools::noHtml($model->cifnif) . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el botón y modal para documentos hijos.
     *
     * @param TransformerDocument $model
     * @return string
     */
    protected static function children(TransformerDocument $model): string
    {
        if (empty($model->id())) {
            return '';
        }

        $childrenDocs = $model->childrenDocuments();
        $count = count($childrenDocs);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return '<div class="col-sm-auto">'
                . '<div class="mb-2">'
                . '<a href="' . $childrenDocs[0]->url() . '" class="btn w-100 btn-info">'
                . '<i class="fa-solid fa-forward fa-fw" aria-hidden="true"></i> ' . $childrenDocs[0]->primaryDescription()
                . '</a>'
                . '</div>'
                . '</div>';
        }

        return '<div class="col-sm-auto">'
            . '<div class="mb-2">'
            . '<button class="btn w-100 btn-info" type="button" title="' . Tools::trans('documents-generated')
            . '" data-bs-toggle="modal" data-bs-target="#childrenModal"><i class="fa-solid fa-forward fa-fw" aria-hidden="true"></i> '
            . $count . ' </button>'
            . '</div>'
            . '</div>'
            . self::documentListModal($childrenDocs, 'documents-generated', 'childrenModal');
    }

    /**
     * Genera el selector de almacén.
     *
     * @param BusinessDocument $model
     * @param string $jsFunction
     * @return string
     */
    protected static function codalmacen(BusinessDocument $model, string $jsFunction): string
    {
        $warehousesCount = count(Almacenes::all());
        $subjectValue = $model->subjectColumnValue();

        if (empty($subjectValue) || $warehousesCount <= 1) {
            return '';
        }

        $options = [];
        foreach (Empresas::all() as $company) {
            if ($company->idempresa != $model->idempresa && $model->exists()) {
                continue;
            }

            $companyOptions = '';
            foreach ($company->getWarehouses() as $warehouse) {
                if ($warehouse->codalmacen != $model->codalmacen && !$warehouse->activo) {
                    continue;
                }

                $selected = $warehouse->codalmacen === $model->codalmacen ? ' selected' : '';
                $companyOptions .= '<option value="' . $warehouse->codalmacen . '"' . $selected . '>' . $warehouse->nombre . '</option>';
            }

            if (!empty($companyOptions)) {
                $options[] = '<optgroup label="' . $company->nombrecorto . '">' . $companyOptions . '</optgroup>';
            }
        }

        $attributes = $model->editable
            ? 'name="codalmacen" onchange="return ' . $jsFunction . '(\'recalculate\', \'0\');" required'
            : 'disabled';

        return '<div class="col-sm-6 col-md-4 col-lg">'
            . '<div class="mb-2">'
            . '<a href="' . Almacenes::get($model->codalmacen)->url() . '">' . Tools::trans('company-warehouse') . '</a>'
            . '<select ' . $attributes . ' class="form-select">' . implode('', $options) . '</select>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el selector de divisa.
     *
     * @param BusinessDocument $model
     * @return string
     */
    protected static function coddivisa(BusinessDocument $model): string
    {
        $subjectValue = $model->subjectColumnValue();
        if (empty($subjectValue)) {
            return '';
        }

        $options = [];
        foreach (Divisas::all() as $currency) {
            $selected = $currency->coddivisa === $model->coddivisa ? ' selected' : '';
            $options[] = '<option value="' . $currency->coddivisa . '"' . $selected . '>' . $currency->descripcion . '</option>';
        }

        $attributes = $model->editable ? 'name="coddivisa" required' : 'disabled';
        return '<div class="col-sm-6">'
            . '<div class="mb-2">'
            . '<a href="' . Divisas::get($model->coddivisa)->url() . '">' . Tools::trans('currency') . '</a>'
            . '<select ' . $attributes . ' class="form-select">' . implode('', $options) . '</select>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el selector de forma de pago.
     *
     * @param BusinessDocument $model
     * @return string
     */
    protected static function codpago(BusinessDocument $model): string
    {
        $subjectValue = $model->subjectColumnValue();
        if (empty($subjectValue)) {
            return '';
        }

        $options = [];
        foreach (self::getPaymentMethods($model) as $paymentMethod) {
            $selected = $paymentMethod->codpago === $model->codpago ? ' selected' : '';
            $options[] = '<option value="' . $paymentMethod->codpago . '"' . $selected . '>' . $paymentMethod->descripcion . '</option>';
        }

        $attributes = $model->editable ? 'name="codpago" required' : 'disabled';
        return '<div class="col-sm-6 col-md-4 col-lg">'
            . '<div id="payment-methods" class="mb-2">'
            . '<a href="' . FormasPago::get($model->codpago)->url() . '">' . Tools::trans('payment-method') . '</a>'
            . '<select ' . $attributes . ' class="form-select">' . implode('', $options) . '</select>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el selector de serie.
     *
     * @param BusinessDocument $model
     * @param string $jsFunction
     * @return string
     */
    protected static function codserie(BusinessDocument $model, string $jsFunction): string
    {
        $subjectValue = $model->subjectColumnValue();
        if (empty($subjectValue)) {
            return '';
        }

        $isRectificative = $model->hasColumn('idfacturarect') && $model->idfacturarect;
        $options = [];

        foreach (Series::all() as $serie) {
            if ($serie->codserie === $model->codserie) {
                $options[] = '<option value="' . $serie->codserie . '" selected>' . $serie->descripcion . '</option>';
                continue;
            }

            if ($isRectificative && $serie->tipo === 'R') {
                $options[] = '<option value="' . $serie->codserie . '">' . $serie->descripcion . '</option>';
                continue;
            }

            if (!$isRectificative && $serie->tipo !== 'R') {
                $options[] = '<option value="' . $serie->codserie . '">' . $serie->descripcion . '</option>';
            }
        }

        $attributes = $model->editable
            ? 'name="codserie" onchange="return ' . $jsFunction . '(\'recalculate\', \'0\');" required'
            : 'disabled';

        return '<div class="col-sm-6 col-md-4 col-lg">'
            . '<div class="mb-2">'
            . '<a href="' . Series::get($model->codserie)->url() . '">' . Tools::trans('serie') . '</a>'
            . '<select ' . $attributes . ' class="form-select">' . implode('', $options) . '</select>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera una columna de total (para neto, total, etc.).
     *
     * @param BusinessDocument $model
     * @param string $columnName
     * @param string $label
     * @param bool $autoHide
     * @param int $level
     * @return string
     */
    protected static function column(BusinessDocument $model, string $columnName, string $label, bool $autoHide = false, int $level = 0): string
    {
        if (!self::checkLevel($level)) {
            return '';
        }

        $decimals = Tools::settings('default', 'decimals', 2);
        $separator = Tools::settings('default', 'decimal_separator', ',');
        $value = $model->{$columnName};

        if (empty($value) && $autoHide) {
            return '';
        }

        $formatted = number_format($value, $decimals, $separator, '');
        return '<div class="col-sm-6 col-md-4 col-lg"><div class="mb-2">' . Tools::trans($label)
            . '<input type="text" value="' . $formatted
            . '" class="form-control" disabled/></div></div>';
    }

    /**
     * Genera el botón de eliminar documento.
     *
     * @param BusinessDocument $model
     * @param string $jsFunction
     * @return string
     */
    protected static function deleteBtn(BusinessDocument $model, string $jsFunction): string
    {
        if (!$model->id() || !$model->editable) {
            return '';
        }

        $modalHtml = '<div class="modal fade" id="deleteDocModal" tabindex="-1" aria-hidden="true">'
            . '<div class="modal-dialog">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title"></h5>'
            . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
            . '</div>'
            . '<div class="modal-body text-center">'
            . '<i class="fa-solid fa-trash-alt fa-3x"></i>'
            . '<h5 class="mt-3 mb-1">' . Tools::trans('confirm-delete') . '</h5>'
            . '<p class="mb-0">' . Tools::trans('are-you-sure') . '</p>'
            . '</div>'
            . '<div class="modal-footer">'
            . '<button type="button" class="btn btn-spin-action btn-secondary" data-bs-dismiss="modal">' . Tools::trans('cancel') . '</button>'
            . '<button type="button" class="btn btn-spin-action btn-danger" onclick="return ' . $jsFunction . '(\'delete-doc\', \'0\');">'
            . Tools::trans('delete') . '</button>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';

        return '<button type="button" class="btn btn-spin-action btn-danger mb-2" data-bs-toggle="modal" data-bs-target="#deleteDocModal">'
            . '<i class="fa-solid fa-trash-alt fa-fw"></i> ' . Tools::trans('delete')
            . '</button>'
            . $modalHtml;
    }

    /**
     * Genera el campo de descuento global 1.
     *
     * @param BusinessDocument $model
     * @param string $jsFunction
     * @return string
     */
    protected static function dtopor1(BusinessDocument $model, string $jsFunction): string
    {
        if (empty($model->netosindto) && empty($model->dtopor1)) {
            return '<input type="hidden" name="dtopor1" value="0"/>';
        }

        $attributes = $model->editable
            ? 'max="100" min="0" name="dtopor1" required step="any" onkeyup="return ' . $jsFunction . '(\'recalculate\', \'0\', event);"'
            : 'disabled';

        return '<div class="col-sm"><div class="mb-2">' . Tools::trans('global-dto')
            . '<div class="input-group">'
            . '<span class="input-group-text"><i class="fa-solid fa-percentage"></i></span>'
            . '<input type="number" ' . $attributes . ' value="' . floatval($model->dtopor1) . '" class="form-control"/>'
            . '</div></div></div>';
    }

    /**
     * Genera el campo de descuento global 2.
     *
     * @param BusinessDocument $model
     * @param string $jsFunction
     * @return string
     */
    protected static function dtopor2(BusinessDocument $model, string $jsFunction): string
    {
        if (empty($model->dtopor1) && empty($model->dtopor2)) {
            return '<input type="hidden" name="dtopor2" value="0"/>';
        }

        $attributes = $model->editable
            ? 'max="100" min="0" name="dtopor2" required step="any" onkeyup="return ' . $jsFunction . '(\'recalculate\', \'0\', event);"'
            : 'disabled';

        return '<div class="col-sm-2 col-md"><div class="mb-2">' . Tools::trans('global-dto-2')
            . '<div class="input-group">'
            . '<span class="input-group-text"><i class="fa-solid fa-percentage"></i></span>'
            . '<input type="number" ' . $attributes . ' value="' . floatval($model->dtopor2) . '" class="form-control"/>'
            . '</div></div></div>';
    }

    /**
     * Genera el botón de email enviado.
     *
     * @param BusinessDocument $model
     * @return string
     */
    private static function email(BusinessDocument $model): string
    {
        if (empty($model->femail)) {
            return '';
        }

        return '<div class="col-sm-auto">'
            . '<div class="mb-2">'
            . '<button class="btn btn-outline-info" type="button" title="' . Tools::trans('email-sent')
            . '" data-bs-toggle="modal" data-bs-target="#headerModal"><i class="fa-solid fa-envelope fa-fw" aria-hidden="true"></i> '
            . $model->femail . ' </button></div></div>';
    }

    /**
     * Genera el campo de entrada rápida de líneas.
     *
     * @param BusinessDocument $model
     * @param string $jsFunction
     * @return string
     */
    protected static function fastLineInput(BusinessDocument $model, string $jsFunction): string
    {
        if (!$model->editable) {
            return '<div class="col"></div>';
        }

        return '<div class="col-8 col-md">'
            . '<div class="input-group mb-2">'
            . '<span class="input-group-text"><i class="fa-solid fa-barcode"></i></span>'
            . '<input type="text" name="fastli" class="form-control" placeholder="' . Tools::trans('barcode')
            . '" onkeyup="' . $jsFunction . '(event)"/>'
            . '</div></div>';
    }

    /**
     * Genera el campo de fecha.
     *
     * @param BusinessDocument $model
     * @param bool $enabled
     * @return string
     */
    protected static function fecha(BusinessDocument $model, bool $enabled = true): string
    {
        $subjectValue = $model->subjectColumnValue();
        if (empty($subjectValue)) {
            return '';
        }

        $attributes = $model->editable && $enabled ? 'name="fecha" required' : 'disabled';
        $dateValue = date('Y-m-d', strtotime($model->fecha));

        return '<div class="col-sm-6 col-md-4 col-lg">'
            . '<div id="document-date" class="mb-2">' . Tools::trans('date')
            . '<input type="date" ' . $attributes . ' value="' . $dateValue . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el campo de fecha de devengo.
     *
     * @param BusinessDocument $model
     * @return string
     */
    protected static function fechadevengo(BusinessDocument $model): string
    {
        $subjectValue = $model->subjectColumnValue();
        if (empty($subjectValue) || !$model->hasColumn('fechadevengo')) {
            return '';
        }

        $attributes = $model->editable ? 'name="fechadevengo" required' : 'disabled';
        $dateValue = empty($model->fechadevengo) ? '' : date('Y-m-d', strtotime($model->fechadevengo));

        return '<div class="col-sm">'
            . '<div class="mb-2">' . Tools::trans('accrual-date')
            . '<input type="date" ' . $attributes . ' value="' . $dateValue . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el campo de fecha de email enviado.
     *
     * @param BusinessDocument $model
     * @return string
     */
    protected static function femail(BusinessDocument $model): string
    {
        if (empty($model->id())) {
            return '';
        }

        $attributes = empty($model->femail) && $model->editable ? 'name="femail"' : 'disabled';
        $dateValue = empty($model->femail) ? '' : date('Y-m-d', strtotime($model->femail));

        return '<div class="col-sm-6">'
            . '<div class="mb-2">' . Tools::trans('email-sent')
            . '<input type="date" ' . $attributes . ' value="' . $dateValue . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    /**
     * Obtiene las formas de pago disponibles para el documento.
     *
     * @param BusinessDocument $model
     * @return array
     */
    protected static function getPaymentMethods(BusinessDocument $model): array
    {
        $methods = [];
        foreach (FormasPago::all() as $payment) {
            if ($payment->idempresa != $model->idempresa) {
                continue;
            }

            if ($payment->codpago != $model->codpago && !$payment->activa) {
                continue;
            }

            $methods[] = $payment;
        }

        return $methods;
    }

    /**
     * Genera el campo de hora.
     *
     * @param BusinessDocument $model
     * @return string
     */
    protected static function hora(BusinessDocument $model): string
    {
        $subjectValue = $model->subjectColumnValue();
        if (empty($subjectValue)) {
            return '';
        }

        $attributes = $model->editable ? 'name="hora" required' : 'disabled';
        $timeValue = date('H:i:s', strtotime($model->hora));

        return '<div class="col-sm-6">'
            . '<div class="mb-2">' . Tools::trans('hour')
            . '<input type="time" ' . $attributes . ' value="' . $timeValue . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el selector de estado del documento.
     *
     * @param TransformerDocument $model
     * @param string $jsFunction
     * @return string
     */
    protected static function idestado(TransformerDocument $model, string $jsFunction): string
    {
        if (empty($model->id())) {
            return '';
        }

        $status = $model->getStatus();
        $btnClass = 'btn w-100 btn-secondary btn-spin-action';

        if (!$status->editable && empty($status->generadoc) && empty($status->actualizastock)) {
            $btnClass = 'btn w-100 btn-danger btn-spin-action';
        }

        if ($status->generadoc) {
            return '<div class="col-sm-auto">'
                . '<div class="mb-2">'
                . '<button type="button" class="' . $btnClass . '">'
                . '<i class="' . self::statusIcon($status) . ' fa-fw"></i> ' . $status->nombre
                . '</button>'
                . '</div>'
                . '</div>';
        }

        $options = [];
        foreach ($model->getAvailableStatus() as $availableStatus) {
            if ($availableStatus->idestado === $model->idestado || !$availableStatus->activo) {
                continue;
            }

            $options[] = '<a class="dropdown-item' . self::statusTextColor($availableStatus) . '"'
                . ' href="#" onclick="return ' . $jsFunction . '(\'save-status\', \'' . $availableStatus->idestado . '\', this);">'
                . '<i class="' . self::statusIcon($availableStatus, true) . ' fa-fw"></i> ' . $availableStatus->nombre . '</a>';
        }

        if ($model->editable && !in_array($model->modelClassName(), ['FacturaCliente', 'FacturaProveedor'])) {
            $options[] = '<div class="dropdown-divider"></div>'
                . '<a class="dropdown-item" href="DocumentStitcher?model=' . $model->modelClassName() . '&codes=' . $model->id() . '">'
                . '<i class="fa-solid fa-magic fa-fw" aria-hidden="true"></i> ' . Tools::trans('group-or-split')
                . '</a>';
        }

        return '<div class="col-sm-auto">'
            . '<div class="mb-2 statusButton">'
            . '<div class="dropdown">'
            . '<button class="' . $btnClass . ' dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'
            . '<i class="' . self::statusIcon($status) . ' fa-fw"></i> ' . $status->nombre
            . '</button>'
            . '<div class="dropdown-menu dropdown-menu-right">' . implode('', $options) . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Devuelve el icono para un estado.
     *
     * @param EstadoDocumento $status
     * @param bool $alternative
     * @return string
     */
    protected static function statusIcon(EstadoDocumento $status, bool $alternative = false): string
    {
        if ($status->icon) {
            return $status->icon;
        }

        if ($status->generadoc && $alternative) {
            return 'fa-solid fa-forward';
        }

        return $status->editable ? 'fa-solid fa-pen' : 'fa-solid fa-lock';
    }

    /**
     * Devuelve el color del texto para un estado.
     *
     * @param EstadoDocumento $status
     * @return string
     */
    protected static function statusTextColor(EstadoDocumento $status): string
    {
        if ($status->generadoc) {
            return ' text-success';
        }

        return !$status->editable && empty($status->actualizastock) ? ' text-danger' : '';
    }

    /**
     * Genera un modal con lista de documentos.
     *
     * @param array $documents
     * @param string $title
     * @param string $modalId
     * @return string
     */
    public static function documentListModal(array $documents, string $title, string $modalId): string
    {
        $rows = '';
        $totalSum = 0;

        foreach ($documents as $doc) {
            $rows .= '<tr>'
                . '<td><a href="' . $doc->url() . '">' . Tools::trans($doc->modelClassName()) . ' ' . $doc->codigo . '</a></td>'
                . '<td>' . $doc->observaciones . '</td>'
                . '<td class="text-end text-nowrap">' . Tools::money($doc->total) . '</td>'
                . '<td class="text-end text-nowrap">' . $doc->fecha . ' ' . $doc->hora . '</td>'
                . '</tr>';
            $totalSum += $doc->total;
        }

        $rows .= '<tr class="table-warning">'
            . '<td class="text-end text-nowrap" colspan="3">'
            . Tools::trans('total') . ' <b>' . Tools::money($totalSum) . '</b></td>'
            . '<td></td>'
            . '</tr>';

        return '<div class="modal fade" tabindex="-1" id="' . $modalId . '">'
            . '<div class="modal-dialog modal-xl">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title"><i class="fa-solid fa-copy fa-fw" aria-hidden="true"></i> ' . Tools::trans($title) . '</h5>'
            . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . Tools::trans('close') . '"></button>'
            . '</div>'
            . '<div class="table-responsive">'
            . '<table class="table table-hover mb-0">'
            . '<thead>'
            . '<tr>'
            . '<th>' . Tools::trans('document') . '</th>'
            . '<th>' . Tools::trans('observations') . '</th>'
            . '<th class="text-end">' . Tools::trans('total') . '</th>'
            . '<th class="text-end">' . Tools::trans('date') . '</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el campo de neto sin descuento.
     *
     * @param BusinessDocument $model
     * @return string
     */
    protected static function netosindto(BusinessDocument $model): string
    {
        if (empty($model->dtopor1) && empty($model->dtopor2)) {
            return '';
        }

        $decimals = Tools::settings('default', 'decimals', 2);
        $separator = Tools::settings('default', 'decimal_separator', ',');
        $formatted = number_format($model->netosindto, $decimals, $separator, '');

        return '<div class="col-sm-6 col-md-4 col-lg"><div class="mb-2">' . Tools::trans('subtotal')
            . '<input type="text" value="' . $formatted
            . '" class="form-control" disabled/></div></div>';
    }

    /**
     * Genera el botón de nueva línea.
     *
     * @param BusinessDocument $model
     * @param string $jsFunction
     * @return string
     */
    protected static function newLineBtn(BusinessDocument $model, string $jsFunction): string
    {
        if (!$model->editable) {
            return '';
        }

        return '<div class="col-3 col-md-auto">'
            . '<a href="#" class="btn btn-success w-100 btn-spin-action mb-2" onclick="return ' . $jsFunction . '(\'new-line\', \'0\');">'
            . '<i class="fa-solid fa-plus fa-fw"></i> ' . Tools::trans('line') . '</a></div>';
    }

    /**
     * Genera el campo de observaciones.
     *
     * @param BusinessDocument $model
     * @return string
     */
    protected static function observaciones(BusinessDocument $model): string
    {
        $attributes = $model->editable ? 'name="observaciones"' : 'disabled';
        $observations = $model->observaciones ?? '';
        $lines = explode("\n", $observations);
        $rows = 1;

        foreach ($lines as $line) {
            $rows += mb_strlen($line) < 140 ? 1 : ceil(mb_strlen($line) / 140);
        }

        return '<div class="col-sm-12"><div class="mb-2">' . Tools::trans('observations')
            . '<textarea ' . $attributes . ' class="form-control" placeholder="' . Tools::trans('observations')
            . '" rows="' . $rows . '">' . Tools::noHtml($observations) . '</textarea>'
            . '</div></div>';
    }

    /**
     * Genera el selector de operación.
     *
     * @param BusinessDocument $model
     * @return string
     */
    protected static function operacion(BusinessDocument $model): string
    {
        $options = ['<option value="">------</option>'];
        foreach (InvoiceOperation::all() as $key => $value) {
            $selected = $key === $model->operacion ? ' selected' : '';
            $options[] = '<option value="' . $key . '"' . $selected . '>' . Tools::trans($value) . '</option>';
        }

        $attributes = $model->editable ? ' name="operacion"' : ' disabled';
        return '<div class="col-sm-6">'
            . '<div class="mb-2">' . Tools::trans('operation')
            . '<select' . $attributes . ' class="form-select">' . implode('', $options) . '</select>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el botón de pago/impago.
     *
     * @param BusinessDocument $model
     * @return string
     */
    protected static function paid(BusinessDocument $model): string
    {
        if (empty($model->id()) || !method_exists($model, 'getReceipts')) {
            return '';
        }

        if ($model->paid()) {
            return '<div class="col-sm-auto">'
                . '<div class="mb-2">'
                . '<button class="btn btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">'
                . '<i class="fa-solid fa-check-square fa-fw"></i> ' . Tools::trans('paid') . '</button>'
                . '<div class="dropdown-menu dropdown-menu-end">'
                . '<a class="dropdown-item text-danger" href="#" onclick="prepareForm(\'save-paid\', {\'paid-status\': 0});">'
                . '<i class="fa-solid fa-times fa-fw"></i> ' . Tools::trans('unpaid') . '</a></div>'
                . '</div>'
                . '</div>';
        }

        $html = '<div class="col-sm-auto">'
            . '<div class="mb-2">'
            . '<button class="btn btn-spin-action btn-outline-danger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">'
            . '<i class="fa-solid fa-times fa-fw"></i> ' . Tools::trans('unpaid') . '</button>'
            . '<div class="dropdown-menu dropdown-menu-end">'
            . '<button type="button" class="dropdown-item text-success" data-bs-toggle="modal" data-bs-target="#modalPaymentConditions">'
            . '<i class="fa-solid fa-check-square fa-fw"></i> ' . Tools::trans('paid') . '</button>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '<div class="modal fade" id="modalPaymentConditions" tabindex="-1" aria-hidden="true">'
            . '<div class="modal-dialog modal-dialog-centered">'
            . '<div class="modal-content">'
            . '<div class="modal-body">'
            . '<div class="row g-2">'
            . '<div class="col-12 mb-2">'
            . '<a href="' . FormasPago::get($model->codpago)->url() . '">' . Tools::trans('payment-method') . '</a>'
            . '<select id="paid-payment-modal" class="form-select" required>';

        foreach (self::getPaymentMethods($model) as $paymentMethod) {
            $selected = $paymentMethod->codpago === $model->codpago ? ' selected' : '';
            $html .= '<option value="' . $paymentMethod->codpago . '"' . $selected . '>' . $paymentMethod->descripcion . '</option>';
        }

        $html .= '</select>'
            . '</div>'
            . '<div class="col-12 mb-2">'
            . Tools::trans('date')
            . '<input type="date" id="paid-date-modal" value="' . date('Y-m-d') . '" class="form-control" required/>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '<div class="modal-footer">'
            . '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">'
            . Tools::trans('close')
            . '</button>'
            . '<button type="button" class="btn btn-primary btn-spin-action" onclick="(function(){
                let payment = document.getElementById(\'paid-payment-modal\').value;
                let date = document.getElementById(\'paid-date-modal\').value;
                prepareForm(\'save-paid\', {\'paid-payment-modal\': payment, \'paid-date-modal\': date, \'paid-status\': 1});
            })()">'
            . Tools::trans('paid')
            . '</button>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';

        return $html;
    }

    /**
     * Genera el botón y modal para documentos padres.
     *
     * @param TransformerDocument $model
     * @return string
     */
    protected static function parents(TransformerDocument $model): string
    {
        if (empty($model->id())) {
            return '';
        }

        $parentDocs = $model->parentDocuments();
        $count = count($parentDocs);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return '<div class="col-sm-auto">'
                . '<div class="mb-2">'
                . '<a href="' . $parentDocs[0]->url() . '" class="btn w-100 btn-warning">'
                . '<i class="fa-solid fa-backward fa-fw" aria-hidden="true"></i> ' . $parentDocs[0]->primaryDescription()
                . '</a>'
                . '</div>'
                . '</div>';
        }

        return '<div class="col-sm-auto">'
            . '<div class="mb-2">'
            . '<button class="btn w-100 btn-warning" type="button" title="' . Tools::trans('previous-documents')
            . '" data-bs-toggle="modal" data-bs-target="#parentsModal"><i class="fa-solid fa-backward fa-fw" aria-hidden="true"></i> '
            . $count . ' </button>'
            . '</div>'
            . '</div>'
            . self::documentListModal($parentDocs, 'previous-documents', 'parentsModal');
    }

    /**
     * Genera el botón de búsqueda de productos.
     *
     * @param BusinessDocument $model
     * @return string
     */
    protected static function productBtn(BusinessDocument $model): string
    {
        if (!$model->editable) {
            return '';
        }

        return '<div class="col-9 col-md col-lg-2">'
            . '<div class="input-group mb-2">'
            . '<input type="text" id="findProductInput" class="form-control" placeholder="' . Tools::trans('reference') . '"/>'
            . '<button class="btn btn-info" type="button" onclick="$(\'#findProductModal\').modal(\'show\');'
            . ' $(\'#productModalInput\').select();"><i class="fa-solid fa-book fa-fw"></i></button>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el botón de guardar.
     *
     * @param BusinessDocument $model
     * @param string $jsFunction
     * @return string
     */
    protected static function saveBtn(BusinessDocument $model, string $jsFunction): string
    {
        if (!$model->subjectColumnValue() || !$model->editable) {
            return '';
        }

        return '<button type="button" class="btn btn-primary btn-spin-action"'
            . ' load-after="true" onclick="return ' . $jsFunction . '(\'save-doc\', \'0\');">'
            . '<i class="fa-solid fa-save fa-fw"></i> ' . Tools::trans('save')
            . '</button>';
    }

    /**
     * Genera el botón para ordenar líneas.
     *
     * @param BusinessDocument $model
     * @return string
     */
    protected static function sortableBtn(BusinessDocument $model): string
    {
        if (!$model->editable) {
            return '';
        }

        return '<div class="col-4 col-md-auto">'
            . '<button type="button" class="btn w-100 btn-light mb-2" id="sortableBtn">'
            . '<i class="fa-solid fa-arrows-alt-v fa-fw"></i> ' . Tools::trans('move-lines')
            . '</button>'
            . '</div>';
    }

    /**
     * Genera el botón para cambiar entre vista de neto y subtotal.
     *
     * @return string
     */
    protected static function subtotalNetoBtn(): string
    {
        $isSubtotal = self::$columnView === 'subtotal';

        $html = '<div class="col-12 col-md-auto mb-2">'
            . '<div id="columnView" class="btn-group w-100" role="group">';

        if ($isSubtotal) {
            $html .= '<button type="button" class="btn btn-light" data-column="neto" onclick="changeColumn(this)">'
                . Tools::trans('net') . '</button>'
                . '<button type="button" class="btn btn-light active" data-column="subtotal" onclick="changeColumn(this)">'
                . Tools::trans('subtotal') . '</button>';
        } else {
            $html .= '<button type="button" class="btn btn-light active" data-column="neto" onclick="changeColumn(this)">'
                . Tools::trans('net') . '</button>'
                . '<button type="button" class="btn btn-light" data-column="subtotal" onclick="changeColumn(this)">'
                . Tools::trans('subtotal') . '</button>';
        }

        $html .= '</div></div>';
        return $html;
    }

    /**
     * Genera el campo de tasa de conversión.
     *
     * @param BusinessDocument $model
     * @return string
     */
    protected static function tasaconv(BusinessDocument $model): string
    {
        $attributes = $model->editable ? 'name="tasaconv" step="any" autocomplete="off"' : 'disabled';
        return '<div class="col-sm-6">'
            . '<div class="mb-2">' . Tools::trans('conversion-rate')
            . '<input type="number" ' . $attributes . ' value="' . floatval($model->tasaconv) . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }

    /**
     * Genera el campo de total con botón de guardar.
     *
     * @param BusinessDocument $model
     * @param string $jsFunction
     * @return string
     */
    protected static function total(BusinessDocument $model, string $jsFunction): string
    {
        if (empty($model->total)) {
            return '';
        }

        $decimals = Tools::settings('default', 'decimals', 2);
        $separator = Tools::settings('default', 'decimal_separator', ',');
        $formatted = number_format($model->total, $decimals, $separator, '');

        return '<div class="col-sm-6 col-md-4 col-lg"><div class="mb-2">' . Tools::trans('total')
            . '<div class="input-group">'
            . '<input type="text" value="' . $formatted
            . '" class="form-control" disabled/>'
            . '<button class="btn btn-primary btn-spin-action" onclick="return ' . $jsFunction
            . '(\'save-doc\', \'0\');" title="' . Tools::trans('save') . '" type="button">'
            . '<i class="fa-solid fa-save fa-fw"></i></button>'
            . '</div></div></div>';
    }

    /**
     * Genera el botón de deshacer.
     *
     * @param BusinessDocument $model
     * @return string
     */
    protected static function undoBtn(BusinessDocument $model): string
    {
        if (!$model->subjectColumnValue() || !$model->editable) {
            return '';
        }

        return '<a href="' . $model->url() . '" class="btn btn-secondary me-2">'
            . '<i class="fa-solid fa-undo fa-fw"></i> ' . Tools::trans('undo')
            . '</a>';
    }

    /**
     * Genera el campo de usuario.
     *
     * @param BusinessDocument $model
     * @return string
     */
    protected static function user(BusinessDocument $model): string
    {
        $subjectValue = $model->subjectColumnValue();
        if (empty($subjectValue)) {
            return '';
        }

        return '<div class="col-sm-6">'
            . '<div class="mb-2">' . Tools::trans('user')
            . '<input type="text" disabled value="' . Tools::noHtml($model->nick) . '" class="form-control"/>'
            . '</div>'
            . '</div>';
    }
}