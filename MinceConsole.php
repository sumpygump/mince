<?php
/**
 * Mince Console class file 
 *
 * @package Mince
 */

/**
 * Mince console class
 *
 * A command line terminal interface to Mince
 * 
 * @package Mince
 * @author Jansen Price <jansen.price@nerdery.com>
 * @version $Id$
 */
class MinceConsole
{
    /**
     * The terminal object
     *
     * @var object Terminal
     */
    protected $_terminal;

    /**
     * Be quiet
     *
     * @var mixed
     */
    protected $_quiet = false;

    /**
     * Be verbose
     *
     * @var mixed
     */
    protected $_verbose = false;

    /**
     * Exit status
     * 
     * @var float
     */
    protected $_status = 0;

    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct()
    {
        $this->_terminal = new Qi_Console_Terminal();
    }

    /**
     * Execute Mince
     * 
     * @param string $filename Config filename to use
     * @return int
     */
    public function execute($filename = '')
    {
        $mince = new Mince($this);

        if ($filename != '') {
            $mince->setConfigFile($filename);
        }

        try {
            $mince->execute();
        } catch (Exception $e) {
            $this->_halt($e->getMessage());
        }

        return $this->_status;
    }

    /**
     * Set Verbose level
     * 
     * @param bool $value Value
     * @return void
     */
    public function setVerbose($value)
    {
        $this->_verbose = (bool) $value;
    }
    
    /**
     * Set quiet level
     * 
     * @param bool $value Value
     * @return void
     */
    public function setQuiet($value)
    {
        $this->_quiet = (bool) $value;
    }

    /**
     * Show version information
     * 
     * @return void
     */
    public function showVersion()
    {
        echo "Mince " . Mince::getVersion() . "\n";
    }

    /**
     * Notify (only display if verbose)
     *
     * @param string $message Message
     * @param int $level Message level
     *      0 = warning message
     *      1 = regular
     *      2 = verbose
     *      3 = action
     * @return void
     */
    public function notify($message, $level = 1)
    {
        switch ($level) {
        case 0:
            $this->_displayWarning($message);
            $this->_status = 2;
            break;
        case 1:
            if (!$this->_quiet) {
                $this->_displayMessage($message);
            }
            break;
        case 2:
            if ($this->_verbose && !$this->_quiet) {
                $this->_displayMessage(">> " . $message, true, 3);
            }
            break;
        default:
            if ($this->_quiet) {
                return;
            }

            if (substr($message, -1) != "\n") {
                $message .= "\n";
            }

            echo $message;

            break;
        }
    }

    /**
     * Display Message
     *
     * @param string $message Message
     * @param bool $ensureNewline Ensure message has a new line
     * @param int $color Text foreground color
     * @return void
     */
    protected function _displayMessage(
        $message, $ensureNewline = true, $color = 2)
    {
        if ($ensureNewline && substr($message, -1) != "\n") {
            $message .= "\n";
        }

        $this->_terminal->setaf($color);

        echo $message;

        $this->_terminal->op();
    }

    /**
     * Warning message
     *
     * @param string $message Message to display
     * @param bool $ensureNewline Ensure message has a new line
     * @return void
     */
    protected function _displayWarning($message, $ensureNewline = true)
    {
        $this->_displayMessage($message, $ensureNewline, 1); //red
    }

    /**
     * Display error message
     *
     * @param mixed $message Message
     * @return void
     */
    protected function _displayError($message)
    {
        echo "\n";

        $this->_terminal->pretty_message($message, 7, 1);

        echo "\n";
    }

    /**
     * Exit with error message
     *
     * @param string $message Error message
     * @return void
     */
    protected function _halt($message)
    {
        $this->_displayError($message);
        exit(255);
    }
}
