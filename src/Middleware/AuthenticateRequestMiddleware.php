<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Middleware that validates the incoming request has an 'X-API-KEY' header
 * and that the header value matches `$_ENV['API_KEY']`
 *
 * If validation fails, this middleware returns a 401 response containing an unauthorized message
 */
class AuthenticateRequestMiddleware implements MiddlewareInterface
{
    /**
     * This method reads the value associated with the 'X-API-KEY' header
     * and validates that it matches `$_ENV['API_KEY]`. 
     * If the validation fails, a 401 JSON response - { "error": "unauthorized" }
     * is returned.
     * 
     * @param Request        $request The incoming server request.
     * @param RequestHandler $handler The next request handler to delegate to if validation passes.
     *
     * @return Response A PSR-7 response. On validation failure this is a 401 response containing
     *                  the Unauthorized error as JSON; otherwise it is the response returned by
     *                  the delegated handler.
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $apiKey = $request->getHeaderLine('X-API-Key');
        
        if (empty($apiKey) || !hash_equals($_ENV['API_KEY'], $apiKey)) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withStatus(401);
        }
        
        return $handler->handle($request);
    }
}