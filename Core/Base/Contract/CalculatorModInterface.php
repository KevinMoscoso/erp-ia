<?php
namespace ERPIA\Core\Base\Contract;

use ERPIA\Core\Model\Base\BusinessDocument;
use ERPIA\Core\Model\Base\BusinessDocumentLine;

interface CalculatorModInterface
{
    /**
     * Applies modifications to the document and its lines
     * 
     * @param BusinessDocument $doc
     * @param BusinessDocumentLine[] $lines
     * @return bool
     */
    public function apply(BusinessDocument &$doc, array &$lines): bool;

    /**
     * Performs complete calculations for the document
     * 
     * @param BusinessDocument $doc
     * @param BusinessDocumentLine[] $lines
     * @return bool
     */
    public function calculate(BusinessDocument &$doc, array &$lines): bool;

    /**
     * Executes specific calculations for a single line
     * 
     * @param BusinessDocument $doc
     * @param BusinessDocumentLine $line
     * @return bool
     */
    public function calculateLine(BusinessDocument $doc, BusinessDocumentLine &$line): bool;

    /**
     * Clears or resets calculated values
     * 
     * @param BusinessDocument $doc
     * @param BusinessDocumentLine[] $lines
     * @return bool
     */
    public function clear(BusinessDocument &$doc, array &$lines): bool;

    /**
     * Retrieves and modifies custom subtotals
     * 
     * @param array $subtotals
     * @param BusinessDocument $doc
     * @param BusinessDocumentLine[] $lines
     * @return bool
     */
    public function getSubtotals(array &$subtotals, BusinessDocument $doc, array $lines): bool;
}