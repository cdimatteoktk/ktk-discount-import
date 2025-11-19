<?php

class KTK_CsvHelpers
{
    public static function getProductIdByReference($reference){
        
  
        $sql = "SELECT "._DB_PREFIX_."product.id_product FROM "._DB_PREFIX_."product_shop, "._DB_PREFIX_."product 
                WHERE "._DB_PREFIX_."product_shop.id_product = "._DB_PREFIX_."product.id_product AND reference= '".pSQL($reference)."'
                AND id_shop=".pSQL((int)Context::getContext()->shop->id)."";

        $res = Db::getInstance()->getValue($sql);
        return $res;
    }

    /** 
     * Method getProductCombinationByReference
     * search for combination inside product_attribute table filtering wth reference in input
     * 
     * @param string $combinationReference reference to search for in combinations table
     *
     * @return mixed
     */
    public static function getProductCombinationByReference($combinationReference){


        $results = Db::getInstance()->getRow("SELECT pa.id_product, pa.id_product_attribute 
                                              FROM "._DB_PREFIX_."product_shop, "._DB_PREFIX_."product_attribute pa
                                             WHERE "._DB_PREFIX_."product_shop.id_product = pa.id_product 
                                             AND id_shop=".pSQL( (int)Context::getContext()->shop->id)." AND reference= '".$combinationReference."'");
        
        if (isset($results) && !empty($results) && count($results) == 2) {
            return $results;
        } else {
            return false;
        }

    }

    public static function deleteShopDiscounts() {        
        $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'specific_price WHERE `id_shop`='.(int)Context::getContext()->shop->id.';';
        $res = Db::getInstance()->execute($sql);
    return $res;
    }

    public static function getYesterdayDate(){
        $now = new Datetime();
        $now->modify("-1 day");
        return $now->format("Y-m-d");
    }

    public static function deleteAllExpiredSpecialDiscounts() {
        $last_date = self::getYesterdayDate();
        $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'specific_price WHERE `id_shop`='.(int)Context::getContext()->shop->id.' AND `to` <= DATE(' . '"' . $last_date . '")';
        $res = Db::getInstance()->execute($sql);
        return $res;
    }

    public static function deleteProductExpiredSpecialDiscounts($product_id){
        $last_date = self::getYesterdayDate();
        $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'specific_price WHERE `id_shop`='.(int)Context::getContext()->shop->id.' AND `id_product` = ' . pSQL($product_id) . ' and `to` <= DATE(' . '"' . $last_date . '")';
        $res = Db::getInstance()->execute($sql);
        return $res;
    }

    public static function deleteDuplicateSpecificPrice($productId, $combinationId, $fromQuantity, $idShop = 1) {
        $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'specific_price' 
                . ' WHERE `id_product` = ' . pSQL($productId) 
                . ' AND `id_product_attribute` = ' . pSQL($combinationId)
                . ' AND `from_quantity` = ' . pSQL($fromQuantity)
                . ' AND `id_shop` = ' . pSQL($idShop)
                . ' AND `id_customer` = 0'
                . ' AND `id_cart` = 0'
                . ' AND `id_shop_group` = '.pSQL((int)Context::getContext()->shop->getContextShopGroupID())
                . ' AND `id_currency` = 0'
                . ' AND `id_country` = 0'
                . ' AND `id_group` = 0'
                . ' AND `id_specific_price_rule` = 0';

        $res = Db::getInstance()->execute($sql);
        return $res;
    }

    public static function getSpecialFieldByProductId($product_id){
        $sql = 'SELECT id_specific_price FROM '._DB_PREFIX_.'specific_price WHERE `id_shop`='.(int)Context::getContext()->shop->id.' AND id_product = '.pSQL($product_id);
        $res = Db::getInstance()->getValue($sql);
        return $res;
    }

    public static function validateHeaders($headersToBeValidated) {
        // $neededHeaderString = 'SKU,MINIMUM_QUANTITY,ACTUAL_PRICE,FIXED_PRICE,ABSOLUTE,DISCOUNTED_PRICE,APPLIED_DISCOUNT,TAX_INCLUDED,START_DATE,END_DATE';
        $neededHeaderString = 'SKU;MINIMUM_QUANTITY;ACTUAL_PRICE;FIXED_PRICE;DISCOUNTED_PRICE;APPLIED_DISCOUNT;ABSOLUTE;TAX_INCLUDED;START_DATE;END_DATE';
        // $neededHeaderArray = explode(',', $neededHeaderString);
        $neededHeaderArray = explode(';', $neededHeaderString);
        return $headersToBeValidated === $neededHeaderArray;
    }

    /** 
     * Method getCsvRowObject
     * return an organized object from data row array of splitted CSV rows
     * 
     * @param array $rowData single CSV row
     *
     * @return stdClass
     */
    public static function getCsvRowObject($rowData) {

        $returnObj = new stdClass();
        $returnObj->sku = $rowData[0];
        $returnObj->minimumQuantity = $rowData[1];
        $returnObj->actualPrice = $rowData[2];
        $returnObj->fixedPrice = $rowData[3];
        $returnObj->discountedPrice = $rowData[4];
        $returnObj->appliedDiscount = $rowData[5];
        $returnObj->absolute = $rowData[6];
        $returnObj->taxIncluded = $rowData[7];
        $returnObj->startDate = $rowData[8];
        $returnObj->endDate = $rowData[9];

        return $returnObj;

    }

    public static function createOutputCsv($filepath) {
        $fStream = fopen($filepath, 'a');
        return $fStream;
    }

    public static function writeOutputCsvHeaders($fileStream, $baseHeaders) {
        $completeHeaders = $baseHeaders;
        array_push($completeHeaders, 'STATUS','ERROR');
        $res = fputcsv($fileStream, $completeHeaders);
        return $res;
    }

    public static function writeOutputCsvData($fileStream, $outputData) {
        foreach ($outputData as $outputDataRow) {
            fputcsv($fileStream, $outputDataRow);
        }
    }

    public static function closeOutputCsv($fileStream) {
        $res = fclose($fileStream);
        return $res;
    }

}