<?php

namespace ERPIA\Lib\Email;

use ERPIA\Core\Config;
use ERPIA\Core\Logger;
use ERPIA\Core\TemplateRenderer;
use ERPIA\Models\Company;
use ERPIA\Models\User;
use ERPIA\Models\EmailNotification;
use ERPIA\Models\EmailSent;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Email manager for ERPIA system
 * Handles email sending with PHPMailer, template rendering, and email logging
 */
class NewMail
{
    const ATTACHMENTS_TEMP_PATH = 'Storage/Temp/EmailAttachments/';
    const TEMPLATE_DEFAULT = 'Email/DefaultTemplate.twig';
    
    /** @var Company */
    protected $company;
    
    /** @var PHPMailer */
    protected $mailClient;
    
    /** @var array */
    protected $mainContentBlocks = [];
    
    /** @var array */
    protected $footerContentBlocks = [];
    
    /** @var string */
    protected $senderEmail;
    
    /** @var string */
    protected $senderName;
    
    /** @var string */
    protected $senderUsername;
    
    /** @var string */
    protected $emailSubject;
    
    /** @var string */
    protected $plainTextBody;
    
    /** @var string */
    protected $htmlBody;
    
    /** @var string */
    protected $emailSignature;
    
    /** @var string */
    protected $verificationCode;
    
    /** @var array */
    private static $availableTransports = [
        'mail' => 'PHP Mail',
        'sendmail' => 'Sendmail',
        'smtp' => 'SMTP'
    ];
    
    /** @var string */
    private static $activeTemplate = self::TEMPLATE_DEFAULT;
    
    public function __construct()
    {
        $config = Config::getInstance();
        $this->company = $this->getDefaultCompany();
        
        $this->mailClient = new PHPMailer(true);
        $this->configureMailClient($config);
        
        $this->verificationCode = $this->generateVerificationCode();
        $this->loadDefaultSettings($config);
    }
    
    /**
     * Configure PHPMailer with ERPIA settings
     */
    private function configureMailClient(Config $config): void
    {
        $this->mailClient->CharSet = PHPMailer::CHARSET_UTF8;
        $this->mailClient->Debugoutput = function ($message) {
            Logger::warning('PHPMailer Debug', ['message' => $message]);
        };
        
        $this->mailClient->Mailer = $config->get('email.transport', 'smtp');
        
        $encryption = $config->get('email.encryption', '');
        if (!empty($encryption)) {
            $this->mailClient->SMTPSecure = $encryption;
            $this->mailClient->SMTPAuth = true;
            $this->mailClient->AuthType = $config->get('email.auth_type', 'LOGIN');
        }
        
        $this->mailClient->Host = $config->get('email.host', '');
        $this->mailClient->Port = (int) $config->get('email.port', 587);
        $this->mailClient->Username = $config->get('email.username', $config->get('email.address', ''));
        $this->mailClient->Password = $config->get('email.password', '');
        
        $lowSecurity = (bool) $config->get('email.low_security', false);
        if ($lowSecurity) {
            $this->mailClient->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
        }
    }
    
    /**
     * Load default email settings from configuration
     */
    private function loadDefaultSettings(Config $config): void
    {
        $this->senderEmail = $config->get('email.address', '');
        $this->senderName = $this->company->getShortName();
        $this->emailSignature = $config->get('email.signature', '');
        
        // Add default CC recipients
        $ccRecipients = $this->parseEmailList($config->get('email.cc_recipients', ''));
        foreach ($ccRecipients as $email) {
            $this->addCarbonCopy($email);
        }
        
        // Add default BCC recipients
        $bccRecipients = $this->parseEmailList($config->get('email.bcc_recipients', ''));
        foreach ($bccRecipients as $email) {
            $this->addBlindCarbonCopy($email);
        }
    }
    
    /**
     * Get the default company from ERPIA
     */
    private function getDefaultCompany(): Company
    {
        // This would integrate with ERPIA's company management
        return new Company(); // Assuming Company has getShortName() method
    }
    
    /**
     * Generate a unique verification code
     */
    private function generateVerificationCode(): string
    {
        return bin2hex(random_bytes(10));
    }
    
    /**
     * Parse comma-separated email list into array
     */
    public static function parseEmailList(string $emailList): array
    {
        $emails = [];
        $parts = explode(',', $emailList);
        
        foreach ($parts as $part) {
            $email = trim($part);
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }
        
        return $emails;
    }
    
    /**
     * Add a new mail transport method
     */
    public static function registerTransport(string $key, string $name): void
    {
        if (!array_key_exists($key, self::$availableTransports)) {
            self::$availableTransports[$key] = $name;
        }
    }
    
    /**
     * Get available mail transports
     */
    public static function getAvailableTransports(): array
    {
        $transports = self::$availableTransports;
        
        // Remove mail transport if PHP mail() function is disabled
        if (!function_exists('mail')) {
            unset($transports['mail']);
        }
        
        // Remove sendmail transport if sendmail_path is not configured
        if (empty(ini_get('sendmail_path'))) {
            unset($transports['sendmail']);
        }
        
        return $transports;
    }
    
    /**
     * Create new instance (factory method)
     */
    public static function create(): self
    {
        return new static();
    }
    
    /**
     * Set email subject
     */
    public function setSubject(string $subject): self
    {
        $this->emailSubject = $subject;
        return $this;
    }
    
    /**
     * Set plain text body
     */
    public function setBody(string $body): self
    {
        $this->plainTextBody = $body;
        return $this;
    }
    
    /**
     * Add primary recipient
     */
    public function addRecipient(string $email, string $name = ''): self
    {
        $this->mailClient->addAddress($email, $name);
        return $this;
    }
    
    /**
     * Add CC recipient
     */
    public function addCarbonCopy(string $email, string $name = ''): self
    {
        $this->mailClient->addCC($email, $name);
        return $this;
    }
    
    /**
     * Add BCC recipient
     */
    public function addBlindCarbonCopy(string $email, string $name = ''): self
    {
        $this->mailClient->addBCC($email, $name);
        return $this;
    }
    
    /**
     * Add reply-to address
     */
    public function addReplyAddress(string $email, string $name = ''): self
    {
        $this->mailClient->addReplyTo($email, $name);
        return $this;
    }
    
    /**
     * Add file attachment
     * @throws Exception
     */
    public function attachFile(string $filePath, string $fileName = ''): self
    {
        $this->mailClient->addAttachment($filePath, $fileName);
        return $this;
    }
    
    /**
     * Add content block to main section
     */
    public function addMainBlock(ContentBlock $block): self
    {
        $block->setVerificationCode($this->verificationCode);
        $this->mainContentBlocks[] = $block;
        return $this;
    }
    
    /**
     * Add content block to footer section
     */
    public function addFooterBlock(ContentBlock $block): self
    {
        $block->setVerificationCode($this->verificationCode);
        $this->footerContentBlocks[] = $block;
        return $this;
    }
    
    /**
     * Set sender mailbox
     */
    public function setSenderMailbox(string $email): self
    {
        $this->senderEmail = $email;
        return $this;
    }
    
    /**
     * Set user as sender
     */
    public function setSenderUser(User $user): self
    {
        $this->senderUsername = $user->getUsername();
        return $this;
    }
    
    /**
     * Check if email can be sent
     */
    public function canSend(): bool
    {
        return !empty($this->senderEmail) 
            && !empty($this->mailClient->Password)
            && !empty($this->mailClient->Host);
    }
    
    /**
     * Send email
     * @throws Exception
     */
    public function send(): bool
    {
        if (!$this->canSend()) {
            Logger::warning('Email configuration incomplete');
            return false;
        }
        
        try {
            $this->mailClient->setFrom($this->senderEmail, $this->senderName);
            $this->mailClient->Subject = $this->emailSubject;
            
            $this->renderEmailContent();
            $this->mailClient->msgHTML($this->htmlBody);
            
            if ($this->mailClient->Mailer === 'SMTP') {
                if (!$this->mailClient->smtpConnect()) {
                    Logger::warning('SMTP connection failed');
                    return false;
                }
            }
            
            if ($this->mailClient->send()) {
                $this->logSentEmail();
                return true;
            }
            
            Logger::error('Email sending failed', [
                'error' => $this->mailClient->ErrorInfo
            ]);
            return false;
            
        } catch (Exception $e) {
            Logger::error('Email exception', [
                'message' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Send notification email
     * @throws Exception
     */
    public function sendNotification(string $notificationName, array $parameters = []): bool
    {
        $notification = new EmailNotification();
        
        if (!$notification->loadByName($notificationName)) {
            Logger::warning('Email notification not found', ['name' => $notificationName]);
            return false;
        }
        
        if (!$notification->isEnabled()) {
            Logger::warning('Email notification disabled', ['name' => $notificationName]);
            return false;
        }
        
        if (!isset($parameters['verificode'])) {
            $parameters['verificode'] = $this->verificationCode;
        }
        
        $this->emailSubject = $this->renderNotificationText($notification->getSubject(), $parameters);
        $this->plainTextBody = $this->renderNotificationText($notification->getBody(), $parameters);
        
        return $this->send();
    }
    
    /**
     * Test email connection
     */
    public function testConnection(): bool
    {
        if ($this->mailClient->Mailer !== 'SMTP') {
            Logger::warning('Connection test only available for SMTP');
            return false;
        }
        
        $this->mailClient->SMTPDebug = 3;
        return $this->mailClient->smtpConnect();
    }
    
    /**
     * Get recipient addresses
     */
    public function getRecipients(): array
    {
        $recipients = [];
        foreach ($this->mailClient->getToAddresses() as $address) {
            $recipients[] = $address[0];
        }
        return $recipients;
    }
    
    /**
     * Get CC addresses
     */
    public function getCarbonCopyRecipients(): array
    {
        $recipients = [];
        foreach ($this->mailClient->getCcAddresses() as $address) {
            $recipients[] = $address[0];
        }
        return $recipients;
    }
    
    /**
     * Get BCC addresses
     */
    public function getBlindCarbonCopyRecipients(): array
    {
        $recipients = [];
        foreach ($this->mailClient->getBccAddresses() as $address) {
            $recipients[] = $address[0];
        }
        return $recipients;
    }
    
    /**
     * Get attachment filenames
     */
    public function getAttachmentNames(): array
    {
        $names = [];
        foreach ($this->mailClient->getAttachments() as $attachment) {
            $names[] = $attachment[1];
        }
        return $names;
    }
    
    /**
     * Get available sender mailboxes
     */
    public function getAvailableSenders(): array
    {
        return empty($this->senderEmail) ? [] : [$this->senderEmail];
    }
    
    /**
     * Get attachment storage path
     */
    public static function getAttachmentPath(?string $email, string $folder): string
    {
        $path = 'Storage/Email/{{email}}/' . $folder . '/';
        return str_replace('{{email}}', $email ?? 'unknown', $path);
    }
    
    /**
     * Set email template
     */
    public static function setTemplate(string $template): void
    {
        self::$activeTemplate = $template;
    }
    
    /**
     * Get current template
     */
    public static function getTemplate(): string
    {
        return self::$activeTemplate;
    }
    
    /**
     * Render email content using template engine
     */
    private function renderEmailContent(): void
    {
        $templateData = [
            'company' => $this->company,
            'logo_id' => Config::getInstance()->get('email.logo_id'),
            'footer_blocks' => $this->prepareFooterBlocks(),
            'main_blocks' => $this->prepareMainBlocks(),
            'title' => $this->emailSubject
        ];
        
        $renderer = TemplateRenderer::getInstance();
        $this->htmlBody = $renderer->render(self::$activeTemplate, $templateData);
    }
    
    /**
     * Prepare main content blocks
     */
    private function prepareMainBlocks(): array
    {
        if (empty($this->plainTextBody)) {
            return $this->mainContentBlocks;
        }
        
        $textWithoutHtml = strip_tags($this->plainTextBody);
        
        if ($textWithoutHtml !== $this->plainTextBody) {
            $htmlBlock = new HtmlBlock(nl2br($this->plainTextBody));
            return array_merge([$htmlBlock], $this->mainContentBlocks);
        }
        
        $textBlock = new TextBlock($this->plainTextBody, 'pb-15');
        return array_merge([$textBlock], $this->mainContentBlocks);
    }
    
    /**
     * Prepare footer blocks with signature
     */
    private function prepareFooterBlocks(): array
    {
        $signature = htmlspecialchars($this->emailSignature ?? '');
        
        if (empty($signature)) {
            return $this->footerContentBlocks;
        }
        
        $signatureBlock = new TextBlock($signature, 'text-footer');
        return array_merge($this->footerContentBlocks, [$signatureBlock]);
    }
    
    /**
     * Render notification text with parameters
     */
    private function renderNotificationText(string $template, array $parameters): string
    {
        foreach ($parameters as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        
        return $template;
    }
    
    /**
     * Log sent email to database and handle attachments
     */
    private function logSentEmail(): void
    {
        $allRecipients = array_merge(
            $this->getRecipients(),
            $this->getCarbonCopyRecipients(),
            $this->getBlindCarbonCopyRecipients()
        );
        
        $uniqueId = uniqid('email_', true);
        $attachments = $this->mailClient->getAttachments();
        
        foreach (array_unique($allRecipients) as $recipient) {
            $emailRecord = new EmailSent();
            $emailRecord->setRecipient($recipient);
            $emailRecord->setHasAttachments(!empty($attachments));
            $emailRecord->setBody($this->plainTextBody);
            $emailRecord->setSender($this->senderEmail);
            $emailRecord->setHtmlContent($this->htmlBody);
            $emailRecord->setSenderUsername($this->senderUsername);
            $emailRecord->setSubject($this->emailSubject);
            $emailRecord->setUniqueId($uniqueId);
            $emailRecord->setVerificationCode($this->verificationCode);
            $emailRecord->save();
        }
        
        $this->processAttachments($attachments, $uniqueId);
    }
    
    /**
     * Process and store email attachments
     */
    private function processAttachments(array $attachments, string $uniqueId): void
    {
        if (empty($attachments)) {
            return;
        }
        
        $storagePath = self::getAttachmentPath($this->senderEmail, 'Sent') . $uniqueId . '/';
        $this->ensureDirectoryExists($storagePath);
        
        foreach ($attachments as $attachment) {
            $this->moveAttachment($attachment, $storagePath);
        }
    }
    
    /**
     * Ensure directory exists
     */
    private function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
    
    /**
     * Move attachment to storage
     */
    private function moveAttachment(array $attachment, string $targetPath): void
    {
        $filename = $attachment[1];
        $destination = $targetPath . $filename;
        
        // Check temp path first
        $tempPath = self::ATTACHMENTS_TEMP_PATH . $filename;
        if (file_exists($tempPath)) {
            rename($tempPath, $destination);
            return;
        }
        
        // Check original paths
        $possiblePaths = [
            'Storage/MyFiles/' . $attachment[0],
            $attachment[0],
            ERPIA_ROOT . '/' . $attachment[0]
        ];
        
        foreach ($possiblePaths as $sourcePath) {
            if (file_exists($sourcePath)) {
                copy($sourcePath, $destination);
                return;
            }
        }
        
        Logger::warning('Email attachment not found', ['file' => $attachment[0]]);
    }
    
    /**
     * Deprecated: Add recipient (backward compatibility)
     * @deprecated since ERPIA 1.0 - Use addRecipient() instead
     */
    public function addAddress(string $email, string $name = ''): self
    {
        return $this->addRecipient($email, $name);
    }
    
    /**
     * Deprecated: Add BCC (backward compatibility)
     * @deprecated since ERPIA 1.0 - Use addBlindCarbonCopy() instead
     */
    public function addBCC(string $email, string $name = ''): self
    {
        return $this->addBlindCarbonCopy($email, $name);
    }
    
    /**
     * Deprecated: Add CC (backward compatibility)
     * @deprecated since ERPIA 1.0 - Use addCarbonCopy() instead
     */
    public function addCC(string $email, string $name = ''): self
    {
        return $this->addCarbonCopy($email, $name);
    }
    
    /**
     * Deprecated: Add reply-to (backward compatibility)
     * @deprecated since ERPIA 1.0 - Use addReplyAddress() instead
     */
    public function addReplyTo(string $email, string $name = ''): self
    {
        return $this->addReplyAddress($email, $name);
    }
}