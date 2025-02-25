=== Attributes User Access ===
Contributors: attributeswp
Tags: authentication, login, security, access control
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

**Attributes User Access** is a lightweight and flexible authentication solution for WordPress designed for greater control over login processes.


== Core Features ==

- **Custom Login Page Creation**
  - Generate fully integrated login pages with WordPress.
  - Use shortcode-based forms for easy theme compatibility.
  - Automatically adapts to WordPress core updates.

- **Flexible Login Redirection**
  - Redirect native WordPress login requests.
  - Define role-based and context-aware redirection rules.

- **Developer-Focused Architecture**
  - PSR-4 autoloading and object-oriented design.
  - Extensible with action and filter hooks.
  - Modular components for easy customization.

- **Performance Optimization**
  - Load scripts selectively to minimize impact.
  - Use transients for caching and improved efficiency.
  - Deploy minified assets with source mapping for production.

---

== Premium Features ==

Upgrade to **[Attributes User Access Premium](https://attributeswp.com/premium)** for enhanced security and authentication features:

- **Premium Forms**: Custom **registration, lost password, and reset password** forms.
- **Two-Factor Authentication (2FA)**: Secure logins with app or email verification.
- **Social Login Integration**: Allow users to log in via **Google, Facebook, Twitter, and more**.
- **Custom Login Fields**: Extend login forms with additional fields.
- **Enhanced Security**:
  - Brute force protection.
  - IP management and login activity logs.
  - Email notifications for suspicious login attempts.
- **Custom Email Templates**: Personalize authentication-related email notifications.
- **Priority Support**: Get direct assistance for premium users.

---

== Installation ==

**Standard Installation:**
1. Upload the `attributes-user-access` folder to `/wp-content/plugins/`
2. Activate the plugin via **Plugins** in WordPress.
3. Navigate to **User Access** in the WordPress admin panel.
4. Configure the settings and create authentication pages.

**Manual Installation:**
1. Download the plugin ZIP file.
2. Log in to your WordPress admin panel.
3. Go to **Plugins > Add New** and upload the ZIP file.
4. Activate and configure the plugin.

== Minimum Requirements ==
- **WordPress** 5.8 or higher
- **PHP** 7.4 or higher
- **MySQL** 5.6 or higher
- JavaScript-enabled browser

---

== Configuration ==

== Creating a Custom Login Page ==
1. Go to **Login Settings** in WordPress Admin.
2. Click **Create Page** to generate a login page.
3. The page automatically includes a login form via shortcode.
4. Customize the page title and slug as needed.

== Setting Up Login Redirection ==
1. Enable redirection to override the default WordPress login page.
2. Set up custom redirection rules (e.g., redirect users to a specific page after login).
3. Use hooks and filters to extend redirection logic.

---

== Developer Documentation ==

== Available Hooks ==

**Actions**
| Action | Description |
|--------|-------------|
| `attributes_before_login_form` | Fires before rendering the login form. |
| `attributes_after_login_form` | Fires after rendering the login form. |
| `attributes_login_failed` | Fires when a login attempt fails. |
| `attributes_successful_login` | Fires after successful authentication. |

**Filters**
| Filter | Description |
|--------|-------------|
| `attributes_login_form_fields` | Modify the login form fields. |
| `attributes_login_redirect` | Customize login redirection. |
| `attributes_login_error_message` | Modify login error messages. |

== Shortcode Usage ==

Basic login form:
[attributes_login_form]

With parameters:
[attributes_login_form redirect="/dashboard" remember="false"]

Available parameters:
| Parameter | Description | Default |
|-----------|-------------|---------|
| `redirect` | Target URL after login | Dashboard |
| `remember` | Show "Remember Me" checkbox | true |
| `form_id` | Custom form identifier | attributes_login_form |

== Programmatic Implementation ==

Use directly in theme files:
<?php if (!is_user_logged_in()): ?>
    <?php echo do_shortcode('[attributes_login_form]'); ?>
<?php else: ?>
    <p>You are already logged in. <a href="<?php echo wp_logout_url(get_permalink()); ?>">Logout</a></p>
<?php endif; ?>

== Custom Styling ==

Override the default styles in your theme's CSS:
/* Form Container */
.attributes-form-wrapper {
    /* Custom styles */
}

/* Input Fields */
.attributes-input {
    /* Custom styles */
}

---

== Frequently Asked Questions ==

== Is this plugin compatible with my theme? ==
Yes, **Attributes User Access** is designed to work with any **properly coded** WordPress theme.

== Can I customize the login form design? ==
Yes! You can:
1. Use **CSS** to override styles.
2. Modify form output with **filters**.
3. Override templates in your theme for full customization.

== How can I extend the plugin? ==
Developers can extend functionality using:
- WordPress action and filter hooks.
- Custom template overrides.
- Add-on plugin development.
- Premium feature integrations.

---

== Changelog ==

== 1.0.0 ==
- Initial release.
- Custom login page generation.
- Role-based redirection.

---

== Upgrade Notice ==

== 1.0.0 ==
**Initial release**: Provides custom authentication pages, secure login management, and advanced redirection.

---

== Support ==

- **Documentation**: [https://attributeswp.com/docs](https://attributeswp.com/docs)
- **GitHub Issues**: [https://github.com/attributeswp/attributes-user-access/issues](https://github.com/attributeswp/attributes-user-access/issues)
- **Support Forums**: [https://wordpress.org/support/plugin/attributes-user-access](https://wordpress.org/support/plugin/attributes-user-access)
- **Premium Support**: [https://attributeswp.com/support](https://attributeswp.com/support)
