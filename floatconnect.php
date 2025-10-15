/**
 * Plugin Name: FloatConnect - Professional Chat Widget
 * Plugin URI: https://smtechspire-it.com/floatconnect
 * Description: Professional floating communication widget with WhatsApp, Messenger, Phone, Email and more channels. Lightweight, fast & fully customizable.
 * Version: 1.0.0
 * Author: SM TechSpire-IT
 * Author URI: https://smtechspire-it.com
 * Text Domain: floatconnect
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.0
 */

/*
 * FloatConnect - Professional Chat Widget Plugin
 * Copyright (C) 2025 SM TechSpire-IT
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FLOATCONNECT_VERSION', '1.0.0');
define('FLOATCONNECT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FLOATCONNECT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FLOATCONNECT_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main FloatConnect Plugin Class
 */
class FloatConnect {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('plugin_action_links_' . FLOATCONNECT_PLUGIN_BASENAME, array($this, 'add_settings_link'));
        
        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_footer', array($this, 'render_widget'));
        
        // Activation/Deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $default_options = array(
            'enabled' => 1,
            'position' => 'right',
            'button_size' => 60,
            'button_color' => '#4482FF',
            'icon_color' => '#ffffff',
            'show_on_mobile' => 1,
            'show_on_desktop' => 1,
            'channels' => array()
        );
        
        if (!get_option('floatconnect_options')) {
            add_option('floatconnect_options', $default_options);
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup if needed
    }
    
    /**
     * Add settings link on plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=floatconnect">' . __('Settings', 'floatconnect') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('FloatConnect Settings', 'floatconnect'),
            __('FloatConnect', 'floatconnect'),
            'manage_options',
            'floatconnect',
            array($this, 'render_admin_page'),
            'dashicons-format-chat',
            30
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('floatconnect_settings', 'floatconnect_options', array($this, 'sanitize_options'));
    }
    
    /**
     * Sanitize and validate options
     */
    public function sanitize_options($input) {
        $sanitized = array();
        
        // Basic settings
        $sanitized['enabled'] = isset($input['enabled']) ? 1 : 0;
        $sanitized['position'] = in_array($input['position'], array('left', 'right')) ? $input['position'] : 'right';
        $sanitized['button_size'] = absint($input['button_size']);
        $sanitized['button_size'] = max(40, min(80, $sanitized['button_size'])); // Between 40-80px
        $sanitized['button_color'] = sanitize_hex_color($input['button_color']);
        $sanitized['icon_color'] = sanitize_hex_color($input['icon_color']);
        $sanitized['show_on_mobile'] = isset($input['show_on_mobile']) ? 1 : 0;
        $sanitized['show_on_desktop'] = isset($input['show_on_desktop']) ? 1 : 0;
        
        // Channels
        if (isset($input['channels']) && is_array($input['channels'])) {
            $sanitized['channels'] = array();
            foreach ($input['channels'] as $channel) {
                $sanitized_channel = array(
                    'type' => sanitize_text_field($channel['type']),
                    'value' => sanitize_text_field($channel['value']),
                    'label' => sanitize_text_field($channel['label']),
                    'message' => sanitize_textarea_field($channel['message']),
                    'show_mobile' => isset($channel['show_mobile']) ? 1 : 0,
                    'show_desktop' => isset($channel['show_desktop']) ? 1 : 0,
                    'enabled' => isset($channel['enabled']) ? 1 : 0
                );
                
                // Validate channel type
                $allowed_types = array('whatsapp', 'messenger', 'phone', 'email', 'telegram', 'viber', 'custom');
                if (in_array($sanitized_channel['type'], $allowed_types)) {
                    $sanitized['channels'][] = $sanitized_channel;
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_floatconnect' !== $hook) {
            return;
        }
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_script('jquery-ui-sortable');
        
        // Admin CSS
        wp_add_inline_style('wp-admin', $this->get_admin_css());
        
        // Admin JS
        wp_add_inline_script('jquery', $this->get_admin_js());
    }
    
    /**
     * Get admin CSS
     */
    private function get_admin_css() {
        return '
        .fc-admin-wrap {
            max-width: 1200px;
            margin: 20px 0;
            background: #fff;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .fc-admin-header {
            border-bottom: 2px solid #4482FF;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .fc-admin-header h1 {
            margin: 0;
            color: #23282d;
            font-size: 24px;
        }
        .fc-admin-header p {
            color: #646970;
            margin: 5px 0 0;
        }
        .fc-admin-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .fc-admin-section h2 {
            margin-top: 0;
            color: #23282d;
            font-size: 18px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .fc-form-row {
            margin-bottom: 20px;
        }
        .fc-form-row label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #23282d;
        }
        .fc-form-row input[type="text"],
        .fc-form-row input[type="number"],
        .fc-form-row textarea,
        .fc-form-row select {
            width: 100%;
            max-width: 400px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .fc-form-row textarea {
            min-height: 80px;
            resize: vertical;
        }
        .fc-channels-list {
            margin-top: 20px;
        }
        .fc-channel-item {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: move;
        }
        .fc-channel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        .fc-channel-header h3 {
            margin: 0;
            font-size: 16px;
        }
        .fc-channel-actions button {
            margin-left: 5px;
        }
        .fc-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .fc-btn-primary {
            background: #4482FF;
            color: #fff;
        }
        .fc-btn-primary:hover {
            background: #3571ee;
        }
        .fc-btn-danger {
            background: #dc3545;
            color: #fff;
        }
        .fc-btn-success {
            background: #28a745;
            color: #fff;
        }
        .fc-preview {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 999999;
        }
        .fc-preview-button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #4482FF;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .fc-footer-branding {
            text-align: center;
            padding: 20px 0;
            color: #646970;
            font-size: 14px;
        }
        .fc-footer-branding a {
            color: #4482FF;
            text-decoration: none;
            font-weight: 600;
        }
        .fc-footer-branding a:hover {
            text-decoration: underline;
        }
        ';
    }
    
    /**
     * Get admin JS
     */
    private function get_admin_js() {
        return "
        jQuery(document).ready(function($) {
            // Color picker
            $('.fc-color-picker').wpColorPicker();
            
            // Sortable channels
            $('.fc-channels-list').sortable({
                handle: '.fc-channel-header',
                placeholder: 'fc-channel-placeholder'
            });
            
            // Add channel
            $('#fc-add-channel').on('click', function() {
                var channelType = $('#fc-channel-type').val();
                var channelHtml = getChannelHtml(channelType, Date.now());
                $('.fc-channels-list').append(channelHtml);
            });
            
            // Remove channel
            $(document).on('click', '.fc-remove-channel', function() {
                if(confirm('Are you sure you want to remove this channel?')) {
                    $(this).closest('.fc-channel-item').remove();
                }
            });
            
            function getChannelHtml(type, id) {
                var labels = {
                    'whatsapp': 'WhatsApp',
                    'messenger': 'Facebook Messenger',
                    'phone': 'Phone',
                    'email': 'Email',
                    'telegram': 'Telegram',
                    'viber': 'Viber',
                    'custom': 'Custom Link'
                };
                
                return '<div class=\"fc-channel-item\">' +
                    '<div class=\"fc-channel-header\">' +
                    '<h3>' + labels[type] + '</h3>' +
                    '<div class=\"fc-channel-actions\">' +
                    '<button type=\"button\" class=\"fc-btn fc-btn-danger fc-remove-channel\">Remove</button>' +
                    '</div>' +
                    '</div>' +
                    '<input type=\"hidden\" name=\"floatconnect_options[channels][' + id + '][type]\" value=\"' + type + '\">' +
                    '<div class=\"fc-form-row\">' +
                    '<label>Label</label>' +
                    '<input type=\"text\" name=\"floatconnect_options[channels][' + id + '][label]\" value=\"' + labels[type] + '\">' +
                    '</div>' +
                    '<div class=\"fc-form-row\">' +
                    '<label>Value (Phone/Username/URL)</label>' +
                    '<input type=\"text\" name=\"floatconnect_options[channels][' + id + '][value]\">' +
                    '</div>' +
                    '<div class=\"fc-form-row\">' +
                    '<label>Pre-filled Message (Optional)</label>' +
                    '<textarea name=\"floatconnect_options[channels][' + id + '][message]\" placeholder=\"Use [PAGE_TITLE], [PAGE_URL], [SITE_NAME]\"></textarea>' +
                    '</div>' +
                    '<div class=\"fc-form-row\">' +
                    '<label><input type=\"checkbox\" name=\"floatconnect_options[channels][' + id + '][enabled]\" value=\"1\" checked> Enabled</label>' +
                    '<label><input type=\"checkbox\" name=\"floatconnect_options[channels][' + id + '][show_mobile]\" value=\"1\" checked> Show on Mobile</label>' +
                    '<label><input type=\"checkbox\" name=\"floatconnect_options[channels][' + id + '][show_desktop]\" value=\"1\" checked> Show on Desktop</label>' +
                    '</div>' +
                    '</div>';
            }
        });
        ";
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $options = get_option('floatconnect_options');
        $defaults = array(
            'enabled' => 1,
            'position' => 'right',
            'button_size' => 60,
            'button_color' => '#4482FF',
            'icon_color' => '#ffffff',
            'show_on_mobile' => 1,
            'show_on_desktop' => 1,
            'channels' => array()
        );
        $options = wp_parse_args($options, $defaults);
        
        ?>
        <div class="wrap">
            <div class="fc-admin-wrap">
                <div class="fc-admin-header">
                    <h1><?php echo esc_html__('FloatConnect - Chat Widget Settings', 'floatconnect'); ?></h1>
                    <p><?php echo esc_html__('Configure your floating communication widget', 'floatconnect'); ?></p>
                </div>
                
                <form method="post" action="options.php">
                    <?php settings_fields('floatconnect_settings'); ?>
                    
                    <!-- General Settings -->
                    <div class="fc-admin-section">
                        <h2><?php echo esc_html__('General Settings', 'floatconnect'); ?></h2>
                        
                        <div class="fc-form-row">
                            <label>
                                <input type="checkbox" name="floatconnect_options[enabled]" value="1" <?php checked($options['enabled'], 1); ?>>
                                <?php echo esc_html__('Enable Widget', 'floatconnect'); ?>
                            </label>
                        </div>
                        
                        <div class="fc-form-row">
                            <label><?php echo esc_html__('Widget Position', 'floatconnect'); ?></label>
                            <select name="floatconnect_options[position]">
                                <option value="right" <?php selected($options['position'], 'right'); ?>>Right</option>
                                <option value="left" <?php selected($options['position'], 'left'); ?>>Left</option>
                            </select>
                        </div>
                        
                        <div class="fc-form-row">
                            <label><?php echo esc_html__('Button Size (px)', 'floatconnect'); ?></label>
                            <input type="number" name="floatconnect_options[button_size]" value="<?php echo esc_attr($options['button_size']); ?>" min="40" max="80">
                        </div>
                        
                        <div class="fc-form-row">
                            <label><?php echo esc_html__('Button Color', 'floatconnect'); ?></label>
                            <input type="text" name="floatconnect_options[button_color]" value="<?php echo esc_attr($options['button_color']); ?>" class="fc-color-picker">
                        </div>
                        
                        <div class="fc-form-row">
                            <label><?php echo esc_html__('Icon Color', 'floatconnect'); ?></label>
                            <input type="text" name="floatconnect_options[icon_color]" value="<?php echo esc_attr($options['icon_color']); ?>" class="fc-color-picker">
                        </div>
                        
                        <div class="fc-form-row">
                            <label>
                                <input type="checkbox" name="floatconnect_options[show_on_mobile]" value="1" <?php checked($options['show_on_mobile'], 1); ?>>
                                <?php echo esc_html__('Show on Mobile', 'floatconnect'); ?>
                            </label>
                        </div>
                        
                        <div class="fc-form-row">
                            <label>
                                <input type="checkbox" name="floatconnect_options[show_on_desktop]" value="1" <?php checked($options['show_on_desktop'], 1); ?>>
                                <?php echo esc_html__('Show on Desktop', 'floatconnect'); ?>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Channels -->
                    <div class="fc-admin-section">
                        <h2><?php echo esc_html__('Communication Channels', 'floatconnect'); ?></h2>
                        
                        <div class="fc-form-row">
                            <label><?php echo esc_html__('Add New Channel', 'floatconnect'); ?></label>
                            <select id="fc-channel-type">
                                <option value="whatsapp">WhatsApp</option>
                                <option value="messenger">Facebook Messenger</option>
                                <option value="phone">Phone</option>
                                <option value="email">Email</option>
                                <option value="telegram">Telegram</option>
                                <option value="viber">Viber</option>
                                <option value="custom">Custom Link</option>
                            </select>
                            <button type="button" id="fc-add-channel" class="fc-btn fc-btn-success">Add Channel</button>
                        </div>
                        
                        <div class="fc-channels-list">
                            <?php
                            if (!empty($options['channels'])) {
                                foreach ($options['channels'] as $index => $channel) {
                                    $this->render_channel_form($channel, $index);
                                }
                            }
                            ?>
                        </div>
                    </div>
                    
                    <?php submit_button(__('Save Settings', 'floatconnect'), 'primary', 'submit', true); ?>
                </form>
                
                <div class="fc-footer-branding">
                    <p>
                        <?php echo sprintf(
                            esc_html__('Developed with ♥ by %s', 'floatconnect'),
                            '<a href="https://smtechspire-it.com" target="_blank">SM TechSpire-IT</a>'
                        ); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render channel form
     */
    private function render_channel_form($channel, $index) {
        $labels = array(
            'whatsapp' => 'WhatsApp',
            'messenger' => 'Facebook Messenger',
            'phone' => 'Phone',
            'email' => 'Email',
            'telegram' => 'Telegram',
            'viber' => 'Viber',
            'custom' => 'Custom Link'
        );
        
        $label = isset($labels[$channel['type']]) ? $labels[$channel['type']] : $channel['type'];
        ?>
        <div class="fc-channel-item">
            <div class="fc-channel-header">
                <h3><?php echo esc_html($label); ?></h3>
                <div class="fc-channel-actions">
                    <button type="button" class="fc-btn fc-btn-danger fc-remove-channel">Remove</button>
                </div>
            </div>
            
            <input type="hidden" name="floatconnect_options[channels][<?php echo esc_attr($index); ?>][type]" value="<?php echo esc_attr($channel['type']); ?>">
            
            <div class="fc-form-row">
                <label>Label</label>
                <input type="text" name="floatconnect_options[channels][<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($channel['label']); ?>">
            </div>
            
            <div class="fc-form-row">
                <label>Value (Phone/Username/URL)</label>
                <input type="text" name="floatconnect_options[channels][<?php echo esc_attr($index); ?>][value]" value="<?php echo esc_attr($channel['value']); ?>">
            </div>
            
            <div class="fc-form-row">
                <label>Pre-filled Message (Optional)</label>
                <textarea name="floatconnect_options[channels][<?php echo esc_attr($index); ?>][message]" placeholder="Use [PAGE_TITLE], [PAGE_URL], [SITE_NAME]"><?php echo esc_textarea($channel['message']); ?></textarea>
            </div>
            
            <div class="fc-form-row">
                <label>
                    <input type="checkbox" name="floatconnect_options[channels][<?php echo esc_attr($index); ?>][enabled]" value="1" <?php checked($channel['enabled'], 1); ?>>
                    Enabled
                </label>
                <label>
                    <input type="checkbox" name="floatconnect_options[channels][<?php echo esc_attr($index); ?>][show_mobile]" value="1" <?php checked($channel['show_mobile'], 1); ?>>
                    Show on Mobile
                </label>
                <label>
                    <input type="checkbox" name="floatconnect_options[channels][<?php echo esc_attr($index); ?>][show_desktop]" value="1" <?php checked($channel['show_desktop'], 1); ?>>
                    Show on Desktop
                </label>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        $options = get_option('floatconnect_options');
        
        if (!isset($options['enabled']) || !$options['enabled']) {
            return;
        }
        
        wp_add_inline_style('wp-block-library', $this->get_frontend_css());
        wp_add_inline_script('jquery', $this->get_frontend_js());
        
        wp_localize_script('jquery', 'floatconnect_vars', array(
            'page_url' => get_permalink(),
            'page_title' => get_the_title(),
            'site_name' => get_bloginfo('name')
        ));
    }
    
    /**
     * Get frontend CSS
     */
    private function get_frontend_css() {
        return '
        .floatconnect-widget {
            position: fixed;
            bottom: 20px;
            z-index: 999999;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .floatconnect-widget.fc-position-right {
            right: 20px;
        }
        .floatconnect-widget.fc-position-left {
            left: 20px;
        }
        .fc-main-button {
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(68, 130, 255, 0.4);
            transition: all 0.3s ease;
            animation: fc-pulse 2s ease-in-out infinite;
        }
        @keyframes fc-pulse {
            0%, 100% {
                box-shadow: 0 4px 20px rgba(68, 130, 255, 0.4);
            }
            50% {
                box-shadow: 0 4px 30px rgba(68, 130, 255, 0.7);
            }
        }
        .fc-main-button:hover {
            transform: scale(1.1);
            animation: none;
        }
        .fc-channels {
            position: absolute;
            bottom: 80px;
            right: 0;
            display: none;
            flex-direction: column;
            gap: 10px;
        }
        .floatconnect-widget.fc-position-left .fc-channels {
            right: auto;
            left: 0;
        }
        .floatconnect-widget.fc-active .fc-channels {
            display: flex;
            animation: fc-slideUp 0.3s ease;
        }
        @keyframes fc-slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .fc-channel {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            background: #fff;
            border-radius: 25px;
            text-decoration: none;
            color: #333;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        .fc-channel:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .fc-channel-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .fc-channel-whatsapp { background: #25D366; color: #fff; }
        .fc-channel-messenger { background: #0084FF; color: #fff; }
        .fc-channel-phone { background: #34C759; color: #fff; }
        .fc-channel-email { background: #EA4335; color: #fff; }
        .fc-channel-telegram { background: #0088CC; color: #fff; }
        .fc-channel-viber { background: #7360F2; color: #fff; }
        .fc-channel-custom { background: #6366F1; color: #fff; }
        .fc-channel-label {
            font-size: 14px;
            font-weight: 500;
        }
        @media (max-width: 768px) {
            .fc-hide-mobile {
                display: none !important;
            }
        }
        @media (min-width: 769px) {
            .fc-hide-desktop {
                display: none !important;
            }
        }
        ';
    }
    
    /**
     * Get frontend JS
     */
    private function get_frontend_js() {
        return "
        jQuery(document).ready(function($) {
            $('.fc-main-button').on('click', function() {
                $(this).closest('.floatconnect-widget').toggleClass('fc-active');
            });
            
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.floatconnect-widget').length) {
                    $('.floatconnect-widget').removeClass('fc-active');
                }
            });
        });
        ";
    }
    
    /**
     * Render widget on frontend
     */
    public function render_widget() {
        $options = get_option('floatconnect_options');
        
        if (!isset($options['enabled']) || !$options['enabled']) {
            return;
        }
        
        $defaults = array(
            'position' => 'right',
            'button_size' => 60,
            'button_color' => '#4482FF',
            'icon_color' => '#ffffff',
            'show_on_mobile' => 1,
            'show_on_desktop' => 1,
            'channels' => array()
        );
        
        $options = wp_parse_args($options, $defaults);
        
        $device_class = '';
        if (!$options['show_on_mobile']) {
            $device_class .= ' fc-hide-mobile';
        }
        if (!$options['show_on_desktop']) {
            $device_class .= ' fc-hide-desktop';
        }
        
        ?>
        <div class="floatconnect-widget fc-position-<?php echo esc_attr($options['position']); ?><?php echo esc_attr($device_class); ?>" 
             data-position="<?php echo esc_attr($options['position']); ?>">
            
            <div class="fc-main-button" 
                 style="width: <?php echo esc_attr($options['button_size']); ?>px; 
                        height: <?php echo esc_attr($options['button_size']); ?>px; 
                        background: <?php echo esc_attr($options['button_color']); ?>;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" 
                     fill="none" stroke="<?php echo esc_attr($options['icon_color']); ?>" 
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
            </div>
            
            <div class="fc-channels">
                <?php
                if (isset($options['channels']) && is_array($options['channels'])) {
                    foreach ($options['channels'] as $channel) {
                        if (!isset($channel['enabled']) || !$channel['enabled']) {
                            continue;
                        }
                        
                        $channel_class = '';
                        if (!isset($channel['show_mobile']) || !$channel['show_mobile']) {
                            $channel_class .= ' fc-hide-mobile';
                        }
                        if (!isset($channel['show_desktop']) || !$channel['show_desktop']) {
                            $channel_class .= ' fc-hide-desktop';
                        }
                        
                        $this->render_channel($channel, $channel_class);
                    }
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render individual channel
     */
    private function render_channel($channel, $class = '') {
        $url = $this->get_channel_url($channel);
        if (!$url) {
            return;
        }
        
        $target = '_blank';
        
        ?>
        <a href="<?php echo esc_url($url); ?>" 
           class="fc-channel fc-channel-<?php echo esc_attr($channel['type']); ?><?php echo esc_attr($class); ?>" 
           target="<?php echo esc_attr($target); ?>"
           rel="noopener noreferrer"
           title="<?php echo esc_attr($channel['label']); ?>">
            <span class="fc-channel-icon">
                <?php echo $this->get_channel_icon($channel['type']); ?>
            </span>
            <span class="fc-channel-label"><?php echo esc_html($channel['label']); ?></span>
        </a>
        <?php
    }
    
    /**
     * Get channel URL
     */
    private function get_channel_url($channel) {
        $value = $channel['value'];
        $message = isset($channel['message']) ? $channel['message'] : '';
        
        // Replace dynamic variables
        $message = $this->replace_variables($message);
        
        switch ($channel['type']) {
            case 'whatsapp':
                $phone = preg_replace('/[^0-9]/', '', $value);
                return 'https://wa.me/' . $phone . ($message ? '?text=' . urlencode($message) : '');
                
            case 'messenger':
                return 'https://m.me/' . $value;
                
            case 'phone':
                return 'tel:' . $value;
                
            case 'email':
                $subject = urlencode($this->replace_variables('Inquiry from [SITE_NAME]'));
                $body = $message ? '&body=' . urlencode($message) : '';
                return 'mailto:' . $value . '?subject=' . $subject . $body;
                
            case 'telegram':
                return 'https://t.me/' . $value;
                
            case 'viber':
                return 'viber://chat?number=' . preg_replace('/[^0-9]/', '', $value);
                
            case 'custom':
                return esc_url($value);
                
            default:
                return '';
        }
    }
    
    /**
     * Replace dynamic variables in text
     */
    private function replace_variables($text) {
        global $post;
        
        $replacements = array(
            '[PAGE_URL]' => get_permalink(),
            '[PAGE_TITLE]' => get_the_title(),
            '[SITE_NAME]' => get_bloginfo('name')
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
    
    /**
     * Get channel icon SVG
     */
    private function get_channel_icon($type) {
        $icons = array(
            'whatsapp' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>',
            
            'messenger' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 4.975 0 11.111c0 3.497 1.745 6.616 4.472 8.652V24l4.086-2.242c1.09.301 2.246.464 3.442.464 6.627 0 12-4.974 12-11.111C24 4.975 18.627 0 12 0zm1.193 14.963l-3.056-3.259-5.963 3.259L10.733 8l3.13 3.259L19.752 8l-6.559 6.963z"/></svg>',
            
            'phone' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>',
            
            'email' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>',
            
            'telegram' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>',
            
            'viber' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M11.4 0C9.473.028 5.333.344 3.02 2.467 1.302 4.187.696 6.7.633 9.817.57 12.933.488 18.617 6.55 20.42h.006l-.006 2.381s-.037.98.61 1.179c.777.24 1.236-.5 1.98-1.302.407-.44.97-1.086 1.393-1.58 3.85.326 6.812-.417 7.15-.526.776-.253 5.166-.816 5.883-6.657.74-6.02-.36-9.83-2.34-11.546-.76-.693-2.74-2.39-6.87-2.39h-.013zm.031 1.661h.01c3.6 0 5.37 1.438 6 2.01 1.64 1.49 2.52 4.82 1.87 10.05-.62 5.04-4.17 5.38-4.83 5.59-.28.09-2.96.73-6.25.45 0 0-2.46 2.98-3.23 3.76-.12.13-.26.17-.35.15-.13-.03-.17-.19-.16-.41l.02-4.01c-5.09-1.53-4.78-6.36-4.73-8.99.05-2.63.55-4.77 1.99-6.32 1.96-1.85 5.58-2.17 7.41-2.21z"/></svg>',
            
            'custom' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>'
        );
        
        return isset($icons[$type]) ? $icons[$type] : $icons['custom'];
    }
}

// Initialize the plugin
function floatconnect_init() {
    return FloatConnect::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'floatconnect_init');