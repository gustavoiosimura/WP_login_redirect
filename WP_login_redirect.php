<?php
/*
Plugin Name: Universal Role Redirect
Plugin URI: https://github.com/gustavoiosimrua
Description: Redirects users based on roles to customized URLs, allowing dynamic user-specific parameters in URLs.
Version: 1.0
Author: Gustavo Iosimura
Author URI: https://github.com/gustavoiosimrua
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('admin_menu', 'urr_add_admin_menu_page');

function urr_add_admin_menu_page() {
    add_menu_page(
        'Role Redirect Settings',
        'Role Redirect',
        'manage_options',
        'role-redirect-settings',
        'urr_render_settings_page'
    );
}

function urr_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Role Redirect Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('urr_role_redirect_settings');
            do_settings_sections('role-redirect-settings');
            submit_button();
            ?>
        </form>
    </div>
    <style>
        .urr-role-container {
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            padding: 20px;
            border-radius: 5px;
        }
        .urr-role-header {
            font-size: 16px;
            margin-bottom: 10px;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
    </style>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            function toggleFieldVisibility(role, type) {
                const paramSelect = $(`select[name="${role}_redirect_rule[param]"]`);
                const pathInput = $(`input[name="${role}_redirect_rule[path]"]`);
                const pageSelect = $(`select[name="${role}_redirect_rule[page]"]`);
                const urlInput = $(`input[name="${role}_redirect_rule[url]"]`);

                paramSelect.hide();
                pathInput.hide();
                pageSelect.hide();
                urlInput.hide();

                switch (type) {
                    case 'user_page':
                        paramSelect.show();
                        pathInput.show();
                        break;
                    case 'specific_page':
                        pageSelect.show();
                        break;
                    case 'custom_url':
                        urlInput.show();
                        break;
                }
            }

            $('select[name$="[type]"]').on('change', function() {
                const role = $(this).attr('name').split('_')[0];
                const selectedType = $(this).val();
                toggleFieldVisibility(role, selectedType);
            });

            <?php
            $roles = wp_roles()->roles;
            foreach ($roles as $role => $details) {
                $option = get_option($role . '_redirect_rule');
                $type = $option['type'] ?? '';
                echo "toggleFieldVisibility('{$role}', '{$type}');";
            }
            ?>
        });
    </script>
    <?php
}

add_action('admin_init', 'urr_register_role_redirect_settings');

function urr_register_role_redirect_settings() {
    add_settings_section(
        'urr_role_redirect_section',
        'Redirection Rules',
        'urr_role_redirect_section_cb',
        'role-redirect-settings'
    );

    $roles = wp_roles()->roles;
    foreach ($roles as $role => $details) {
        add_settings_field(
            $role . '_redirect_rule',
            '',  
            'urr_role_redirect_field_cb',
            'role-redirect-settings',
            'urr_role_redirect_section',
            [
                'role' => $role,
                'title' => $details['name'] . ' Role Redirection' // Passed for use inside the container
            ]
        );
        register_setting('urr_role_redirect_settings', $role . '_redirect_rule', 'urr_sanitize_redirect_rule');
    }
}



function urr_role_redirect_section_cb() {
    echo '<p>Configure the redirection rules for each user role.</p>';
}

function urr_role_redirect_field_cb($args) {
    $role = $args['role'];
    $title = $args['title'] ?? 'Default Title'; // Provide a default if not set
    $option = get_option($role . '_redirect_rule');
    $type = $option['type'] ?? 'user_page';
    $param = $option['param'] ?? 'username';
    $path = $option['path'] ?? '/{username}';
    $page_id = $option['page'] ?? '';
    $url = $option['url'] ?? '';

    echo "<div class='urr-role-container'>";
    echo "<div class='urr-role-header'>{$title}</div>"; // Using the title safely
    echo "<select class='redirectiontype' name='{$role}_redirect_rule[type]'>...";
    echo "<option value='user_page' " . selected($type, 'user_page', false) . ">User page</option>";
    echo "<option value='specific_page' " . selected($type, 'specific_page', false) . ">Specific page within the website</option>";
    echo "<option value='custom_url' " . selected($type, 'custom_url', false) . ">Custom URL</option>";
    echo "</select>";

    echo "<select name='{$role}_redirect_rule[param]' style='display:none;'>";
    echo "<option value='username' " . selected($param, 'username', false) . ">Username</option>";
    echo "<option value='userid' " . selected($param, 'userid', false) . ">User ID</option>";
    echo "<option value='email' " . selected($param, 'email', false) . ">Email</option>";
    echo "</select>";

    echo "<input type='text' name='{$role}_redirect_rule[path]' style='display:none;' value='" . esc_attr($path) . "' class='regular-text'>";

    echo "<select name='{$role}_redirect_rule[page]' style='display:none;'>";
    $pages = get_pages();
    foreach ($pages as $page) {
        echo "<option value='" . get_permalink($page->ID) . "' " . selected($page_id, get_permalink($page->ID), false) . ">" . esc_html($page->post_title) . "</option>";
    }
    echo "</select>";

    echo "<input type='text' name='{$role}_redirect_rule[url]' style='display:none;' value='" . esc_attr($url) . "' class='regular-text'>";
    echo "</div>";
}

function urr_sanitize_redirect_rule($input) {
    return [
        'type' => sanitize_text_field($input['type']),
        'param' => sanitize_text_field($input['param']),
        'path' => sanitize_text_field($input['path']),
        'page' => sanitize_text_field($input['page']),
        'url' => sanitize_text_field($input['url'])
    ];
}

add_action('wp_login', 'urr_redirect_after_login', 10, 2);

function urr_redirect_after_login($user_login, $user) {
    if (!is_array($user->roles)) {
        return;
    }
    foreach ($user->roles as $role) {
        $redirect_rule = get_option($role . '_redirect_rule');
        $type = $redirect_rule['type'] ?? '';
        $param = $redirect_rule['param'] ?? '';
        $path_template = $redirect_rule['path'] ?? '';
        $page_url = $redirect_rule['page'] ?? '';
        $url = $redirect_rule['url'] ?? '';

        if ($type === 'user_page' && !empty($param)) {
            $param_value = $user->{$param};
            $path = str_replace(["{username}", "{userid}", "{email}"], $param_value, $path_template);
            $url = home_url($path);
        } elseif ($type === 'specific_page') {
            $url = $page_url;
        }
        elseif ($type === 'custom_url') {
            $custom_path = $redirect_rule['url'] ?? '';
            if (!empty($custom_path)) {
                // Ensure the custom path does not start with a slash to avoid absolute paths
                $custom_path = ltrim($custom_path, '/');
                $url = home_url($custom_path); // Concatenate with the base site URL
                wp_safe_redirect($url);
                exit;
            }
        }

        if (!empty($url) && $_SERVER['REQUEST_URI'] !== parse_url($url, PHP_URL_PATH)) {
            wp_safe_redirect($url);
            exit;
        }
    }
}

function urr_enqueue_admin_styles() {
    // Assuming you place your CSS in the 'css/admin-style.css' within your plugin directory
    wp_enqueue_style('urr_admin_css', plugin_dir_url(__FILE__) . 'css/admin-style.css');
}

add_action('admin_enqueue_scripts', 'urr_enqueue_admin_styles');

