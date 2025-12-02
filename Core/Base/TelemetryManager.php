<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2019-2025 ERPIA Contributors
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

use ERPIA\Core\Http;
use ERPIA\Core\Kernel;
use ERPIA\Core\Plugins;
use ERPIA\Core\Config;

/**
 * This class allow sending telemetry data to the master server,
 * ONLY if the user has registered this installation.
 *
 * @author ERPIA Contributors
 */
final class TelemetryManager
{
    const TELEMETRY_ENDPOINT = 'https://erpia.org/Telemetry';
    const UPDATE_FREQUENCY = 604800; // Weekly update in seconds

    /** @var int */
    private $installationId;

    /** @var int */
    private $lastUpdateTime;

    /** @var string */
    private $signatureKey;

    public function __construct()
    {
        $this->installationId = (int) Config::get('telemetry', 'installation_id', 0);
        $this->lastUpdateTime = (int) Config::get('telemetry', 'last_update', 0);
        $this->signatureKey = Config::get('telemetry', 'signature_key', '');

        // Check for telemetry configuration in constants
        if (empty($this->installationId) && defined('ERPIA_TELEMETRY_TOKEN')) {
            $tokenData = explode(':', ERPIA_TELEMETRY_TOKEN);
            if (count($tokenData) === 2) {
                $this->installationId = (int) $tokenData[0];
                $this->signatureKey = $tokenData[1];
                $this->persistConfiguration();
            }
        }
    }

    /**
     * Generate a claim URL for installation registration
     */
    public function generateClaimUrl(): string
    {
        $parameters = $this->gatherSystemData(true);
        $parameters['operation'] = 'claim';
        $this->generateSignature($parameters);
        return self::TELEMETRY_ENDPOINT . '?' . http_build_query($parameters);
    }

    /**
     * Get the installation identifier
     */
    public function getInstallationId(): ?int
    {
        return $this->installationId;
    }

    /**
     * Register the installation with the telemetry server
     */
    public function registerInstallation(): bool
    {
        if ($this->installationId) {
            return true;
        }

        $parameters = $this->gatherSystemData();
        $parameters['operation'] = 'register';
        $response = Http::create()
            ->get(self::TELEMETRY_ENDPOINT, $parameters)
            ->setTimeout(10)
            ->json();

        if (isset($response['installation_id']) && isset($response['signature_key'])) {
            $this->installationId = $response['installation_id'];
            $this->signatureKey = $response['signature_key'];
            $this->persistConfiguration();
            return true;
        }

        return false;
    }

    /**
     * Check if telemetry is configured and ready
     */
    public function isConfigured(): bool
    {
        return !empty($this->installationId);
    }

    /**
     * Generate a signed URL with telemetry parameters
     */
    public function signUrl(string $url): string
    {
        if (empty($this->installationId)) {
            return $url;
        }

        $parameters = $this->gatherSystemData(true);
        $this->generateSignature($parameters);
        return $url . '?' . http_build_query($parameters);
    }

    /**
     * Unlink this installation from telemetry tracking
     */
    public function unlinkInstallation(): bool
    {
        if (empty($this->installationId)) {
            return true;
        }

        $parameters = $this->gatherSystemData();
        $parameters['operation'] = 'unlink';
        $this->generateSignature($parameters);
        
        $response = Http::create()
            ->get(self::TELEMETRY_ENDPOINT, $parameters)
            ->setTimeout(10)
            ->json();

        if (isset($response['error']) && $response['error']) {
            return false;
        }

        // Clear local configuration
        Config::set('telemetry', 'installation_id', null);
        Config::set('telemetry', 'signature_key', null);
        Config::set('telemetry', 'last_update', null);
        Config::save();

        $this->installationId = null;
        $this->signatureKey = '';
        $this->lastUpdateTime = 0;

        return true;
    }

    /**
     * Update telemetry data on the server
     */
    public function updateTelemetry(): bool
    {
        if (!$this->isConfigured() || time() - $this->lastUpdateTime < self::UPDATE_FREQUENCY) {
            return false;
        }

        $parameters = $this->gatherSystemData();
        $parameters['operation'] = 'update';
        $this->generateSignature($parameters);

        $response = Http::create()
            ->get(self::TELEMETRY_ENDPOINT, $parameters)
            ->setTimeout(3)
            ->json();

        $this->persistConfiguration();
        return isset($response['success']) && $response['success'];
    }

    /**
     * Generate SHA1 signature for request parameters
     */
    private function generateSignature(array &$data): void
    {
        $data['signature'] = sha1($data['nonce'] . $this->signatureKey);
    }

    /**
     * Collect system data for telemetry
     */
    private function gatherSystemData(bool $minimal = false): array
    {
        $data = [
            'country_code' => ERPIA_COUNTRY_CODE,
            'core_version' => Kernel::getVersion(),
            'installation_id' => $this->installationId,
            'language_code' => ERPIA_LANGUAGE,
            'php_version' => (float) PHP_VERSION,
            'nonce' => mt_rand()
        ];

        if (!$minimal) {
            $data['enabled_plugins'] = implode(',', Plugins::getEnabled());
        }

        return $data;
    }

    /**
     * Save telemetry configuration
     */
    private function persistConfiguration(): void
    {
        $this->lastUpdateTime = time();

        Config::set('telemetry', 'installation_id', $this->installationId);
        Config::set('telemetry', 'signature_key', $this->signatureKey);
        Config::set('telemetry', 'last_update', $this->lastUpdateTime);
        Config::save();
    }
}