<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\UserController;
use App\Exceptions\InsufficientPointsException;
use App\Exceptions\InvalidTransactionException;
use App\Exceptions\UserAlreadyExistsException;
use App\Exceptions\UserNotFoundException;
use App\Models\User;
use App\Services\UserService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use ReflectionClass;
use PHPUnit\Framework\MockObject\MockObject;

class UserControllerTest extends TestCase
{
    private UserController $controller;

    /** @var MockObject&UserService */
    private UserService $mockService;

    /** @var MockObject&ServerRequestInterface */
    private ServerRequestInterface $mockRequest;

    /** @var MockObject&ResponseInterface */
    private ResponseInterface $mockResponse;

    /** @var MockObject&StreamInterface */
    private StreamInterface $mockStream;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockService = $this->createMock(UserService::class);

        $this->controller = new UserController();
        $reflection = new ReflectionClass($this->controller);
        $serviceProperty = $reflection->getProperty('service');
        $serviceProperty->setValue($this->controller, $this->mockService);

        $this->mockRequest = $this->createMock(ServerRequestInterface::class);
        $this->mockResponse = $this->createMock(ResponseInterface::class);
        $this->mockStream = $this->createMock(StreamInterface::class);
    }

    /**
     * Test successful user creation via POST /user endpoint.
     *
     * Verifies that the controller correctly delegates to the service,
     * returns a 201 status code, and includes the created user in the
     * JSON response body.
     */
    public function testStoreSuccess(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $newUser = new User(1, 'John Doe', 'john@example.com', 0, null);

        $this->mockRequest
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($userData);

        $this->mockService
            ->expects($this->once())
            ->method('create')
            ->with('John Doe', 'john@example.com')
            ->willReturn($newUser);

        $this->mockResponse
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($this->mockStream);

        $this->mockStream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($json) {
                $data = json_decode($json, true);
                return $data['success'] === true
                    && $data['message'] === 'User created Successfully'
                    && $data['user']['id'] === 1
                    && $data['user']['name'] === 'John Doe'
                    && $data['user']['email'] === 'john@example.com'
                    && $data['user']['pointsBalance'] === 0;
            }));

        $this->mockResponse
            ->expects($this->once())
            ->method('withStatus')
            ->with(201)
            ->willReturn($this->mockResponse);

        $response = $this->controller->store($this->mockRequest, $this->mockResponse);
        $this->assertSame($this->mockResponse, $response);
    }

    /**
     * Test user creation failure when email already exists.
     *
     * Ensures that when the service throws UserAlreadyExistsException,
     * the controller returns a 409 Conflict status with an appropriate
     * error message in the JSON response.
     */
    public function testStoreUserAlreadyExists(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'existing@example.com',
        ];

        $this->mockRequest
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($userData);

        $this->mockService
            ->expects($this->once())
            ->method('create')
            ->with('John Doe', 'existing@example.com')
            ->willThrowException(new UserAlreadyExistsException('A user with this email already exists.'));

        $this->mockResponse
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($this->mockStream);

        $this->mockStream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($json) {
                $data = json_decode($json, true);
                return $data['success'] === false
                    && $data['message'] === 'A user with this email already exists.';
            }));

        $this->mockResponse
            ->expects($this->once())
            ->method('withStatus')
            ->with(409)
            ->willReturn($this->mockResponse);

        $response = $this->controller->store($this->mockRequest, $this->mockResponse);
        $this->assertSame($this->mockResponse, $response);
    }

    /**
     * Test user creation failure with invalid input data.
     *
     * Validates that when the service throws InvalidArgumentException
     * (e.g., invalid email format), the controller returns a 422
     * Unprocessable Entity status with the validation error message.
     */
    public function testStoreInvalidInput(): void
    {
        $userData = [
            'name' => '',
            'email' => 'invalid-email',
        ];

        $this->mockRequest
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($userData);

        $this->mockService
            ->expects($this->once())
            ->method('create')
            ->with('', 'invalid-email')
            ->willThrowException(new InvalidArgumentException('Email must be a valid email'));

        $this->mockResponse
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($this->mockStream);

        $this->mockStream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($json) {
                $data = json_decode($json, true);
                return $data['success'] === false
                    && str_contains($data['message'], 'Email must be a valid email');
            }));

        $this->mockResponse
            ->expects($this->once())
            ->method('withStatus')
            ->with(422)
            ->willReturn($this->mockResponse);

        $response = $this->controller->store($this->mockRequest, $this->mockResponse);
        $this->assertSame($this->mockResponse, $response);
    }

    /**
     * Test successful points earning via POST /user/{id}/earn endpoint.
     *
     * Verifies that the controller correctly delegates to the service,
     * returns a 200 status code, and includes the updated balance in
     * the JSON response body.
     */
    public function testEarnSuccess(): void
    {
        $earnData = [
            'points' => 100,
            'description' => 'Welcome bonus',
        ];

        $this->mockRequest
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($earnData);

        $this->mockService
            ->expects($this->once())
            ->method('earnPoints')
            ->with(100, 1, 'Welcome bonus')
            ->willReturn(100);

        $this->mockResponse
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($this->mockStream);

        $this->mockStream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($json) {
                $data = json_decode($json, true);
                return $data['success'] === true
                    && $data['newBalance'] === 100;
            }));

        $this->mockResponse
            ->expects($this->once())
            ->method('withStatus')
            ->with(200)
            ->willReturn($this->mockResponse);

        $response = $this->controller->earn($this->mockRequest, $this->mockResponse, ['id' => '1']);
        $this->assertSame($this->mockResponse, $response);
    }

    /**
     * Test earn points failure when user does not exist.
     *
     * Ensures that when the service throws UserNotFoundException,
     * the controller returns a 404 Not Found status with an appropriate
     * error message in the JSON response.
     */
    public function testEarnUserNotFound(): void
    {
        $earnData = [
            'points' => 100,
            'description' => 'Welcome bonus',
        ];

        $this->mockRequest
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($earnData);

        $this->mockService
            ->expects($this->once())
            ->method('earnPoints')
            ->with(100, 999, 'Welcome bonus')
            ->willThrowException(new UserNotFoundException('User not found for id 999'));

        $this->mockResponse
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($this->mockStream);

        $this->mockStream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($json) {
                $data = json_decode($json, true);
                return $data['success'] === false
                    && $data['message'] === 'User not found for id 999';
            }));

        $this->mockResponse
            ->expects($this->once())
            ->method('withStatus')
            ->with(404)
            ->willReturn($this->mockResponse);

        $response = $this->controller->earn($this->mockRequest, $this->mockResponse, ['id' => '999']);
        $this->assertSame($this->mockResponse, $response);
    }

    /**
     * Test earn points failure with invalid points amount.
     *
     * Validates that when the service throws InvalidArgumentException
     * (e.g., negative or zero points), the controller returns a 422
     * Unprocessable Entity status with the validation error message.
     */
    public function testEarnInvalidPoints(): void
    {
        $earnData = [
            'points' => -50,
            'description' => 'Invalid amount',
        ];

        $this->mockRequest
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($earnData);

        $this->mockService
            ->expects($this->once())
            ->method('earnPoints')
            ->with(-50, 1, 'Invalid amount')
            ->willThrowException(new InvalidArgumentException('Earned points must be greater than 0'));

        $this->mockResponse
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($this->mockStream);

        $this->mockStream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($json) {
                $data = json_decode($json, true);
                return $data['success'] === false
                    && $data['message'] === 'Earned points must be greater than 0';
            }));

        $this->mockResponse
            ->expects($this->once())
            ->method('withStatus')
            ->with(422)
            ->willReturn($this->mockResponse);

        $response = $this->controller->earn($this->mockRequest, $this->mockResponse, ['id' => '1']);
        $this->assertSame($this->mockResponse, $response);
    }

    /**
     * Test successful points redemption via POST /user/{id}/redeem endpoint.
     *
     * Verifies that the controller correctly delegates to the service,
     * returns a 200 status code, and includes the updated balance in
     * the JSON response body.
     */
    public function testRedeemSuccess(): void
    {
        $redeemData = [
            'points' => 50,
            'description' => 'Purchase reward',
        ];

        $this->mockRequest
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($redeemData);

        $this->mockService
            ->expects($this->once())
            ->method('redeemPoints')
            ->with(50, 1, 'Purchase reward')
            ->willReturn(50);

        $this->mockResponse
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($this->mockStream);

        $this->mockStream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($json) {
                $data = json_decode($json, true);
                return $data['success'] === true
                    && $data['newBalance'] === 50;
            }));

        $this->mockResponse
            ->expects($this->once())
            ->method('withStatus')
            ->with(200)
            ->willReturn($this->mockResponse);

        $response = $this->controller->redeem($this->mockRequest, $this->mockResponse, ['id' => '1']);
        $this->assertSame($this->mockResponse, $response);
    }

    /**
     * Test redeem points failure when user does not exist.
     *
     * Ensures that when the service throws UserNotFoundException,
     * the controller returns a 404 Not Found status with an appropriate
     * error message in the JSON response.
     */
    public function testRedeemUserNotFound(): void
    {
        $redeemData = [
            'points' => 50,
            'description' => 'Purchase reward',
        ];

        $this->mockRequest
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($redeemData);

        $this->mockService
            ->expects($this->once())
            ->method('redeemPoints')
            ->with(50, 999, 'Purchase reward')
            ->willThrowException(new UserNotFoundException('User not found for id 999'));

        $this->mockResponse
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($this->mockStream);

        $this->mockStream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($json) {
                $data = json_decode($json, true);
                return $data['success'] === false
                    && $data['message'] === 'User not found for id 999';
            }));

        $this->mockResponse
            ->expects($this->once())
            ->method('withStatus')
            ->with(404)
            ->willReturn($this->mockResponse);

        $response = $this->controller->redeem($this->mockRequest, $this->mockResponse, ['id' => '999']);
        $this->assertSame($this->mockResponse, $response);
    }

    /**
     * Test redeem points failure when user has insufficient balance.
     *
     * Validates that when the service throws InsufficientPointsException,
     * the controller returns a 422 Unprocessable Entity status with an
     * appropriate error message in the JSON response.
     */
    public function testRedeemInsufficientPoints(): void
    {
        $redeemData = [
            'points' => 150,
            'description' => 'Too expensive',
        ];

        $this->mockRequest
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($redeemData);

        $this->mockService
            ->expects($this->once())
            ->method('redeemPoints')
            ->with(150, 1, 'Too expensive')
            ->willThrowException(new InsufficientPointsException('User 1 has insufficient points'));

        $this->mockResponse
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($this->mockStream);

        $this->mockStream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($json) {
                $data = json_decode($json, true);
                return $data['success'] === false
                    && $data['message'] === 'User 1 has insufficient points';
            }));

        $this->mockResponse
            ->expects($this->once())
            ->method('withStatus')
            ->with(422)
            ->willReturn($this->mockResponse);

        $response = $this->controller->redeem($this->mockRequest, $this->mockResponse, ['id' => '1']);
        $this->assertSame($this->mockResponse, $response);
    }

    /**
     * Test redeem points failure with invalid transaction data.
     *
     * Validates that when the service throws InvalidTransactionException
     * (e.g., invalid points amount), the controller returns a 422
     * Unprocessable Entity status with the validation error message.
     */
    public function testRedeemInvalidTransaction(): void
    {
        $redeemData = [
            'points' => 0,
            'description' => 'Invalid',
        ];

        $this->mockRequest
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($redeemData);

        $this->mockService
            ->expects($this->once())
            ->method('redeemPoints')
            ->with(0, 1, 'Invalid')
            ->willThrowException(new InvalidTransactionException('Invalid transaction data'));

        $this->mockResponse
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($this->mockStream);

        $this->mockStream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($json) {
                $data = json_decode($json, true);
                return $data['success'] === false
                    && $data['message'] === 'Invalid transaction data';
            }));

        $this->mockResponse
            ->expects($this->once())
            ->method('withStatus')
            ->with(422)
            ->willReturn($this->mockResponse);

        $response = $this->controller->redeem($this->mockRequest, $this->mockResponse, ['id' => '1']);
        $this->assertSame($this->mockResponse, $response);
    }
}
