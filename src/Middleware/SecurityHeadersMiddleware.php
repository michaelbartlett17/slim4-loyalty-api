<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Middleware that adds a couple best practice headers to responses.
 * Adds:
 *   - X-Content-Type-Options: 'nosniff' in order to prevent MIME-sniffing respones and force the client to use the content-type specified by this application.
 *   - Referrer-Policy: 'strict-origin-when-cross-origin': sends the origin, path, and query string when performing a same-origin request. For cross origin requests,
 *     the origin, and only the origin, is only sent if the security level stays the same.
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * This method adds several best practice headers to responses in order to implement best security practices.
     * @param Request        $request The incoming server request.
     * @param RequestHandler $handler The next request handler to delegate to if validation passes.
     *
     * @return Response A PSR-7 response. On validation failure this is a 401 response containing
     *                  the Unauthorized error as JSON; otherwise it is the response returned by
     *                  the delegated handler.
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);

        return $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
