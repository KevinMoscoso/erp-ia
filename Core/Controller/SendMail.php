<?php
/**
 * ERPIA - Sistema de Gestión Empresarial
 * Este archivo es parte de ERPIA, software libre bajo licencia GPL.
 * 
 * @package    ERPIA\Core\Controller
 * @author     Equipo de Desarrollo ERPIA
 * @copyright  2023-2025 ERPIA
 * @license    GNU Lesser General Public License v3.0
 */

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\Controller;
use ERPIA\Core\Base\ControllerPermissions;
use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Response;
use ERPIA\Core\Translator;
use ERPIA\Core\Utility\DateHelper;
use ERPIA\Core\Utility\FileSystem;
use ERPIA\Core\Config;
use ERPIA\Dinamic\Lib\Email\NewMail;
use ERPIA\Dinamic\Model\Cliente;
use ERPIA\Dinamic\Model\CodeModel;
use ERPIA\Dinamic\Model\Contacto;
use ERPIA\Dinamic\Model\EmailNotification;
use ERPIA\Dinamic\Model\FacturaCliente;
use ERPIA\Dinamic\Model\Proveedor;
use ERPIA\Dinamic\Model\User;
use PHPMailer\PHPMailer\Exception;

/**
 * Controlador para el envío de correos electrónicos
 * 
 * Gestiona el envío de emails con adjuntos, plantillas configurables
 * y actualización automática de documentos enviados.
 */
class SendMail extends Controller
{
    const MAX_FILE_AGE = 2592000; // 30 días en segundos
    const MODEL_NAMESPACE = '\\ERPIA\\Dinamic\\Model\\';

    /** @var CodeModel */
    public $codeModel;

    /** @var NewMail */
    public $newMail;

    /**
     * Obtiene los metadatos de la página
     * 
     * @return array Configuración de menú, título e icono
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'sales';
        $pageData['title'] = 'send-mail';
        $pageData['icon'] = 'fa-solid fa-envelope';
        $pageData['showonmenu'] = false;
        
        return $pageData;
    }

    /**
     * Ejecuta la lógica privada del controlador
     * 
     * @param Response $response
     * @param User $user
     * @param ControllerPermissions $permissions
     * @throws Exception
     */
    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);
        
        $this->codeModel = new CodeModel();
        $this->newMail = NewMail::create()->setUser($this->user);
        
        // Verificar si el email está configurado
        if (false === $this->newMail->canSendMail()) {
            Translator::log()->warning('email-not-configured');
        }
        
        $action = $this->request->inputOrQuery('action', '');
        $this->execAction($action);
    }

    /**
     * Retorna la URL del controlador actual
     * 
     * @return string URL completa
     */
    public function url(): string
    {
        $sendParams = ['fileName' => $this->request->queryOrInput('fileName', '')];
        
        if (empty($sendParams['fileName'])) {
            return parent::url();
        }
        
        if ($this->request->has('modelClassName') && $this->request->has('modelCode')) {
            $sendParams['modelClassName'] = $this->request->queryOrInput('modelClassName');
            $sendParams['modelCode'] = $this->request->queryOrInput('modelCode');
            
            if ($this->request->has('modelCodes')) {
                $sendParams['modelCodes'] = urldecode($this->request->queryOrInput('modelCodes'));
            }
        }
        
        return parent::url() . '?' . http_build_query($sendParams);
    }

    /**
     * Ejecuta la acción de autocompletado
     * 
     * @return array Resultados para JSON
     */
    protected function autocompleteAction(): array
    {
        $results = [];
        $data = $this->requestGet(['source', 'field', 'title', 'term']);
        
        foreach ($this->codeModel->search($data['source'], $data['field'], $data['title'], $data['term']) as $value) {
            $results[] = ['key' => $value->code, 'value' => $value->description];
        }
        
        return $results;
    }

    /**
     * Verifica facturas editables y muestra advertencia
     */
    protected function checkInvoices(): void
    {
        if ($this->request->query('modelClassName') != 'FacturaCliente') {
            return;
        }
        
        $invoice = new FacturaCliente();
        if ($invoice->load($this->request->query->getAlnum('modelCode')) && $invoice->editable) {
            Translator::log()->warning('sketch-invoice-warning');
        }
    }

    /**
     * Ejecuta las acciones principales del controlador
     * 
     * @param string $action Acción a ejecutar
     * @throws Exception
     */
    protected function execAction(string $action): void
    {
        switch ($action) {
            case 'autocomplete':
                $this->setTemplate(false);
                $data = $this->autocompleteAction();
                $this->response->json($data);
                break;
                
            case 'send':
                // Validar token del formulario
                if (false === $this->validateFormToken()) {
                    break;
                }
                
                if ($this->send()) {
                    Translator::log()->notice('send-mail-ok');
                    $this->updateFemail();
                    $this->redirAfter();
                    break;
                }
                
                Translator::log()->error('send-mail-error');
                break;
                
            default:
                $this->removeOld();
                $this->setEmailAddress();
                $this->setAttachment();
                $this->checkInvoices();
                break;
        }
    }

    /**
     * Obtiene emails separados por comas desde un campo del formulario
     * 
     * @param string $field Nombre del campo
     * @return array Lista de emails
     */
    protected function getEmails(string $field): array
    {
        return NewMail::splitEmails($this->request->input($field, ''));
    }

    /**
     * Carga datos por defecto desde plantillas de notificación
     * 
     * @param mixed $model Modelo del documento
     */
    protected function loadDataDefault($model): void
    {
        $notificationModel = new EmailNotification();
        $where = [
            new DataBaseWhere('name', 'sendmail-' . $model->modelClassName()),
            new DataBaseWhere('enabled', true)
        ];
        
        if ($notificationModel->loadWhere($where)) {
            $shortCodes = ['{code}', '{name}', '{date}', '{total}', '{number2}'];
            $shortValues = [$model->codigo, '', $model->fecha, $model->total, ''];
            
            $shortValues[1] = $model->hasColumn('nombrecliente')
                ? $model->nombrecliente
                : $model->nombre;
                
            $shortValues[4] = $model->hasColumn('numero2')
                ? $model->numero2
                : $model->numproveedor;
            
            $this->newMail->title = str_replace($shortCodes, $shortValues, $notificationModel->subject);
            $this->newMail->text = str_replace($shortCodes, $shortValues, $notificationModel->body);
            return;
        }
        
        $translator = Translator::getInstance();
        
        switch ($model->modelClassName()) {
            case 'AlbaranCliente':
            case 'AlbaranProveedor':
                $this->newMail->title = $translator->trans('delivery-note-email-subject', ['%code%' => $model->codigo]);
                $this->newMail->text = $translator->trans('delivery-note-email-text', ['%code%' => $model->codigo]);
                break;
                
            case 'FacturaCliente':
            case 'FacturaProveedor':
                $this->newMail->title = $translator->trans('invoice-email-subject', ['%code%' => $model->codigo]);
                $this->newMail->text = $translator->trans('invoice-email-text', ['%code%' => $model->codigo]);
                break;
                
            case 'PedidoCliente':
            case 'PedidoProveedor':
                $this->newMail->title = $translator->trans('order-email-subject', ['%code%' => $model->codigo]);
                $this->newMail->text = $translator->trans('order-email-text', ['%code%' => $model->codigo]);
                break;
                
            case 'PresupuestoCliente':
            case 'PresupuestoProveedor':
                $this->newMail->title = $translator->trans('estimation-email-subject', ['%code%' => $model->codigo]);
                $this->newMail->text = $translator->trans('estimation-email-text', ['%code%' => $model->codigo]);
                break;
        }
    }

    /**
     * Redirige después de enviar el email
     */
    protected function redirAfter(): void
    {
        $className = self::MODEL_NAMESPACE . $this->request->queryOrInput('modelClassName');
        
        if (false === class_exists($className)) {
            Translator::log()->notice('reloading');
            $this->redirect('SendMail', 3);
            return;
        }
        
        $model = new $className();
        $modelCode = $this->request->queryOrInput('modelCode');
        
        if ($model->load($modelCode) && $model->hasColumn('femail')) {
            Translator::log()->notice('reloading');
            $this->redirect($model->url(), 3);
        }
    }

    /**
     * Elimina archivos adjuntos antiguos
     */
    protected function removeOld(): void
    {
        $config = Config::getInstance();
        $basePath = $config->get('base_path', '');
        $pattern = $basePath . '/MyFiles/*_mail_*.pdf';
        
        foreach (glob($pattern) as $fileName) {
            $parts = explode('_', $fileName);
            $time = (int)substr(end($parts), 0, -4);
            
            if ($time < (time() - self::MAX_FILE_AGE)) {
                unlink($fileName);
            }
        }
    }

    /**
     * Obtiene valores del request para un conjunto de claves
     * 
     * @param array $keys Claves a obtener
     * @return array Valores asociados
     */
    protected function requestGet(array $keys): array
    {
        $result = [];
        
        foreach ($keys as $value) {
            $result[$value] = $this->request->queryOrInput($value);
        }
        
        return $result;
    }

    /**
     * Envía un email con los datos del formulario
     * 
     * @return bool True si el envío fue exitoso
     * @throws Exception
     */
    protected function send(): bool
    {
        if ($this->newMail->fromEmail != $this->user->email && $this->request->input('replyto', '0')) {
            $this->newMail->replyTo($this->user->email, $this->user->nick);
        }
        
        $this->newMail->title = $this->request->input('subject', '');
        $this->newMail->text = $this->request->input('body', '');
        $this->newMail->setMailbox($this->request->input('email-from', ''));
        
        foreach ($this->getEmails('email') as $email) {
            $this->newMail->to($email);
        }
        
        foreach ($this->getEmails('email-cc') as $email) {
            $this->newMail->cc($email);
        }
        
        foreach ($this->getEmails('email-bcc') as $email) {
            $this->newMail->bcc($email);
        }
        
        $this->setAttachment();
        return $this->newMail->send();
    }

    /**
     * Configura los archivos adjuntos del email
     * 
     * @throws Exception
     */
    protected function setAttachment(): void
    {
        $fileName = $this->request->queryOrInput('fileName', '');
        $config = Config::getInstance();
        $basePath = $config->get('base_path', '');
        
        FileSystem::createDirectory(NewMail::ATTACHMENTS_TMP_PATH);
        
        $filePath = $basePath . '/' . NewMail::ATTACHMENTS_TMP_PATH . $fileName;
        if (file_exists($filePath)) {
            $this->newMail->addAttachment($filePath, $fileName);
        }
        
        foreach ($this->request->files->getArray('uploads') as $file) {
            if ($file->move(NewMail::ATTACHMENTS_TMP_PATH, $file->getClientOriginalName())) {
                $filePath = $basePath . '/' . NewMail::ATTACHMENTS_TMP_PATH . $file->getClientOriginalName();
                $this->newMail->addAttachment($filePath, $file->getClientOriginalName());
            }
        }
    }

    /**
     * Configura la dirección de email del destinatario
     * 
     * @throws Exception
     */
    protected function setEmailAddress(): void
    {
        $email = $this->request->queryOrInput('email', '');
        
        if (!empty($email)) {
            $this->newMail->to($email);
            return;
        }
        
        $className = self::MODEL_NAMESPACE . $this->request->queryOrInput('modelClassName', '');
        
        if (false === class_exists($className)) {
            return;
        }
        
        $model = new $className();
        $model->load($this->request->queryOrInput('modelCode', ''));
        $this->loadDataDefault($model);
        
        if ($model->hasColumn('email') && $model->email) {
            $this->newMail->to($model->email);
            return;
        }
        
        $proveedor = new Proveedor();
        if ($model->hasColumn('codproveedor') && $proveedor->load($model->codproveedor) && $proveedor->email) {
            $this->newMail->to($proveedor->email, $proveedor->razonsocial);
            return;
        }
        
        $contact = new Contacto();
        if ($model->hasColumn('idcontactofact') && $contact->load($model->idcontactofact) && $contact->email) {
            $this->newMail->to($contact->email, $contact->fullName());
            return;
        }
        
        $cliente = new Cliente();
        if ($model->hasColumn('codcliente') && $cliente->load($model->codcliente) && $cliente->email) {
            $this->newMail->to($cliente->email, $cliente->razonsocial);
        }
    }

    /**
     * Actualiza la fecha de envío del email en los documentos
     */
    protected function updateFemail(): void
    {
        $className = self::MODEL_NAMESPACE . $this->request->queryOrInput('modelClassName');
        
        if (false === class_exists($className)) {
            return;
        }
        
        $model = new $className();
        $modelCode = $this->request->queryOrInput('modelCode');
        
        if ($model->load($modelCode) && $model->hasColumn('femail')) {
            $model->femail = DateHelper::currentDate();
            
            if (false === $model->save()) {
                Translator::log()->error('record-save-error');
                return;
            }
            
            $subject = $model->getSubject();
            if (empty($subject->email)) {
                foreach ($this->newMail->getToAddresses() as $email) {
                    $subject->email = $email;
                    $subject->save();
                    break;
                }
            }
        }
        
        $modelCodes = $this->request->queryOrInput('modelCodes', '');
        foreach (explode(',', $modelCodes) as $modelCode) {
            if ($model->load($modelCode) && $model->hasColumn('femail')) {
                $model->femail = DateHelper::currentDate();
                $model->save();
            }
        }
    }

    /**
     * Valida el token del formulario para prevenir CSRF
     * 
     * @return bool True si el token es válido
     */
    protected function validateFormToken(): bool
    {
        $token = $this->request->inputOrQuery('multireqtoken', '');
        
        if (empty($token)) {
            Translator::log()->warning('invalid-request');
            return false;
        }
        
        // Implementación básica de validación de token
        // En una implementación real, usaríamos MultiRequestProtection de ERPIA
        $sessionToken = $this->request->session()->get('form_token');
        
        if ($sessionToken !== $token) {
            Translator::log()->warning('invalid-request');
            return false;
        }
        
        return true;
    }
}