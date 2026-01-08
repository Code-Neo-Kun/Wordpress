# License Server - WordPress Plugin Licensing System

A **production-ready, enterprise-grade WordPress plugin licensing system** for selling paid WordPress plugins with centralized license management.

## Features

✅ **Multi-Plugin Support** - Manage unlimited paid plugins from one server  
✅ **Flexible Licensing** - Single, Bundle, or Lifetime licenses  
✅ **Domain Binding** - Enforce site limits and prevent sharing  
✅ **Auto-Updates** - Only show updates to active licenses  
✅ **User Dashboard** - Customers manage licenses themselves  
✅ **Admin Panel** - Full control over licenses and versions  
✅ **REST API** - Secure, rate-limited endpoints  
✅ **Audit Logging** - Complete history of all actions  
✅ **Email Notifications** - Renewal reminders and expiration notices  
✅ **Security First** - Hashed keys, HTTPS, IP tracking, rate limiting

## Quick Start

### 1. Install License Server

```bash
# Copy to WordPress
cp -r license-server /path/to/wordpress/wp-content/plugins/

# Activate
wp plugin activate license-server
```

### 2. Create Your First License

1. Go to WordPress Admin → License Server → Licenses
2. Click "Add New License"
3. Enter customer email
4. Select plan type (Single/Bundle/Lifetime)
5. Set domain limit (e.g., 1-5)
6. Click "Create License"
7. Share the generated key with customer

### 3. Upload Plugin Versions

1. Go to Admin → License Server → Upload Plugins
2. Enter plugin slug (e.g., `my-awesome-plugin`)
3. Enter version (e.g., `1.0.0`)
4. Provide download URL (CDN/cloud storage)
5. Add changelog
6. Click "Upload Version"

### 4. Integrate Your Plugin

Add to your paid plugin's main file:

```php
// At top of plugin
require_once 'includes/Plugin_License.php';

class My_Plugin {
    private $license;

    public function __construct() {
        $this->license = new Plugin_License(
            'my-awesome-plugin',
            'https://license.example.com'
        );
    }

    public function init() {
        if ( ! $this->license->is_active() ) {
            add_action( 'admin_notices', [ $this, 'license_required_notice' ] );
            return;
        }

        // Load premium features
        $this->load_features();
    }

    public function license_required_notice() {
        ?>
        <div class="notice notice-error">
            <p>This plugin requires an active license.</p>
            <p><a href="<?php echo admin_url( 'admin.php?page=my-plugin-license' ); ?>">
                Activate License
            </a></p>
        </div>
        <?php
    }
}
```

### 5. Add License Settings Page

```php
public function add_license_page() {
    add_menu_page(
        'My Plugin License',
        'My Plugin',
        'manage_options',
        'my-plugin-license',
        [ $this, 'render_license_page' ]
    );
}

public function render_license_page() {
    if ( isset( $_POST['license_key'] ) ) {
        $this->license->set_license_key( $_POST['license_key'] );
    }

    $is_active = $this->license->is_active();
    $key = $this->license->get_license_key();

    ?>
    <div class="wrap">
        <h1><?php echo esc_html( 'License Settings' ); ?></h1>

        <form method="post">
            <input type="password" name="license_key"
                   value="<?php echo esc_attr( $key ); ?>"
                   class="regular-text">
            <button type="submit" class="button button-primary">
                Activate License
            </button>
        </form>

        <?php if ( $is_active ) : ?>
            <p style="color: green;">✓ License is active</p>
        <?php else : ?>
            <p style="color: red;">✗ License is not active</p>
        <?php endif; ?>
    </div>
    <?php
}
```

### 6. User Dashboard

Add shortcode to any page:

```
[license_dashboard]
```

Users see their licenses, domains, and can manage activations.

---

## Architecture

```
LICENSE SERVER (WordPress)
├── Admin Dashboard (view stats)
├── License Manager (CRUD operations)
├── Plugin Uploader (version management)
├── REST API (plugin communication)
├── User Dashboard (frontend)
└── Database (MySQL)

Each PAID PLUGIN includes:
├── Plugin_License class (communicates with server)
├── License settings page
└── Feature locker (checks license status)
```

---

## Database Tables

| Table                     | Purpose                             |
| ------------------------- | ----------------------------------- |
| `wp_ls_licenses`          | Main license records                |
| `wp_ls_license_plugins`   | Which plugins each license includes |
| `wp_ls_activated_domains` | Active installations per license    |
| `wp_ls_plugin_versions`   | Downloadable versions               |
| `wp_ls_activation_logs`   | Audit trail of all actions          |
| `wp_ls_license_history`   | License change history              |

---

## REST API Endpoints

```
POST   /wp-json/license-server/v1/verify         - Check if license is valid
POST   /wp-json/license-server/v1/activate       - Activate a domain
POST   /wp-json/license-server/v1/deactivate     - Deactivate a domain
POST   /wp-json/license-server/v1/check-update   - Check for plugin updates
GET    /wp-json/license-server/v1/download       - Download plugin version
POST   /wp-json/license-server/v1/renew          - Renew a license
```

Example request:

```bash
curl -X POST https://license.example.com/wp-json/license-server/v1/verify \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "XXXX-XXXX-XXXX-XXXX",
    "plugin_slug": "my-awesome-plugin",
    "domain": "example.com"
  }'
```

---

## Security

✅ License keys are hashed in database (SHA256)  
✅ Full keys shown only once at creation  
✅ HTTPS required in production  
✅ Rate limiting (30 verifications/hour per IP)  
✅ Domain binding prevents sharing  
✅ IP tracking and activation tokens  
✅ Complete audit logs  
✅ Constant-time comparison for hashes

---

## Configuration

Edit `license-server.php`:

```php
define( 'LS_HASH_ALGO', 'sha256' );           // Hashing algorithm
define( 'LS_API_VERSION', 'v1' );             // API version
define( 'LS_MAX_DOMAINS_DEFAULT', 5 );        // Default domain limit per license
define( 'LS_LICENSE_VALIDITY_DAYS', 365 );    // Default license duration (days)
```

---

## Plugin License Class API

```php
$license = new Plugin_License( 'plugin-slug', 'https://license-server.com' );

// Set/get license key
$license->set_license_key( 'XXXX-XXXX-XXXX-XXXX' );
$key = $license->get_license_key();

// Check status
$is_active = $license->is_active();
$info = $license->get_info();

// Get expiration
$days = $license->get_days_until_expiration();
$is_expiring_soon = $license->is_expiring_soon( 30 );  // Check if < 30 days

// Check for updates
$update = $license->check_updates( '1.0.0' );
// Returns: [
//   'version' => '1.1.0',
//   'download' => 'https://...',
//   'changelog' => '...',
//   'requires_wp' => '5.0',
//   'requires_php' => '7.4'
// ]

// License actions
$license->deactivate();  // Deactivate license
```

---

## Example Paid Plugin

See `sample-paid-plugin.php` for a complete example of integrating licensing.

---

## Admin Functions

**Create License (programmatically):**

```php
$license = \LicenseServer\Models\License::create( [
    'user_id'     => 123,
    'plan_type'   => 'bundle',
    'max_domains' => 5,
    'expires_at'  => date( 'Y-m-d H:i:s', strtotime( '+1 year' ) ),
] );

$license->add_plugin( 'my-awesome-plugin' );
$license->add_plugin( 'another-plugin' );

echo $license->get_license_key();  // Show once!
```

**Suspend License:**

```php
$license = new \LicenseServer\Models\License( $license_id );
$license->suspend( 'Refund issued' );
```

**Renew License:**

```php
$license->renew( 365 );  // Renew for 365 days
```

---

## Troubleshooting

### License won't activate

1. Check license key format: `XXXX-XXXX-XXXX-XXXX`
2. Verify status is 'active' (not suspended/expired)
3. Confirm plugin slug matches
4. Ensure domain is correct
5. Check that server is reachable
6. Verify HTTPS is working

### Updates not showing

1. License must be active
2. Plugin version must exist in database
3. Current version must be lower than available
4. Check URL is correct in admin

### Domain limit exceeded

1. Deactivate old domain from user dashboard
2. Or admin deactivates from license manager
3. Or renew with higher domain limit

---

## Documentation

Complete documentation available in `DOCUMENTATION.md`:

- Full API reference
- Database schema explanation
- Security best practices
- Edge case handling
- Integration guide
- Troubleshooting guide

---

## Requirements

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+ or MariaDB 10.2+
- HTTPS (required for production)

---

## File Structure

```
license-server/
├── license-server.php              # Main plugin file
├── DOCUMENTATION.md                # Full documentation
├── includes/
│   ├── Database/
│   │   ├── Schema.php
│   │   └── Installer.php
│   ├── Security/
│   │   ├── Encryption.php
│   │   ├── Validation.php
│   │   └── RateLimiter.php
│   ├── Models/
│   │   ├── License.php
│   │   ├── PluginVersion.php
│   │   └── ActivatedDomain.php
│   ├── API/
│   │   └── REST_API.php
│   ├── Admin/
│   │   ├── Admin_Dashboard.php
│   │   ├── License_Manager.php
│   │   └── Plugin_Uploader.php
│   ├── Frontend/
│   │   ├── User_Dashboard.php
│   │   └── Account_Endpoints.php
│   ├── Utilities/
│   │   ├── Logger.php
│   │   └── Email_Handler.php
│   └── Plugin_License.php          # Reusable class for paid plugins
├── assets/
│   ├── admin/
│   │   ├── css/admin.css
│   │   └── js/admin.js
│   └── frontend/
│       ├── css/frontend.css
│       └── js/frontend.js
└── logs/                           # Generated at runtime
```

---

## License

GPL-2.0+

---

## Support

For detailed documentation, troubleshooting, and advanced configuration, see `DOCUMENTATION.md`.

---

**Version:** 1.0.0  
**Build Date:** 2025  
**Status:** Production Ready
