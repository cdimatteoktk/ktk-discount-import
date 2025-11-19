<?php

class KTK_CsvProcessor {

    private $logger;
    private $executionID;
    private $prefix;

    private $trackOutputCsv;
    protected $csvTrackingBaseFolder;
    protected $csvTrackingSpecificFolder;
    protected $trackingOutputFilePath;

    private $sendResultMail;
    private $resultMailRecipients;

    private $csvSeparatorChar;
    protected $shopId;
    protected $shopGroupId;

    /**
     * @param string $executionID used to uniquely track execution of csv import, must be filled
     * @param bool $writeOutputTrackingCsv whether to write or not an output csv file that track status of import for each line
     * @param bool $notifyResultByMail wheter to send an email with import result to $mailNotificationRecipient. If output is tracked down in a csv file, it will be attached
     * @param string $mailNotificationRecipient who must receive notification mail of result if previous parameter is set to true
     * @param string $csvSeparatorChar char used to split columns of CSV
     *
     */
    public function __construct($executionID, $writeOutputTrackingCsv = true, $notifyResultByMail = false, $mailNotificationRecipient = '', $csvSeparatorChar = ',') {
        
        $this->shopId = (int)Context::getContext()->shop->id;
        $this->shopGroupId = (int)Context::getContext()->shop->getContextShopGroupID();
        
        $this->logger = new KTK_CsvImportFileLogger(1);
        $today = date_format((new DateTime()), 'Ymd');
        $this->logger->setFilename(_PS_ROOT_DIR_ . "/modules/ktk_discount_import/log/".$this->shopId."_"  . $today . "_process-csv.log");

        $this->executionID = $executionID;
        $this->prefix = '[' . $executionID . ']';

        $this->trackOutputCsv = $writeOutputTrackingCsv;
        $this->csvTrackingBaseFolder = _PS_ROOT_DIR_ . "/modules/ktk_discount_import/log/csv_tracking/";
        if (!file_exists($this->csvTrackingBaseFolder)) {
            mkdir($this->csvTrackingBaseFolder);
        }
        $this->csvTrackingSpecificFolder = $this->csvTrackingBaseFolder . $this->executionID . "/";

        if($this->trackOutputCsv){
            if (!file_exists($this->csvTrackingSpecificFolder)) {
                mkdir($this->csvTrackingSpecificFolder);
            }
        }

        $this->trackingOutputFilePath = $this->csvTrackingSpecificFolder.$this->shopId.'_output.csv';

        $this->sendResultMail = $notifyResultByMail;
        $this->resultMailRecipients = $mailNotificationRecipient;

        $this->csvSeparatorChar = $csvSeparatorChar;

    }

    public function getCsvTrackingSpecificFolder() : string {
        return $this->csvTrackingSpecificFolder;
    }

    public function getTrackingOutputFilePath() : string {
        return $this->trackingOutputFilePath;
    }

    private function getFilePathNum($file_num){
        $today = date_format((new DateTime()), 'Ymd');
        $shop_id= Context::getContext()->shop->id;
        return  $shop_id."_".$today."_".$file_num.".csv";
    }

    /**
     * This method do the effective importation of the discounted price inside Catalog
     * Other class function are called to perform differnt task like cancellation, validation and import
     * 
     * @param string $filepath path to file to be imported
     *
     * @return mixed $importResult
     */
    public function processCSV($filepath) {

       /* split read csv */
        $file_num=1;
        $file_path_num = Configuration::get('KTK_DISCOUNT_IMPORT_CSV_FILE_PATH_NUM');
        $splitted_path = Configuration::get('KTK_DISCOUNT_IMPORT_CRON_JOB_FILE_PATH_SPLIT');
        $splitted_path = _PS_ROOT_DIR_.$splitted_path;
        if($file_path_num=="NO_PATH"){
            $file_path_num = $this->getFilePathNum($file_num);
        }

       
        $filepath = $splitted_path.$file_path_num;

       

        $this->logger->logMessage($this->prefix . ' | processCSV - START --> ' . $filepath, 1);
        if(file_exists($filepath)){
            $this->logger->logMessage($this->prefix . ' | processCSV - Splitted file  found ', 1);
        }else{
            $this->logger->logMessage($this->prefix . ' | processCSV - END , Splitted file not found' , 1);
        }

        $deletionResult = $this->deleteExpiredSpecificPrice();

        $validationResult = $this->validateCSV($filepath);

        if (!$validationResult->valid) {
            throw new \Exception('FILE INVALID: ' . $validationResult->error, 400);
        }


        $trackingInputFilePath = $this->csvTrackingSpecificFolder.$this->shopId.'_input.csv';
        $this->logger->logMessage($this->prefix . ' | processCSV - Copy Input File for tracking in path --> ' . $trackingInputFilePath, 1);
        $copyRes = copy($filepath, $trackingInputFilePath);
        if (!$copyRes) {
            throw new \Exception('Unable to copy input file for tracking', 500);
        }

        /* read split csv init*/

        $file_num=1;
        $today = date_format((new DateTime()), 'Ymd');
        $file_path_num = Configuration::get('KTK_DISCOUNT_IMPORT_CSV_FILE_PATH_NUM');
        $splitted_path =  Configuration::get('KTK_DISCOUNT_IMPORT_CRON_JOB_FILE_PATH_SPLIT');
        $splitted_path = _PS_ROOT_DIR_.$splitted_path;

        $this->logger->logMessage($this->prefix . ' | processCSV - Copy Input File for tracking in path --> ' . $trackingInputFilePath, 1);


        if($file_path_num=="NO_PATH"){
            $file_path_num =   $this->getFilePathNum($file_num); 
        }else{
            $rpos=strrpos($file_path_num,'_');
            $rpos+=1;
            $file_num = substr($file_path_num,$rpos);
            $file_num = str_replace( ".csv","",$file_num);
        }

        $this->logger->logMessage($this->prefix . ' | processCSV - splitted file path -->' . $file_path_num, 1);

        if(file_exists($splitted_path.$file_path_num)){
            $filepath = $splitted_path.$file_path_num;
            $this->logger->logMessage($this->prefix . ' | processCSV - splitted file path found', 1);
        }else{
            $this->logger->logMessage($this->prefix . ' | processCSV - splitted file path not found', 1);
            $this->logger->logMessage($this->prefix . ' | processCSV - END', 1);
            exit;
        }

        /* read split  csv end */


        $importResult = $this->importData($filepath);

        if ($this->sendResultMail) {
            try {
                $this->sendResultMail($importResult);
            } catch (\Exception $exc) {
                $this->logger->logMessage($this->prefix . ' | processCSV ---  Result Mail Send EXCEPTION  --> ' . $exc->getMessage() . ' -- ' . $exc->getFile() .'::'.$exc->getLine() . ' -- STACK : ' . $exc->getTraceAsString(), 2);
            }
        }

        /* read split  csv init*/

        $file_num++;
        $file_path_num  = $this->getFilePathNum($file_num);
        Configuration::updateValue('KTK_DISCOUNT_IMPORT_CSV_FILE_PATH_NUM',$file_path_num);
        $this->logger->logMessage($this->prefix . ' | processCSV - END', 1);

        /* read split  csv end*/
        
    }

    public function importData($filepath) {

        $this->logger->logMessage($this->prefix . ' | importData - START - Separator --> "' . $this->csvSeparatorChar . '"', 1);

        $handle = fopen($filepath, "r");
        
        $this->logger->logMessage($this->prefix . ' | importData - Validate Headers', 1);

        $headers = fgetcsv($handle, 1000, $this->csvSeparatorChar);

        if ($headers === false) {
            $this->logger->logMessage($this->prefix . ' | importData - HEADERS NOT VALIDABLE', 2);
            throw new \Exception('FILE INVALID: unable to read headers for validation', 400);
        }

        $this->logger->logMessage($this->prefix . ' | H: ' . json_encode($headers), 2);

        $headerIsValid = KTK_CsvHelpers::validateHeaders($headers);

        if (!$headerIsValid) {
            $this->logger->logMessage($this->prefix . ' | importData - HEADERS NOT VALID', 2);
            throw new \Exception('FILE INVALID: headers not valid', 400);
        }

        $result = array();

        $stats = array();
        $okNumber = 0;
        $koNumber = 0;
        $totalNumber = 0;

        $this->logger->logMessage($this->prefix . ' | importData - START ROW LOOP', 1);

        // import data ROW LOOP
        while (($data = fgetcsv($handle, 1000, $this->csvSeparatorChar)) !== FALSE) {

            $totalNumber++;
            $pReference = $data[0];

            try {
                $tmpImportedStatus = true;

                $this->logger->logMessage($this->prefix . ' | importData --- ' . $pReference . ' --- START', 1);
    
                if (count($data) !== count($headers)) {

                    $this->logger->logMessage($this->prefix . ' | importData --- ' . $pReference . ' --- INVALID ROW COUNT: ' . count($data), 2);
                    $tmpResult = $data;
                    array_push($tmpResult, 'KO', 'Invalid Row Count');
                    array_push($result, $tmpResult);
                    $tmpImportedStatus = false;
                    $koNumber++;

                } else { 

                    $this->logger->logMessage($this->prefix . ' | importData --- ' . $pReference . ' --- Search product id', 1);

                    $product_id = KTK_CsvHelpers::getProductIdByReference($pReference);

                    if ($product_id === false || $product_id === null) {
                        $this->logger->logMessage($this->prefix . ' | importData --- ' . $pReference . ' --- Search combination id', 1);

                        $combination = KTK_CsvHelpers::getProductCombinationByReference($pReference);
                        if ($combination != false) {
                            $product_id = $combination['id_product'];
                            $combination_id = $combination['id_product_attribute'];
                        } else {
                            $product_id = false; 
                        }
                    } else {
                        $combination_id = 0;
                    }

                    if ($product_id !== false) {

                        $rowObject = KTK_CsvHelpers::getCsvRowObject($data);

                        $this->logger->logMessage($this->prefix . ' | importData --- ' . $pReference . ' --- Delete duplicate for product id: ' . $product_id . ' : combination id: ' . $combination_id, 1);

                        $duplicateDeleteResult = KTK_CsvHelpers::deleteDuplicateSpecificPrice($product_id, $combination_id, $rowObject->minimumQuantity, $this->shopId);
                        $this->logger->logMessage($this->prefix . ' | importData --- ' . $pReference . ' --- Delete duplicate res: ' . $duplicateDeleteResult, 1);

                        $this->logger->logMessage($this->prefix . ' | importData --- ' . $pReference . ' --- Insert Specific Price for product id: ' . $product_id . ' : combination id: ' . $combination_id, 1);

                        $specific_price_data = new SpecificPrice();

                        //MINIMUM_QUANTITY
                        $specific_price_data->from_quantity = $rowObject->minimumQuantity;

                        //col ABSOLUTE - if 0 then amount else percentage value
                        $specific_price_data->reduction_type = $rowObject->absolute === 0 ? 'amount': 'percentage';

                        //col APPLIED_DISCOUNT - if amount then raw vaue. else value/100 to obtain percentage
                        $specific_price_data->reduction =  $rowObject->absolute === 0 ? $rowObject->appliedDiscount : $rowObject->appliedDiscount/100;

                        //TAX_INCLUDED
                        $specific_price_data->reduction_tax = $rowObject->taxIncluded;

                        //product id reference
                        $specific_price_data->id_product = $product_id;
                        $specific_price_data->id_product_attribute = $combination_id;

                        //TODO id_product_attribute for combinations

                        // default value
                        $specific_price_data->id_shop = $this->shopId;

                        // reference DB
                        $specific_price_data->id_shop_group = $this->shopGroupId;;
                        $specific_price_data->id_currency = 0;
                        $specific_price_data->id_country = 0;
                        $specific_price_data->id_group= 0;
                        $specific_price_data->id_customer = 0;
                        $specific_price_data->id_specific_price_rule = 0;
                        $specific_price_data->id_cart = 0;
                    
                        //STARTING NEW PRICE - col DISCOUNTED_PRICE
                        // $specific_price_data->price = $data[5];
                        $specific_price_data->price = -1;

                        $from = str_replace('/','-', $rowObject->startDate);
                        $to = str_replace('/','-', $rowObject->endDate);
                        $formattedFrom = date_format(DateTime::createFromFormat('Y-m-d', $from), 'Y-m-d');
                        $formattedTo = date_format(DateTime::createFromFormat('Y-m-d', $to), 'Y-m-d');
                        $specific_price_data->from = $formattedFrom;
                        $specific_price_data->to = $formattedTo;

                        $specific_price_data->save();
                    
                    } else {

                        $this->logger->logMessage($this->prefix . ' | importData --- ' . $pReference . ' - REFERENCE NOT FOUND', 2);
                        $tmpResult = $data;
                        array_push($tmpResult, 'KO', 'Reference NOT found');
                        array_push($result, $tmpResult);
                        $tmpImportedStatus = false;
                        $koNumber++;

                    }
                }

                if ($tmpImportedStatus) {
                    $tmpResult = $data;
                    array_push($tmpResult, 'OK', '//');
                    array_push($result, $tmpResult);
                    $okNumber++;
                }
            
            } catch (\Exception $exc) {
                $this->logger->logMessage($this->prefix . ' | importData --- ' . $pReference . ' ROW PROCESSING EXCEPTION  --> ' . $exc->getMessage() . ' -- ' . $exc->getFile() .'::'.$exc->getLine() . ' -- STACK : ' . $exc->getTraceAsString(), 2);
                $tmpResult = $data;
                array_push($tmpResult, 'KO', 'UNHANDLED: ' . $exc->getMessage());
                array_push($result, $tmpResult);
                $tmpImportedStatus = false;
                $koNumber++;
            }

        }

        fclose($handle);

        if ($this->trackOutputCsv) {

            $this->logger->logMessage($this->prefix . ' | importData - Writing output data at path --> ' . $this->trackingOutputFilePath, 1);

            $trackerFileStream = KTK_CsvHelpers::createOutputCsv($this->trackingOutputFilePath);

            KTK_CsvHelpers::writeOutputCsvHeaders($trackerFileStream, $headers);
            KTK_CsvHelpers::writeOutputCsvData($trackerFileStream, $result);
            KTK_CsvHelpers::closeOutputCsv($trackerFileStream);
        }

        $stats['total'] = $totalNumber;
        $stats['ok'] = $okNumber;
        $stats['ko'] = $koNumber;

        $this->logger->logMessage($this->prefix . ' | importData - END', 1);
        return $stats;
    }

    public function validateCSV($filepath) {

        $this->logger->logMessage($this->prefix . ' | validateCsv - CSV Validation START --> ' . $filepath, 1);
        
        
        $validationObj = new stdClass();
        $validationObj->valid = true;
        $validationObj->error = null;

        $this->logger->logMessage($this->prefix . ' | validateCsv - Checking file exists at --> ' . $filepath, 1);
        
        if (!file_exists($filepath)) {
            $this->logger->logMessage($this->prefix .  ' | validateCsv - IMPORT FILE NOT FOUND ON THE SERVER - 400', 3);
            $validationObj->valid = false;
            $validationObj->error = 'file not found on the server';
            return $validationObj;
        }

        $ext = pathinfo($filepath, PATHINFO_EXTENSION); //Check the extension
        if ($ext !== 'csv') {
            $this->logger->logMessage($this->prefix . ' | validateCsv - Invalid filetype --> ' . $ext, 3);
            $validationObj->valid = false;
            $validationObj->error = 'invalid file type';
            return $validationObj;
        }

        if(filesize($filepath) === false || filesize($filepath) === 0) {
            $this->logger->logMessage($this->prefix . ' | validateCsv - file empty', 3);
            $validationObj->valid = false;
            $validationObj->error = 'file empty';
            return $validationObj;
        }

        $this->logger->logMessage($this->prefix . ' | validateCsv - CSV Validation END', 1);

        return $validationObj;

    }

    public function deleteExpiredSpecificPrice() {

        $this->logger->logMessage($this->prefix . ' | deleteExpiredSpecificPrice - START', 1);

        $res = KTK_CsvHelpers::deleteAllExpiredSpecialDiscounts();

        $this->logger->logMessage($this->prefix . ' | deleteExpiredSpecificPrice - END with result: ' . $res, 1);
        
        return $res;

    }

    public function sendResultMail($resultData = null) {

        $this->logger->logMessage($this->prefix . ' | sendResultMail - START', 1);

        $attachArray = array();
        if ($this->trackOutputCsv) {
            $content = file_get_contents($this->trackingOutputFilePath);
            if ($content !== false) {
                array_push($attachArray, array( 'content' => $content, 'mime' => 'text/csv', 'name' => $this->executionID . '_out.csv'));
            }
        }

        $this->logger->logMessage($this->prefix . ' | sendResultMail - START ' . json_encode($attachArray) , 1);

        Mail::send(
            (int)(Configuration::get('PS_LANG_DEFAULT')), // defaut language id
            'discount_csv_import_result',
            ' - [' . $this->executionID . '] - DISCOUNT CSV IMPORT RESULT',
            array(
                '{executionId}' => $this->executionID,
                '{ok}' => $resultData['ok'],
                '{ko}' => $resultData['ko'],
                '{total}' => $resultData['total'],
                '{site_url}' => _PS_BASE_URL_,
            ),
            $this->resultMailRecipients,
            NULL,
            NULL,
            NULL,
            $attachArray,
            NULL,
            _PS_MODULE_DIR_ . 'ktk_discount_import/mails'
        );

        $this->logger->logMessage($this->prefix . ' | sendResultMail - END', 1);
    }

    public function sendErrorMail($errorMessage, $errorStatus = null) {

        $this->logger->logMessage($this->prefix . ' | sendErrorMail - START', 1);

        Mail::send(
            (int)(Configuration::get('PS_LANG_DEFAULT')), // defaut language id
            'discount_csv_import_error',
            ' - [ERROR] - [' . $this->executionID . '] - DISCOUNT CSV IMPORT',
            array(
                '{executionId}' => $this->executionID,
                '{errorMessage}' => $errorMessage,
                '{site_url}' => _PS_BASE_URL_,
            ),
            $this->resultMailRecipients,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            _PS_MODULE_DIR_ . 'ktk_discount_import/mails'
        );

        $this->logger->logMessage($this->prefix . ' | sendErrorMail - END', 1);
    }

    public function splitCsv($filepath){ 

        $this->logger->logMessage($this->prefix . ' | splitData - START - Separator --> "' . $this->csvSeparatorChar . '"', 1);

        $this->logger->logMessage($this->prefix . ' | splitData - START DELETE DISCOUNT SHOP', 1);
        KTK_CsvHelpers::deleteShopDiscounts();
        $this->logger->logMessage($this->prefix . ' | splitData - END DELETE DISCOUNT SHOP', 1);
    

        $counter=1;
        $file_num=1;
        $splitted_path = Configuration::get('KTK_DISCOUNT_IMPORT_CRON_JOB_FILE_PATH_SPLIT');;
        $row_to_split= Configuration::get('KTK_DISCOUNT_IMPORT_CSV_ROW_TO_SPLIT');
        $splitted_path =  Configuration::get('KTK_DISCOUNT_IMPORT_CRON_JOB_FILE_PATH_SPLIT');;
        $splitted_path = _PS_ROOT_DIR_.$splitted_path;

        $this->logger->logMessage($this->prefix . ' | splitData - START - Separator --> "' . $this->csvSeparatorChar . '"', 1);
       
        $handle = fopen($filepath, "r");
        
        $this->logger->logMessage($this->prefix . ' | splitData - Validate Headers', 1);

        $headers = fgetcsv($handle, 1000, $this->csvSeparatorChar);

        $headers_row = implode(';',$headers);

        if ($headers === false) {
            $this->logger->logMessage($this->prefix . ' | splitData - HEADERS NOT VALIDABLE', 2);
            throw new \Exception('FILE INVALID: unable to read headers for validation', 400);
        }

        $this->logger->logMessage($this->prefix . ' | splitData - START ROW LOOP', 1);

        $file_content=$headers_row."\n";

        while (($data = fgetcsv($handle, 1000, $this->csvSeparatorChar)) !== FALSE) {
            
            $file_content.= implode(';',$data);
            $file_content.="\n";
            $file_path_num = $this->getFilePathNum($file_num);
            if($counter%$row_to_split==0){
                $new_csv = fopen($splitted_path.$file_path_num, "w");
                fwrite($new_csv, $file_content);
                fclose($new_csv);
                $file_content=$headers_row."\n";
                $file_num++;
            }
            $counter++;
        }
        $this->logger->logMessage($this->prefix . ' | splitData - END ROW LOOP', 1);

        if(($file_num-1)*$row_to_split<$counter){
            $file_path_num = $this->getFilePathNum($file_num);
            $new_csv = fopen($splitted_path.$file_path_num, "w");
            fwrite($new_csv, $file_content);
            fclose($new_csv);
        }

        Configuration::updateValue('KTK_DISCOUNT_IMPORT_CSV_FILE_PATH_NUM',"NO_PATH");
        $this->logger->logMessage($this->prefix . ' | splitData - END', 1);
    }  

}