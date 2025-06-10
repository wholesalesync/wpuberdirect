# Uber Direct for WooCommerce

Uber Direct for WooCommerce is a plugin that integrates Uber Direct's on-demand delivery services with WooCommerce stores. It enables merchants to offer real-time, on-demand delivery options to their customers, leveraging Uber's logistics network.

## Features
- Real-time Uber Direct shipping method for WooCommerce checkout
- On-demand and scheduled delivery support
- Age verification and notification for restricted products
- Free shipping options based on cart total or coupon
- Admin dashboard for delivery management
- Webhook support for delivery status updates
- Logging and debug tools for troubleshooting

## Requirements
- WordPress >= 5.5
- WooCommerce >= 5.5
- PHP >= 7.4
- Uber Direct API credentials (Client ID, Client Secret, Customer ID, Webhook Signature Secret)

## Installation
1. Copy the plugin files to your `wp-content/plugins/wc-uber` directory.
2. Activate the plugin via the WordPress admin dashboard.
3. Ensure WooCommerce is active before using this plugin.

## Configuration
1. Go to **WooCommerce > Settings > Shipping > Uber Direct**.
2. Enter your Uber Direct API credentials:
   - Merchant Phone
   - Customer ID
   - Client ID
   - Client Secret
   - Webhook Signature Secret
3. Configure additional options:
   - Additional Fees
   - Free Shipping (by cart total or coupon)
   - Merchant time zone
   - Age notification image and text
   - Enable logging for debugging
4. Save changes.
5. (Optional) Add the webhook URL (e.g., `https://yourstore.com/wp-json/uber/v2/webhook/`) to your Uber Direct dashboard for delivery status updates.

## Usage
- Customers will see "Uber Direct" as a shipping option at checkout if their address is serviceable.
- Admins can manage deliveries from the WooCommerce order page and the Uber Dashboard submenu.
- Delivery status and tracking URLs are displayed in order details.
- Age verification notifications are shown if configured.

## Directory Structure
```
assets/           # CSS, JS, images, and third-party plugins
includes/         # Core PHP classes (shipping, API, hooks, AJAX, logging)
templates/        # HTML templates for admin and frontend
wc-uber.php       # Main plugin file
```

## Security & Best Practices
- All API credentials are stored securely using WordPress options.
- Webhook endpoints validate input and permissions.
- Logging is optional and can be enabled for debugging.
- Assertions and error handling are used throughout the codebase.

## Testing
There are currently no automated tests included. Contributions to add unit or integration tests are welcome.

## Contribution
Contributions are encouraged! Please:
- Follow the existing coding style and use explicit variable names
- Prioritize performance and security
- Add tests for new features where possible
- Open issues or pull requests for discussion

## License
GPL v2 or later. See [LICENSE](http://www.gnu.org/licenses/gpl-2.0.txt).

## Support
For support, open an issue or contact the plugin author at [https://idelivernear.me](https://idelivernear.me). 