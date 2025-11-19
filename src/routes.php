<?php

declare(strict_types=1);

use App\Controllers\UserController;
use App\Enum\DatabaseOrder;
use App\Enum\ValidatorRule;
use App\Enum\ValidatorType;
use App\Middleware\DataValidatorMiddleware;
use App\Services\UserService;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/users', function (RouteCollectorProxy $group) {
        $group->get('[/]', UserController::class . ':index')
            ->add(new DataValidatorMiddleware([
                'queryParams' => [
                    'order' => [
                        ValidatorRule::IsOneOf->value => array_map(fn (BackedEnum $case) => $case->value, DatabaseOrder::cases()),
                    ],
                    'orderBy' => [
                        ValidatorRule::IsOneOf->value => UserService::$sortableFields,
                    ],
                    'limit' => [
                        ValidatorRule::CanCast->value => ValidatorType::Integer,
                        ValidatorRule::Min->value     => 1,
                        ValidatorRule::Max->value     => 100,
                    ],
                    'offset' => [
                        ValidatorRule::CanCast->value => ValidatorType::Integer,
                        ValidatorRule::Min->value     => 1,
                        ValidatorRule::Max->value     => 100,
                    ],
                ],
            ]));

        $group->post('[/]', UserController::class . ':store')
            ->add(new DataValidatorMiddleware([
                'body' => [
                    'name' => [
                        ValidatorRule::Type->value         => ValidatorType::String,
                        ValidatorRule::Required->value     => true,
                        ValidatorRule::MinStrLength->value => 1,
                        ValidatorRule::MaxStrLength->value => 255,
                    ],
                    'email' => [
                        ValidatorRule::Type->value         => ValidatorType::Email,
                        ValidatorRule::Required->value     => true,
                        ValidatorRule::MaxStrLength->value => 255,
                    ],
                ],
            ]));

        $group->post('/{id:[0-9]+}/earn', UserController::class . ':earn')
            ->add(new DataValidatorMiddleware([
                'body' => [
                    'points' => [
                        ValidatorRule::Type->value     => ValidatorType::Integer,
                        ValidatorRule::Required->value => true,
                        ValidatorRule::Min->value      => 1,
                    ],
                    'description' => [
                        ValidatorRule::Type->value         => ValidatorType::String,
                        ValidatorRule::Required->value     => true,
                        ValidatorRule::MinStrLength->value => 1,
                        ValidatorRule::MaxStrLength->value => 255,
                    ],
                ],
            ]));

        $group->post('/{id:[0-9]+}/redeem', UserController::class . ':redeem')
            ->add(new DataValidatorMiddleware([
                'body' => [
                    'points' => [
                        ValidatorRule::Type->value     => ValidatorType::Integer,
                        ValidatorRule::Required->value => true,
                        ValidatorRule::Min->value      => 1,
                    ],
                    'description' => [
                        ValidatorRule::Type->value         => ValidatorType::String,
                        ValidatorRule::Required->value     => true,
                        ValidatorRule::MinStrLength->value => 1,
                        ValidatorRule::MaxStrLength->value => 255,
                    ],
                ],
            ]));

        $group->delete('/{id:[0-9]+}', UserController::class . ':destroy');
    });
};
