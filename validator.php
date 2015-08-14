<?php
/*
   (C) 2015 Alex < raziel at eml dot cc >
   see the file LICENSE
   GNU GENERAL PUBLIC LICENSE Version 2, June 1991

*/

// TODO check minimal required php version with phpcompatinfo (currently >= 5.2)

class ValidatorFieldsMap {
    public $args = null;
    public $replaces = array();

    function args() {
        $this->args = func_get_args();
    }

    function replace($template, $replace) {
        $this->replaces[$template] = $replace;
    }
}

class ValidatorHelpers {

    function start_with($string, $needle) {
        return substr($string, 0, strlen($needle)) === $needle;
    }

    function end_with($string, $needle) {
        return (($temp = strlen($string) - strlen($needle)) >= 0 && strpos($string, $needle, $temp) !== false);
    }

    function fetch($name) {
        return isset($_REQUEST[$name]) ? $_REQUEST[$name] : null;
    }
}

class ValidatorBuiltIn extends ValidatorHelpers {

    static function required($value) {
        return $value !== null && trim($value) !== '';
    }

    static function equal_to($value, $other) {
        return $value === $other;
    }

    /* html must be *declared* as: input type=checkbox name="group[]" value="..." */
    static function checked($group, $value) {
        return is_array($group) && in_array($value, $group);
    }

    static function email($value) {
        return filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false;
    }

    static function minlen($value, $min) {
        return mb_strlen($value) >= intval($min);
    }

    static function maxlen($value, $max) {
        return mb_strlen($value) <= intval($max);
    }

    static function must_contain_digit($value, $min) {
        return preg_match_all('/[0-9]/', $value) >= $min;
    }

    static function must_contain_symbol($value, $min) {
        return preg_match_all('/[\p{S}\p{P}]/u', $value) >= $min;
    }

    static function must_contain_upper($value, $min) {
        return preg_match_all('/\p{Lu}/u', $value) >= $min;
    }

    static function must_contain_lower($value, $min) {
        return preg_match_all('/\p{Ll}/u', $value) >= $min;
    }


    /* map_fields need to return the values to be passed to the validators (in the same order) with $map->args($arg...)
                  and the template to replace with a field name or a value: array( '{%template%}' => $field )
       {% %} indirect: replace $field with -> if(isset($names[$field]) $field = $names[$field]
       {{ }} direct: replace as it is
    */
    function map_fields($form, $field, $rule, $value, &$map) {
        $replaces = array();

        if ($rule == 'required' || $rule == 'email') {

            $map->args($this->fetch($field));
            $replaces = array('{%field%}' => $field);

        } else if ($rule == 'equal-to') {

            $map->args($this->fetch($field), $this->fetch($value));
            $replaces = array('{%field%}' => $field, '{%equal%}' => $value);

        } else if ($rule == 'checked') {

            $map->args($this->fetch($value['group']), $value['value']);
            $replaces = array('{%field%}' => $field, '{{value}}' => $value['value']);

        } else if ($rule == 'minlen' || $rule == 'maxlen') {

            $map->args($this->fetch($field), $value);
            $replaces = array('{%field%}' => $this->fetch($field), '{{value}}' => $value);

        } else if ($this->start_with($rule, 'must-contain')) {

            $map->args($this->fetch($field), $value);
            $replaces = array('{%field%}' => $field, '{{value}}' => $value,
                '{{type}}' => substr($rule, strlen('must-contain-')));

        }

        foreach($replaces as $search => $replace)
            $map->replace($search, $replace);
    }
}

class ValidatorErrors {
    // TODO private?
    public $forms = array();

    public function addForm($form) {
        if (!isset($this->forms[$form])) {
            $this->forms[$form] = array();
        }
    }

    public function addField($form, $field) {
        if (!isset($this->forms[$form][$field])) {
            $this->forms[$form][$field] = array();
        }
    }

    // TODO those functions assume previous node was already created...
    public function addFieldName($form, $field, $name) {
        if (!isset($this->forms[$form][$field]['name'])) {
            $this->forms[$form][$field]['name'] = $name;
        }
    }

    public function addError($form, $field, $error) {
        if (!isset($this->forms[$form][$field]['errors'])) {
            $this->forms[$form][$field]['errors'] = array($error);
        } else {
            if (!in_array($error, $this->forms[$form][$field]['errors'])) {
                $this->forms[$form][$field]['errors'][] = $error;
            }
        }
    }

    public function count($form=null, $field=null) {
        $count = 0;

        foreach($this->forms as $formId => $fields) {
//            ob_debug(function() use ($formId, $fields) {
//                echo "form: $formId\n";
//               //print_r($fields);
//            });
        }
        return $count;
    }
}

class Validator extends ValidatorBuiltIn {
    // TODO adjust access *privilegies*
    // TODO default messages as variables? array?
    public static $msg = array( 'default' => "Invalid {{field}}" );

    function __construct() {
    }

    static function dash2_($string) {
        return str_replace('-', '_', $string);
    }

    static function rule_exists($rule) {
        return method_exists(__CLASS__, self::dash2_($rule)); // TODO check __CLASS__ works when subclassed
    }

    static function validator() {
        $argc = func_num_args();
        if ($argc < 2) {
            error_log("error: validator(): should be called with a 'rule' and one value argument at least ($argc given)");
            return null;
        }
        $args = func_get_args();
        $rule = array_pop($args);
        $method = self::dash2_($rule);
        if (self::rule_exists($method)) // TODO check __CLASS__ works when subclassed
            return call_user_func_array(array(__CLASS__, $method), $args);
        error_log("error: validator(): rule '$rule' not found");
        return null;
    }

    function form_field_rule_exists($node, $path) {
        foreach ($path as $next) {
            if (($node = isset($node[$next]) ? $node = &$node[$next] : null) == null)
                break;
        }
        return $node;
    }

    function resolve_rule(&$call_list, $path, $rules, $msg_path) {
        if (self::rule_exists($path)) {
            $call_list[] = array($path, $rules, $msg_path);
        } else  if (is_array($rules)) {
            foreach ($rules as $rule => $value) {
                array_push($msg_path, $rule);
                self::resolve_rule($call_list, $path.'-'.$rule, $value, $msg_path);
                array_pop($msg_path);
            }
        }
    }

    function validate($rules, $messages=null, $thisForm=null, $thisField=null) {
        $result1 = new ValidatorErrors();

        if ($messages != null)
            $this->messages = $messages;

        if (isset($messages['generic']))
            $msg = $messages['generic'];
        else
            $msg = self::$msg['default'];

        foreach($rules as $form => $fields) {
            if ($thisForm != null && $form != $thisForm)
                continue;
            $result1->addForm($form);

            foreach($fields as $field => $fieldRules) {
                if ($thisField != null && $thisField != $field)
                    continue;

                $result1->addField($form, $field);
                $fieldName = $this->map_names($field, $messages);
                if ($fieldName != $field) {
                    $result1->addFieldName($form, $field, $fieldName);
                }

                foreach($fieldRules as $fieldRule => $value) {

                    if (isset($messages['by-rule'][$fieldRule]))
                        $msg = $messages['by-rule'][$fieldRule];

                    $msg_path = array($fieldRule);
                    $call_list = array();
                    $this->resolve_rule($call_list, $fieldRule, $value, $msg_path);

                    foreach($call_list as $method_value) {

                        list( $method, $value, $msg_path ) = $method_value;

                        $save_msg = $msg;
                        if (isset($messages['by-form'][$form][$field])) {
                            $tmp = $this->form_field_rule_exists($messages['by-form'][$form][$field], $msg_path);
                            if ($tmp != null) {
                                $msg = $tmp;
                            }
                        }

                        $args_map = new ValidatorFieldsMap();
                        $this->map_fields($form, $field, $method, $value, $args_map);
                        if ($args_map->args == null) {
                            error_log("error: map_fields(): missing map for $form > $field > $method)");
                            continue;
                        }

                        $args = $args_map->args;
                        array_push($args, self::dash2_($method));
                        $validate = call_user_func_array(array('self', 'validator'), $args);
                        if ($validate === false) {
                            $error_msg = $this->map_errors($msg, $form, $field, $fieldRule, $value, $args_map->replaces, $messages);
                            $result1->addError($form, $field, $error_msg);
                        } else {

                        }
                        if ($msg != $save_msg)
                            $msg = $save_msg;
                    }
                }
                if ($thisField != null && $thisField == $field)
                    break;
            }
            if ($thisForm != null && $form == $thisForm)
                break;
        }
        return $result1;
    }

    function map_names($name, $messages) {
        if (is_string($name) && trim($name) !== '' && isset($messages['names'][$name]))
            return $messages['names'][$name];
        return $name;
    }

    function map_errors($msg, $form, $field, $fieldRule, $value, &$args_map, $messages) {
        foreach ($args_map as $template => $replace) {
            if (self::start_with($template, '{%') && self::end_with($template, '%}')) {
                $replace = $this->map_names($replace, $messages);
            }
            $msg = str_replace($template, $replace, $msg);
        }
        return $msg;
    }
}
