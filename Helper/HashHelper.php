<?php

class HashHelper {
    /**
     * Hashes a password using PHP's password_hash function.
     *
     * @param string $password The plain-text password.
     * @return string The hashed password.
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verifies a password against a hash.
     *
     * @param string $password The plain-text password.
     * @param string $hashedPassword The hash from the database.
     * @return bool True if the password matches the hash, false otherwise.
     */
    public static function verifyPassword($password, $hashedPassword) {
        return password_verify($password, $hashedPassword);
    }
}