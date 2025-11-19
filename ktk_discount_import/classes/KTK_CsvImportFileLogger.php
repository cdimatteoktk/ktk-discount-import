<?php 

/**
 * KFK_SyncFileLogger
 * this is only a wrapper to provide a single function to add a log line cleanly and without hassle
 * an object of this class can be instantiated in other classes/files to log information
 * 
 * it could also be possible to add a check to the logMessage() function to prevent logging, maybe in view of a backend configuration of the module
 * 
 */
class KTK_CsvImportFileLogger extends FileLogger{
    /**
     * Method logMessage
     *
     * @param $message $message a string
     * @param $level $level 0 for errors or 1 for info
     *
     * @return void
     */
    public function logMessage($message, $level){
        parent::logMessage($message, $level);
    }
}