<?php
/**
 * Spyc -- A Simple PHP YAML Class
 *
 * @package Qi
 */

if (!function_exists('spyc_load')) {
    /**
     * Parses YAML to array.
     *
     * @param string $string YAML string.
     * @return array
     */
    function spyc_load ($string) {
        return Qi_Spyc::YAMLLoadString($string);
    }
}

if (!function_exists('spyc_load_file')) {
    /**
     * Parses YAML to array.
     *
     * @param string $file Path to YAML file.
     * @return array
     */
    function spyc_load_file ($file) {
        return Qi_Spyc::YAMLLoad($file);
    }
}

/**
 * The Simple PHP YAML Class.
 *
 * This class can be used to read a YAML file and convert its contents
 * into a PHP array.  It currently supports a very limited subsection of
 * the YAML spec.
 *
 * Usage:
 * <code>
 *   $spyc  = new Qi_Spyc;
 *   $array = $spyc->load($file);
 * </code>
 * or:
 * <code>
 *   $array = Qi_Spyc::YAMLLoad($file);
 * </code>
 * or:
 * <code>
 *   $array = spyc_load_file($file);
 * </code>
 *
 * @package Qi
 * @author Vlad Andersen <vlad.andersen@gmail.com>
 * @author Chris Wanstrath <chris@ozmm.org>
 * @copyright 2005-2006 Chris Wanstrath, 2006-2009 Vlad Andersen
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 * @version 0.4.5
 * @link http://code.google.com/p/spyc/
 */
class Qi_Spyc {

    /**
     * Setting this to true will force YAMLDump to enclose any string value in
     * quotes.  False by default.
     *
     * @var bool
     */
    public $setting_dump_force_quotes = false;

    /**
     * Setting this to true will forse YAMLLoad to use syck_load function when
     * possible. False by default.
     *
     * @var bool
     */
    public $setting_use_syck_is_possible = false;

    /**#@+
     * Private object storage
     *
     * @var mixed
     */
    private $_dumpIndent;
    private $_dumpWordWrap;
    private $_containsGroupAnchor = false;
    private $_containsGroupAlias  = false;
    private $_path;
    private $_result;
    private $_literalPlaceHolder = '___YAML_Literal_Block___';
    private $_savedGroups        = array();
    private $_indent;

    /**
     * Path modifier that should be applied after adding current element.
     *
     * @var array
     */
    private $_delayedPath = array();

    /**
     * Load a valid YAML string to Qi_Spyc.
     *
     * @param string $input The input string
     * @return array
     */
    public function load($input) {
        return $this->_loadString($input);
    }

    /**
     * Load a valid YAML file to Qi_Spyc.
     *
     * @param string $file The filename
     * @return array
     */
    public function loadFile ($file) {
        return $this->_load($file);
    }

    /**
     * Load YAML into a PHP array statically
     *
     * The load method, when supplied with a YAML stream (string or file),
     * will do its best to convert YAML in a file into a PHP array.  Pretty
     * simple.
     *  Usage:
     *  <code>
     *   $array = Qi_Spyc::YAMLLoad('lucky.yaml');
     *   print_r($array);
     *  </code>
     *
     * @param string $input Path of YAML file or string containing YAML
     * @return array
     */
    public static function YAMLLoad($input) {
        $spyc = new Qi_Spyc;
        return $spyc->_load($input);
    }

    /**
     * Load a string of YAML into a PHP array statically
     *
     * The load method, when supplied with a YAML string, will do its best
     * to convert YAML in a string into a PHP array.  Pretty simple.
     *
     * Note: use this function if you don't want files from the file system
     * loaded and processed as YAML.  This is of interest to people concerned
     * about security whose input is from a string.
     *
     *  Usage:
     *  <code>
     *   $array = Qi_Spyc::YAMLLoadString("---\n0: hello world\n");
     *   print_r($array);
     *  </code>
     *
     * @param string $input String containing YAML
     * @return array
     */
    public static function YAMLLoadString($input) {
        $spyc = new Qi_Spyc;
        return $spyc->_loadString($input);
    }

    /**
     * Dump YAML from PHP array statically
     *
     * The dump method, when supplied with an array, will do its best
     * to convert the array into friendly YAML.  Pretty simple.  Feel free to
     * save the returned string as nothing.yaml and pass it around.
     *
     * Oh, and you can decide how big the indent is and what the wordwrap
     * for folding is.  Pretty cool -- just pass in 'false' for either if
     * you want to use the default.
     *
     * Indent's default is 2 spaces, wordwrap's default is 40 characters.  And
     * you can turn off wordwrap by passing in 0.
     *
     * @param array $array PHP array
     * @param int $indent Pass in false to use the default, which is 2
     * @param int $wordwrap Pass in 0 for no wordwrap, false for default (40)
     * @return string
     */
    public static function YAMLDump($array, $indent = false, $wordwrap = false) {
        $spyc = new Qi_Spyc;
        return $spyc->dump($array, $indent, $wordwrap);
    }

    /**
     * Dump PHP array to YAML
     *
     * The dump method, when supplied with an array, will do its best
     * to convert the array into friendly YAML.  Pretty simple.  Feel free to
     * save the returned string as tasteful.yaml and pass it around.
     *
     * Oh, and you can decide how big the indent is and what the wordwrap
     * for folding is.  Pretty cool -- just pass in 'false' for either if
     * you want to use the default.
     *
     * Indent's default is 2 spaces, wordwrap's default is 40 characters.  And
     * you can turn off wordwrap by passing in 0.
     *
     * @param array $array PHP array
     * @param int $indent Pass in false to use the default, which is 2
     * @param int $wordwrap Pass in 0 for no wordwrap, false for default (40)
     * @return string
     */
    public function dump($array, $indent = false, $wordwrap = false) {
        // Dumps to some very clean YAML.  We'll have to add some more features
        // and options soon.  And better support for folding.

        // New features and options.
        if ($indent === false or !is_numeric($indent)) {
            $this->_dumpIndent = 2;
        } else {
            $this->_dumpIndent = $indent;
        }

        if ($wordwrap === false or !is_numeric($wordwrap)) {
            $this->_dumpWordWrap = 40;
        } else {
            $this->_dumpWordWrap = $wordwrap;
        }

        // New YAML document
        $string = "---\n";

        // Start at the base of the array and move through it.
        if ($array) {
            $array     = (array)$array;
            $first_key = key($array);

            $previous_key = -1;
            foreach ($array as $key => $value) {
                $string      .= $this->_yamlize($key, $value, 0, $previous_key, $first_key);
                $previous_key = $key;
            }
        }
        return $string;
    }

    /**
     * Attempts to convert a key / value array item to YAML
     *
     * @param string $key The name of the key
     * @param mixed $value The value of the item
     * @param bool $indent The indent of the current node
     * @param int $previous_key The previous key
     * @param int $first_key The first key
     * @return string
     */
    private function _yamlize($key, $value, $indent, $previous_key = -1, $first_key = 0) {
        if (is_array($value)) {
            if (empty ($value)) {
                return $this->_dumpNode($key, array(), $indent, $previous_key, $first_key);
            }
            // It has children.  What to do?
            // Make it the right kind of item
            $string = $this->_dumpNode($key, null, $indent, $previous_key, $first_key);
            // Add the indent
            $indent += $this->_dumpIndent;
            // Yamlize the array
            $string .= $this->_yamlizeArray($value, $indent);
        } elseif (!is_array($value)) {
            // It doesn't have children.  Yip.
            $string = $this->_dumpNode($key, $value, $indent, $previous_key, $first_key);
        }
        return $string;
    }

    /**
     * Attempts to convert an array to YAML
     *
     * @param array $array The array you want to convert
     * @param mixed $indent The indent of the current level
     * @return string
     */
    private function _yamlizeArray($array, $indent) {
        if (is_array($array)) {
            $string       = '';
            $previous_key = -1;
            $first_key    = key($array);

            foreach ($array as $key => $value) {
                $string      .= $this->_yamlize($key, $value, $indent, $previous_key, $first_key);
                $previous_key = $key;
            }
            return $string;
        } else {
            return false;
        }
    }

    /**
     * Returns YAML from a key and a value
     *
     * @param string $key The name of the key
     * @param mixed $value The value of the item
     * @param bool $indent The indent of the current node
     * @param int $previous_key The previous key
     * @param int $first_key The first key
     * @return string
     */
    private function _dumpNode($key, $value, $indent, $previous_key = -1, $first_key = 0) {
        // do some folding here, for blocks
        if (is_string($value)
            && ((strpos($value, "\n") !== false
            || strpos($value, ": ") !== false
            || strpos($value, "- ") !== false
            || strpos($value, "*") !== false
            || strpos($value, "#") !== false
            || strpos($value, "<") !== false
            || strpos($value, ">") !== false
            || strpos($value, '  ') !== false
            || strpos($value, "[") !== false
            || strpos($value, "]") !== false
            || strpos($value, "{") !== false
            || strpos($value, "}") !== false)
            || substr($value, -1, 1) == ':')
        ) {
            $value = $this->_doLiteralBlock($value, $indent);
        } else {
            $value = $this->_doFolding($value, $indent);
        }

        if ($value === array()) {
            $value = '[ ]';
        }
        if (in_array($value, array('true', 'TRUE', 'false', 'FALSE', 'y', 'Y', 'n', 'N', 'null', 'NULL'), true)) {
            $value = $this->_doLiteralBlock($value, $indent);
        }
        if (trim($value) != $value) {
            $value = $this->_doLiteralBlock($value, $indent);
        }

        if (is_bool($value)) {
            $value = ($value) ? "true" : "false";
        }

        $spaces = str_repeat(' ', $indent);

        if (is_int($key) && $key - 1 == $previous_key && $first_key===0) {
            // It's a sequence
            $string = $spaces.'- '.$value."\n";
        } else {
            if ($first_key===0) {
                throw new Exception('Keys are all screwy.  The first one was zero, now it\'s "'. $key .'"');
            }
            // It's mapped
            if (strpos($key, ":") !== false) {
                $key = '"' . $key . '"';
            }
            $string = $spaces.$key.': '.$value."\n";
        }
        return $string;
    }

    /**
     * Creates a literal block for dumping
     *
     * @param mixed $value The value
     * @param int $indent The value of the indent
     * @return string
     */
    private function _doLiteralBlock($value, $indent) {
        if ($value === "\n") {
            return '\n';
        }
        if (strpos($value, "\n") === false && strpos($value, "'") === false) {
            return sprintf("'%s'", $value);
        }
        if (strpos($value, "\n") === false && strpos($value, '"') === false) {
            return sprintf('"%s"', $value);
        }
        $exploded = explode("\n", $value);
        $newValue = '|';
        $indent  += $this->_dumpIndent;
        $spaces   = str_repeat(' ', $indent);

        foreach ($exploded as $line) {
            $newValue .= "\n" . $spaces . ($line);
        }
        return $newValue;
    }

    /**
     * Folds a string of text, if necessary
     *
     * @param string $value The string you wish to fold
     * @param int $indent The value of the indent
     * @return string
     */
    private function _doFolding($value, $indent) {
        // Don't do anything if wordwrap is set to 0

        if ($this->_dumpWordWrap !== 0
            && is_string($value) && strlen($value) > $this->_dumpWordWrap
        ) {
            $indent += $this->_dumpIndent;
            $indent  = str_repeat(' ', $indent);
            $wrapped = wordwrap($value, $this->_dumpWordWrap, "\n$indent");
            $value   = ">\n" . $indent . $wrapped;
        } else {
            if ($this->setting_dump_force_quotes && is_string($value)) {
                $value = '"' . $value . '"';
            }
        }

        return $value;
    }

    /**
     * Load
     * 
     * @param mixed $input The yaml input filename
     * @return mixed
     */
    private function _load($input) {
        $source = $this->_loadFromSource($input);
        return $this->_loadWithSource($source);
    }

    /**
     * Load a string
     * 
     * @param mixed $input Yaml string
     * @return mixed
     */
    private function _loadString($input) {
        $source = $this->_loadFromString($input);
        return $this->_loadWithSource($source);
    }

    /**
     * Load with source
     * 
     * @param mixed $source The source loaded
     * @return array
     */
    private function _loadWithSource($source) {
        if (empty ($source)) {
            return array();
        }

        if ($this->setting_use_syck_is_possible && function_exists('syck_load')) {
            $array = syck_load(implode('', $source));
            return is_array($array) ? $array : array();
        }

        $this->_path   = array();
        $this->_result = array();

        $cnt = count($source);
        for ($i = 0; $i < $cnt; $i++) {
            $line = $source[$i];

            $this->_indent = strlen($line) - strlen(ltrim($line));

            $tempPath = $this->_getParentPathByIndent($this->_indent);
            $line     = self::_stripIndent($line, $this->_indent);

            if (self::_isComment($line)) {
                continue;
            }

            if (self::_isEmpty($line)) {
                continue;
            }

            $this->_path = $tempPath;

            $literalBlockStyle = self::_startsLiteralBlock($line);
            if ($literalBlockStyle) {
                $line         = rtrim($line, $literalBlockStyle . " \n");
                $literalBlock = '';

                $line .= $this->_literalPlaceHolder;

                while (++$i < $cnt && $this->_literalBlockContinues($source[$i], $this->_indent)) {
                    $literalBlock = $this->_addLiteralLine($literalBlock, $source[$i], $literalBlockStyle);
                }
                $i--;
            }

            while (++$i < $cnt && self::_greedilyNeedNextLine($line)) {
                $line = rtrim($line, " \n\t\r") . ' ' . ltrim($source[$i], " \t");
            }
            $i--;

            if (strpos($line, '#')) {
                if (strpos($line, '"') === false && strpos($line, "'") === false) {
                    $line = preg_replace('/\s+#(.+)$/', '', $line);
                }
            }

            $lineArray = $this->_parseLine($line);

            if ($literalBlockStyle) {
                $lineArray = $this->revertLiteralPlaceHolder($lineArray, $literalBlock);
            }

            $this->_addArray($lineArray, $this->_indent);

            foreach ($this->_delayedPath as $indent => $delayedPath) {
                $this->_path[$indent] = $delayedPath;
            }

            $this->_delayedPath = array();
        }

        return $this->_result;
    }

    /**
     * Load from source
     * 
     * @param string $input Filename
     * @return mixed
     */
    private function _loadFromSource($input) {
        if (!empty($input) && strpos($input, "\n") === false && file_exists($input)) {
            return file($input);
        }

        return $this->_loadFromString($input);
    }

    /**
     * Load from string
     * 
     * @param string $input Yaml string
     * @return array
     */
    private function _loadFromString ($input) {
        $lines = explode("\n", $input);

        foreach ($lines as $k => $_) {
            $lines[$k] = rtrim($_, "\r");
        }

        return $lines;
    }

    /**
     * Parses YAML code and returns an array for a node
     *
     * @param string $line A line from the YAML file
     * @return array
     */
    private function _parseLine($line) {
        if (!$line) {
            return array();
        }
        $line = trim($line);
        if (!$line) {
            return array();
        }

        $array = array();

        $group = $this->_nodeContainsGroup($line);
        if ($group) {
            $this->_addGroup($line, $group);
            $line = $this->_stripGroup($line, $group);
        }

        if ($this->_startsMappedSequence($line)) {
            return $this->_returnMappedSequence($line);
        }

        if ($this->_startsMappedValue($line)) {
            return $this->_returnMappedValue($line);
        }

        if ($this->_isArrayElement($line)) {
            return $this->_returnArrayElement($line);
        }

        if ($this->_isPlainArray($line)) {
            return $this->_returnPlainArray($line);
        }

        return $this->_returnKeyValuePair($line);
    }

    /**
     * Finds the type of the passed value, returns the value as the new type.
     *
     * @param string $value Value
     * @return mixed
     */
    private function _toType($value) {
        if ($value === '') {
            return null;
        }
        $first_character = $value[0];
        $last_character  = substr($value, -1, 1);

        $is_quoted = false;
        do {
            if (!$value) {
                break;
            }

            if ($first_character != '"' && $first_character != "'") {
                break;
            }

            if ($last_character != '"' && $last_character != "'") {
                break;
            }
            $is_quoted = true;
        } while (0);

        if ($is_quoted) {
            return strtr(substr($value, 1, -1), array('\\"' => '"', '\'\'' => '\'', '\\\'' => '\''));
        }

        if (strpos($value, ' #') !== false && !$is_quoted) {
            $value = preg_replace('/\s+#(.+)$/', '', $value);
        }

        if (!$is_quoted) {
            $value = str_replace('\n', "\n", $value);
        }

        if ($first_character == '[' && $last_character == ']') {
            // Take out strings sequences and mappings
            $innerValue = trim(substr($value, 1, -1));
            if ($innerValue === '') {
                return array();
            }
            $explode = $this->_inlineEscape($innerValue);
            // Propagate value array
            $value = array();
            foreach ($explode as $v) {
                $value[] = $this->_toType($v);
            }
            return $value;
        }

        if (strpos($value, ': ') !== false && $first_character != '{') {
            $array = explode(': ', $value);
            $key   = trim($array[0]);
            array_shift($array);
            $value = trim(implode(': ', $array));
            $value = $this->_toType($value);
            return array($key => $value);
        }

        if ($first_character == '{' && $last_character == '}') {
            $innerValue = trim(substr($value, 1, -1));
            if ($innerValue === '') {
                return array();
            }
            // Inline Mapping
            // Take out strings sequences and mappings
            $explode = $this->_inlineEscape($innerValue);
            // Propagate value array
            $array = array();
            foreach ($explode as $v) {
                $SubArr = $this->_toType($v);
                if (empty($SubArr)) {
                    continue;
                }
                if (is_array($SubArr)) {
                    $array[key($SubArr)] = $SubArr[key($SubArr)];
                    continue;
                }
                $array[] = $SubArr;
            }
            return $array;
        }

        if ($value == 'null' || $value == 'NULL' || $value == 'Null' || $value == '' || $value == '~') {
            return null;
        }

        if (intval($first_character) > 0 && preg_match('/^[1-9]+[0-9]*$/', $value)) {
            $intvalue = (int) $value;
            if ($intvalue != PHP_INT_MAX) {
                $value = $intvalue;
            }
            return $value;
        }

        if (in_array($value, array('true', 'on', '+', 'yes', 'y', 'True', 'TRUE', 'On', 'ON', 'YES', 'Yes', 'Y'))) {
            return true;
        }

        if (in_array(strtolower($value), array('false', 'off', '-', 'no', 'n'))) {
            return false;
        }

        if (is_numeric($value)) {
            if ($value === '0') {
                return 0;
            }
            if (trim($value, 0) === $value) {
                $value = (float) $value;
            }
            return $value;
        }

        return $value;
    }

    /**
     * Used in inlines to check for more inlines or quoted strings
     *
     * @param mixed $inline Inline text
     * @return void
     */
    private function _inlineEscape($inline) {
        // There's gotta be a cleaner way to do this...
        // While pure sequences seem to be nesting just fine,
        // pure mappings and mappings with sequences inside can't go very
        // deep.  This needs to be fixed.

        $seqs          = array();
        $maps          = array();
        $saved_strings = array();

        // Check for strings
        $regex = '/(?:(")|(?:\'))((?(1)[^"]+|[^\']+))(?(1)"|\')/';
        if (preg_match_all($regex, $inline, $strings)) {
            $saved_strings = $strings[0];
            $inline        = preg_replace($regex, 'YAMLString', $inline);
        }
        unset($regex);

        $i = 0;
        do {
            // Check for sequences
            while (preg_match('/\[([^{}\[\]]+)\]/U', $inline, $matchseqs)) {
                $seqs[] = $matchseqs[0];
                $inline = preg_replace('/\[([^{}\[\]]+)\]/U', ('YAMLSeq' . (count($seqs) - 1) . 's'), $inline, 1);
            }

            // Check for mappings
            while (preg_match('/{([^\[\]{}]+)}/U', $inline, $matchmaps)) {
                $maps[] = $matchmaps[0];
                $inline = preg_replace('/{([^\[\]{}]+)}/U', ('YAMLMap' . (count($maps) - 1) . 's'), $inline, 1);
            }

            if ($i++ >= 10) {
                break;
            }
        } while (strpos($inline, '[') !== false || strpos($inline, '{') !== false);

        $explode = explode(', ', $inline);
        $stringi = 0;
        $i       = 0;

        while (1) {
            // Re-add the sequences
            if (!empty($seqs)) {
                foreach ($explode as $key => $value) {
                    if (strpos($value, 'YAMLSeq') !== false) {
                        foreach ($seqs as $seqk => $seq) {
                            $explode[$key] = str_replace(('YAMLSeq' . $seqk . 's'), $seq, $value);

                            $value = $explode[$key];
                        }
                    }
                }
            }

            // Re-add the mappings
            if (!empty($maps)) {
                foreach ($explode as $key => $value) {
                    if (strpos($value, 'YAMLMap') !== false) {
                        foreach ($maps as $mapk => $map) {
                            $explode[$key] = str_replace(('YAMLMap' . $mapk . 's'), $map, $value);

                            $value = $explode[$key];
                        }
                    }
                }
            }

            // Re-add the strings
            if (!empty($saved_strings)) {
                foreach ($explode as $key => $value) {
                    while (strpos($value, 'YAMLString') !== false) {
                        $explode[$key] = preg_replace('/YAMLString/', $saved_strings[$stringi], $value, 1);
                        unset($saved_strings[$stringi]);
                        ++$stringi;
                        $value = $explode[$key];
                    }
                }
            }

            $finished = true;
            foreach ($explode as $key => $value) {
                if (strpos($value, 'YAMLSeq') !== false) {
                    $finished = false;
                    break;
                }
                if (strpos($value, 'YAMLMap') !== false) {
                    $finished = false;
                    break;
                }
                if (strpos($value, 'YAMLString') !== false) {
                    $finished = false;
                    break;
                }
            }
            if ($finished) {
                break;
            }

            $i++;
            if ($i > 10) {
                break; // Prevent infinite loops.
            }
        }

        return $explode;
    }

    /**
     * Literal block continues
     *
     * @param string $line The yml line
     * @param int $lineIndent The indent value
     * @return bool
     */
    private function _literalBlockContinues($line, $lineIndent) {
        if (!trim($line)) {
            return true;
        }
        if (strlen($line) - strlen(ltrim($line)) > $lineIndent) {
            return true;
        }
        return false;
    }

    /**
     * Reference contents by alias
     * 
     * @param string $alias Alias name
     * @return mixed
     */
    private function _referenceContentsByAlias($alias) {
        do {
            if (!isset($this->_savedGroups[$alias])) {
                echo "Bad group name: $alias.";
                break;
            }
            $groupPath = $this->_savedGroups[$alias];
            $value     = $this->_result;

            foreach ($groupPath as $k) {
                $value = $value[$k];
            }
        } while (false);

        return $value;
    }

    /**
     * Add array inline
     * 
     * @param array $array An array
     * @param int $indent Indent value
     * @return bool
     */
    private function _addArrayInline($array, $indent) {
        $CommonGroupPath = $this->_path;
        if (empty($array)) {
            return false;
        }

        foreach ($array as $k => $_) {
            $this->_addArray(array($k => $_), $indent);
            $this->_path = $CommonGroupPath;
        }
        return true;
    }

    /**
     * Add array
     * 
     * @param array $incoming_data Input data
     * @param int $incoming_indent Indent value
     * @return mixed
     */
    private function _addArray($incoming_data, $incoming_indent) {
        if (count($incoming_data) > 1) {
            return $this->_addArrayInline($incoming_data, $incoming_indent);
        }

        $key   = key($incoming_data);
        $value = isset($incoming_data[$key]) ? $incoming_data[$key] : null;

        if ($key === '__!YAMLZero') {
            $key = '0';
        }

        if ($incoming_indent == 0 && !$this->_containsGroupAlias && !$this->_containsGroupAnchor) { // Shortcut for root-level values.
            if ($key || $key === '' || $key === '0') {
                $this->_result[$key] = $value;
            } else {
                $this->_result[] = $value;
                end($this->_result);
                $key = key($this->_result);
            }
            $this->_path[$incoming_indent] = $key;
            return;
        }

        $history = array();
        // Unfolding inner array tree.
        $history[] = $_arr = $this->_result;
        foreach ($this->_path as $k) {
            $history[] = $_arr = $_arr[$k];
        }

        if ($this->_containsGroupAlias) {
            $value = $this->_referenceContentsByAlias($this->_containsGroupAlias);

            $this->_containsGroupAlias = false;
        }

        // Adding string or numeric key to the innermost level or $this->arr.
        if (is_string($key) && $key == '<<') {
            if (!is_array($_arr)) {
                $_arr = array ();
            }

            $_arr = array_merge($_arr, $value);
        } else if ($key || $key === '' || $key === '0') {
            $_arr[$key] = $value;
        } else {
            if (!is_array($_arr)) {
                $_arr = array($value);
                $key  = 0;
            } else { 
                $_arr[] = $value;
                end($_arr);
                $key = key($_arr);
            }
        }

        $reverse_path       = array_reverse($this->_path);
        $reverse_history    = array_reverse($history);
        $reverse_history[0] = $_arr;

        $cnt = count($reverse_history) - 1;

        for ($i = 0; $i < $cnt; $i++) {
            $reverse_history[$i+1][$reverse_path[$i]] = $reverse_history[$i];
        }
        $this->_result = $reverse_history[$cnt];

        $this->_path[$incoming_indent] = $key;

        if ($this->_containsGroupAnchor) {
            $this->_savedGroups[$this->_containsGroupAnchor] = $this->_path;
            if (is_array($value)) {
                $k = key($value);
                if (!is_int($k)) {
                    $this->_savedGroups[$this->_containsGroupAnchor][$incoming_indent + 2] = $k;
                }
            }
            $this->_containsGroupAnchor = false;
        }
    }

    /**
     * Starts literal block
     * 
     * @param string $line Line
     * @return mixed
     */
    private static function _startsLiteralBlock($line) {
        $lastChar = substr(trim($line), -1);

        if ($lastChar != '>' && $lastChar != '|') {
            return false;
        }

        if ($lastChar == '|') {
            return $lastChar;
        }

        // HTML tags should not be counted as literal blocks.
        if (preg_match('#<.*?>$#', $line)) {
            return false;
        }

        return $lastChar;
    }

    /**
     * Greedily need next line
     * 
     * @param string $line Line
     * @return bool
     */
    private static function _greedilyNeedNextLine($line) {
        $line = trim($line);

        if (!strlen($line)) {
            return false;
        }

        if (substr($line, -1, 1) == ']') {
            return false;
        }

        if ($line[0] == '[') {
            return true;
        }

        if (preg_match('#^[^:]+?:\s*\[#', $line)) {
            return true;
        }

        return false;
    }

    /**
     * Add literal line
     * 
     * @param mixed $literalBlock Literal block
     * @param mixed $line Line
     * @param mixed $literalBlockStyle Style
     * @return mixed
     */
    private function _addLiteralLine ($literalBlock, $line, $literalBlockStyle) {
        $line = self::_stripIndent($line);
        $line = rtrim($line, "\r\n\t ") . "\n";

        if ($literalBlockStyle == '|') {
            return $literalBlock . $line;
        }

        if (strlen($line) == 0) {
            return rtrim($literalBlock, ' ') . "\n";
        }

        if ($line == "\n" && $literalBlockStyle == '>') {
            return rtrim($literalBlock, " \t") . "\n";
        }

        if ($line != "\n") {
            $line = trim($line, "\r\n ") . " ";
        }

        return $literalBlock . $line;
    }

    /**
     * Revert literal place holder
     * 
     * @param mixed $lineArray Line array
     * @param mixed $literalBlock Literal block
     * @return array
     */
    public function revertLiteralPlaceHolder($lineArray, $literalBlock) {
        foreach ($lineArray as $k => $_) {
            if (is_array($_)) {
                $lineArray[$k] = $this->revertLiteralPlaceHolder($_, $literalBlock);
            } else if (substr($_, -1 * strlen($this->_literalPlaceHolder)) == $this->_literalPlaceHolder) {
                $lineArray[$k] = rtrim($literalBlock, " \r\n");
            }
        }
        return $lineArray;
    }

    /**
     * Strip indent
     * 
     * @param mixed $line Line
     * @param int $indent Indent value
     * @return string
     */
    private static function _stripIndent($line, $indent = -1) {
        if ($indent == -1) {
            $indent = strlen($line) - strlen(ltrim($line));
        }
        return substr($line, $indent);
    }

    /**
     * Get parent path by indent
     * 
     * @param int $indent Indent value
     * @return mixed
     */
    private function _getParentPathByIndent($indent) {
        if ($indent == 0) {
            return array();
        }

        $linePath = $this->_path;

        do {
            end($linePath);
            $lastIndentInParentPath = key($linePath);
            if ($indent <= $lastIndentInParentPath) {
                array_pop($linePath);
            }
        } while ($indent <= $lastIndentInParentPath);

        return $linePath;
    }

    /**
     * Clear bigger path values
     * 
     * @param int $indent Indent value
     * @return mixed
     */
    private function _clearBiggerPathValues ($indent) {
        if ($indent == 0) {
            $this->_path = array();
        }
        if (empty($this->_path)) {
            return true;
        }

        foreach ($this->_path as $k => $_) {
            if ($k > $indent) {
                unset($this->_path[$k]);
            }
        }

        return true;
    }

    /**
     * Is comment
     * 
     * @param string $line Line
     * @return bool
     */
    private static function _isComment($line) {
        if (!$line) {
            return false;
        }

        if ($line[0] == '#') {
            return true;
        }

        if (trim($line, " \r\n\t") == '---') {
            return true;
        }

        return false;
    }

    /**
     * Is empty
     * 
     * @param string $line Line
     * @return bool
     */
    private static function _isEmpty($line) {
        return (trim($line) === '');
    }

    /**
     * Is array element
     * 
     * @param string $line Line
     * @return bool
     */
    private function _isArrayElement($line) {
        if (!$line) {
            return false;
        }
        
        if ($line[0] != '-') {
            return false;
        }

        if (strlen($line) > 3) {
            if (substr($line, 0, 3) == '---') {
                return false;
            }
        }

        return true;
    }

    /**
     * Is hash element
     * 
     * @param string $line Line
     * @return int
     */
    private function _isHashElement($line) {
        return strpos($line, ':');
    }

    /**
     * Is literal
     * 
     * @param string $line Line
     * @return void
     */
    private function _isLiteral($line) {
        if ($this->_isArrayElement($line)) {
            return false;
        }

        if ($this->_isHashElement($line)) {
            return false;
        }

        return true;
    }

    /**
     * Unquote
     * 
     * @param string $value A value
     * @return void
     */
    private static function _unquote($value) {
        if (!$value) {
            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }

        if ($value[0] == '\'') {
            return trim($value, '\'');
        }

        if ($value[0] == '"') {
            return trim($value, '"');
        }

        return $value;
    }

    /**
     * Starts mapped sequence
     * 
     * @param string $line Line
     * @return bool
     */
    private function _startsMappedSequence($line) {
        return ($line[0] == '-' && substr($line, -1, 1) == ':');
    }

    /**
     * Return mapped sequence
     * 
     * @param string $line Line
     * @return array
     */
    private function _returnMappedSequence($line) {
        $array = array();

        $key         = self::_unquote(trim(substr($line, 1, -1)));
        $array[$key] = array();

        $this->_delayedPath = array(strpos($line, $key) + $this->_indent => $key);

        return array($array);
    }

    /**
     * Return mapped value
     * 
     * @param string $line Line
     * @return array
     */
    private function _returnMappedValue($line) {
        $array = array();

        $key = self::_unquote(trim(substr($line, 0, -1)));

        $array[$key] = '';

        return $array;
    }

    /**
     * Starts mapped values
     * 
     * @param string $line Line
     * @return bool
     */
    private function _startsMappedValue ($line) {
        return (substr($line, -1, 1) == ':');
    }

    /**
     * Is plain array
     * 
     * @param string $line Line
     * @return bool
     */
    private function _isPlainArray($line) {
        return ($line[0] == '[' && substr($line, -1, 1) == ']');
    }

    /**
     * Return plain array
     * 
     * @param string $line Line
     * @return string
     */
    private function _returnPlainArray($line) {
        return $this->_toType($line);
    }

    /**
     * Return key value pair
     * 
     * @param string $line Line
     * @return array
     */
    private function _returnKeyValuePair($line) {
        $array = array();
        $key   = '';

        if (strpos($line, ':')) {
            // It's a key/value pair most likely
            // If the key is in double quotes pull it out
            if (($line[0] == '"' || $line[0] == "'") && preg_match('/^(["\'](.*)["\'](\s)*:)/', $line, $matches)) {
                $value = trim(str_replace($matches[1], '', $line));
                $key   = $matches[2];
            } else {
                // Do some guesswork as to the key and the value
                $explode = explode(':', $line);
                $key     = trim($explode[0]);
                array_shift($explode);
                $value = trim(implode(':', $explode));
            }
            // Set the type of the value.  Int, string, etc
            $value = $this->_toType($value);
            if ($key === '0') {
                $key = '__!YAMLZero';
            }
            $array[$key] = $value;
        } else {
            $array = array($line);
        }
        return $array;

    }

    /**
     * Return array element
     * 
     * @param string $line Line
     * @return array
     */
    private function _returnArrayElement($line) {
        if (strlen($line) <= 1) {
            return array(array()); // Weird %)
        }
        
        $array = array();

        $value = trim(substr($line, 1));
        $value = $this->_toType($value);

        $array[] = $value;

        return $array;
    }

    /**
     * Node contains group
     * 
     * @param string $line Line
     * @return bool
     */
    private function _nodeContainsGroup($line) {
        $symbolsForReference = 'A-z0-9_\-';

        if (strpos($line, '&') === false && strpos($line, '*') === false) {
            return false; // Please die fast ;-)
        }

        if ($line[0] == '&' && preg_match('/^(&['.$symbolsForReference.']+)/', $line, $matches)) {
            return $matches[1];
        }

        if ($line[0] == '*' && preg_match('/^(\*['.$symbolsForReference.']+)/', $line, $matches)) {
            return $matches[1];
        }

        if (preg_match('/(&['.$symbolsForReference.']+)$/', $line, $matches)) {
            return $matches[1];
        }

        if (preg_match('/(\*['.$symbolsForReference.']+$)/', $line, $matches)) {
            return $matches[1];
        }

        if (preg_match('#^\s*<<\s*:\s*(\*[^\s]+).*$#', $line, $matches)) {
            return $matches[1];
        }

        return false;
    }

    /**
     * Add group
     * 
     * @param string $line Line
     * @param mixed $group Group
     * @return void
     */
    private function _addGroup ($line, $group) {
        if ($group[0] == '&') {
            $this->_containsGroupAnchor = substr($group, 1);
        }

        if ($group[0] == '*') {
            $this->_containsGroupAlias = substr($group, 1);
        }
    }

    /**
     * Strip group
     * 
     * @param mixed $line Line
     * @param mixed $group Group
     * @return string
     */
    private function _stripGroup($line, $group) {
        $line = trim(str_replace($group, '', $line));
        return $line;
    }
}

// Enable use of Qi_Spyc from command line
// The syntax is the following: php spyc.php spyc.yaml

define('SPYC_FROM_COMMAND_LINE', false);

do {
    if (!SPYC_FROM_COMMAND_LINE) {
        break;
    }
    if (empty($_SERVER['argc']) || $_SERVER['argc'] < 2) {
        break;
    }
    if (empty($_SERVER['PHP_SELF']) || $_SERVER['PHP_SELF'] != 'spyc.php') {
        break;
    }
    $file = $argv[1];
    
    printf("Spyc loading file: %s\n", $file);
    print_r(spyc_load_file($file));
} while (0);
