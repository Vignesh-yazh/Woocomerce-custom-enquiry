<?php
class MCE_Validator {
    public static function validate_field($value, $rules) {
        $errors = array();
        
        foreach ($rules as $rule => $param) {
            switch ($rule) {
                case 'required':
                    if (empty($value)) {
                        $errors[] = __('This field is required', 'my-custom-enquiry');
                    }
                    break;
                    
                case 'email':
                    if (!empty($value) && !is_email($value)) {
                        $errors[] = __('Please enter a valid email address', 'my-custom-enquiry');
                    }
                    break;
                    
                case 'min_length':
                    if (strlen($value) < $param) {
                        $errors[] = sprintf(__('Minimum length is %d characters', 'my-custom-enquiry'), $param);
                    }
                    break;
            }
        }
        
        return $errors;
    }
}
