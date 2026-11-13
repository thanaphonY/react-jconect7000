<?php
class NotificationRouter {
    public static function send($type, $config, $message) {
        switch ($type) {
            case 'line':
                $driver = new LineDriver();
                return $driver->send($config, $message);
            default:
                return ['success' => false, 'error' => 'Unsupported type'];
        }
    }
}
?>
