<?php

class MinceClient extends Qi_Console_Client
{
    /**
     * Be quiet
     *
     * @var mixed
     */
    protected $_quiet = false;

    /**
     * Exit status
     * 
     * @var float
     */
    protected $_status = 0;
    /**
     * Set Verbose level
     * 
     * @param bool $value Value
     * @return void
     */
    public function setVerbose($value)
    {
        self::$_verbose = (bool) $value;
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

    public function init()
    {
        if ($this->_args->version) {
            $this->showVersion();
            $this->_safeExit();
        }

        $this->setVerbose($this->_args->verbose);
        $this->setQuiet($this->_args->quiet);
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
            if (self::$_verbose && !$this->_quiet) {
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
}

