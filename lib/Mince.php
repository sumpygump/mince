<?php
/**
 * Mince class file
 *
 * @package Mince
 */

require_once 'bootstrap.php';

/**
 * Mince class
 *
 * Minify and combine files according to a config file
 *
 * @package Mince
 * @author Jansen Price <jansen.price@nerdery.com>
 * @version $Id$
 */
class Mince
{
    /**
     * Version
     * 
     * @var string
     */
    const VERSION = '1.3.0';

    /**
     * The filename of the minceconf file
     *
     * @var string
     */
    protected $_configFilename = '.minceconf';

    /**
     * Mince configuration
     * 
     * @var array
     */
    protected $_minceconf = array();

    /**
     * Output
     *
     * @var array
     */
    public $output = array();

    /**
     * Constructor
     *
     * @param MinceClient $console Mince console object
     * @return void
     */
    public function __construct(MinceClient $console = null)
    {
        $this->_console = $console;
    }

    /**
     * Set config file
     * 
     * @param mixed $filename Filename
     * @return void
     */
    public function setConfigFile($filename)
    {
        $this->_configFilename = $filename;
    }

    /**
     * Initiate the minify and combine (mince) action
     *
     * @return void
     */
    public function execute()
    {
        $this->readConfig();

        if (empty($this->_minceconf['minify'])
            && empty($this->_minceconf['combine'])
        ) {
            throw new Exception(
                'No minify or combine directives in minceconf. '
                . '(\'' . $this->_configFilename . '\')'
            );
        }

        // Process the directives in the order they appear in the config
        foreach ($this->_minceconf as $section => $value) {
            switch ($section) {
            case 'minify':
                if (empty($value)) {
                    continue;
                }
                $this->_minify();
                break;
            case 'combine':
                if (empty($value)) {
                    continue;
                }
                $this->_combine();
                break;
            default:
                break;
            }
        }

        return $this->output;
    }

    /**
     * Read the config file
     *
     * @param string $filename Filename
     * @return object self
     */
    public function readConfig($filename = null)
    {
        if (null === $filename) {
            $filename = $this->_configFilename;
        }

        if (!file_exists($filename)) {
            throw new Exception(
                'Minceconf file not found (' . $filename. ').'
            );
        }

        $this->_configFilename = $filename;

        $this->_notify("Reading file '$filename'.", 2);

        $conf = file_get_contents($filename);

        $this->_parseMinceconf($conf);

        return $this;
    }

    /**
     * Parse mince conf
     *
     * @param string $conf Configuration data
     * @return void
     */
    protected function _parseMinceconf($conf)
    {
        $this->_notify('Parsing mince config.', 2);

        $this->_minceconf = array_merge(
            $this->_minceconf, Qi_Spyc::YAMLLoad($conf)
        );

        $counts = $this->_countRules();
        $this->_notify('Parsed ' . array_sum($counts) . ' rules.', 2);
    }

    /**
     * Count mince rules
     * 
     * @return array
     */
    protected function _countRules()
    {
        if (empty($this->_minceconf)) {
            $counts = array(0, 0);
        }

        $minifyCount  = 0;
        $combineCount = 0;

        if (!empty($this->_minceconf['minify'])) {
            $minifyCount = count($this->_minceconf['minify']);
        }

        if (!empty($this->_minceconf['combine'])) {
            foreach ($this->_minceconf['combine'] as $combine) {
                if (!is_array($combine)) {
                    continue;
                }
                $combineCount += count($combine);
            }
        }

        return array($minifyCount, $combineCount);
    }

    /**
     * Minify files according to conf directives
     *
     * @return void
     */
    protected function _minify()
    {
        $this->_notify('Begin minifying ...');

        foreach ($this->_minceconf['minify'] as $filename) {
            if (!file_exists($filename)) {
                $this->_notify("  File '$filename' doesn't exist.", 0);
                continue;
            }

            $filetype = pathinfo($filename, PATHINFO_EXTENSION);
            switch ($filetype) {
            case 'js':
                $outfile = preg_replace('/\.js$/', '.min.js', $filename);

                $cmd = 'jsmin > ' . $outfile . ' < ' . $filename;
                $this->_runCmd($cmd);
                break;
            case 'css':
                $outfile = str_replace('.css', '.min.css', $filename);

                $cmd = 'csstidy ' . $filename . ' ' . $outfile
                    . ' --template=highest --silent=true';
                $this->_runCmd($cmd);
                break;
            }
        }

        $this->_notify('Done minifying');
    }

    /**
     * Combine files according to the conf directives
     *
     * @return void
     */
    protected function _combine()
    {
        $this->_notify('Begin combining...');

        foreach ($this->_minceconf['combine'] as $targetFile => $files) {
            $combined = '';

            if (!is_array($files)) {
                $files = array($files);
            }

            foreach ($files as $filename) {
                if (!file_exists($filename)) {
                    $this->_notify("  File '$filename' does not exist.", 0);
                    continue;
                }
                $fileData = file_get_contents($filename);
                $combined = $combined . "\n/*" . $filename . "*/ " . $fileData;
                $this->_notify(
                    '  Adding ' . $filename . ' to ' . $targetFile . "\n", 3
                );
            }

            $message  = 'Writing ' . $targetFile;
            $result   = file_put_contents($targetFile, $combined);
            $message .= " (" . $result . " bytes)";
            $this->_notify($message, 3);
        }

        $this->_notify('Done combining.');
    }

    /**
     * Run a shell command
     *
     * @param string $cmd Command
     * @return void
     */
    protected function _runCmd($cmd)
    {
        $this->_notify('  ' . $cmd, 3);

        exec($cmd, $output, $result);

        if ($result) {
            $this->_notify($result . ' ' . implode("\n", $output), 0);
        }
    }

    /**
     * Notify
     * 
     * @param mixed $message Message to display
     * @param int $level Message level
     *      0 = warning message
     *      1 = regular
     *      2 = verbose
     *      3 = action
     * @return void
     */
    protected function _notify($message, $level = 1)
    {
        if ($this->_console != null) {
            $this->_console->notify($message, $level);
        }

        $message = trim($message);
        if ($level == 0) {
            $message = "WARNING: " . $message;
        }

        array_push($this->output, $message);
    }

    /**
     * Get the version number
     * 
     * @return string
     */
    public static function getVersion()
    {
        return self::VERSION;
    }
}
