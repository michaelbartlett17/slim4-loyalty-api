<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;

/**
 * Base HTTP controller with small helper method used by concrete controllers.
 *
 * Provides convenience helpers to return JSON responses and to parse the
 * request body into an associative array. Subclasses should implement action
 * methods that accept PSR-7 `Request`/`Response` objects.
 */
abstract class Controller
{
    /**
     * Write JSON-encoded data to the response body and set a status code.
     *
     * @param  Response $response PSR-7 response instance to write into.
     * @param  mixed    $data     Data that will be JSON-encoded.
     * @param  int      $status   HTTP status code to return (defaults to 200).
     * @return Response Modified response instance with the body written and status applied.
     */
    protected function json(Response $response, $data, int $status = 200): Response
    {
        $payload = json_encode($data);
        $response->getBody()->write($payload);
        return $response->withStatus($status);
    }
}
