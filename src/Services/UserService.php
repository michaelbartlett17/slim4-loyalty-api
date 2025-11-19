<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Database\Filter;
use App\Database\Pagination;
use App\Enum\TransactionOperation;
use App\Exceptions\InsufficientPointsException;
use App\Exceptions\InvalidTransactionException;
use App\Exceptions\UserAlreadyExistsException;
use App\Exceptions\UserNotFoundException;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use InvalidArgumentException;
use PDO;
use PDOException;
use ValueError;

/**
 * Service responsible for user-related business logic.
 *
 * This service coordinates between repositories and the database to perform
 * higher-level operations such as creating users, retrieving lists of users,
 * and performing point transactions (earn/redeem). It manages database
 * transactions for operations that affect multiple tables (users and
 * transactions) and throws domain-specific exceptions on error.
 */
class UserService
{
    /** @var UserRepository an instance of a User Repository */
    protected UserRepository $userRepository;

    /** @var TransactionRepository an instance of a Transaction Repository */
    protected TransactionRepository $transactionRepository;

    /** @var PDO a database connection */
    private PDO $dbConnection;

    protected array $defaultFindableFields = [
        'id',
        'name',
        'email',
        'pointsBalance',
    ];

    public static array $sortableFields = [
        'id',
        'name',
    ];

    protected array $defaultFilters = [];

    public function __construct()
    {
        $this->dbConnection = Database::getInstance()->getConnection();
        $this->userRepository = new UserRepository($this->dbConnection);
        $this->transactionRepository = new TransactionRepository($this->dbConnection);

        $this->defaultFilters = [
            new Filter(User::class, 'deleted', false),
        ];
    }

    /**
     * Create a new user record.
     *
     * Attempts to insert a new user and convert common PDO errors into
     * domain-specific exceptions (e.g. `UserAlreadyExistsException` for
     * unique constraint violations). Will throw `InvalidArgumentException`
     * when provided invalid input that fails model construction.
     *
     * @param  string                     $name  User's display name.
     * @param  string                     $email User's email address.
     * @return User                       The created user model (primary key populated).
     * @throws UserAlreadyExistsException When a user with the same email exists.
     * @throws InvalidArgumentException   When input is invalid for model construction.
     */

    public function create(string $name, string $email): User
    {
        try {
            return $this->userRepository->create(new User(0, $name, $email));
        } catch (PDOException $err) {
            if ($err->getCode() == 23000) { // unique constraint error
                throw new UserAlreadyExistsException('A user with this email already exists.');
            } else {
                throw $err;
            }
        } catch (ValueError $err) {
            throw new InvalidArgumentException($err->getMessage(), 0, $err);
        }
    }

    /**
     * Retrieve all users matching provided filters, optionally paginated.
     *
     * This method applies a default set of selectable fields and always
     * includes repository-level default filters (e.g. excluding deleted users).
     *
     * @param  Pagination|null $pagination Optional pagination and ordering.
     * @param  Filter          ...$filters Additional filters to apply to the query.
     * @return User[]          Array of `User` models.
     */
    public function getAll(?Pagination $pagination = null, Filter ...$filters): array
    {
        return $this->userRepository->findAll($this->defaultFindableFields, $pagination, ...[...$filters, ...$this->defaultFilters]);
    }

    /**
     * Delete users matching the provided filters.
     *
     * Delegates to the `UserRepository::delete` method. If no rows are
     * affected this method throws `UserNotFoundException` to indicate the
     * filters did not match any existing user.
     *
     * @param  Filter                ...$filters One or more `Filter` instances to identify users to delete.
     * @return int                   Number of rows deleted.
     * @throws UserNotFoundException When no matching user was found.
     */
    public function delete(Filter ...$filters): int
    {
        $deletedCount = $this->userRepository->delete(...$filters);

        if ($deletedCount === 0) {
            throw new UserNotFoundException('No user found for the provided filter(s).');
        }

        return $deletedCount;
    }

    /**
     * Add points to a user's balance and record a corresponding transaction.
     *
     * This operation is performed inside a database transaction: the user's
     * points balance is updated and a new `Transaction` record is created.
     * If any step fails the transaction is rolled back.
     *
     * @param  int                         $earnedPoints Points to add (must be > 0).
     * @param  int                         $userId       ID of the user to credit.
     * @param  string                      $description  Description for the transaction record.
     * @return int                         The user's updated points balance.
     * @throws InvalidArgumentException    When `$earnedPoints` is not > 0.
     * @throws UserNotFoundException       When no user exists with the provided id.
     * @throws InvalidTransactionException When the transaction model cannot be created.
     * @throws \Throwable                  Propagates other errors after rolling back the DB transaction.
     */
    public function earnPoints(int $earnedPoints, int $userId, string $description): int
    {
        if ($earnedPoints <= 0) {
            throw new InvalidArgumentException('Earned points must be greater than 0');
        }

        /** @var User $user */
        $user = $this->userRepository->find([], new Filter(User::class, 'id', $userId));

        if (!$user) {
            throw new UserNotFoundException("User not found for id {$userId}");
        }

        try {
            $this->dbConnection->beginTransaction();

            $user->pointsBalance = $user->pointsBalance + $earnedPoints;
            $this->userRepository->save($user);

            try {
                $transaction = new Transaction(0, $userId, TransactionOperation::Earn, $earnedPoints, $description);
                $this->transactionRepository->save($transaction);
            } catch (ValueError $err) {
                throw new InvalidTransactionException('Invalid transaction data: ' . $err->getMessage(), 0, $err);
            }

            $this->dbConnection->commit();
            return $user->pointsBalance;
        } catch (\Throwable $t) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            throw $t;
        }
    }

    /**
     * Redeem (subtract) points from a user's balance and record a transaction.
     *
     * Verifies the user has sufficient points before performing a transactional
     * update. Throws `InsufficientPointsException` when the balance is too low.
     *
     * @param  int                         $redeemedPoints Points to subtract (must be > 0).
     * @param  int                         $userId         ID of the user to debit.
     * @param  string                      $description    Description for the transaction record.
     * @return int                         The user's updated points balance.
     * @throws InvalidArgumentException    When `$redeemedPoints` is not > 0.
     * @throws UserNotFoundException       When no user exists with the provided id.
     * @throws InsufficientPointsException When the user does not have enough points.
     * @throws InvalidTransactionException When the transaction model cannot be created.
     * @throws \Throwable                  Propagates other errors after rolling back the DB transaction.
     */
    public function redeemPoints(int $redeemedPoints, int $userId, string $description): int
    {
        if ($redeemedPoints <= 0) {
            throw new \InvalidArgumentException('Redeemed points must be > 0');
        }

        /** @var User $user */
        $user = $this->userRepository->find([], new Filter(User::class, 'id', $userId));

        if (!$user) {
            throw new UserNotFoundException("User not found for id {$userId}");
        }

        if ($user->pointsBalance < $redeemedPoints) {
            throw new InsufficientPointsException("User {$userId} has insufficient points");
        }

        try {
            $this->dbConnection->beginTransaction();

            try {
                $user->pointsBalance = $user->pointsBalance - $redeemedPoints;
                $this->userRepository->save($user);
            } catch (ValueError $err) {
                throw new InsufficientPointsException($err->getMessage(), 0, $err);
            }

            try {
                $transaction = new Transaction(0, $userId, TransactionOperation::Redeem, $redeemedPoints, $description);
                $this->transactionRepository->save($transaction);
            } catch (ValueError $err) {
                throw new InvalidTransactionException('Invalid transaction data: ' . $err->getMessage(), 0, $err);
            }

            $this->dbConnection->commit();
            return $user->pointsBalance;
        } catch (\Throwable $t) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            throw $t;
        }
    }
}
