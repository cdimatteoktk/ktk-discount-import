<?php 

/**
 * ktk_CsvResponseUtils
 * helper class for utils needed in response method
 * 
 */

 class ktk_CsvResponseUtils {

    /**
     * Static Method setJSONHeader
     * useful to set header for JSON response choosing correct HTTP status code
     * @param $statusCode $statusCode HTTP Status code that will be set in response
     * @return void
     */
    public static function setJSONHeader($statusCode) {
        header('Content-Type: application/json', true, $statusCode);
    }

    /**
     * Method encodeUnescapedUnicodeJSON
     * return json_encode with JSON_UNESCAPED_UNICODE flags enabled
     * @param $dataToBeEncoded $dataToBeEncoded Data that will be encoded as JSON without escaping unicode Char
     * @return string
     */
    public static function encodeUnescapedUnicodeJSON($dataToBeEncoded) {
        return json_encode($dataToBeEncoded, JSON_UNESCAPED_UNICODE);
    }



 }