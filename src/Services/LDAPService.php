<?php

namespace App\Services;

use App\Config\LDAPConfig;
use Exception;

class LDAPService
{
    private $connection;
    private bool $bound = false;
    private string $username;
    private string $password;
    
    /**
     * Constructor
     *
     * @param string|null $username Username without domain
     * @param string|null $password User password
     */
    public function __construct(?string $username = null, ?string $password = null)
    {
        // Use provided credentials or get from session
        if ($username && $password) {
            $this->username = $username . '@' . LDAPConfig::getDomain() . '.local';
            $this->password = $password;
        } else {
            $creds = AuthService::getCurrentCredentials();
            $this->username = $creds['username'] . '@' . LDAPConfig::getDomain() . '.local';
            $this->password = $creds['password'];
        }
        
        $this->connection = ldap_connect(LDAPConfig::getConnectionString());
        if (!$this->connection) {
            throw new Exception('Could not connect to LDAP server');
        }
        
        // Set LDAP options
        ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->connection, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($this->connection, LDAP_OPT_NETWORK_TIMEOUT, 10);
    }
    
    /**
     * Bind to LDAP server
     *
     * @return bool
     * @throws Exception
     */
    public function bind(): bool
    {
        if (!$this->bound) {
            $this->bound = @ldap_bind($this->connection, $this->username, $this->password);
            if (!$this->bound) {
                throw new Exception('LDAP bind failed: ' . ldap_error($this->connection));
            }
        }
        return $this->bound;
    }
    
    /**
     * Search for users in LDAP
     *
     * @param string $searchTerm Search term
     * @param int $limit Maximum number of results
     * @return array Array of user data
     * @throws Exception
     */
    public function searchUsers(string $searchTerm, int $limit = 20): array
    {
        $this->bind();
        
        // Build fuzzy search filter with UPN restriction and user object filter
        $emailDomain = LDAPConfig::getEmailDomain();
        $filter = "(&" .
            "(objectClass=user)" .
            "(objectCategory=person)" .
            "(userPrincipalName=*{$emailDomain})" .
            "(|" .
                "(sAMAccountName=*{$searchTerm}*)" .
                "(givenName=*{$searchTerm}*)" .
                "(sn=*{$searchTerm}*)" .
                "(displayName=*{$searchTerm}*)" .
                "(mail=*{$searchTerm}*)" .
            ")" .
            ")";
        
        $attributes = [
            'sAMAccountName', 'givenName', 'sn', 'displayName', 'mail',
            'telephoneNumber', 'title', 'department', 'manager', 'distinguishedName',
            'userPrincipalName'
        ];
        
        $search = ldap_search($this->connection, LDAPConfig::getBaseDN(), $filter, $attributes);
        
        if (!$search) {
            throw new Exception('LDAP search failed: ' . ldap_error($this->connection));
        }
        
        $entries = ldap_get_entries($this->connection, $search);
        $users = [];
        
        for ($i = 0; $i < $entries['count'] && $i < $limit; $i++) {
            $entry = $entries[$i];
            
            // Double-check UPN filter on results
            $upn = $entry['userprincipalname'][0] ?? '';
            if (!str_ends_with(strtolower($upn), strtolower(LDAPConfig::getEmailDomain()))) {
                continue;
            }
            
            $users[] = $this->extractUserData($entry);
        }
        
        return $users;
    }
    
    /**
     * Get user by username
     *
     * @param string $username
     * @return array|null User data or null if not found
     * @throws Exception
     */
    public function getUserByUsername(string $username): ?array
    {
        $this->bind();
        $escapedUsername = ldap_escape($username, '', LDAP_ESCAPE_FILTER);
        $emailDomain = LDAPConfig::getEmailDomain();
        $filter = "(&" .
            "(objectClass=user)" .
            "(objectCategory=person)" .
            "(userPrincipalName=*{$emailDomain})" .
            "(sAMAccountName={$escapedUsername})" .
            ")";
        $attributes = [
            'sAMAccountName', 'givenName', 'sn', 'displayName', 'mail',
            'telephoneNumber', 'title', 'department', 'manager', 'distinguishedName',
            'userPrincipalName'
        ];
        
        $search = ldap_search($this->connection, LDAPConfig::getBaseDN(), $filter, $attributes);
        if (!$search) {
            throw new Exception('LDAP search failed: ' . ldap_error($this->connection));
        }
        
        $entries = ldap_get_entries($this->connection, $search);
        
        if ($entries['count'] === 0) {
            error_log("LDAP: No user found for username: {$username}");
            return null;
        }
        
        $entry = $entries[0];

        // Double-check UPN filter on result
        $upn = $entry['userprincipalname'][0] ?? '';
        if (!str_ends_with(strtolower($upn), strtolower($emailDomain))) {
            error_log("LDAP: User {$username} does not have {$emailDomain} UPN: {$upn}");
            return null;
        }

        $user = $this->extractUserData($entry);
        
        // Get manager display name if manager DN exists
        if (!empty($user['manager'])) {
            $managerInfo = $this->getUserByDN($user['manager']);
            if ($managerInfo) {
                $user['managerInfo'] = $managerInfo;
            }
        }

        return $user;
    }
    
    /**
     * Get user by Distinguished Name
     *
     * @param string $dn
     * @return array|null User data or null if not found
     */
    public function getUserByDN(string $dn): ?array
    {
        $this->bind();
        
        $attributes = ['sAMAccountName', 'displayName', 'givenName', 'sn'];
        $search = ldap_read($this->connection, $dn, '(objectClass=*)', $attributes);
        
        if (!$search) {
            return null;
        }
        
        $entries = ldap_get_entries($this->connection, $search);
        
        if ($entries['count'] === 0) {
            return null;
        }
        
        $entry = $entries[0];
        
        // Try both case variations for attribute names
        $username = '';
        if (isset($entry['samaccountname'][0])) {
            $username = $entry['samaccountname'][0];
        } elseif (isset($entry['sAMAccountName'][0])) {
            $username = $entry['sAMAccountName'][0];
        }
        
        return [
            'username' => $username,
            'displayName' => $entry['displayname'][0] ?? $entry['displayName'][0] ?? '',
            'firstName' => $entry['givenname'][0] ?? $entry['givenName'][0] ?? '',
            'lastName' => $entry['sn'][0] ?? '',
            'dn' => $dn
        ];
    }

    /**
     * Update user information in LDAP
     *
     * @param string $username
     * @param array $userData
     * @return bool
     * @throws Exception
     */
    public function updateUser(string $username, array $userData): bool
    {
        $this->bind();
        error_log("Updating user: $username");

        $user = $this->getUserByUsername($username);
        if (!$user) {
            throw new Exception('User not found');
        }
        error_log("Current LDAP record: " . json_encode($user));
        error_log("Incoming data: " . json_encode($userData));

        $lastName = trim((string)($userData['lastName'] ?? ''));
        if ($lastName === '') {
            throw new Exception('Last name cannot be empty');
        }

        $dn = $user['dn'];
        $modifications = [];

        $fieldMapping = [
            'firstName'  => 'givenName',
            'lastName'   => 'sn',
            'phone'      => 'telephoneNumber',
            'title'      => 'title',
            'department' => 'department',
        ];

        foreach ($fieldMapping as $formField => $ldapAttr) {
            $newValue = trim((string)($userData[$formField] ?? ''));
            $oldValue = trim((string)($user[$formField] ?? ''));

            error_log("$formField: old = '$oldValue' | new = '$newValue'");

            if ($newValue === '' && $oldValue !== '') {
                $modifications[$ldapAttr] = [];
            } elseif ($newValue !== '' && strcasecmp($newValue, $oldValue) !== 0) {
                $modifications[$ldapAttr] = $newValue;
            }
        }

        if (isset($userData['manager'])) {
            $newManagerInput = trim((string)$userData['manager']);
            $oldManagerDN = trim((string)($user['manager'] ?? ''));

            if ($newManagerInput === '') {
                if ($oldManagerDN !== '') {
                    $modifications['manager'] = [];
                }
            } else {
                if (strpos($newManagerInput, '=') !== false && strpos($newManagerInput, ',') !== false) {
                    $newManagerDN = $newManagerInput;
                } else {
                    $newManagerDN = $this->getDNByUsername($newManagerInput);
                    if ($newManagerDN === null) {
                        $msg = "Manager username '{$newManagerInput}' not found in LDAP";
                        error_log($msg);
                        throw new Exception($msg);
                    }
                }
                if (strcasecmp($newManagerDN, $oldManagerDN) !== 0) {
                    $modifications['manager'] = $newManagerDN;
                }
            }
        }

        if (isset($userData['firstName'], $userData['lastName'])) {
            $newDisplayName = trim($userData['firstName'] . ' ' . $userData['lastName']);
            $oldDisplayName = trim((string)($user['displayName'] ?? ''));
            if (strcasecmp($newDisplayName, $oldDisplayName) !== 0) {
                $modifications['displayName'] = $newDisplayName;
            }
        }

        if (empty($modifications)) {
            error_log("No modifications detected for user '{$username}'");
            throw new Exception('No modifications to make');
        }

        error_log("Modifications to apply for DN {$dn}: " . print_r($modifications, true));

        $result = ldap_modify($this->connection, $dn, $modifications);
        if (!$result) {
            $err = ldap_error($this->connection);
            error_log("LDAP modify failed: $err");
            throw new Exception("LDAP modify failed: $err");
        }

        return true;
    }
    
    /**
     * Get Distinguished Name by username
     *
     * @param string $username
     * @return string|null
     */
    private function getDNByUsername(string $username): ?string
    {
        $this->bind();
        $escapedUsername = ldap_escape($username, '', LDAP_ESCAPE_FILTER);
        $emailDomain = LDAPConfig::getEmailDomain();
        $filter = "(&" .
            "(objectClass=user)" .
            "(objectCategory=person)" .
            "(userPrincipalName=*{$emailDomain})" .
            "(sAMAccountName={$escapedUsername})" .
            ")";
        
        $attributes = ['distinguishedName', 'userPrincipalName'];
        
        $search = ldap_search($this->connection, LDAPConfig::getBaseDN(), $filter, $attributes);
        
        if (!$search) {
            error_log('LDAP search for DN failed: ' . ldap_error($this->connection));
            return null;
        }
        
        $entries = ldap_get_entries($this->connection, $search);
        
        if ($entries['count'] === 0) {
            return null;
        }
        
        // Double-check UPN filter on result
        $upn = $entries[0]['userprincipalname'][0] ?? '';
        if (!str_ends_with(strtolower($upn), strtolower($emailDomain))) {
            error_log("LDAP: User {$username} does not have {$emailDomain} UPN: {$upn}");
            return null;
        }
        
        return $entries[0]['distinguishedname'][0] ?? null;
    }
    
    /**
     * Extract user data from LDAP entry
     *
     * @param array $entry LDAP entry
     * @return array User data array
     */
    private function extractUserData(array $entry): array
    {
        return [
            'username' => $entry['samaccountname'][0] ?? '',
            'firstName' => $entry['givenname'][0] ?? '',
            'lastName' => $entry['sn'][0] ?? '',
            'displayName' => $entry['displayname'][0] ?? '',
            'email' => $entry['userprincipalname'][0] ?? '',
            'phone' => $entry['telephonenumber'][0] ?? '',
            'title' => $entry['title'][0] ?? '',
            'department' => $entry['department'][0] ?? '',
            'manager' => $entry['manager'][0] ?? '',
            'dn' => $entry['distinguishedname'][0] ?? ''
        ];
    }
    
    /**
     * Destructor - close LDAP connection
     */
    public function __destruct()
    {
        if ($this->connection) {
            ldap_close($this->connection);
        }
    }
}
