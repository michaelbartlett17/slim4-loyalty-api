CREATE TABLE users(
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `points_balance` INT NOT NULL DEFAULT(0),
    `deleted_at` TIMESTAMP NULL,
    UNIQUE INDEX `unique_email_deleted_at` (email, (COALESCE(deleted_at, "1000-01-01")))
);

CREATE TABLE transactions(
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `operation` ENUM('earn', 'redeem'),
    `amount` INT UNSIGNED NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    CONSTRAINT fk_user
        FOREIGN KEY (user_id)
        REFERENCES users(`id`)
        ON DELETE CASCADE
);