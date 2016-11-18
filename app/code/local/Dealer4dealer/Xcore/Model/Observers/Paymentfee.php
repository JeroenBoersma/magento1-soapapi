<?php
/**
 * Dealer4dealer_Xcore extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/mit-license.php
 *
 * @category       Dealer4dealer
 * @copyright      Copyright (c) 2016
 * @license        http://opensource.org/licenses/mit-license.php MIT License
 */
/**
 * Observer to add payment fees added in backend sys conf
 *
 * @category    Dealer4dealer
 * @author      Sander Mangel <sander@sandermangel.nl>
 */
class Dealer4dealer_Xcore_Model_Observers_Paymentfee
{
    /**
     * @param Varien_Event_Observer $o
     * @return Dealer4dealer_Xcore_Model_Observers_Paymentfee
     */
    public function dealer4dealerXcoreSalesOrderPaymentFee(Varien_Event_Observer $o)
    {
        $this->_applySysconfPaymentfees($o);

        return $this;
    }

    /**
     * @param Varien_Event_Observer $o
     */
    protected function _applySysconfPaymentfees(Varien_Event_Observer $o)
    {
        /* @var $order Mage_Sales_Model_Order */
        $order  = $o->getOrder();
        /* @var $store Mage_Core_Model_Store */
        $store  = $order->getStore();
        $fees   = Mage::helper('dealer4dealer_xcore')->getPaymentFeesData($store->getId());

        /* @var $taxCalculation Mage_Tax_Model_Calculation */
        $taxCalculation = Mage::getModel('tax/calculation');
        /* @var $request Varien_Object */
        $request = $taxCalculation->getRateRequest(
            $order->getShippingAddress(),
            $order->getBillingAddress(),
            null,
            $store
        );

        $paymentFeeObjects = [];
        foreach ($fees as $feeData) {
            $percent = $taxCalculation->getRate($request->setProductClassId($feeData['tax_rate']));

            $paymentFeeObjects[] = Mage::getModel('dealer4dealer_xcore/payment_fee')->setData([
                'title'         => $feeData['title'],
                'base_amount'   => (float)$order->getData($feeData['base_amount']),
                'amount'        => (float)$order->getData($feeData['amount']),
                'tax_rate'      => (float)$percent,
            ]);
        }

        $order->setData('xcore_payment_fees', $paymentFeeObjects);
    }
}