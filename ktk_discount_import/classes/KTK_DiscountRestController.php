<?php

/**
 * AbstractRestController
 * simple abstract class to handle the received calls
 * the methods will be implemented in the actual controllers
 */

abstract class DiscountAbstractRestController extends ModuleFrontController
{
    protected $shopId;
    protected $shopGroupId;

    public function init() 
    {
        parent::init();
        $this->shopId = (int)Context::getContext()->shop->id;
        $this->shopGroupId = (int)Context::getContext()->shop->getContextShopGroupID();

        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $this->processGetRequest();
                break;
            case 'POST':
                $this->processPostRequest();
                break;
            case 'PATCH': // you can also separate these into their own methods
            case 'PUT':
                $this->processPutRequest();
                break;
            case 'DELETE':
                $this->processDeleteRequest();
                break;
            default:
                $this->error();
                break;
                // throw some error or whatever
        }
    }

    abstract protected function processGetRequest();
    abstract protected function processPostRequest();
    abstract protected function processPutRequest();
    abstract protected function processDeleteRequest();
    abstract protected function error();
}