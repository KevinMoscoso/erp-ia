<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2017-2024 ERPIA Contributors
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

namespace ERPIA\Core\Controller;

use ERPIA\Core\Lib\ExtendedController\ListController;
use ERPIA\Core\Model\CronJob;
use ERPIA\Core\Model\LogMessage;
use ERPIA\Core\Model\WorkEvent;
use ERPIA\Core\App\Translator;
use ERPIA\Core\App\Logger;

/**
 * Controller to list the items in the LogMessage model
 *
 * @author ERPIA Contributors
 */
class ListLogMessage extends ListController
{
    /**
     * Returns page configuration data
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'admin';
        $pageData['title'] = 'logs';
        $pageData['icon'] = 'fa-solid fa-file-medical-alt';
        return $pageData;
    }

    /**
     * Create and configure the views
     */
    protected function createViews()
    {
        $this->createViewsLogs();
        $this->createViewsCronJobs();
        $this->createViewsWorkEvents();
    }

    /**
     * Creates and configures the cron jobs view
     *
     * @param string $viewName
     */
    protected function createViewsCronJobs(string $viewName = 'ListCronJob'): void
    {
        // Add cron jobs view
        $this->addView($viewName, 'CronJob', 'crons', 'fa-solid fa-cogs');
        
        // Configure search fields
        $this->addSearchFields(['jobname', 'pluginname']);
        
        // Configure default order
        $this->addOrderBy(['jobname'], 'job-name');
        $this->addOrderBy(['pluginname'], 'plugin');
        $this->addOrderBy(['date'], 'date');
        $this->addOrderBy(['duration'], 'duration');

        // Filters
        $this->addFilterPeriod($viewName, 'date', 'period', 'date', true);

        // Plugin filter
        $plugins = $this->codeModel->getAll('cronjobs', 'pluginname', 'pluginname');
        $this->addFilterSelect($viewName, 'pluginname', 'plugin', 'pluginname', $plugins);

        // Status filter
        $this->addFilterSelect($viewName, 'enabled', 'status', 'enabled', [
            '' => '------',
            '0' => Translator::trans('disabled'),
            '1' => Translator::trans('enabled'),
        ]);

        // Disable new button
        $this->setSettings($viewName, 'btnNew', false);

        // Add enable and disable buttons
        $this->addButton($viewName, [
            'action' => 'enable-cronjob',
            'color' => 'success',
            'icon' => 'fa-solid fa-check-square',
            'label' => 'enable'
        ]);

        $this->addButton($viewName, [
            'action' => 'disable-cronjob',
            'color' => 'warning',
            'icon' => 'fa-regular fa-square',
            'label' => 'disable'
        ]);
    }

    /**
     * Creates and configures the logs view
     *
     * @param string $viewName
     */
    protected function createViewsLogs(string $viewName = 'ListLogMessage'): void
    {
        // Add logs view
        $this->addView($viewName, 'LogMessage', 'history', 'fa-solid fa-history');
        
        // Configure search fields
        $this->addSearchFields(['context', 'message', 'uri']);
        
        // Configure default order
        $this->addOrderBy(['time', 'id'], 'date', 2);
        $this->addOrderBy(['level'], 'level');
        $this->addOrderBy(['ip'], 'ip');

        // Filters
        // Channel filter
        $channels = $this->codeModel->getAll('logs', 'channel', 'channel');
        $this->addFilterSelect($viewName, 'channel', 'channel', 'channel', $channels);

        // Level filter
        $levels = $this->codeModel->getAll('logs', 'level', 'level');
        $this->addFilterSelect($viewName, 'level', 'level', 'level', $levels);

        // User filter (autocomplete)
        $this->addFilterAutocomplete($viewName, 'nick', 'user', 'nick', 'users');
        
        // IP filter (autocomplete)
        $this->addFilterAutocomplete($viewName, 'ip', 'ip', 'ip', 'logs');

        // URL filter
        $uris = $this->codeModel->getAll('logs', 'uri', 'uri');
        $this->addFilterSelect($viewName, 'url', 'url', 'uri', $uris);

        // Model filter (document type)
        $models = $this->codeModel->getAll('logs', 'model', 'model');
        $this->addFilterSelect($viewName, 'model', 'doc-type', 'model', $models);

        // Period filter
        $this->addFilterPeriod($viewName, 'time', 'period', 'time', true);

        // Disable new button
        $this->setSettings($viewName, 'btnNew', false);

        // Add delete logs button (modal)
        $this->addButton($viewName, [
            'action' => 'delete-logs',
            'color' => 'warning',
            'icon' => 'fa-solid fa-trash-alt',
            'label' => 'delete',
            'type' => 'modal',
        ]);
    }

    /**
     * Creates and configures the work events view
     *
     * @param string $viewName
     */
    protected function createViewsWorkEvents(string $viewName = 'ListWorkEvent'): void
    {
        // Add work events view
        $this->addView($viewName, 'WorkEvent', 'work-events', 'fa-solid fa-calendar-alt');
        
        // Configure default order
        $this->addOrderBy(['creation_date'], 'creation-date');
        $this->addOrderBy(['done_date'], 'date');
        $this->addOrderBy(['id'], 'id', 2);
        
        // Configure search fields
        $this->addSearchFields(['name', 'value']);
        
        // Disable new button
        $this->setSettings($viewName, 'btnNew', false);

        // Filters
        // Status filter
        $this->addFilterSelect($viewName, 'done', 'status', 'done', [
            '' => '------',
            '0' => Translator::trans('pending'),
            '1' => Translator::trans('done'),
        ]);

        // Event name filter
        $events = $this->codeModel->getAll('work_events', 'name', 'name');
        $this->addFilterSelect($viewName, 'name', 'name', 'name', $events);

        // Creation date period filter
        $this->addFilterPeriod($viewName, 'creation_date', 'period', 'creation_date', true);
    }

    /**
     * Delete logs based on filters
     */
    protected function deleteLogsAction(): void
    {
        // Validate form token
        if (!$this->validateFormToken()) {
            return;
        }

        // Check delete permission
        if (!$this->permissions->allowDelete) {
            Logger::warning('not-allowed-delete');
            return;
        }

        // Get filter parameters from request
        $from = $this->request->input('delete_from', '');
        $to = $this->request->input('delete_to', '');
        $channel = $this->request->input('delete_channel', '');

        // Build query using LogMessage model methods
        $query = LogMessage::table()
            ->whereGte('time', $from)
            ->whereLte('time', $to);

        // Cannot delete audit logs
        if ('audit' === $channel) {
            Logger::warning('cant-delete-audit-log');
            return;
        } elseif ($channel !== '') {
            $query->whereEq('channel', $channel);
        } else {
            $query->whereNotEq('channel', 'audit');
        }

        // Execute delete
        if (!$query->delete()) {
            Logger::warning('record-deleted-error');
            return;
        }

        Logger::notice('record-deleted-correctly');
    }

    /**
     * Enable or disable cron jobs
     *
     * @param bool $value
     */
    protected function enableCronJobAction(bool $value): void
    {
        // Validate form token
        if (!$this->validateFormToken()) {
            return;
        }

        // Check user permission
        if (!$this->user->can('EditCronJob', 'update')) {
            Logger::warning('not-allowed-modify');
            return;
        }

        // Get selected codes
        $codes = $this->request->request->getArray('codes');
        if (!is_array($codes)) {
            return;
        }

        // Update each cron job
        foreach ($codes as $code) {
            $cron = new CronJob();
            if (!$cron->loadFromCode($code)) {
                continue;
            }

            $cron->enabled = $value;
            if (!$cron->save()) {
                Logger::warning('record-save-error');
                return;
            }
        }

        Logger::notice('record-updated-correctly');
    }

    /**
     * Execute actions before reading data
     *
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'delete-logs':
                $this->deleteLogsAction();
                break;

            case 'disable-cronjob':
                $this->enableCronJobAction(false);
                break;

            case 'enable-cronjob':
                $this->enableCronJobAction(true);
                break;
        }

        return parent::execPreviousAction($action);
    }
}