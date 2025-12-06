<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2017-2025 ERPIA Team
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

namespace ERPIA\Core\Lib\API\Base;

use Exception;
use ERPIA\Core\Request;
use ERPIA\Core\Response;
use ERPIA\Core\Tools;

/**
 * APIResource is an abstract class for any API Resource.
 *
 * @author ERPIA Team
 */
abstract class APIResourceClass
{
    /**
     * Contains the HTTP method (GET, PUT, PATCH, POST, DELETE).
     * PUT, PATCH and POST used in the same way.
     *
     * @var string
     */
    protected $httpMethod;

    /**
     * Parameters passed to the resource.
     *
     * @var array
     */
    protected $parameters;

    /**
     * Gives us access to the HTTP request parameters.
     *
     * @var Request
     */
    protected $request;

    /**
     * HTTP response object.
     *
     * @var Response
     */
    protected $response;

    /**
     * Returns an associative array with the resources, where the index is
     * the public name of the resource.
     *
     * @return array
     */
    abstract public function getResources(): array;

    /**
     * APIResourceClass constructor.
     *
     * @param Response $response
     * @param Request $request
     * @param array $params
     */
    public function __construct(Response $response, Request $request, array $params)
    {
        $this->parameters = $params;
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Process the DELETE request. Override this function to implement its functionality.
     * It is not defined as abstract because descendants may not need this method if
     * they override processResource.
     *
     * @return bool
     */
    public function doDELETE(): bool
    {
        return true;
    }

    /**
     * Process the GET request. Override this function to implement its functionality.
     * It is not defined as abstract because descendants may not need this method if
     * they override processResource.
     *
     * @return bool
     */
    public function doGET(): bool
    {
        return true;
    }

    /**
     * Process the POST request. Override this function to implement its functionality.
     * It is not defined as abstract because descendants may not need this method if
     * they override processResource.
     *
     * @return bool
     */
    public function doPOST(): bool
    {
        return true;
    }

    /**
     * Process the PUT request. Override this function to implement its functionality.
     * It is not defined as abstract because descendants may not need this method if
     * they override processResource.
     *
     * @return bool
     */
    public function doPUT(): bool
    {
        return true;
    }

    /**
     * Process the resource, allowing POST/PUT/DELETE/GET ALL actions
     *
     * @param string $resourceName Name of the resource, used only if there are several.
     * @return bool
     */
    public function processResource(string $resourceName): bool
    {
        $this->httpMethod = $this->request->getMethod();

        try {
            switch ($this->httpMethod) {
                case 'DELETE':
                    return $this->doDELETE();

                case 'GET':
                    return $this->doGET();

                case 'PATCH':
                case 'PUT':
                    return $this->doPUT();

                case 'POST':
                    return $this->doPOST();

                default:
                    $this->setError('Unsupported HTTP method', null, Response::HTTP_METHOD_NOT_ALLOWED);
                    return false;
            }
        } catch (Exception $exception) {
            $this->setError('API-ERROR: ' . $exception->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
            return false;
        }
    }

    /**
     * Register a resource
     *
     * @param string $name
     * @return array
     */
    public function setResource(string $name): array
    {
        return [
            'API' => get_class($this),
            'ResourceName' => $name
        ];
    }

    /**
     * Return the array with the result, and HTTP_OK status code.
     *
     * @param array $data
     */
    protected function returnResult(array $data): void
    {
        $this->response
            ->setStatusCode(Response::HTTP_OK)
            ->json($data);
    }

    /**
     * Return a confirmation message. For example for a DELETE operation.
     * Can return an array with additional information.
     *
     * @param string $message Informative text of the confirmation message.
     * @param array|null $data Additional information.
     */
    protected function setOk(string $message, ?array $data = null): void
    {
        Tools::log('api')->notice($message);

        $responseData = ['ok' => $message];
        if ($data !== null) {
            $responseData['data'] = $data;
        }

        $this->response
            ->setStatusCode(Response::HTTP_OK)
            ->json($responseData);
    }

    /**
     * Return an error message and the corresponding status.
     * Can also return an array with additional information.
     *
     * @param string $message
     * @param array|null $data
     * @param int $status
     */
    protected function setError(string $message, ?array $data = null, int $status = Response::HTTP_BAD_REQUEST): void
    {
        Tools::log('api')->error($message);

        $responseData = ['error' => $message];
        if ($data !== null) {
            $responseData['data'] = $data;
        }

        $this->response
            ->setStatusCode($status)
            ->json($responseData);
    }
}