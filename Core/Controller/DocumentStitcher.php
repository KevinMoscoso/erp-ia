<?php

namespace ERPIA\Core\Controller;

use ERPIA\Core\Base\Controller;
use ERPIA\Core\Base\ControllerPermissions;
use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Model\Base\TransformerDocument;
use ERPIA\Core\Response;
use ERPIA\Core\SystemTools;
use ERPIA\Dinamic\Lib\BusinessDocumentGenerator;
use ERPIA\Dinamic\Model\CodeModel;
use ERPIA\Dinamic\Model\EstadoDocumento;
use ERPIA\Dinamic\Model\User;

/**
 * Class DocumentStitcher
 * @author ERPIA Team
 */
class DocumentStitcher extends Controller
{
    const MODEL_NAMESPACE = '\\ERPIA\\Dinamic\\Model\\';

    /** @var array */
    public $codes = [];
    
    /** @var TransformerDocument[] */
    public $documents = [];
    
    /** @var string */
    public $modelName;
    
    /** @var TransformerDocument[] */
    public $moreDocuments = [];

    /**
     * Retrieve available statuses for current document type
     * @return array
     */
    public function getAvailableStatus(): array
    {
        $statusList = [];
        $conditions = [
            new DataBaseWhere('activo', true),
            new DataBaseWhere('tipodoc', $this->modelName)
        ];
        
        foreach (EstadoDocumento::all($conditions) as $documentState) {
            if ($documentState->generadoc) {
                $statusList[] = $documentState;
            }
        }
        return $statusList;
    }

    /**
     * Get page metadata
     * @return array
     */
    public function getPageData(): array
    {
        $pageInfo = parent::getPageData();
        $pageInfo['menu'] = 'sales';
        $pageInfo['title'] = 'group-or-split';
        $pageInfo['icon'] = 'fa-solid fa-wand-magic-sparkles';
        $pageInfo['showonmenu'] = false;
        return $pageInfo;
    }

    /**
     * Retrieve all series
     * @return array
     */
    public function getSeries(): array
    {
        return CodeModel::all('series', 'codserie', 'descripcion', false);
    }

    /**
     * Main controller logic
     * @param Response $response
     * @param User $user
     * @param ControllerPermissions $permissions
     */
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        
        $this->codes = $this->fetchDocumentCodes();
        $this->modelName = $this->fetchModelName();
        
        // Prevent grouping/splitting for invoices
        if (in_array($this->modelName, ['FacturaCliente', 'FacturaProveedor'])) {
            $this->redirect('List' . $this->modelName);
            return;
        }
        
        $this->loadSelectedDocuments();
        $this->loadCompatibleDocuments();
        
        $statusParam = $this->request->input('status', '');
        if ($statusParam) {
            // Validate form security token
            if (!$this->validateFormToken()) {
                return;
            }
            
            // Close documents if status starts with 'close:'
            if (strpos($statusParam, 'close:') === 0) {
                $closeStatus = substr($statusParam, 6);
                $this->finalizeDocuments((int)$closeStatus);
            } else {
                $this->createDocumentFromStatus((int)$statusParam);
            }
        }
    }

    /**
     * Add separator line between documents
     * @param array $lineCollection
     * @param TransformerDocument $document
     */
    protected function insertSeparatorLine(array &$lineCollection, $document): void
    {
        $separator = $document->createNewLine([
            'cantidad' => 0,
            'mostrar_cantidad' => false,
            'mostrar_precio' => false
        ]);
        $this->executeHook('insertSeparatorLine', $separator);
        $lineCollection[] = $separator;
    }

    /**
     * Validate and add document to collection
     * @param TransformerDocument $newDocument
     * @return bool
     */
    protected function validateAndAddDocument($newDocument): bool
    {
        foreach ($this->documents as $existingDoc) {
            if ($existingDoc->codalmacen != $newDocument->codalmacen ||
                $existingDoc->coddivisa != $newDocument->coddivisa ||
                $existingDoc->idempresa != $newDocument->idempresa ||
                $existingDoc->dtopor1 != $newDocument->dtopor1 ||
                $existingDoc->dtopor2 != $newDocument->dtopor2 ||
                $existingDoc->subjectColumnValue() != $newDocument->subjectColumnValue()) {
                SystemTools::log()->warning('incompatible-document-detected', ['%code%' => $newDocument->codigo]);
                return false;
            }
        }
        $this->documents[] = $newDocument;
        return true;
    }

    /**
     * Add informational line about source document
     * @param array $lineCollection
     * @param TransformerDocument $document
     */
    protected function insertInfoLine(array &$lineCollection, $document): void
    {
        $info = $document->createNewLine([
            'cantidad' => 0,
            'descripcion' => $this->generateDocumentDescription($document),
            'mostrar_cantidad' => false,
            'mostrar_precio' => false
        ]);
        $this->executeHook('insertInfoLine', $info);
        $lineCollection[] = $info;
    }

    /**
     * Process lines for partial document generation
     * @param TransformerDocument $document
     * @param array $documentLines
     * @param array $newLineCollection
     * @param array $quantityMap
     * @param int $targetStatus
     */
    protected function processPartialLines(&$document, &$documentLines, &$newLineCollection, &$quantityMap, $targetStatus): void
    {
        $completeDocument = true;
        
        foreach ($documentLines as $line) {
            $approvedQty = (float)$this->request->input('approve_quant_' . $line->id(), '0');
            $quantityMap[$line->id()] = $approvedQty;
            
            if (empty($approvedQty) && $line->cantidad) {
                $completeDocument = $completeDocument && $line->servido >= $line->cantidad;
                continue;
            } elseif (($approvedQty + $line->servido) < $line->cantidad) {
                $completeDocument = false;
            }
            
            $this->executeHook('processPartialLines', $line);
            $newLineCollection[] = $line;
        }
        
        if ($completeDocument) {
            $document->disableGenerationMode();
            $document->idestado = $targetStatus;
            if (!$document->save()) {
                $this->database->rollback();
                SystemTools::log()->error('save-operation-failed');
                return;
            }
        }
        
        // Update lines with approved quantities
        foreach ($document->getLines() as $line) {
            $line->servido += $quantityMap[$line->id()];
            if (!$line->save()) {
                $this->database->rollback();
                SystemTools::log()->error('save-operation-failed');
                return;
            }
        }
    }

    /**
     * Close documents with specified status
     * @param int $targetStatus
     */
    protected function finalizeDocuments(int $targetStatus): void
    {
        $this->database->beginTransaction();
        
        foreach ($this->documents as $document) {
            $document->disableGenerationMode();
            $document->idestado = $targetStatus;
            if (!$document->save()) {
                $this->database->rollback();
                SystemTools::log()->error('save-operation-failed');
                return;
            }
        }
        
        $this->database->commit();
        SystemTools::log()->notice('documents-finalized-successfully');
    }

    /**
     * Create new document from selected status
     * @param int $targetStatus
     */
    protected function createDocumentFromStatus(int $targetStatus): void
    {
        $this->database->beginTransaction();
        
        $newLines = [];
        $extraParams = ['fecha' => $this->request->input('fecha', '')];
        $templateDocument = null;
        $quantityMapping = [];
        
        foreach ($this->documents as $document) {
            $lines = $document->getLines();
            
            if ($templateDocument === null) {
                $templateDocument = clone $document;
                $templateDocument->codserie = $this->request->input('codserie', $document->codserie);
            } elseif ($this->request->input('extralines', '') === 'true' && !empty($lines)) {
                $this->insertSeparatorLine($newLines, $document);
            }
            
            if ($this->request->input('extralines', '') === 'true' && !empty($lines)) {
                $this->insertInfoLine($newLines, $document);
            }
            
            $this->processPartialLines($document, $lines, $newLines, $quantityMapping, $targetStatus);
        }
        
        if ($templateDocument === null || empty($newLines)) {
            $this->database->rollback();
            return;
        }
        
        // Allow plugins to modify template before generation
        if (!$this->executeHook('validateTemplate', $templateDocument, $newLines)) {
            $this->database->rollback();
            return;
        }
        
        $generator = new BusinessDocumentGenerator();
        $outputClass = $this->determineOutputClass($targetStatus);
        
        if (empty($outputClass)) {
            $this->database->rollback();
            return;
        }
        
        if (!$generator->generate($templateDocument, $outputClass, $newLines, $quantityMapping, $extraParams)) {
            $this->database->rollback();
            SystemTools::log()->error('generation-failed');
            return;
        }
        
        $this->database->commit();
        
        // Redirect to first generated document
        foreach ($generator->getLastDocs() as $generatedDoc) {
            $this->redirect($generatedDoc->url());
            SystemTools::log()->notice('document-created-successfully');
            break;
        }
    }

    /**
     * Extract document codes from request
     * @return array
     */
    protected function fetchDocumentCodes(): array
    {
        $codeArray = $this->request->request->getArray('codes');
        if ($codeArray) {
            return $codeArray;
        }
        
        $codeString = $this->request->queryOrInput('codes', '');
        $codeList = explode(',', $codeString);
        $additionalCodes = $this->request->request->getArray('newcodes');
        
        return empty($additionalCodes) ? $codeList : array_merge($codeList, $additionalCodes);
    }

    /**
     * Generate description line for source document
     * @param TransformerDocument $document
     * @return string
     */
    protected function generateDocumentDescription($document): string
    {
        $description = SystemTools::translate($document->modelClassName() . '-min') . ' ' . $document->codigo;
        
        if (isset($document->numero2) && $document->numero2) {
            $description .= ' (' . $document->numero2 . ')';
        } elseif (isset($document->numproveedor) && $document->numproveedor) {
            $description .= ' (' . $document->numproveedor . ')';
        }
        
        $description .= ', ' . $document->fecha . "\n--------------------";
        return $description;
    }

    /**
     * Determine class to generate based on status
     * @param int $targetStatus
     * @return string|null
     */
    protected function determineOutputClass(int $targetStatus): ?string
    {
        $status = new EstadoDocumento();
        $status->load($targetStatus);
        return $status->generadoc;
    }

    /**
     * Extract model name from request
     * @return string
     */
    protected function fetchModelName(): string
    {
        return $this->request->inputOrQuery('model', '');
    }

    /**
     * Load selected documents
     */
    protected function loadSelectedDocuments(): void
    {
        if (empty($this->codes) || empty($this->modelName)) {
            return;
        }
        
        $modelClass = self::MODEL_NAMESPACE . $this->modelName;
        
        foreach ($this->codes as $code) {
            $document = new $modelClass();
            if ($document->loadFromCode($code)) {
                $this->validateAndAddDocument($document);
            }
        }
        
        // Sort by date and time
        uasort($this->documents, function ($docA, $docB) {
            $timeA = strtotime($docA->fecha . ' ' . $docA->hora);
            $timeB = strtotime($docB->fecha . ' ' . $docB->hora);
            
            if ($timeA > $timeB) return 1;
            if ($timeA < $timeB) return -1;
            return 0;
        });
    }

    /**
     * Load additional compatible documents
     */
    protected function loadCompatibleDocuments(): void
    {
        if (empty($this->documents) || empty($this->modelName)) {
            return;
        }
        
        $modelClass = self::MODEL_NAMESPACE . $this->modelName;
        $modelInstance = new $modelClass();
        $firstDoc = $this->documents[0];
        
        $conditions = [
            new DataBaseWhere('codalmacen', $firstDoc->codalmacen),
            new DataBaseWhere('coddivisa', $firstDoc->coddivisa),
            new DataBaseWhere('codserie', $firstDoc->codserie),
            new DataBaseWhere('dtopor1', $firstDoc->dtopor1),
            new DataBaseWhere('dtopor2', $firstDoc->dtopor2),
            new DataBaseWhere('editable', true),
            new DataBaseWhere('idempresa', $firstDoc->idempresa),
            new DataBaseWhere($modelInstance->subjectColumn(), $firstDoc->subjectColumnValue())
        ];
        
        $ordering = ['fecha' => 'ASC', 'hora' => 'ASC'];
        $compatibleDocs = $modelInstance->all($conditions, $ordering, 0, 0);
        
        foreach ($compatibleDocs as $document) {
            if (!in_array($document->id(), $this->fetchDocumentCodes())) {
                $this->moreDocuments[] = $document;
            }
        }
    }
}