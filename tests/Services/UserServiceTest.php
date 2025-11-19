<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Database\Filter;
use App\Enum\TransactionOperation;
use App\Exceptions\InsufficientPointsException;
use App\Exceptions\UserAlreadyExistsException;
use App\Exceptions\UserNotFoundException;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Services\UserService;
use InvalidArgumentException;
use PDO;
use PDOException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class UserServiceTest extends TestCase
{
    private UserService $service;

    /** @var MockObject&UserRepository */
    private UserRepository $mockUserRepository;

    /** @var MockObject&TransactionRepository */
    private TransactionRepository $mockTransactionRepository;

    /** @var MockObject&PDO */
    private PDO $mockPDO;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new UserService();

        $this->mockUserRepository = $this->createMock(UserRepository::class);
        $this->mockTransactionRepository = $this->createMock(TransactionRepository::class);
        $this->mockPDO = $this->createMock(PDO::class);

        $reflection = new ReflectionClass($this->service);

        $userRepoProperty = $reflection->getProperty('userRepository');
        $userRepoProperty->setValue($this->service, $this->mockUserRepository);

        $transactionRepoProperty = $reflection->getProperty('transactionRepository');
        $transactionRepoProperty->setValue($this->service, $this->mockTransactionRepository);

        $dbProperty = $reflection->getProperty('dbConnection');
        $dbProperty->setValue($this->service, $this->mockPDO);
    }

    /**
     * Test successful user creation via the service layer.
     *
     * Verifies that the service correctly delegates to the repository and
     * returns the created user with all expected properties populated.
     */
    public function testCreateSuccess(): void
    {
        $expectedUser = new User(1, 'John Doe', 'john@example.com', 0, null);

        $this->mockUserRepository
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function (User $user) {
                return $user->name === 'John Doe'
                    && $user->email === 'john@example.com'
                    && $user->pointsBalance === 0;
            }))
            ->willReturn($expectedUser);

        $result = $this->service->create('John Doe', 'john@example.com');

        $this->assertSame($expectedUser, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('John Doe', $result->name);
        $this->assertEquals('john@example.com', $result->email);
    }

    /**
     * Test user creation failure when email already exists.
     *
     * Ensures that a PDOException with code 23000 (unique constraint violation)
     * is caught and converted into a UserAlreadyExistsException.
     */
    public function testCreateUserAlreadyExists(): void
    {
        $pdoException = new PDOException('Duplicate entry');
        $pdoException->errorInfo = ['23000', 1062, 'Duplicate entry'];

        // Use reflection to set the code property since it's readonly
        $reflection = new ReflectionClass($pdoException);
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setValue($pdoException, 23000);

        $this->mockUserRepository
            ->expects($this->once())
            ->method('create')
            ->willThrowException($pdoException);

        $this->expectException(UserAlreadyExistsException::class);
        $this->expectExceptionMessage('A user with this email already exists.');

        $this->service->create('John Doe', 'existing@example.com');
    }

    /**
     * Test user creation failure with invalid email format.
     *
     * Validates that attempting to create a user with an invalid email
     * throws an InvalidArgumentException with the appropriate message.
     */
    public function testCreateInvalidEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Email must be a valid email');

        $this->service->create('John Doe', 'invalid-email');
    }

    /**
     * Test user creation failure with empty name.
     *
     * Ensures that ValueError thrown by the User model for empty names
     * is caught and re-thrown as InvalidArgumentException.
     */
    public function testCreateEmptyName(): void
    {
        // The repository will be called, but will throw ValueError due to name validation
        $this->mockUserRepository
            ->expects($this->once())
            ->method('create')
            ->willThrowException(new \ValueError('Name must be a non-empty string'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name must be a non-empty string');

        $this->service->create('', 'john@example.com');
    }

    /**
     * Test successful points earning operation.
     *
     * Verifies that the service correctly updates the user's balance,
     * creates a transaction record, and commits the database transaction.
     */
    public function testEarnPointsSuccess(): void
    {
        $user = new User(1, 'John Doe', 'john@example.com', 50, null);

        $this->mockUserRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($user);

        $this->mockPDO
            ->expects($this->once())
            ->method('beginTransaction');

        $this->mockUserRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $savedUser) {
                return $savedUser->pointsBalance === 150;
            }));

        $this->mockTransactionRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Transaction $transaction) {
                return $transaction->userId === 1
                    && $transaction->operation === TransactionOperation::Earn
                    && $transaction->amount === 100
                    && $transaction->description === 'Bonus points';
            }));

        $this->mockPDO
            ->expects($this->once())
            ->method('commit');

        $newBalance = $this->service->earnPoints(100, 1, 'Bonus points');

        $this->assertEquals(150, $newBalance);
    }

    /**
     * Test earn points failure when user does not exist.
     *
     * Ensures that attempting to earn points for a non-existent user
     * throws a UserNotFoundException.
     */
    public function testEarnPointsUserNotFound(): void
    {
        $this->mockUserRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn(null);

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User not found for id 999');

        $this->service->earnPoints(100, 999, 'Bonus points');
    }

    /**
     * Test earn points validation with negative amount.
     *
     * Validates that attempting to earn negative points throws an
     * InvalidArgumentException before any database operations.
     */
    public function testEarnPointsNegativeAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Earned points must be greater than 0');

        $this->service->earnPoints(-50, 1, 'Invalid');
    }

    /**
     * Test earn points validation with zero amount.
     *
     * Validates that attempting to earn zero points throws an
     * InvalidArgumentException before any database operations.
     */
    public function testEarnPointsZeroAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Earned points must be greater than 0');

        $this->service->earnPoints(0, 1, 'Invalid');
    }

    /**
     * Test database transaction rollback on error during earn points.
     *
     * Ensures that when an error occurs during the transaction, the
     * database transaction is properly rolled back and the exception
     * is re-thrown.
     */
    public function testEarnPointsRollbackOnError(): void
    {
        $user = new User(1, 'John Doe', 'john@example.com', 50, null);

        $this->mockUserRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($user);

        $this->mockPDO
            ->expects($this->once())
            ->method('beginTransaction');

        $this->mockUserRepository
            ->expects($this->once())
            ->method('save')
            ->willThrowException(new \Exception('Database error'));

        $this->mockPDO
            ->expects($this->once())
            ->method('inTransaction')
            ->willReturn(true);

        $this->mockPDO
            ->expects($this->once())
            ->method('rollBack');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->service->earnPoints(100, 1, 'Bonus points');
    }

    /**
     * Test successful points redemption operation.
     *
     * Verifies that the service correctly updates the user's balance,
     * creates a transaction record, and commits the database transaction.
     */
    public function testRedeemPointsSuccess(): void
    {
        $user = new User(1, 'John Doe', 'john@example.com', 100, null);

        $this->mockUserRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($user);

        $this->mockPDO
            ->expects($this->once())
            ->method('beginTransaction');

        $this->mockUserRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $savedUser) {
                return $savedUser->pointsBalance === 50;
            }));

        $this->mockTransactionRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Transaction $transaction) {
                return $transaction->userId === 1
                    && $transaction->operation === TransactionOperation::Redeem
                    && $transaction->amount === 50
                    && $transaction->description === 'Purchase reward';
            }));

        $this->mockPDO
            ->expects($this->once())
            ->method('commit');

        $newBalance = $this->service->redeemPoints(50, 1, 'Purchase reward');

        $this->assertEquals(50, $newBalance);
    }

    /**
     * Test redeem points failure when user does not exist.
     *
     * Ensures that attempting to redeem points for a non-existent user
     * throws a UserNotFoundException.
     */
    public function testRedeemPointsUserNotFound(): void
    {
        $this->mockUserRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn(null);

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User not found for id 999');

        $this->service->redeemPoints(50, 999, 'Purchase reward');
    }

    /**
     * Test redeem points failure when user has insufficient balance.
     *
     * Validates that attempting to redeem more points than available
     * throws an InsufficientPointsException.
     */
    public function testRedeemPointsInsufficientBalance(): void
    {
        $user = new User(1, 'John Doe', 'john@example.com', 30, null);

        $this->mockUserRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($user);

        $this->expectException(InsufficientPointsException::class);
        $this->expectExceptionMessage('User 1 has insufficient points');

        $this->service->redeemPoints(50, 1, 'Purchase reward');
    }

    /**
     * Test redeem points validation with negative amount.
     *
     * Validates that attempting to redeem negative points throws an
     * InvalidArgumentException before any database operations.
     */
    public function testRedeemPointsNegativeAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Redeemed points must be > 0');

        $this->service->redeemPoints(-50, 1, 'Invalid');
    }

    /**
     * Test redeem points validation with zero amount.
     *
     * Validates that attempting to redeem zero points throws an
     * InvalidArgumentException before any database operations.
     */
    public function testRedeemPointsZeroAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Redeemed points must be > 0');

        $this->service->redeemPoints(0, 1, 'Invalid');
    }

    /**
     * Test database transaction rollback on error during redeem points.
     *
     * Ensures that when an error occurs during the transaction, the
     * database transaction is properly rolled back and the exception
     * is re-thrown.
     */
    public function testRedeemPointsRollbackOnError(): void
    {
        $user = new User(1, 'John Doe', 'john@example.com', 100, null);

        $this->mockUserRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($user);

        $this->mockPDO
            ->expects($this->once())
            ->method('beginTransaction');

        $this->mockUserRepository
            ->expects($this->once())
            ->method('save')
            ->willThrowException(new \Exception('Database error'));

        $this->mockPDO
            ->expects($this->once())
            ->method('inTransaction')
            ->willReturn(true);

        $this->mockPDO
            ->expects($this->once())
            ->method('rollBack');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->service->redeemPoints(50, 1, 'Purchase reward');
    }

    /**
     * Test redeeming exact balance amount.
     *
     * Verifies that a user can redeem their entire point balance,
     * resulting in a final balance of zero.
     */
    public function testRedeemPointsExactBalance(): void
    {
        $user = new User(1, 'John Doe', 'john@example.com', 100, null);

        $this->mockUserRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($user);

        $this->mockPDO
            ->expects($this->once())
            ->method('beginTransaction');

        $this->mockUserRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $savedUser) {
                return $savedUser->pointsBalance === 0;
            }));

        $this->mockTransactionRepository
            ->expects($this->once())
            ->method('save');

        $this->mockPDO
            ->expects($this->once())
            ->method('commit');

        $newBalance = $this->service->redeemPoints(100, 1, 'Redeem all points');

        $this->assertEquals(0, $newBalance);
    }
}