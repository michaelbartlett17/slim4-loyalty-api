<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Filter;
use App\Database\Pagination;
use App\Enum\DatabaseOrder;
use App\Exceptions\InsufficientPointsException;
use App\Exceptions\InvalidTransactionException;
use App\Exceptions\UserAlreadyExistsException;
use App\Exceptions\UserNotFoundException;
use App\Models\User;
use App\Services\UserService;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * HTTP controller for user-related endpoints.
 *
 * Routes handled by this controller typically include listing users,
 * creating a new user, earning/redeeming points, and deleting users. The
 * controller delegates business logic to `UserService` and converts domain
 * exceptions into appropriate HTTP responses.
 */
class UserController extends Controller
{
    /** @var UserService an instance of the User Service */
    private UserService $service;

    public function __construct()
    {
        $this->service = new UserService();
    }

    /**
     * Handle the index (list) endpoint.
     *
     * Reads pagination and ordering parameters from the query string and
     * returns an array of users as JSON.
     *
     * @param  Request  $request  PSR-7 request.
     * @param  Response $response PSR-7 response.
     * @return Response JSON response containing user list.
     */
    public function index(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();

        $limit = isset($queryParams['limit']) ? (int) $queryParams['limit'] : 50;
        $offset = isset($queryParams['offset']) ? (int) $queryParams['offset'] : 0;

        $pagination = new Pagination(
            User::class,
            $queryParams['orderBy'] ?? 'id',
            $limit,
            $offset,
            isset($queryParams['order']) ? DatabaseOrder::from($queryParams['order']) : DatabaseOrder::ASC,
        );
        return $this->json($response, $this->service->getAll($pagination));
    }

    /**
     * Handle user creation (store) requests.
     *
     * Translates domain errors into HTTP status codes: conflict for existing
     * user, unprocessable entity for validation errors.
     *
     * @param  Request  $request
     * @param  Response $response
     * @return Response
     */
    public function store(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            $newUser = $this->service->create($data['name'], $data['email']);
            return $this->json($response, [
                'success' => true,
                'message' => 'User created Successfully',
                'user'    => $newUser->toArray(['id', 'name', 'email', 'pointsBalance']),
            ], 201);
        } catch (UserAlreadyExistsException $err) {
            return $this->json($response, [
                'success' => false,
                'message' => $err->getMessage(),
            ], 409);
        } catch (InvalidArgumentException $err) {
            return $this->json($response, [
                'success' => false,
                'message' => $err->getMessage(),
            ], 422);
        }
    }

    /**
     * Handle earning points for a user.
     *
     * Returns 404 when the user does not exist and 422 for invalid input or
     * transaction errors.
     *
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args     Route arguments (expects `id`).
     * @return Response
     */
    public function earn(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int) $args['id'];
            $data = $request->getParsedBody();

            $newBalance = $this->service->earnPoints($data['points'], $id, $data['description']);

            return $this->json($response, [
                'success'    => true,
                'newBalance' => $newBalance,
            ]);
        } catch (UserNotFoundException $err) {
            return $this->json($response, [
                'success' => false,
                'message' => $err->getMessage(),
            ], 404);
        } catch (InvalidTransactionException | InvalidArgumentException $err) {
            return $this->json($response, [
                'success' => false,
                'message' => $err->getMessage(),
            ], 422);
        }
    }

    /**
     * Handle point redemption for a user.
     *
     * Returns 404 when user is not found, 422 for insufficient balance or
     * other transaction-related failures.
     *
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args     Route arguments (expects `id`).
     * @return Response
     */
    public function redeem(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int) $args['id'];
            $data = $request->getParsedBody();

            $newBalance = $this->service->redeemPoints($data['points'], $id, $data['description']);

            return $this->json($response, [
                'success'    => true,
                'newBalance' => $newBalance,
            ]);
        } catch (UserNotFoundException $err) {
            return $this->json($response, [
                'success' => false,
                'message' => $err->getMessage(),
            ], 404);
        } catch (InsufficientPointsException | InvalidTransactionException | InvalidArgumentException $err) {
            return $this->json($response, [
                'success' => false,
                'message' => $err->getMessage(),
            ], 422);
        }
    }

    /**
     * Handle user deletion.
     *
     * Returns 204 on success and 404 when the target user cannot be found.
     *
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args     Route arguments (expects `id`).
     * @return Response
     */
    public function destroy(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        try {
            $this->service->delete(new Filter(User::class, 'id', $id));
            return $response->withStatus(204);
        } catch (UserNotFoundException $err) {
            return $this->json($response, [
                'success' => false,
                'message' => 'No user in the system is associated with the given ID',
            ], 404);
        }
    }
}
