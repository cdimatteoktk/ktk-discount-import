<?php

/**
 * KTK_DiscountRestControllerWrapper
 * 
 * wraps the controller abstract class to implement some functions common to all controllers
 */
class KTK_DiscountRestControllerWrapper extends DiscountAbstractRestController {  

    /**
     * Method getJsonBody
     * recovers the json from the body of the received call
     *
     * @return array 
     */
    public function getJsonBody(){
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, TRUE);

        return $input;
    }

    
    /**
     * Method checkPermissions
     * checks if the caller is allowed to use the endpoint
     * log file can be found at path /modules/ktk_sync/log/ + filename
     * @return void
     */
    public function checkPermissions() {
        
        $logger = new KTK_CsvImportFileLogger(1);  // SETTING LOG FILE
        $today = date_format((new DateTime()), 'Ymd');
        $logger->setFilename(_PS_ROOT_DIR_."/modules/ktk_discount_import/log/".$this->shopId."_" . $today . "_auth.log");
        
        $logger->logMessage('<--- Check Authorization START --->', 1);
        
        $requestInfo = $_SERVER['REQUEST_METHOD'] . ' -- URI: ' . $_SERVER['REQUEST_URI'];
        $logger->logMessage('Request Info -> ' . str_replace("\n", "", print_r($requestInfo, TRUE)), 1);

        try {

            $moduleApiKey = Configuration::get('KTK_DISCOUNT_IMPORT_API_KEY');

            $authorized = false;
            $auth_error = '';

            if (empty($moduleApiKey)) {
                $logger->logMessage('ApiKey conf empty, skipping auth check', 2);
                $authorized = true;
            } else {

                $logger->logMessage('Checking Query params for Api Key', 1);
                
                $isSet = isset($_GET['api_key']);
                $requestApiKey = $isSet ? $_GET['api_key'] : null;

                if (empty($requestApiKey)) {
                    $logger->logMessage('Missing api_key in query params', 2);
                    $authorized = false;
                    $auth_error = 'missing api_key param in query';
                } elseif ($requestApiKey !== $moduleApiKey) { 
                    $logger->logMessage('Invalid api_key in query params', 2);
                    $authorized = false;
                    $auth_error = 'invalid api_key param in query';
                } else {
                    $authorized = true;
                }
            }

            if (!$authorized) {
                $logger->logMessage('---> User NOT AUTHORIZED -- ' . $auth_error . ' <---', 2);
                $this->endOnErrorMessage('401', 401, $auth_error);
            }

        }  catch (Exception $e) {
            $errMess = '!!! Unable to perform AUTHORIZATION -- ' . $e->getMessage() . ' !!!';
            $logger->logMessage($errMess, 3);
            $this->endOnErrorMessage('500', 500, $errMess);
        }
    }

    /**
     * Method endOnError
     * ends the execution on error
     * 
     * @param $error $error error code, i.e. 001
     *
     * @return void
     */
    public function endOnError($error){
        $this->ajaxRender(json_encode([
            'success' => false,
            'operation' => 'ERROR',
            'code' => $error,
        ]));
        exit;
    }

    public function endOnErrorMessage($error, $statusCode, $errorMessage) {

        ktk_ResponseUtils::setJSONHeader($statusCode);
        $this->ajaxRender(ktk_ResponseUtils::encodeUnescapedUnicodeJSON([
            'success' => false,
            'operation' => 'ERROR',
            'code' => $error . ' - ' . $errorMessage,
        ]));
        exit;
    }
    
    /**
     * Method getParam
     * recovers a parameter from the $_GET variable
     *
     * @param $param $param [explicite description]
     *
     * @return void
     */
    public function getParam($param){
        if(isset($_GET[$param])){
            return $_GET[$param];
        }
        return '';
    }


    // IMPLEMENTING PROTECTED FUNCTIONS TO AVOID ERRORS
    protected function processGetRequest(){}
    protected function processPostRequest(){}
    protected function processPutRequest(){}
    protected function processDeleteRequest(){}
    protected function error(){}
}