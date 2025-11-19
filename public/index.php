<?php

declare(strict_types=1);

use App\Middleware\AuthenticateRequestMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response as SlimResponse;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

// CORS setup from slim docs
$app->options('/{routes:.+}', function (Request $request, Response $response, $args) {
    return $response;
});

$app->add(function (Request $request, RequestHandler $handler) {
    $response = $handler->handle($request);
    return $response
            ->withHeader('Access-Control-Allow-Origin', $_ENV['CORS_ALLOWED_ORIGINS'])
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// add error handler
$errorMiddleware = $app->addErrorMiddleware($_ENV['APP_ENV'] === 'development', true, true);

// not found handler
$errorMiddleware->setErrorHandler(
    HttpNotFoundException::class,
    function (Request $request, Throwable $exception, bool $displayErrorDetails) {
        $response = new SlimResponse();
        $res = [ 'message' => 'Not Found' ];
        if ($displayErrorDetails) {
            $res['error'] = (string) $exception;
        }
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(404);
    },
);

// default error handler to log errors and return a 500 response
$errorMiddleware->setDefaultErrorHandler(function (
    Request $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails,
) {
    $response = new SlimResponse();
    $res = ['message' => 'an error occurred while processing this request'];

    if ($displayErrorDetails) {
        $res['error'] = (string) $exception;
    }

    if ($logErrorDetails) {
        error_log(sprintf(
            '[%s] %s %s - %s',
            date('c'),
            $request->getMethod(),
            (string) $request->getUri(),
            (string) $exception,
        ));
    } elseif ($logErrors) {
        error_log(sprintf(
            '[%s] %s %s - %s',
            date('c'),
            $request->getMethod(),
            (string) $request->getUri(),
            $exception->getMessage(),
        ));
    }
    $response->getBody()->write(json_encode($res));
    return $response->withStatus(500);
});

// middleware to validate API key
$app->add(new AuthenticateRequestMiddleware());

// adds some best practice headeres to the responses
$app->add(new SecurityHeadersMiddleware());

// specify all responses are JSON
$app->add(function (Request $request, RequestHandler $handler): Response {
    $response = $handler->handle($request);
    return $response->withHeader('Content-Type', 'application/json');
});

(require __DIR__ . '/../src/routes.php')($app);

// catch all to throw not found exception 
// mentioned in the slim CORS docs
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
    throw new HttpNotFoundException($request);
});

$app->run();
