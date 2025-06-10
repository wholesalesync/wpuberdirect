<?php
class Winscode_Debug_Logger {
    const LOG_FILE = 'winscode-debug.log';

    public static function log($message, $type = 'info') {
        if (!defined('WINSCODE_DEBUG') || !WINSCODE_DEBUG) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $log_path = trailingslashit($upload_dir['basedir']) . self::LOG_FILE;

        $time = current_time('mysql');
        $formatted = "[{$time}] [{$type}] {$message}" . PHP_EOL;

        error_log($formatted, 3, $log_path);
    }

    public static function log_error($message) {
        self::log($message, 'error');
    }

    public static function log_info($message) {
        self::log($message, 'info');
    }
}
?>
