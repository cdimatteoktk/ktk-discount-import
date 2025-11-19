<?php



class ImportCsvController extends ModuleAdminController
{
    public $logger;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->logger = new KTK_CsvImportFileLogger(1);
        $this->logger->setFilename(_PS_ROOT_DIR_ . "/modules/ktk_discount_import/log/".$this->shopId."_" . date('Ymd') . "_ImportCsvController.log");
        $this->logger->logMessage("ImportCsvController - START", 3);
        parent::__construct();
    }

    public function initContent()
    {
        parent::initContent();
        $this->content = $this->renderForm();
        $this->context->smarty->assign(array(
            'content' => $this->content,
            'url_post' => self::$currentIndex.'&token='.$this->token,			
        ));
    }

    public function renderForm(){
        $this->fields_form = array(
			'legend' => array(
				'title' => $this->l('Import Csv'),
				'icon' => 'icon-export'
			),
			'input' => array(
				
                array(
					'type' => 'file',
					'label' => $this->l('Upload CSV'),
					'name' => 'csv',					
                ),
			),
			'submit' => array(
				'title' => $this->l('Import'),
				'id' => 'submitImport',
				'icon' => 'process-icon-import',
                'name'=>'submitImport'
			)
		);
		$this->show_toolbar = false;
		$this->show_form_cancel_button = false;
		$this->toolbar_title = $this->l('Import');
		return parent::renderForm();
    }

    public function postProcess()
	{
        try{
            if (isset($_POST['submitImport'])) {
                $ext = pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION);                
                if($_FILES["csv"]["size"] == 0)
                {
                    ktk_ResponseUtils::setJSONHeader(500);
                    $eMsg = $this->l('Please upload CSV!');
                    $this->ajaxRender(ktk_ResponseUtils::encodeUnescapedUnicodeJSON([
                        'success' => false,
                        'operation' => 'post',
                        'Exception' => $eMsg,
                    ]));
                    $this->logger->logMessage('500 - ' . $eMsg, 3);
                    exit;
                }
                if ($ext !== 'csv') {
                    ktk_ResponseUtils::setJSONHeader(500);
                    $eMsg = $this->l('Invalid file type! Only CSV format can be uploaded.');
                    $this->ajaxRender(ktk_ResponseUtils::encodeUnescapedUnicodeJSON([
                        'success' => false,
                        'operation' => 'post',
                        'Exception' => $eMsg,
                    ]));
                    $this->logger->logMessage('500 - ' . $eMsg, 3);
                    exit;
                }
                if($_FILES["csv"]["size"] > 0)
                {
                    $filename = $_FILES['csv']['tmp_name'];
                    $this->logger->logMessage('500 - ' . $filename, 3);

                    $execId = 'IMPORTCSVCONTROLLER_MANUAL_' . date('YmdHis');
                    $processor = new KTK_CsvProcessor($execId, true, false, '');
                    $processor->processCSV($filename);
                }
            }
        } catch (Exception $e){
            $excS = $e->getMessage() . ' -- ' . $e->getFile() . '::' . $e->getLine(). ' -> ' . $e->getTraceAsString();
            $excArray = array(
                'success' => false,
                'operation' => 'post',
                'Exception' => $excS,
            );
            $this->logger->logMessage('500 - ' . $e->getMessage() . ' -- ' . $e->getFile() .'::'.$e->getLine() . ' -- STACK : ' . $e->getTraceAsString(), 3);
            ktk_CsvResponseUtils::setJSONHeader(500);
            $this->ajaxRender(ktk_CsvResponseUtils::encodeUnescapedUnicodeJSON($excArray));
            return parent::postProcess();
        }
	}

}