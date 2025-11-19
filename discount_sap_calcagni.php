<?php

require_once(dirname(__FILE__) . '/config/config.inc.php');
require_once(dirname(__FILE__) . '/init.php');
require_once(dirname(__FILE__) . '/classes/SpecificPrice.php');
use PrestaShop\PrestaShop\Core\MailTemplate\MailTemplateRenderer;

// Configurazioni
$csvFolder = __DIR__ . '/sap_uploads/';
$backupTable = 'ps_specific_price_backup';
$targetTable = 'ps_specific_price';
$archiveFolder = __DIR__ . '/sap_splitted/';
$errorLog = [];

$db = Db::getInstance();

// Backup della tabella ps_specific_price
echo "Creazione backup della tabella...\n";
$db->execute("DROP TABLE IF EXISTS $backupTable");
$db->execute("CREATE TABLE $backupTable AS SELECT * FROM $targetTable");

// Svuota la tabella
$db->execute("TRUNCATE TABLE $targetTable");

// Recupera i file CSV
$files = glob($csvFolder . '*_discount.csv');
if (!$files) {
    echo "Nessun file CSV trovato.\n";
    exit;
}

foreach ($files as $file) {
    echo "Elaborazione file: $file\n";
    
    // Estrazione id_shop dal nome file
    preg_match('/(\d+)_discount\.csv$/', basename($file), $matches);
    if (!isset($matches[1])) {
        $errorLog[] = "File con nome errato: $file";
        continue;
    }
    $id_shop = (int)$matches[1];
    
    // Apertura file CSV
    if (($handle = fopen($file, 'r')) !== FALSE) {
        fgetcsv($handle, 1000, ';'); // Salta l'intestazione
        while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
            list($sku, $min_qty, $actual_price, $fixed_price, $discounted_price, $discount, $absolute, $tax_included, $start_date, $end_date) = $data;
            
            // Pulizia SKU
            $sku = trim($sku);
            
            // Recupero id_product_attribute
            $query = "SELECT id_product, id_product_attribute FROM ps_product_attribute WHERE reference = '" . pSQL($sku) . "' LIMIT 1";
            $product = $db->getRow($query);
            
            if (!$product) {
                $errorLog[] = "SKU non trovato per shop ID $id_shop: $sku";
                continue;
            }
            
            $id_product = (int)$product['id_product'];
            $id_product_attribute = (int)$product['id_product_attribute'];
            
            // Verifica id_shop in ps_product_attribute_shop
            $query = "SELECT id_product_attribute FROM ps_product_attribute_shop WHERE id_product_attribute = $id_product_attribute AND id_shop = $id_shop LIMIT 1";
            if (!$db->getRow($query)) {
                $errorLog[] = "SKU $sku non associato allo shop ID $id_shop";
                continue;
            }
            
            // Calcolo riduzione
            $reduction = $discount / 100;
            $reduction_type = 'percentage';
            if ($absolute) {
                $reduction = $actual_price - $discounted_price;
                $reduction_type = 'amount';
            }
            
            // Inserimento nella tabella ps_specific_price
            $insertQuery = "INSERT INTO ps_specific_price (id_specific_price, id_specific_price_rule, id_cart, id_product, id_shop, id_shop_group, id_currency, id_country, id_group, id_customer, id_product_attribute, price, from_quantity, reduction, reduction_tax, reduction_type, `from`, `to`) VALUES 
            (NULL, 0, 0, $id_product, $id_shop, 0, 0, 0, 0, 0, $id_product_attribute, -1.000000, $min_qty, $reduction, $tax_included, '$reduction_type', '$start_date 00:00:00', '$end_date 00:00:00')";
            
            if (!$db->execute($insertQuery)) {
                $errorLog[] = "Errore durante l'inserimento di SKU: $sku";
            }
        }
        fclose($handle);
    }
    
    // Sposta il file nella cartella di archivio
    $datePath = date('Y/m/d/');
    $destDir = $archiveFolder . $datePath;
    if (!file_exists($destDir)) {
        mkdir($destDir, 0777, true);
    }
    rename($file, $destDir . basename($file));
}

// Invia email in caso di errori
if (!empty($errorLog)) {
    $subject = "Errore importazione CSV - " . date('Y-m-d H:i:s');
    $body = implode("\n", $errorLog);
    
    Mail::Send(
        (int)Configuration::get('PS_LANG_DEFAULT'),
        'alert',
        $subject,
        ['{message}' => nl2br($body)],
        'ktk@kotuko.it',
        'Kotuko Admin',
        null,
        null,
        null,
        null,
        _PS_MAIL_DIR_,
        false,
        (int)Configuration::get('PS_SHOP_DEFAULT')
    );
}

echo "Processo completato.\n";
?>