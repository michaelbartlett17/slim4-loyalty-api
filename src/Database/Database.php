<?php

declare(strict_types=1);

namespace App\Database;

/**
 * Lightweight singleton wrapper around PDO for obtaining a shared DB connection.
 *
 * The class reads connection parameters from `$_ENV` and constructs a PDO
 * instance with recommended options. Use `getInstance()->getConnection()` to
 * obtain the PDO connection for repositories and services.
 */
class Database
{
    /** @var self the singleton Database instance, if created  */
    private static $instance = null;
    
    /** @var \PDO the underlying PDO connection */
    private $connection;

    private function __construct()
    {
        try {
            [
                'DB_HOST'     => $host,
                'DB_PORT'     => $port,
                'DB_NAME'     => $dbname,
                'DB_USER'     => $user,
                'DB_PASSWORD' => $password,
                'DB_CHARSET'  => $charset
            ] = $_ENV;

            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

            $options = [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
                \PDO::ATTR_PERSISTENT         => true,
            ];

            $this->connection = new \PDO($dsn, $user, $password, $options);
        } catch (\PDOException $err) {
            error_log('Database Connection Failed: ' . $err->getMessage());
            throw $err;
        }
    }

    /**
     * Return the singleton Database instance, creating it on first call.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get the underlying PDO connection.
     *
     * @return \PDO
     */
    public function getConnection(): \PDO
    {
        return $this->connection;
    }
}
