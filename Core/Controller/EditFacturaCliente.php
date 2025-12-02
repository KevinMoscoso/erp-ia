<?php
/**
 * Copyright (C) 2024-2025 ERPIA Team
 */

namespace ERPIA\Controller;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Lib\AjaxForms\SalesController;
use ERPIA\Core\Lib\Calculator;
use ERPIA\Core\Lib\ExtendedController\BaseView;
use ERPIA\Core\Tools;
use ERPIA\Dinamic\Lib\Accounting\InvoiceToAccounting;
use ERPIA\Dinamic\Lib\ReceiptGenerator;
use ERPIA\Dinamic\Model\FacturaCliente;
use ERPIA\Dinamic\Model\ReciboCliente;

/**
 * Controlador para editar facturas de clientes
 * 
 * Gestiona facturas de venta con funcionalidades avanzadas como generación
 * de recibos, asientos contables, facturas rectificativas y gestión de pagos.
 */
class EditFacturaCliente extends SalesController
{
    private const VIEW_ACCOUNTS = 'ListAsiento';
    private const VIEW_RECEIPTS = 'ListReciboCliente';
    
    /**
     * Devuelve el nombre de la clase del modelo principal
     * 
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'FacturaCliente';
    }

    /**
     * Obtiene los metadatos de la página
     * 
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'sales';
        $pageData['title'] = 'invoice';
        $pageData['icon'] = 'fa-solid fa-file-invoice-dollar';
        $pageData['showonmenu'] = false;
        
        return $pageData;
    }

    /**
     * Configura las vistas del controlador
     */
    protected function createViews(): void
    {
        parent::createViews();
        $this->createViewsReceipts();
        $this->createViewsAccounting();
        $this->createViewsRefunds();
    }

    /**
     * Crea la vista de asientos contables
     * 
     * @param string $viewName Nombre de la vista
     */
    private function createViewsAccounting(string $viewName = self::VIEW_ACCOUNTS): void
    {
        $this->addListView($viewName, 'Asiento', 'accounting-entries', 'fa-solid fa-balance-scale')
            ->addSearchFields(['concepto'])
            ->addOrderBy(['fecha'], 'date', 1);
        
        // Botón para generar asiento contable
        $this->addButton($viewName, [
            'action' => 'generate-accounting',
            'icon' => 'fa-solid fa-wand-magic-sparkles',
            'label' => 'generate-accounting-entry'
        ]);
        
        // Configuración
        $this->setSettings($viewName, 'btnNew', false);
    }

    /**
     * Crea la vista de facturas rectificativas
     * 
     * @param string $viewName Nombre de la vista
     */
    private function createViewsRefunds(string $viewName = 'refunds'): void
    {
        $this->addHtmlView($viewName, 'Tab/RefundFacturaCliente', 'FacturaCliente', 'refunds', 'fa-solid fa-share-square');
    }

    /**
     * Crea la vista de recibos
     * 
     * @param string $viewName Nombre de la vista
     */
    private function createViewsReceipts(string $viewName = self::VIEW_RECEIPTS): void
    {
        $this->addListView($viewName, 'ReciboCliente', 'receipts', 'fa-solid fa-dollar-sign')
            ->addSearchFields(['observaciones'])
            ->addOrderBy(['vencimiento'], 'expiration')
            ->addOrderBy(['importe'], 'amount');
        
        // Botones
        $this->addButton($viewName, [
            'action' => 'generate-receipts',
            'confirm' => 'true',
            'icon' => 'fa-solid fa-wand-magic-sparkles',
            'label' => 'generate-receipts'
        ]);
        
        $this->addButton($viewName, [
            'action' => 'paid',
            'color' => 'outline-success',
            'confirm' => 'true',
            'icon' => 'fa-solid fa-check',
            'label' => 'paid'
        ]);
        
        // Deshabilitar columnas
        $this->views[$viewName]->disableColumn('customer');
        $this->views[$viewName]->disableColumn('invoice');
        
        // Configuración
        $this->setSettings($viewName, 'modalInsert', 'generate-receipts');
    }

    /**
     * Ejecuta acciones previas
     * 
     * @param string $action Acción a ejecutar
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'generate-accounting':
                return $this->generateAccountingAction();
            case 'generate-receipts':
                return $this->generateReceiptsAction();
            case 'new-refund':
                return $this->newRefundAction();
            case 'paid':
                return $this->paidAction();
        }
        return parent::execPreviousAction($action);
    }

    /**
     * Genera el asiento contable para la factura
     * 
     * @return bool
     */
    private function generateAccountingAction(): bool
    {
        $factura = new FacturaCliente();
        if (false === $factura->load($this->request->query('code'))) {
            Tools::log()->warning('record-not-found');
            return true;
        } elseif (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }
        
        $generador = new InvoiceToAccounting();
        $generador->generate($factura);
        
        if (empty($factura->idasiento)) {
            Tools::log()->error('record-save-error');
            return true;
        }
        
        if ($factura->save()) {
            Tools::log()->notice('record-updated-correctly');
            return true;
        }
        
        Tools::log()->error('record-save-error');
        return true;
    }

    /**
     * Genera recibos para la factura
     * 
     * @return bool
     */
    private function generateReceiptsAction(): bool
    {
        $factura = new FacturaCliente();
        if (false === $factura->load($this->request->query('code'))) {
            Tools::log()->warning('record-not-found');
            return true;
        } elseif (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }
        
        $generador = new ReceiptGenerator();
        $numero = (int)$this->request->input('number', '0');
        
        if ($generador->generate($factura, $numero)) {
            $generador->update($factura);
            $factura->save();
            Tools::log()->notice('record-updated-correctly');
            return true;
        }
        
        Tools::log()->error('record-save-error');
        return true;
    }

    /**
     * Carga datos en una vista específica
     * 
     * @param string $viewName Nombre de la vista
     * @param BaseView $view Instancia de la vista
     */
    protected function loadData($viewName, $view)
    {
        $vistaPrincipal = $this->getMainViewName();
        
        switch ($viewName) {
            case self::VIEW_RECEIPTS:
                $filtro = [new DataBaseWhere('idfactura', $this->getViewModelValue($vistaPrincipal, 'idfactura'))];
                $view->loadData('', $filtro);
                if (empty($view->query)) {
                    $this->checkReceiptsTotal($view->cursor);
                }
                break;
                
            case self::VIEW_ACCOUNTS:
                $filtro = [new DataBaseWhere('idasiento', $this->getViewModelValue($vistaPrincipal, 'idasiento'))];
                $view->loadData('', $filtro);
                break;
                
            case 'refunds':
                if ($this->getViewModelValue($vistaPrincipal, 'idfacturarect')) {
                    $this->setSettings($viewName, 'active', false);
                    break;
                }
                $filtro = [new DataBaseWhere('idfacturarect', $this->getViewModelValue($vistaPrincipal, 'idfactura'))];
                $view->loadData('', $filtro);
                break;
                
            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    /**
     * Crea una factura rectificativa
     * 
     * @return bool
     */
    protected function newRefundAction(): bool
    {
        $factura = new FacturaCliente();
        if (false === $factura->load($this->request->input('idfactura'))) {
            Tools::log()->warning('record-not-found');
            return true;
        } elseif (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }
        
        $lineas = [];
        foreach ($factura->getLines() as $linea) {
            $cantidad = (float)$this->request->input('refund_' . $linea->id(), '0');
            if (!empty($cantidad)) {
                $lineas[] = $linea;
            }
        }
        
        if (empty($lineas)) {
            Tools::log()->warning('no-selected-item');
            return true;
        }
        
        $this->dataBase->beginTransaction();
        
        if ($factura->editable) {
            foreach ($factura->getAvailableStatus() as $estado) {
                if ($estado->editable || !$estado->activo) {
                    continue;
                }
                $factura->idestado = $estado->idestado;
                if (false === $factura->save()) {
                    Tools::log()->error('record-save-error');
                    $this->dataBase->rollback();
                    return true;
                }
            }
        }
        
        $nuevaRectificativa = new FacturaCliente();
        $nuevaRectificativa->loadFromData($factura->toArray(), $factura::dontCopyFields());
        $nuevaRectificativa->codigorect = $factura->codigo;
        $nuevaRectificativa->codserie = $this->request->input('codserie');
        $nuevaRectificativa->idfacturarect = $factura->idfactura;
        $nuevaRectificativa->nick = $this->user->nick;
        $nuevaRectificativa->observaciones = $this->request->input('observaciones');
        $nuevaRectificativa->setDate($this->request->input('fecha'), date(Tools::HOUR_STYLE));
        
        if (false === $nuevaRectificativa->save()) {
            Tools::log()->error('record-save-error');
            $this->dataBase->rollback();
            return true;
        }
        
        foreach ($lineas as $linea) {
            $nuevaLinea = $nuevaRectificativa->getNewLine($linea->toArray());
            $nuevaLinea->cantidad = 0 - (float)$this->request->input('refund_' . $linea->id(), '0');
            $nuevaLinea->idlinearect = $linea->idlinea;
            if (false === $nuevaLinea->save()) {
                Tools::log()->error('record-save-error');
                $this->dataBase->rollback();
                return true;
            }
        }
        
        $nuevasLineas = $nuevaRectificativa->getLines();
        $nuevaRectificativa->idestado = $factura->idestado;
        
        if (false === Calculator::calculate($nuevaRectificativa, $nuevasLineas, true)) {
            Tools::log()->error('record-save-error');
            $this->dataBase->rollback();
            return true;
        }
        
        // Si la factura estaba pagada, marcamos los recibos de la nueva como pagados
        if ($factura->pagada) {
            foreach ($nuevaRectificativa->getReceipts() as $recibo) {
                $recibo->pagado = true;
                $recibo->save();
            }
        }
        
        // Asignamos el estado de la factura
        $nuevaRectificativa->idestado = $this->request->input('idestado');
        if (false === $nuevaRectificativa->save()) {
            Tools::log()->error('record-save-error');
            $this->dataBase->rollback();
            return true;
        }
        
        $this->dataBase->commit();
        Tools::log()->notice('record-updated-correctly');
        $this->redirect($nuevaRectificativa->url() . '&action=save-ok');
        return false;
    }

    /**
     * Verifica que la suma de los recibos coincida con el total de la factura
     * 
     * @param ReciboCliente[] $recibos
     */
    private function checkReceiptsTotal(array &$recibos): void
    {
        $total = 0.00;
        foreach ($recibos as $fila) {
            $total += $fila->importe;
        }
        
        $diferencia = $this->getModel()->total - $total;
        if (abs($diferencia) > 0.01) {
            Tools::log()->warning('invoice-receipts-diff', ['%diff%' => $diferencia]);
        }
    }

    /**
     * Marca recibos como pagados
     * 
     * @return bool
     */
    private function paidAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }
        
        $codigos = $this->request->request->getArray('codes');
        $modelo = $this->views[$this->active]->model;
        
        if (empty($codigos) || empty($modelo)) {
            Tools::log()->warning('no-selected-item');
            return true;
        }
        
        foreach ($codigos as $codigo) {
            if (false === $modelo->loadFromCode($codigo)) {
                Tools::log()->error('record-not-found');
                continue;
            }
            
            $modelo->nick = $this->user->nick;
            $modelo->pagado = true;
            if (false === $modelo->save()) {
                Tools::log()->error('record-save-error');
                return true;
            }
        }
        
        Tools::log()->notice('record-updated-correctly');
        $modelo->clear();
        return true;
    }
}