<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2020-2025 ERPIA Contributors
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

namespace ERPIA\Core\Base;

use ERPIA\Core\Base\DataBase\DataBaseWhere;
use ERPIA\Core\Config;
use ERPIA\Dinamic\Model\ShippingAgency;
use ERPIA\Dinamic\Model\PaymentMethod;
use ERPIA\Dinamic\Model\LogMessage;
use ERPIA\Dinamic\Model\Series;

/**
 * Database migrations management
 *
 * @author ERPIA Contributors
 */
final class Migrations
{
    /** @var DataBase */
    private static $database;

    /**
     * Execute all pending migrations
     */
    public static function run(): void
    {
        self::cleanupOldLogs();
        self::fixShippingAgencies();
        self::fixPaymentMethods();
        self::fixRectifiedInvoices();
        self::updateSeries();
    }

    /**
     * Clean up old log entries for performance
     */
    private static function cleanupOldLogs(): void
    {
        $logModel = new LogMessage();
        $where = [new DataBaseWhere('channel', 'system')];
        if ($logModel->count($where) < 20000) {
            return;
        }

        // Remove old system logs to maintain performance
        $cutoffDate = date("Y-m-d H:i:s", strtotime("-1 month"));
        $sql = "DELETE FROM system_logs WHERE channel = 'system' AND timestamp < '" . $cutoffDate . "';";
        self::db()->execute($sql);
    }

    /**
     * Get database instance
     */
    private static function db(): DataBase
    {
        if (self::$database === null) {
            self::$database = new DataBase();
        }

        return self::$database;
    }

    /**
     * Fix shipping agency references
     */
    private static function fixShippingAgencies(): void
    {
        // Force table verification
        new ShippingAgency();

        // Clean up references to non-existent shipping agencies
        $tables = ['delivery_notes_cust', 'invoices_cust', 'orders_cust', 'quotations_cust'];
        foreach ($tables as $table) {
            if (!self::db()->tableExists($table)) {
                continue;
            }

            $sql = "UPDATE " . $table . " SET shipping_agency_id = NULL 
                    WHERE shipping_agency_id IS NOT NULL
                    AND shipping_agency_id NOT IN (SELECT id FROM shipping_agencies);";

            self::db()->execute($sql);
        }
    }

    /**
     * Fix invalid payment method references
     */
    private static function fixPaymentMethods(): void
    {
        // Force table verification
        new PaymentMethod();

        // Check document tables for invalid payment method references
        $documentTables = [
            'delivery_notes_cust', 'delivery_notes_supp', 'invoices_cust', 'invoices_supp',
            'orders_cust', 'orders_supp', 'quotations_cust', 'quotations_supp'
        ];

        foreach ($documentTables as $table) {
            if (!self::db()->tableExists($table)) {
                continue;
            }

            // Find payment methods not in payment_methods table
            $sql = "SELECT DISTINCT payment_method FROM " . $table . 
                   " WHERE payment_method NOT IN (SELECT code FROM payment_methods);";
            
            foreach (self::db()->select($sql) as $row) {
                $paymentMethod = new PaymentMethod();
                $paymentMethod->active = false;
                $paymentMethod->code = $row['payment_method'];
                $paymentMethod->description = Config::trans('deleted-item');
                
                if ($paymentMethod->save()) {
                    continue;
                }

                // Fallback SQL insertion if save fails
                $sql = "INSERT INTO " . PaymentMethod::tableName() . " (code, description, active) VALUES (" .
                    self::db()->valueToSql($paymentMethod->code) . ", " .
                    self::db()->valueToSql($paymentMethod->description) . ", " .
                    self::db()->valueToSql($paymentMethod->active) . ");";
                self::db()->execute($sql);
            }
        }
    }

    /**
     * Fix rectified invoice references
     */
    private static function fixRectifiedInvoices(): void
    {
        // Set rectified_invoice_id to null for non-existent invoices
        foreach (['invoices_cust', 'invoices_supp'] as $table) {
            if (!self::db()->tableExists($table)) {
                continue;
            }

            $sql = "UPDATE " . $table . " SET rectified_invoice_id = NULL
                    WHERE rectified_invoice_id IS NOT NULL
                    AND rectified_invoice_id NOT IN (
                        SELECT invoice_id FROM (SELECT invoice_id FROM " . $table . ") AS subquery
                    );";

            self::db()->execute($sql);
        }
    }

    /**
     * Update series configuration
     */
    private static function updateSeries(): void
    {
        // Force table verification
        new Series();

        // Update series type to 'R' for rectification series from configuration
        $rectificationSeries = Config::get('default', 'rectification_series', '');
        if (empty($rectificationSeries)) {
            return;
        }

        $sql = "UPDATE series SET type = 'R' WHERE code = " . self::db()->valueToSql($rectificationSeries) . ";";
        self::db()->execute($sql);
    }
}