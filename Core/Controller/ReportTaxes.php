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
use ERPIA\Core\DataSrc\Divisas;
use ERPIA\Core\DataSrc\Paises;
use ERPIA\Core\Response;
use ERPIA\Core\Translator;
use ERPIA\Core\Utility\Formatter;
use ERPIA\Core\Utility\DateHelper;
use ERPIA\Core\Config;
use ERPIA\Dinamic\Lib\ExportManager;
use ERPIA\Dinamic\Lib\InvoiceOperation;
use ERPIA\Dinamic\Model\Divisa;
use ERPIA\Dinamic\Model\Pais;
use ERPIA\Dinamic\Model\Serie;
use ERPIA\Dinamic\Model\User;

/**
 * Controlador para generar informes detallados de impuestos
 * 
 * Genera informes de IVA, recargo de equivalencia e IRPF
 * para ventas y compras con validación de datos y exportación.
 */
class ReportTaxes extends Controller
{
    const MAX_TOTAL_DIFF = 0.05;

    /** @var string */
    public $coddivisa;

    /** @var string */
    public $codpais;

    /** @var string */
    public $codserie;

    /** @var array */
    protected $columns = [];

    /** @var string */
    public $datefrom;

    /** @var string */
    public $dateto;

    /** @var Divisa */
    public $divisa;

    /** @var string */
    public $format;

    /** @var int */
    public $idempresa;

    /** @var Pais */
    public $pais;

    /** @var Serie */
    public $serie;

    /** @var string */
    public $source;

    /** @var string */
    public $typeDate;

    /**
     * Obtiene los metadatos de la página
     * 
     * @return array Configuración de menú, título e icono
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'taxes';
        $pageData['menu'] = 'reports';
        $pageData['icon'] = 'fa-solid fa-wallet';
        
        return $pageData;
    }

    /**
     * Ejecuta la lógica privada del controlador
     * 
     * @param Response $response
     * @param User $user
     * @param ControllerPermissions $permissions
     */
    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);
        
        $this->divisa = new Divisa();
        $this->pais = new Pais();
        $this->serie = new Serie();
        
        $this->initFilters();
        $this->initColumns();
        
        if ('export' === $this->request->input('action')) {
            $this->exportAction();
        }
    }

    /**
     * Procesa la acción de exportación del informe
     */
    protected function exportAction(): void
    {
        if (empty($this->columns)) {
            return;
        }

        $data = $this->getReportData();
        if (empty($data)) {
            Translator::log()->warning('no-data');
            return;
        }

        $lastCode = '';
        $lines = [];
        $translator = Translator::getInstance();
        $formatter = Formatter::getInstance();

        foreach ($data as $row) {
            $hide = $row['codigo'] === $lastCode && $this->format === 'PDF';
            
            if ($this->source === 'sales') {
                $number2title = $translator->trans('number2');
                $number2value = $hide ? '' : $row['numero2'];
            } else {
                $number2title = $translator->trans('numsupplier');
                $number2value = $hide ? '' : $row['numproveedor'];
            }

            $lines[] = [
                $translator->trans('serie') => $hide ? '' : $row['codserie'],
                $translator->trans('code') => $hide ? '' : $row['codigo'],
                $number2title => $number2value,
                $translator->trans('date') => $hide ? '' : DateHelper::format($row['fecha']),
                $translator->trans('name') => $hide ? '' : htmlspecialchars($row['nombre']),
                $translator->trans('cifnif') => $hide ? '' : $row['cifnif'],
                $translator->trans('country') => $hide ? '' : ($row['codpais'] ? Paises::get($row['codpais'])->nombre : ''),
                $translator->trans('net') => $this->exportFieldFormat('number', $row['neto']),
                $translator->trans('pct-tax') => $this->exportFieldFormat('number', $row['iva']),
                $translator->trans('tax') => $this->exportFieldFormat('number', $row['totaliva']),
                $translator->trans('pct-surcharge') => $this->exportFieldFormat('number', $row['recargo']),
                $translator->trans('surcharge') => $this->exportFieldFormat('number', $row['totalrecargo']),
                $translator->trans('pct-irpf') => $this->exportFieldFormat('number', $row['irpf']),
                $translator->trans('irpf') => $this->exportFieldFormat('number', $row['totalirpf']),
                $translator->trans('supplied-amount') => $this->exportFieldFormat('number', $row['suplidos']),
                $translator->trans('total') => $hide ? '' : $this->exportFieldFormat('number', $row['total'])
            ];
            
            $lastCode = $row['codigo'];
        }

        $totalsData = $this->getTotals($data);
        if (false === $this->validateTotals($totalsData)) {
            return;
        }

        $totals = [];
        foreach ($totalsData as $row) {
            $total = $row['neto'] + $row['totaliva'] + $row['totalrecargo'] - $row['totalirpf'] - $row['suplidos'];
            $totals[] = [
                $translator->trans('net') => $this->exportFieldFormat('number', $row['neto']),
                $translator->trans('pct-tax') => $this->exportFieldFormat('percentage', $row['iva']),
                $translator->trans('tax') => $this->exportFieldFormat('number', $row['totaliva']),
                $translator->trans('pct-surcharge') => $this->exportFieldFormat('percentage', $row['recargo']),
                $translator->trans('surcharge') => $this->exportFieldFormat('number', $row['totalrecargo']),
                $translator->trans('pct-irpf') => $this->exportFieldFormat('percentage', $row['irpf']),
                $translator->trans('irpf') => $this->exportFieldFormat('number', $row['totalirpf']),
                $translator->trans('supplied-amount') => $this->exportFieldFormat('number', $row['suplidos']),
                $translator->trans('total') => $this->exportFieldFormat('number', $total)
            ];
        }

        $this->setTemplate(false);
        $this->processLayout($lines, $totals);
    }

    /**
     * Formatea un campo para exportación
     * 
     * @param string $format Tipo de formato (number, percentage)
     * @param string $value Valor a formatear
     * @return string Valor formateado
     */
    protected function exportFieldFormat(string $format, string $value): string
    {
        $formatter = Formatter::getInstance();
        
        switch ($format) {
            case 'number':
                return $this->format === 'PDF' ? $formatter->number($value) : $value;
            case 'percentage':
                return $this->format === 'PDF' ? $formatter->number($value) . ' %' : $value;
            default:
                return $value;
        }
    }

    /**
     * Obtiene la fecha de inicio o fin del trimestre actual/anterior
     * 
     * @param bool $start True para inicio, false para fin
     * @return string Fecha en formato Y-m-d
     */
    protected function getQuarterDate(bool $start): string
    {
        $month = (int)date('m');
        
        if ($month === 1) {
            return $start ?
                date('Y-10-01', strtotime('-1 year')) :
                date('Y-12-31', strtotime('-1 year'));
        }
        
        if ($month >= 1 && $month <= 4) {
            return $start ? date('Y-01-01') : date('Y-03-31');
        }
        
        if ($month >= 4 && $month <= 7) {
            return $start ? date('Y-04-01') : date('Y-06-30');
        }
        
        if ($month >= 7 && $month <= 10) {
            return $start ? date('Y-07-01') : date('Y-09-30');
        }
        
        return $start ? date('Y-10-01') : date('Y-12-31');
    }

    /**
     * Obtiene los datos del informe según los filtros aplicados
     * 
     * @return array Datos agrupados del informe
     */
    protected function getReportData(): array
    {
        $sql = '';
        $config = Config::getInstance();
        $dbType = $config->get('db_type');
        $numCol = strtolower($dbType) == 'postgresql' ? 'CAST(f.numero as integer)' : 'CAST(f.numero as unsigned)';
        $columnDate = $this->typeDate === 'create' ? 'f.fecha' : 'COALESCE(f.fechadevengo, f.fecha)';
        
        switch ($this->source) {
            case 'purchases':
                $sql .= 'SELECT f.codserie, f.codigo, f.numproveedor, f.fecha, f.fechadevengo, f.nombre, f.cifnif, l.pvptotal,'
                    . ' l.iva, l.recargo, l.irpf, l.suplido, f.dtopor1, f.dtopor2, f.total, f.operacion'
                    . ' FROM lineasfacturasprov AS l'
                    . ' LEFT JOIN facturasprov AS f ON l.idfactura = f.idfactura '
                    . ' WHERE f.idempresa = ' . $this->dataBase->var2str($this->idempresa)
                    . ' AND ' . $columnDate . ' >= ' . $this->dataBase->var2str($this->datefrom)
                    . ' AND ' . $columnDate . ' <= ' . $this->dataBase->var2str($this->dateto)
                    . ' AND (l.pvptotal <> 0.00 OR l.iva <> 0.00)'
                    . ' AND f.coddivisa = ' . $this->dataBase->var2str($this->coddivisa);
                break;
                
            case 'sales':
                $sql .= 'SELECT f.codserie, f.codigo, f.numero2, f.fecha, f.fechadevengo, f.nombrecliente AS nombre, f.cifnif, l.pvptotal,'
                    . ' l.iva, l.recargo, l.irpf, l.suplido, f.dtopor1, f.dtopor2, f.total, f.operacion, f.codpais'
                    . ' FROM lineasfacturascli AS l'
                    . ' LEFT JOIN facturascli AS f ON l.idfactura = f.idfactura '
                    . ' WHERE f.idempresa = ' . $this->dataBase->var2str($this->idempresa)
                    . ' AND ' . $columnDate . ' >= ' . $this->dataBase->var2str($this->datefrom)
                    . ' AND ' . $columnDate . ' <= ' . $this->dataBase->var2str($this->dateto)
                    . ' AND (l.pvptotal <> 0.00 OR l.iva <> 0.00)'
                    . ' AND f.coddivisa = ' . $this->dataBase->var2str($this->coddivisa);
                if ($this->codpais) {
                    $sql .= ' AND codpais = ' . $this->dataBase->var2str($this->codpais);
                }
                break;
                
            default:
                Translator::log()->warning('wrong-source');
                return [];
        }
        
        if ($this->codserie) {
            $sql .= ' AND codserie = ' . $this->dataBase->var2str($this->codserie);
        }
        
        $sql .= ' ORDER BY ' . $columnDate . ', ' . $numCol . ' ASC;';
        
        $data = [];
        foreach ($this->dataBase->select($sql) as $row) {
            $pvpTotal = floatval($row['pvptotal']) * (100 - floatval($row['dtopor1'])) * (100 - floatval($row['dtopor2'])) / 10000;
            $code = $row['codigo'] . '-' . $row['iva'] . '-' . $row['recargo'] . '-' . $row['irpf'] . '-' . $row['suplido'];
            
            if (isset($data[$code])) {
                $data[$code]['neto'] += $row['suplido'] ? 0 : $pvpTotal;
                $data[$code]['totaliva'] += $row['suplido'] || $row['operacion'] === InvoiceOperation::INTRA_COMMUNITY ? 0 : (float)$row['iva'] * $pvpTotal / 100;
                $data[$code]['totalrecargo'] += $row['suplido'] ? 0 : (float)$row['recargo'] * $pvpTotal / 100;
                $data[$code]['totalirpf'] += $row['suplido'] ? 0 : (float)$row['irpf'] * $pvpTotal / 100;
                $data[$code]['suplidos'] += $row['suplido'] ? $pvpTotal : 0;
                continue;
            }
            
            $data[$code] = [
                'codpais' => $row['codpais'] ?? null,
                'codserie' => $row['codserie'],
                'codigo' => $row['codigo'],
                'numero2' => $row['numero2'],
                'numproveedor' => $row['numproveedor'],
                'fecha' => $this->typeDate == 'create' ?
                    $row['fecha'] :
                    $row['fechadevengo'] ?? $row['fecha'],
                'nombre' => $row['nombre'],
                'cifnif' => $row['cifnif'],
                'neto' => $row['suplido'] ? 0 : $pvpTotal,
                'iva' => $row['suplido'] ? 0 : (float)$row['iva'],
                'totaliva' => $row['suplido'] || $row['operacion'] === InvoiceOperation::INTRA_COMMUNITY ? 0 : (float)$row['iva'] * $pvpTotal / 100,
                'recargo' => $row['suplido'] ? 0 : (float)$row['recargo'],
                'totalrecargo' => $row['suplido'] ? 0 : (float)$row['recargo'] * $pvpTotal / 100,
                'irpf' => $row['suplido'] ? 0 : (float)$row['irpf'],
                'totalirpf' => $row['suplido'] ? 0 : (float)$row['irpf'] * $pvpTotal / 100,
                'suplidos' => $row['suplido'] ? $pvpTotal : 0,
                'total' => (float)$row['total']
            ];
        }
        
        $nf0 = $config->get('default.decimals', 2);
        foreach ($data as $key => $value) {
            $data[$key]['neto'] = round($value['neto'], $nf0);
            $data[$key]['totaliva'] = round($value['totaliva'], $nf0);
            $data[$key]['totalrecargo'] = round($value['totalrecargo'], $nf0);
            $data[$key]['totalirpf'] = round($value['totalirpf'], $nf0);
            $data[$key]['suplidos'] = round($value['suplidos'], $nf0);
        }
        
        return $data;
    }

    /**
     * Agrupa totales por combinación de impuestos
     * 
     * @param array $data Datos del informe
     * @return array Totales agrupados
     */
    protected function getTotals(array $data): array
    {
        $totals = [];
        
        foreach ($data as $row) {
            $code = $row['iva'] . '-' . $row['recargo'] . '-' . $row['irpf'];
            
            if (isset($totals[$code])) {
                $totals[$code]['neto'] += $row['neto'];
                $totals[$code]['totaliva'] += $row['totaliva'];
                $totals[$code]['totalrecargo'] += $row['totalrecargo'];
                $totals[$code]['totalirpf'] += $row['totalirpf'];
                $totals[$code]['suplidos'] += $row['suplidos'];
                continue;
            }
            
            $totals[$code] = [
                'neto' => $row['neto'],
                'iva' => $row['iva'],
                'totaliva' => $row['totaliva'],
                'recargo' => $row['recargo'],
                'totalrecargo' => $row['totalrecargo'],
                'irpf' => $row['irpf'],
                'totalirpf' => $row['totalirpf'],
                'suplidos' => $row['suplidos']
            ];
        }
        
        return $totals;
    }

    /**
     * Inicializa las columnas seleccionadas desde el request
     */
    protected function initColumns(): void
    {
        $translator = Translator::getInstance();
        
        foreach ($this->request->request->all() as $key => $value) {
            if (strpos($key, 'column_') === 0) {
                $column = substr($key, 7);
                $column = str_replace('_', '-', $column);
                $column = $translator->trans($column);
                
                if (!in_array($column, $this->columns)) {
                    $this->columns[] = $column;
                }
            }
        }
    }

    /**
     * Inicializa los filtros desde el request
     */
    protected function initFilters(): void
    {
        $config = Config::getInstance();
        
        $this->coddivisa = $this->request->input(
            'coddivisa',
            $config->get('default.coddivisa')
        );
        $this->codpais = $this->request->input('codpais', '');
        $this->codserie = $this->request->input('codserie', '');
        $this->datefrom = $this->request->input('datefrom', $this->getQuarterDate(true));
        $this->dateto = $this->request->input('dateto', $this->getQuarterDate(false));
        $this->idempresa = (int)$this->request->input(
            'idempresa',
            $config->get('default.idempresa')
        );
        $this->format = $this->request->input('format');
        $this->source = $this->request->input('source');
        $this->typeDate = $this->request->input('type-date');
    }

    /**
     * Procesa el layout de exportación usando ExportManager
     * 
     * @param array $lines Líneas del informe
     * @param array $totals Totales del informe
     */
    protected function processLayout(array &$lines, array &$totals): void
    {
        $exportManager = new ExportManager();
        $exportManager->setOrientation('landscape');
        
        $translator = Translator::getInstance();
        $exportManager->newDoc($this->format, $translator->trans('taxes'));
        $exportManager->setCompany($this->idempresa);
        
        $dateHelper = DateHelper::getInstance();
        
        $exportManager->addTablePage(
            [
                $translator->trans('report'),
                $translator->trans('currency'),
                $translator->trans('date'),
                $translator->trans('from-date'),
                $translator->trans('until-date')
            ],
            [
                [
                    $translator->trans('report') => $translator->trans('taxes') . ' ' . $translator->trans($this->source),
                    $translator->trans('currency') => Divisas::get($this->coddivisa)->descripcion,
                    $translator->trans('date') => $translator->trans($this->typeDate === 'create' ? 'creation-date' : 'accrual-date'),
                    $translator->trans('from-date') => $dateHelper->format($this->datefrom),
                    $translator->trans('until-date') => $dateHelper->format($this->dateto)
                ]
            ]
        );
        
        $options = [
            $translator->trans('net') => ['display' => 'right'],
            $translator->trans('pct-tax') => ['display' => 'right'],
            $translator->trans('tax') => ['display' => 'right'],
            $translator->trans('pct-surcharge') => ['display' => 'right'],
            $translator->trans('surcharge') => ['display' => 'right'],
            $translator->trans('pct-irpf') => ['display' => 'right'],
            $translator->trans('irpf') => ['display' => 'right'],
            $translator->trans('supplied-amount') => ['display' => 'right'],
            $translator->trans('total') => ['display' => 'right']
        ];
        
        $this->reduceLines($lines);
        $headers = empty($lines) ? [] : array_keys(end($lines));
        $exportManager->addTablePage($headers, $lines, $options);
        
        $headTotals = empty($totals) ? [] : array_keys(end($totals));
        $exportManager->addTablePage($headTotals, $totals, $options);
        
        if (ob_get_length()) {
            ob_end_clean();
        }
        
        $exportManager->show($this->response);
    }

    /**
     * Reduce las líneas eliminando columnas no seleccionadas
     * 
     * @param array $lines Líneas del informe
     */
    protected function reduceLines(array &$lines): void
    {
        foreach ($lines as $key => $line) {
            foreach ($line as $column => $value) {
                if (!in_array($column, $this->columns)) {
                    unset($lines[$key][$column]);
                }
            }
        }
    }

    /**
     * Valida los totales calculados contra la base de datos
     * 
     * @param array $totalsData Totales calculados
     * @return bool True si los totales son válidos
     */
    protected function validateTotals(array $totalsData): bool
    {
        $neto = $totalIva = $totalRecargo = 0.0;
        
        foreach ($totalsData as $row) {
            $neto += $row['neto'];
            $totalIva += $row['totaliva'];
            $totalRecargo += $row['totalrecargo'];
        }
        
        $neto2 = $totalIva2 = $totalRecargo2 = 0.0;
        $tableName = $this->source === 'sales' ? 'facturascli' : 'facturasprov';
        $columnDate = $this->typeDate === 'create' ? 'fecha' : 'COALESCE(fechadevengo, fecha)';
        
        $sql = 'SELECT SUM(neto) as neto, SUM(totaliva) as t1, SUM(totalrecargo) as t2'
            . ' FROM ' . $tableName
            . ' WHERE idempresa = ' . $this->dataBase->var2str($this->idempresa)
            . ' AND ' . $columnDate . ' >= ' . $this->dataBase->var2str($this->datefrom)
            . ' AND ' . $columnDate . ' <= ' . $this->dataBase->var2str($this->dateto)
            . ' AND coddivisa = ' . $this->dataBase->var2str($this->coddivisa);
        
        if ($this->codserie) {
            $sql .= ' AND codserie = ' . $this->dataBase->var2str($this->codserie);
        }
        
        if ($this->codpais && $this->source === 'sales') {
            $sql .= ' AND codpais = ' . $this->dataBase->var2str($this->codpais);
        }
        
        foreach ($this->dataBase->selectLimit($sql) as $row) {
            $neto2 += (float)$row['neto'];
            $totalIva2 += (float)$row['t1'];
            $totalRecargo2 += (float)$row['t2'];
        }
        
        $result = true;
        
        if (abs($neto - $neto2) > self::MAX_TOTAL_DIFF) {
            Translator::log()->error('calculated-net-diff', ['%net%' => $neto, '%net2%' => $neto2]);
            $result = false;
        }
        
        if (abs($totalIva - $totalIva2) > self::MAX_TOTAL_DIFF) {
            Translator::log()->error('calculated-tax-diff', ['%tax%' => $totalIva, '%tax2%' => $totalIva2]);
            $result = false;
        }
        
        if (abs($totalRecargo - $totalRecargo2) > self::MAX_TOTAL_DIFF) {
            Translator::log()->error('calculated-surcharge-diff', [
                '%surcharge%' => $totalRecargo,
                '%surcharge2%' => $totalRecargo2
            ]);
            $result = false;
        }
        
        return $result;
    }
}