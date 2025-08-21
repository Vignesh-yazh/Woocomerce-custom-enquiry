<?php
class MCE_Settings {
    private static $instance = null;
    private $options;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->options = get_option('mce_settings', array());
    }
    
    public function get_option($key, $default = '') {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }
    
    public function update_option($key, $value) {
        $this->options[$key] = $value;
        return update_option('mce_settings', $this->options);
    }
}
