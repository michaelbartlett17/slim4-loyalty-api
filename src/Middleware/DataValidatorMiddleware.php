<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\DataValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Middleware that validates the incoming request body against a set of rules.
 *
 * Extends DataValidator to reuse validation logic
 * If validation fails, this middleware returns a 422 response containing the validation errors
 */

class DataValidatorMiddleware extends DataValidator implements MiddlewareInterface
{
    /**
     * @var array<string, array<string, mixed>> Validation rules keyed by 'queryParams' or 'body',
     *                                          with field names mapped to validation constraints.
     */
    private array $rules;

    /**
     * DataValidatorMiddleware constructor.
     *
     * @param array $rules Validation rules used by DataValidator::validateArrayOfValues().
     *                     Expected to be an associative array where keys are 'queryParams' or 'body'
     *                     and values are an associative array where keys are fieldNames
     *                     and values describe validation constraints.
     */
    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    /**
     * This method reads the parsed request body, validates it using the configured rules,
     * and if validation fails returns a 422 JSON response with an "errors" key. If there
     * are no validation errors it forwards the request to the next handler.
     *
     * @param Request        $request The incoming server request.
     * @param RequestHandler $handler The next request handler to delegate to if validation passes.
     *
     * @return Response A PSR-7 response. On validation failure this is a 422 response containing
     *                  the validation errors as JSON; otherwise it is the response returned by
     *                  the delegated handler.
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $errors = [
            'body'        => [],
            'queryParams' => [],
        ];

        if (!empty($this->rules['body'])) {
            $errors['body'] = static::validateArrayOfValues($request->getParsedBody(), $this->rules['body']);
        }

        if (!empty($this->rules['queryParams'])) {
            $errors['queryParams'] = static::validateArrayOfValues($request->getQueryParams(), $this->rules['queryParams']);
        }

        if (!empty($errors['body']) || !empty($errors['queryParams'])) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode([
                'errors' => $errors,
            ]));
            return $response->withStatus(422);
        }

        return $handler->handle($request);
    }
}
