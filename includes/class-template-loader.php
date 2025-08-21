<?php
class MCE_Template_Loader {
    public static function get_template_part($slug, $name = '') {
        $template = '';
        
        // Look in plugin/templates/slug-name.php
        if ($name) {
            $template = locate_template("my-custom-enquiry/{$slug}-{$name}.php");
        }
        
        // Look in plugin/templates/slug.php
        if (!$template) {
            $template = locate_template("my-custom-enquiry/{$slug}.php");
        }
        
        if (!$template) {
            if ($name) {
                $template = MCE_PLUGIN_DIR . "templates/{$slug}-{$name}.php";
            } else {
                $template = MCE_PLUGIN_DIR . "templates/{$slug}.php";
            }
        }
        
        if (file_exists($template)) {
            load_template($template, false);
        }
    }
}
