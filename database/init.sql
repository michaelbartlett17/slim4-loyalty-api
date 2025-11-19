CREATE TABLE users(
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `points_balance` INT NOT NULL DEFAULT(0),
    `deleted` BOOLEAN NOT NULL DEFAULT(0)
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