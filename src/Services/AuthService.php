<?php

namespace App\Services;

use App\Config\LDAPConfig;

class AuthService
{
    /**
     * Authenticate a user against LDAP
     *
     * @param string $username Username without domain
     * @param string $password User password
     * @return bool True if authentication successful
     */
    public static function authenticate(string $username, string $password): bool
    {
        $connection = ldap_connect(LDAPConfig::getConnectionString());
        if (!$connection) {
            return false;
        }
        
        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, 10);
        
        // Try to bind with user credentials
        $userDN = $username . '@' . LDAPConfig::getDomain() . '.local';
        $bound = @ldap_bind($connection, $userDN, $password);
        
        ldap_close($connection);
        return $bound;
    }
    
    /**
     * Check if user is logged in
     *
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }
    
    /**
     * Log in a user
     *
     * @param string $username
     * @param string $password
     * @return bool True if login successful
     */
    public static function login(string $username, string $password): bool
    {
        if (self::authenticate($username, $password)) {
            $_SESSION['authenticated'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['password'] = $password; // Store encrypted in production
            return true;
        }
        return false;
    }
    
    /**
     * Log out the current user
     */
    public static function logout(): void
    {
        session_destroy();
    }
    
    /**
     * Get the current logged-in username
     *
     * @return string|null
     */
    public static function getCurrentUser(): ?string
    {
        return $_SESSION['username'] ?? null;
    }
    
    /**
     * Get the current user's credentials
     *
     * @return array{username: string|null, password: string|null}
     */
    public static function getCurrentCredentials(): array
    {
        return [
            'username' => $_SESSION['username'] ?? null,
            'password' => $_SESSION['password'] ?? null
        ];
    }
}
