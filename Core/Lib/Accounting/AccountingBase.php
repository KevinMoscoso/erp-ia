<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2025 ERPIA Development Team
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

namespace ERPIA\Core\Lib\Accounting;

use ERPIA\Core\Base\DataBase;
use ERPIA\Dinamic\Model\Ejercicio;

/**
 * Base class for accounting reports.
 */
abstract class AccountingBase
{
    /**
     * Database connection.
     *
     * @var DataBase
     */
    protected $database;

    /**
     * Start date for the report.
     *
     * @var string
     */
    protected $startDate;

    /**
     * End date for the report.
     *
     * @var string
     */
    protected $endDate;

    /**
     * Current fiscal exercise.
     *
     * @var Ejercicio
     */
    protected $exercise;

    /**
     * Generate the accounting report for the specified date range.
     *
     * @param string $dateFrom Start date
     * @param string $dateTo End date
     * @param array $params Additional parameters
     */
    abstract public function generate(string $dateFrom, string $dateTo, array $params = []);

    /**
     * Retrieve the accounting data for the report.
     */
    abstract protected function getData();

    public function __construct()
    {
        $this->database = new DataBase();
        $this->exercise = new Ejercicio();
    }

    /**
     * Set the fiscal exercise by its code.
     *
     * @param string $code
     */
    public function setExercise($code): void
    {
        $this->exercise->loadByCode($code);
    }

    /**
     * Set the fiscal exercise based on company and date.
     *
     * @param int $companyId
     * @param string $date
     * @return bool
     */
    public function setExerciseFromDate($companyId, $date): bool
    {
        $this->exercise->idempresa = $companyId;
        return $this->exercise->loadFromDate($date, false, false);
    }

    /**
     * Add a time interval to a date.
     *
     * @param string $date Original date
     * @param string $interval Interval to add
     * @return string
     */
    protected function addToDate($date, $interval)
    {
        return date('d-m-Y', strtotime($interval, strtotime($date)));
    }
}