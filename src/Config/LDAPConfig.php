<?php

namespace App\Config;

class LDAPConfig
{
    const SERVER = 'ldap://somecorp.local';
    const PORT = 389;
    const DOMAIN = 'SOMECORP';
    const BASE_DN = 'DC=somecorp,DC=local';
    const EMAIL_DOMAIN = '@yourcompany.com';
    
    public static function getServer(): string
    {
        return getenv('LDAP_SERVER') ?: self::SERVER;
    }
    
    public static function getPort(): int
    {
        return (int)(getenv('LDAP_PORT') ?: self::PORT);
    }
    
    public static function getDomain(): string
    {
        return getenv('LDAP_DOMAIN') ?: self::DOMAIN;
    }
    
    public static function getBaseDN(): string
    {
        return getenv('LDAP_BASE_DN') ?: self::BASE_DN;
    }
    
    public static function getEmailDomain(): string
    {
        return getenv('LDAP_EMAIL_DOMAIN') ?: self::EMAIL_DOMAIN;
    }
    
    public static function getConnectionString(): string
    {
        return self::getServer() . ':' . self::getPort();
    }
}
