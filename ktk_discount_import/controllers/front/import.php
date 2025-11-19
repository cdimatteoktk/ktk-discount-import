<?php

class ktk_discount_importimportModuleFrontController extends KTK_DiscountRestControllerWrapper {

    protected function processGetRequest() {

        $logger = new KTK_CsvImportFileLogger(1);
        $today = date_format((new DateTime()), 'Ymd');
        $logger->setFilename(_PS_ROOT_DIR_ . "/modules/ktk_discount_import/log/".$this->shopId."_".$today."_discount-import-GET.log");

        $execId = 'IMPORTCONTROLLER_GET_'.$this->shopId."_".date_format((new DateTime()), 'YmdHis');
        $prefix = '[' . $execId . ']';

        $logger->logMessage($prefix . ' - GET START', 1);
 
        $do_split= isset($_GET["do_split"])?$_GET["do_split"]:0;

        $this->checkPermissions();

        $logger->logMessage($prefix . ' - AUTH OK', 1);

        $configFilePath = Configuration::get('KTK_DISCOUNT_IMPORT_CRON_JOB_FILE_PATH');
        $allowed = Configuration::get('KTK_DISCOUNT_IMPORT_ALLOW_EXECUTION');
        $trackOutputWithFile = Configuration::get('KTK_DISCOUNT_IMPORT_CRON_JOB_TRACK_OUTPUT_CSV');
        $sendMail = Configuration::get('KTK_DISCOUNT_IMPORT_CRON_JOB_NOTIFY_RESULT_MAIL');
        $sendMailRecipient = Configuration::get('KTK_DISCOUNT_IMPORT_CRON_JOB_NOTIFY_RESULT_MAIL_RECIPIENT');
        
        if (!$allowed) {
            $logger->logMessage($prefix .  ' - NOT ALLOWED FROM CONFIGURATION - 400', 3);
            ktk_CsvResponseUtils::setJSONHeader(400);
            $this->ajaxRender(ktk_CsvResponseUtils::encodeUnescapedUnicodeJSON([
                'success' => false,
                'operation' => 'GET',
                'message' => 'CronJob import execution not allowed from module configuration',
                'execution_id' => $execId
            ]));
            exit;
        }

        if (empty($configFilePath)) {
            $logger->logMessage($prefix .  ' - MISSING FILEPATH FROM CONFIGURATION - 400', 3);
            ktk_CsvResponseUtils::setJSONHeader(400);
            $this->ajaxRender(ktk_CsvResponseUtils::encodeUnescapedUnicodeJSON([
                'success' => false,
                'operation' => 'GET',
                'message' => 'Missing Filepath value from module configuration',
                'execution_id' => $execId
            ]));
            exit;
        }

        $configFilePath = _PS_ROOT_DIR_ . (substr($configFilePath, 0, 1 ) !== '/' ? '/' : '') . $configFilePath;

        $csvSeparator = Configuration::get('KTK_DISCOUNT_IMPORT_CSV_SEPARATOR_CHAR');

        if($do_split==1){
            //STEP 1, split csv in multiple parts
            $processor = new KTK_CsvProcessor($execId, false, $sendMail, $sendMailRecipient, $csvSeparator);
            $processor->splitCsv($configFilePath);
        }else{
            //MORE STEPS, split csv in multiple parts
            try {
                $processor = new KTK_CsvProcessor($execId, $trackOutputWithFile, $sendMail, $sendMailRecipient, $csvSeparator);
                $processor->processCSV($configFilePath);
            } catch (\Exception $exc) {
                $eCode = $exc->getCode() != 0 ? $exc->getCode() : 500;
                $logger->logMessage($prefix .  ' - PROCESSING EXCEPTION - ' . $eCode . ' --> ' . $exc->getMessage() . ' -- ' . $exc->getFile() .'::'.$exc->getLine() . ' -- STACK : ' . $exc->getTraceAsString(), 3);

                if (!empty($sendMailRecipient)) {
                    $processor->sendErrorMail($exc->getMessage());
                }

                ktk_CsvResponseUtils::setJSONHeader($eCode);
                $this->ajaxRender(ktk_CsvResponseUtils::encodeUnescapedUnicodeJSON([
                    'success' => false,
                    'operation' => 'GET',
                    'message' => $exc->getMessage(),
                    'execution_id' => $execId
                ]));
                exit;
            }

            ktk_CsvResponseUtils::setJSONHeader(200);
            $this->ajaxRender(ktk_CsvResponseUtils::encodeUnescapedUnicodeJSON([
                'success' => true,
                'operation' => 'GET',
                'execution_id' => $execId
            ]));

            }
            exit;
    }

    protected function processPostRequest()
    {
        // TODO: Implement processPostRequest() method.
    }

    protected function processPutRequest()
    {
        // TODO: Implement processPutRequest() method.
    }

    protected function processDeleteRequest()
    {
        // TODO: Implement processDeleteRequest() method.
    }

    protected function error()
    {
        // TODO: Implement error() method.
    }
}