# LDAP Front-Office User Management System

This web application allows authorized users to view and modify **common Active Directory user object properties** (e.g., name, title, department, manager, etc.) through a modern, user-friendly web interface.

## 📋 Table of Contents
- [Features](#-features)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Deployment](#-deployment)
- [Active Directory Delegation](#-active-directory-delegation)
- [Usage](#-usage)
- [Security Considerations](#-security-considerations)
- [Troubleshooting](#-troubleshooting)

## ✨ Features

- **LDAP Authentication**: Secure login using Active Directory credentials
- **User Search**: Fast, fuzzy search across multiple user attributes
- **User Profile Management**: Edit common AD user properties
- **Manager Assignment**: Search and assign managers with autocomplete
- **Responsive Design**: Modern UI that works on desktop and mobile
- **Session Management**: Secure session handling with automatic timeouts
- **UPN Filtering**: Restrict access to specific email domain users

## 📦 Requirements

### Server Requirements
- **PHP 8.0 or higher**
- **Apache 2.4+** with `mod_rewrite` enabled
- **PHP Extensions:**
  - `ext-ldap` - LDAP functionality
  - `ext-session` - Session management
  - `ext-json` - JSON encoding/decoding
  - `ext-mbstring` - Multibyte string support

### Network Requirements
- Network connectivity to your Active Directory LDAP server (typically port 389 or 636 for LDAPS)
- Access to Active Directory domain controllers

### Composer Dependencies
- `slim/slim` ^4.0 - Micro-framework
- `slim/psr7` ^1.6 - PSR-7 implementation
- `slim/twig-view` ^3.3 - Twig template engine integration

## 🚀 Installation

### 1. Clone or Download the Repository
```bash
git clone https://github.com/jeffcaldwellca/LDAPFrontOffice.git
cd LDAPFrontOffice
```

### 2. Install Dependencies
```bash
composer install
```

If you don't have Composer installed, download it from [getcomposer.org](https://getcomposer.org/).

### 3. Verify PHP Extensions
Check that required PHP extensions are installed:
```bash
php -m | grep -E 'ldap|session|json|mbstring'
```

If any are missing, install them (example for Ubuntu/Debian):
```bash
sudo apt-get install php-ldap php-mbstring
sudo systemctl restart apache2
```

## ⚙️ Configuration

### LDAP Configuration Constants

Edit the `LDAPConfig` class in `src/Config/LDAPConfig.php` to match your Active Directory environment:

```php
<?php

namespace App\Config;

class LDAPConfig
{
    const SERVER = 'ldap://somecorp.local';     // Your LDAP server hostname or IP
    const PORT = 389;                            // 389 for LDAP, 636 for LDAPS
    const DOMAIN = 'SOMECORP';                   // Your AD domain name (NetBIOS)
    const BASE_DN = 'DC=somecorp,DC=local';      // Your LDAP base DN
    const EMAIL_DOMAIN = '@yourcompany.com';     // Email domain for UPN filtering
    
    // Static methods provide environment variable override support
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
}
```

#### Configuration Details:

| Constant | Description | Example |
|----------|-------------|---------|
| `SERVER` | LDAP server address (use `ldaps://` for SSL) | `ldap://dc01.example.com` |
| `PORT` | LDAP port (389 standard, 636 for LDAPS) | `389` |
| `DOMAIN` | Active Directory NetBIOS domain name | `EXAMPLE` |
| `BASE_DN` | Base Distinguished Name for LDAP searches | `DC=example,DC=com` |
| `EMAIL_DOMAIN` | Email domain suffix for user filtering | `@example.com` |

#### Environment Variable Override

You can override **any configuration value** using environment variables. This is recommended for production deployments:

| Environment Variable | Description | Example |
|---------------------|-------------|---------|
| `LDAP_SERVER` | LDAP server address | `ldap://dc01.example.com` |
| `LDAP_PORT` | LDAP port number | `389` or `636` |
| `LDAP_DOMAIN` | NetBIOS domain name | `EXAMPLE` |
| `LDAP_BASE_DN` | Base Distinguished Name | `DC=example,DC=com` |
| `LDAP_EMAIL_DOMAIN` | Email domain for filtering | `@example.com` |

**Apache (in VirtualHost or .htaccess):**
```apache
SetEnv LDAP_SERVER "ldap://dc01.example.com"
SetEnv LDAP_PORT "389"
SetEnv LDAP_DOMAIN "EXAMPLE"
SetEnv LDAP_BASE_DN "DC=example,DC=com"
SetEnv LDAP_EMAIL_DOMAIN "@example.com"
```

**PHP-FPM (.env file):**
```bash
LDAP_SERVER="ldap://dc01.example.com"
LDAP_PORT="389"
LDAP_DOMAIN="EXAMPLE"
LDAP_BASE_DN="DC=example,DC=com"
LDAP_EMAIL_DOMAIN="@example.com"
```

**Docker Environment Variables:**
```bash
docker run -d \
  -e LDAP_SERVER="ldap://dc01.example.com" \
  -e LDAP_PORT="389" \
  -e LDAP_DOMAIN="EXAMPLE" \
  -e LDAP_BASE_DN="DC=example,DC=com" \
  -e LDAP_EMAIL_DOMAIN="@example.com" \
  ldap-manager
```

### Logo Customization

Replace the logo file at `assets/Logosm.png` with your company logo. Recommended dimensions: 120x120 pixels or similar square aspect ratio.

## 🌐 Deployment

### Option 1: Apache/PHP Native Deployment (Recommended for Production)

#### 1. **Copy Files to Web Root**

```bash
# Copy application to Apache web directory
sudo cp -r /path/to/LDAPFrontOffice /var/www/html/ldap-manager
sudo chown -R www-data:www-data /var/www/html/ldap-manager
```

#### 2. **Configure Apache Virtual Host**

Create a new virtual host configuration:

```bash
sudo nano /etc/apache2/sites-available/ldap-manager.conf
```

Add the following configuration:

```apache
<VirtualHost *:80>
    ServerName ldap-manager.example.com
    DocumentRoot /var/www/html/ldap-manager
    
    # Optional: Force HTTPS (recommended)
    # RewriteEngine On
    # RewriteCond %{HTTPS} off
    # RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
    
    <Directory /var/www/html/ldap-manager>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Set environment variable (optional)
    SetEnv LDAP_EMAIL_DOMAIN "@example.com"
    
    # Security headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    
    ErrorLog ${APACHE_LOG_DIR}/ldap-manager-error.log
    CustomLog ${APACHE_LOG_DIR}/ldap-manager-access.log combined
</VirtualHost>
```

#### 3. **Enable Required Apache Modules**

```bash
sudo a2enmod rewrite
sudo a2enmod headers
sudo systemctl restart apache2
```

#### 4. **Enable the Site**

```bash
sudo a2ensite ldap-manager.conf
sudo systemctl reload apache2
```

#### 5. **SSL/TLS Configuration (Recommended)**

For HTTPS support, create an SSL virtual host:

```bash
sudo apt-get install certbot python3-certbot-apache
sudo certbot --apache -d ldap-manager.example.com
```

Or manually configure SSL:

```apache
<VirtualHost *:443>
    ServerName ldap-manager.example.com
    DocumentRoot /var/www/html/ldap-manager
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/your-cert.crt
    SSLCertificateKeyFile /etc/ssl/private/your-key.key
    SSLCertificateChainFile /etc/ssl/certs/chain.crt
    
    # Same Directory configuration as above
    <Directory /var/www/html/ldap-manager>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    SetEnv LDAP_EMAIL_DOMAIN "@example.com"
    
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    
    ErrorLog ${APACHE_LOG_DIR}/ldap-manager-ssl-error.log
    CustomLog ${APACHE_LOG_DIR}/ldap-manager-ssl-access.log combined
</VirtualHost>
```

### Option 2: PHP Built-in Server (Development Only)

For testing and development purposes only:

```bash
php -S localhost:8080 -t .
```

**⚠️ WARNING**: The built-in PHP server is **NOT** suitable for production use!

### Option 3: Docker Deployment

Create a `Dockerfile`:

```dockerfile
FROM php:8.1-apache

# Install LDAP extension
RUN apt-get update && apt-get install -y \
    libldap2-dev \
    && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install ldap

# Enable Apache modules
RUN a2enmod rewrite headers

# Copy application
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

EXPOSE 80
```

Build and run:
```bash
docker build -t ldap-manager .
docker run -d -p 8080:80 -e LDAP_EMAIL_DOMAIN="@example.com" ldap-manager
```

## 🔐 Active Directory Delegation

To allow non-admin users to use this app for editing AD user objects, you must delegate the proper permissions:

### ✅ Minimum Required Permissions

Grant **Read** and **Write** access to the following AD object attributes:

| Attribute | Description |
|-----------|-------------|
| `givenName` | First Name |
| `sn` | Last Name (Surname) |
| `displayName` | Display Name |
| `mail` | Email Address |
| `userPrincipalName` | User Principal Name |
| `telephoneNumber` | Phone Number |
| `title` | Job Title |
| `department` | Department |
| `manager` | Manager DN |

### Delegation Steps (Active Directory Users and Computers)

1. Open **Active Directory Users and Computers**
2. Right-click the desired **OU** containing users to manage
3. Choose **Delegate Control...**
4. Click **Next**, then **Add** to select the user(s) or group who will use the app
5. Click **Next**, select **Create a custom task to delegate**
6. Select **Only the following objects in the folder** → **User objects**
7. Check **Property-specific** at the bottom
8. Scroll down and select the attributes listed above
9. Check both **Read** and **Write** for each attribute
10. Click **Next**, then **Finish**

### PowerShell Delegation Script (Alternative)

```powershell
# Example: Grant permissions to a security group
$OU = "OU=Users,DC=example,DC=com"
$Group = "LDAP-Managers"
$Properties = @(
    "givenName",
    "sn",
    "displayName",
    "mail",
    "telephoneNumber",
    "title",
    "department",
    "manager"
)

# Get the OU and group objects
$OUObj = Get-ADOrganizationalUnit -Identity $OU
$GroupObj = Get-ADGroup -Identity $Group

# Set ACL for each property
foreach ($prop in $Properties) {
    dsacls $OU /G "$($GroupObj.SID):RPWP;$prop;user"
}
```

### ⚠️ Important Notes
- Without proper delegation, standard users will **fail to modify** AD user objects
- Always follow least-privilege best practices
- Test with a non-privileged account to verify delegation is working
- Consider creating a dedicated security group for LDAP managers

## 📖 Usage

### Login
1. Navigate to the application URL
2. Enter your Active Directory username (without domain)
3. Enter your AD password
4. Click **Sign In**

### Search for Users
1. After login, use the search bar at the top
2. Enter name, username, email, or department
3. Results appear in real-time as you type
4. Click on a user to view/edit their details

### Edit User Information
1. Select a user from search results
2. Modify editable fields (grayed-out fields are read-only)
3. Use the **Manager** field to search and assign a manager
4. Click **Save Changes** to update Active Directory
5. Click **Cancel** to discard changes

### Logout
Click the **Logout** button in the top-right corner.

## 🔒 Security Considerations

### Session Security
- Sessions are stored server-side with PHP's native session handling
- **Production Recommendation**: Store passwords encrypted or use a credential vault
- Session timeout is controlled by PHP's `session.gc_maxlifetime`

### LDAP Security
- **Use LDAPS (LDAP over SSL)**: Change `SERVER` to `ldaps://` and `PORT` to `636`
- Ensure certificate validation in production
- Never log LDAP passwords to files or error logs

### Apache Security
- Disable directory listing (already set with `-Indexes`)
- Use HTTPS in production (SSL/TLS certificates)
- Implement rate limiting for login attempts
- Consider IP whitelisting if appropriate

### Recommended PHP Settings

In `php.ini` or `.htaccess`:
```ini
; Session security
session.cookie_httponly = 1
session.cookie_secure = 1        ; Only if using HTTPS
session.use_strict_mode = 1

; Error handling
display_errors = Off
log_errors = On
error_log = /var/log/php/ldap-manager-errors.log

; File uploads disabled (not needed)
file_uploads = Off
```

## 🛠️ Troubleshooting

### Common Issues

#### 1. **"LDAP bind failed" error**
- **Cause**: Incorrect LDAP configuration or credentials
- **Solution**: 
  - Verify `LDAPConfig::SERVER`, `DOMAIN`, and `BASE_DN` are correct
  - Test LDAP connection: `ldapsearch -x -H ldap://yourserver -D "user@domain" -W -b "DC=example,DC=com"`
  - Check firewall rules allow connection to LDAP port (389 or 636)

#### 2. **"No modifications to make" when saving**
- **Cause**: No fields were actually changed
- **Solution**: Modify at least one field before saving

#### 3. **"LDAP modify failed: Insufficient access rights"**
- **Cause**: User lacks write permissions in Active Directory
- **Solution**: Follow [Active Directory Delegation](#-active-directory-delegation) steps

#### 4. **500 Internal Server Error**
- **Cause**: PHP error or missing extension
- **Solution**: 
  - Check Apache error log: `sudo tail -f /var/log/apache2/error.log`
  - Verify PHP LDAP extension: `php -m | grep ldap`
  - Check file permissions: `sudo chown -R www-data:www-data /var/www/html/ldap-manager`

#### 5. **Manager search returns no results**
- **Cause**: `EMAIL_DOMAIN` filter too restrictive
- **Solution**: Verify users have matching UPN suffix or adjust `EMAIL_DOMAIN` constant

#### 6. **Session expires too quickly**
- **Cause**: Low `session.gc_maxlifetime` value
- **Solution**: Increase in `php.ini`: `session.gc_maxlifetime = 3600` (1 hour)

### Enable Debug Mode

For troubleshooting, enable PHP error display (development only):

```php
// Add to top of index.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

**⚠️ Disable this in production!**

### Check LDAP Connection

Test LDAP connectivity from command line:
```bash
ldapsearch -x -H ldap://yourserver:389 -D "user@domain.local" -W \
  -b "DC=domain,DC=local" "(sAMAccountName=testuser)"
```

## 📝 License

GPL-3.0. See [LICENSE](LICENSE).

## 👤 Author

**Jeff Caldwell** - [@jeffcaldwellca](https://github.com/jeffcaldwellca)

## 🤝 Contributing

Contributions, issues, and feature requests are welcome!

---

**Need Help?** Check the [Troubleshooting](#-troubleshooting) section or open an issue on GitHub.
