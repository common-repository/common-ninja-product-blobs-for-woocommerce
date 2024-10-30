<?php 
(function () {
    if (!function_exists('add_action')) {
        echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
        exit;
    }

    $cn_plugin_config = require_once(plugin_dir_path(__FILE__) . '/../config.php');
    
    $cn_plugin_page_slug = basename(plugin_dir_path(__DIR__, '/../'));
    
    $cn_isUsingPrettyPermalinks = function () {
        return !empty(get_option('permalink_structure'));
    };

    $cn_isWooCommerceActivated = function () {
        return is_plugin_active('woocommerce/woocommerce.php');
    };

    $cn_getPluginToken = function () use ($cn_plugin_page_slug) {
        $token_key = $cn_plugin_page_slug . '_plugin_token';

        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : get_transient($token_key);

        if (empty($token)) {
            delete_transient($token_key);
            return false;
        }

        set_transient($token_key, $token, 7 * 24 * 60);
        return $token;
    };

    $cn_getEncodedUrl = function ($url) {
        $revert = array('%21' => '!', '%2A' => '*', '%27' => "'", '%28' => '(', '%29' => ')');
        return strtr(rawurlencode($url), $revert);
    };

    $cn_getRedirectUrl = function () use ($cn_getEncodedUrl) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $url = sanitize_url($protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        $encodedUrl = $cn_getEncodedUrl($url);
        return $cn_getEncodedUrl($url);
    };

    $cn_getStoreUrl = function () use ($cn_getEncodedUrl) {
        return $cn_getEncodedUrl(get_site_url());
    };

    $cn_generatePluginUrl = function ($token) use ($cn_plugin_config, $cn_getRedirectUrl, $cn_getStoreUrl) {
        $base_url = 'https://integrations.commoninja.com/integrations/woocommerce/';
        $query_params = !$token ? 'redirectUrl=' . $cn_getRedirectUrl() : 'token=' . $token;
        $final_url = $base_url . $cn_plugin_config['cn_app_id'] . '/oauth/authenticate?store_url=' . $cn_getStoreUrl() . "&" . $query_params;
        return $final_url;
    };

    $cn_renderPlugin = function ($plugin_url) {
    //     echo '<div class="cn-integrations cn-integrations-plugin">
    //     <iframe src="' . $plugin_url . '" width="100%" height="100%" frameborder="0"></iframe>
    // </div>';
    };
    
    $cn_getMenuIcon = function () use ($cn_plugin_config, $cn_plugin_page_slug) {
        // If not icon is set in the config file, a Common Ninja logo will appear
        if (empty($cn_plugin_config['plugin_icon'])) {
            return plugin_dir_url('') . $cn_plugin_page_slug . '/_inc/commonninja.png';
        } 

        // Load an icon stored locally in _inc file by prefixing 'plugin_icon' with './'
        if (str_starts_with($cn_plugin_config['plugin_icon'], './')) {
            return plugin_dir_url('') . $cn_plugin_page_slug . substr($cn_plugin_config['plugin_icon'], 1);
        }

        // returns the icon as set in the config file  - able to render a path, url or base64-image as string
        return $cn_plugin_config['plugin_icon'];
    };

    $cn_renderErrorPage = function ($error_message) use ($cn_plugin_page_slug, $cn_getMenuIcon) {
        $allowed_html = array(
            'a'      => array(
                'href'  => array(),
                'title' => array(),
                'class' => array(),
                'target' => array(),
            ),
            'br'     => array(),
            'em'     => array(),
            'strong' => array(),
        );
        echo '<div class="cn-integrations cn-integrations-error">
        <img src="' . $cn_getMenuIcon() . '" alt="Common Ninja Logo" style="max-width: 100px" />
        <h4 style="font-size: 20px; margin: 0 0 10px;">' . wp_kses($error_message['error'], $allowed_html) . '</h4>
        <p style="font-size: 16px; margin: 0 0 20px;">' . wp_kses($error_message['message'], $allowed_html) . '</p>
        <a class="action" href="' . $error_message['link'] . '">' . wp_kses($error_message['action'], $allowed_html) .'</a> 
    </div>';
    };

    $cn_renderPluginPage = function () use ($cn_isUsingPrettyPermalinks, $cn_isWooCommerceActivated, $cn_renderErrorPage, $cn_getPluginToken, $cn_generatePluginUrl, $cn_renderPlugin, $cn_plugin_page_slug) {
        if (!$cn_isUsingPrettyPermalinks()) {
            $cn_renderErrorPage(array(
                'error' => 'It looks like you are using the <a href="https://wordpress.org/support/article/using-permalinks/#permalink-types-1" title="See more details on using permalinks at WordPress.org documentation" class="question-mark" target="_blank">Plain Permalinks</a> setting on your website.', 
                'message' => 'Please change the permalinks setting to any other value.',
                'action' => 'Change Permalink Settings', 
                'link' => '/wp-admin/options-permalink.php'
            ));
            return;
        }
        if (!$cn_isWooCommerceActivated()) {
            $cn_renderErrorPage(array(
                'error' => 'Please install & activate WooCommerce in order to use this plugin.', 
                'message' => 'This plugin requires WooCommerce to be installed and activated.',
                'action' => 'Go to WooCommerce', 
                'link' => '/wp-admin/plugin-install.php?s=woocommerce&tab=search&type=term'
            ));
            return;
        }

        $token = $cn_getPluginToken($cn_plugin_page_slug);

        $plugin_url = $cn_generatePluginUrl($token);

        wp_redirect($plugin_url);

        // if (!$token) {
        //     wp_redirect($plugin_url); // if no token is found, redirects user to WooCommerce authentication page
        // }

        // $cn_renderPlugin($plugin_url);
    };

    $cn_addPluginPage = function () use ($cn_plugin_config, $cn_renderPluginPage, $cn_plugin_page_slug) {
        if (isset($cn_plugin_config['parent_menu']) && !empty($cn_plugin_config['parent_menu'])) {
            $parent_slug = $cn_plugin_config['parent_menu']['slug'];
            $menu_url = menu_page_url($parent_slug, false);

            // Add top menu if doesn't exists
            if (!$menu_url) {
                add_menu_page( 
                    $cn_plugin_config['parent_menu']['name'],
                    $cn_plugin_config['parent_menu']['name'],
                    '', 
                    $parent_slug, 
                    null, 
                    'none',
                );
            }

            // Add submenu
            add_submenu_page(
                $parent_slug,
                $cn_plugin_config['plugin_name'],
                $cn_plugin_config['plugin_name'],
                'manage_options',
                $cn_plugin_page_slug,
                $cn_renderPluginPage,
            );
        } else {
            add_menu_page(
                $cn_plugin_config['plugin_name'],
                $cn_plugin_config['plugin_name'],
                'manage_options',
                $cn_plugin_page_slug,
                $cn_renderPluginPage,
                'none',
            );
        }
    };

    $cn_loadPluginStyle = function () use ($cn_plugin_page_slug) {
        wp_enqueue_style('cn-integrations-admin-styles', plugin_dir_url('') . $cn_plugin_page_slug . '/_inc/admin.css');

        if (isset($_GET['page']) && $_GET['page'] === $cn_plugin_page_slug) {
            wp_enqueue_style('cn-integrations-hide-update-nags', plugin_dir_url('') . $cn_plugin_page_slug . '/_inc/hide_update_nags.css');
        };
    };

    $cn_loadMenuIcon = function () use ($cn_getMenuIcon, $cn_plugin_page_slug, $cn_plugin_config) {
        $icon_path = $cn_getMenuIcon();
        $plugin_class = isset($cn_plugin_config['parent_menu']) && !empty($cn_plugin_config['parent_menu']) ? $cn_plugin_config['parent_menu']['slug'] : $cn_plugin_page_slug;

        echo '<style>.menu-top.toplevel_page_' . $plugin_class . ' ' . '.wp-menu-image { background-size: 50%; background-repeat: no-repeat; background-position: center; background-image: url(' . $icon_path . '); } </style>';
    };

    $cn_init = function () use ($cn_addPluginPage, $cn_loadPluginStyle, $cn_loadMenuIcon) {
        add_action('admin_menu', $cn_addPluginPage);

        add_action('admin_enqueue_scripts', $cn_loadPluginStyle);

        add_action('admin_enqueue_scripts', $cn_loadMenuIcon);
    };
    
    $cn_init();
})();
