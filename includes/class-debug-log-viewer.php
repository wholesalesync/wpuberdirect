<?php
class Winscode_Debug_Log_Viewer {
    public static function render_page() {
        if (!current_user_can('manage_options')) return;

        $upload_dir = wp_upload_dir();
        $log_path = trailingslashit($upload_dir['basedir']) . Winscode_Debug_Logger::LOG_FILE;

        echo '<div class="wrap"><h2>Winscode Debug Log</h2><pre style="background:#000;color:#0f0;padding:1em;">';
        if (file_exists($log_path)) {
            echo esc_html(file_get_contents($log_path));
        } else {
            echo 'Log file not found.';
        }
        echo '</pre></div>';
    }

    public static function admin_menu() {
        add_menu_page(
            'Debug Log',
            'Debug Log',
            'manage_options',
            'winscode-debug-log',
            [self::class, 'render_page'],
            'dashicons-admin-tools'
        );
    }
}
?>
