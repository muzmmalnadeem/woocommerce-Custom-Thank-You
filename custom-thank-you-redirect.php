<?php
/**
 * Plugin Name: Custom Thank You Redirect
 * Description: Redirects users to specific thank you pages based on purchased products.
 * Version: 1.3
 * Author: Borderless Solutions
 * Author URI: https://borderlesssolutions.net.au/
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue Font Awesome for the icon
add_action('admin_enqueue_scripts', 'cty_enqueue_font_awesome');
function cty_enqueue_font_awesome() {
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css');
    wp_enqueue_style('cty-custom-style', plugin_dir_url(__FILE__) . 'style.css'); // Custom styles
}

// Create a menu item in the WordPress admin
add_action('admin_menu', 'cty_redirect_menu');
function cty_redirect_menu() {
    add_menu_page(
        'Thank You Redirect Settings',
        'Thank You Redirect <span class="dashicons dashicons-external"></span>',
        'manage_options',
        'cty-redirect-settings',
        'cty_redirect_settings_page',
        'dashicons-external',
        56
    );
}

// Render the settings page
function cty_redirect_settings_page() {
    ?>
    <div class="wrap">
        <h1>Custom Thank You Redirect Settings</h1>
        <p>Author: <a href="https://borderlesssolutions.net.au/" target="_blank">Borderless Solutions</a></p>
        <form method="post" action="">
            <?php
            // Check if the form was submitted
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                // Save selected products and thank you pages
                $product_ids = isset($_POST['cty_product_ids']) ? array_map('sanitize_text_field', $_POST['cty_product_ids']) : [];
                $thank_you_pages = isset($_POST['cty_thank_you_pages']) ? array_map('sanitize_text_field', $_POST['cty_thank_you_pages']) : [];
                $general_thank_you_page = sanitize_text_field($_POST['cty_general_thank_you_page']);

                // Update options
                update_option('cty_product_ids', $product_ids);
                update_option('cty_thank_you_pages', $thank_you_pages);
                update_option('cty_general_thank_you_page', $general_thank_you_page);

                echo '<div class="updated"><p>Settings saved!</p></div>';
            }

            // Get the saved options
            $product_ids = get_option('cty_product_ids', []);
            $thank_you_pages = get_option('cty_thank_you_pages', []);
            $general_thank_you_page = get_option('cty_general_thank_you_page', '');
            ?>

            <div id="custom-redirects">
                <h2>Custom Redirection 1</h2>
                <div class="redirect-row">
                    <label for="cty_product_ids[]">Select Product</label>
                    <select name="cty_product_ids[]" class="product-select">
                        <option value="">Select a product</option>
                        <?php
                        $products = wc_get_products(array('limit' => -1));
                        foreach ($products as $product) {
                            $selected = (isset($product_ids[0]) && $product->get_id() == $product_ids[0]) ? 'selected' : '';
                            echo '<option value="' . esc_attr($product->get_id()) . '" ' . $selected . '>' . esc_html($product->get_name()) . '</option>';
                        }
                        ?>
                    </select>
                    <label for="cty_thank_you_pages[]">Select Thank You Page</label>
                    <select name="cty_thank_you_pages[]" class="thank-you-select">
                        <option value="">Select a thank you page</option>
                        <?php
                        $pages = get_pages();
                        foreach ($pages as $page) {
                            $selected = (isset($thank_you_pages[0]) && $page->ID == $thank_you_pages[0]) ? 'selected' : '';
                            echo '<option value="' . esc_attr($page->ID) . '" ' . $selected . '>' . esc_html($page->post_title) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div id="additional-redirects"></div>

                <button type="button" class="button" id="add-more">Add More</button>
            </div>

            <h2>General Thank You Page</h2>
            <select name="cty_general_thank_you_page">
                <option value="">Select a general thank you page</option>
                <?php
                foreach ($pages as $page) {
                    $selected = ($page->ID == $general_thank_you_page) ? 'selected' : '';
                    echo '<option value="' . esc_attr($page->ID) . '" ' . $selected . '>' . esc_html($page->post_title) . '</option>';
                }
                ?>
            </select>

            <?php submit_button(); ?>
        </form>

        <script>
            document.getElementById('add-more').addEventListener('click', function() {
                const index = document.querySelectorAll('.redirect-row').length; // Get current number of fields

                const newRedirectRow = document.createElement('div');
                newRedirectRow.classList.add('redirect-row');
                newRedirectRow.innerHTML = `
                    <h2>Custom Redirection ${index + 1}</h2>
                    <label for="cty_product_ids[]">Select Product</label>
                    <select name="cty_product_ids[]" class="product-select">
                        <option value="">Select a product</option>
                        <?php
                        foreach ($products as $product) {
                            echo '<option value="' . esc_attr($product->get_id()) . '">' . esc_html($product->get_name()) . '</option>';
                        }
                        ?>
                    </select>
                    <label for="cty_thank_you_pages[]">Select Thank You Page</label>
                    <select name="cty_thank_you_pages[]" class="thank-you-select">
                        <option value="">Select a thank you page</option>
                        <?php
                        foreach ($pages as $page) {
                            echo '<option value="' . esc_attr($page->ID) . '">' . esc_html($page->post_title) . '</option>';
                        }
                        ?>
                    </select>
                `;

                document.getElementById('additional-redirects').appendChild(newRedirectRow);
            });
        </script>

        <style>
            .redirect-row {
                display: flex;
                flex-direction: column;
                margin-bottom: 20px;
                border: 1px solid #ccc;
                padding: 15px;
                border-radius: 5px;
                background-color: #f9f9f9;
            }

            .redirect-row label {
                margin-bottom: 5px;
                font-weight: bold;
            }

            .redirect-row select {
                margin-bottom: 10px;
                padding: 5px;
            }

            #custom-redirects h2 {
                margin-top: 30px;
            }
        </style>
    </div>
    <?php
}

// Redirect to the specified thank you page based on product IDs
add_action('woocommerce_thankyou', 'cty_redirect_based_on_product');
function cty_redirect_based_on_product($order_id) {
    $order = wc_get_order($order_id);
    $product_ids = get_option('cty_product_ids', []);
    $thank_you_pages = get_option('cty_thank_you_pages', []);
    $general_thank_you_page = get_option('cty_general_thank_you_page');

    $redirect_url = null;

    foreach ($order->get_items() as $item) {
        if (in_array($item->get_product_id(), $product_ids)) {
            $index = array_search($item->get_product_id(), $product_ids);
            if ($index !== false && isset($thank_you_pages[$index])) {
                $redirect_url = get_permalink($thank_you_pages[$index]);
                break; // Stop after finding the first match
            }
        }
    }

    // Fallback to the general thank you page if no specific redirect was found
    if (!$redirect_url) {
        $redirect_url = get_permalink($general_thank_you_page);
    }

    if ($redirect_url) {
        wp_redirect($redirect_url);
        exit;
    }
}
