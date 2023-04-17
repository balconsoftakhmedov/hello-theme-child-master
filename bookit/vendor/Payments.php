<?php

namespace Bookit\Classes\Vendor;

use Bookit\Classes\Database\Services;
use Bookit\Helpers\AddonHelper;
use Bookit\Classes\Vendor\Payments;

class PaymentsChild extends Payments {

	private $redirect_url;
	private $appointment;
	private $payment_method;
	private $service;
	private $className;
	private $token;

	public function __construct( $appointment = [] ) {
		$this->appointment      = $appointment;
		$this->payment_method   = $appointment['payment_method'];
		$this->token            = $appointment['token'];
		$this->service          = Services::get('id', $this->appointment['service_id']);
		$this->className        = sprintf('%s\Classes\Payments\%s', $this->getPaymentPluginName(),ucwords($this->payment_method));

		$this->{$this->payment_method}();
	}

	/** Used while Bookit Pro is alive */
	private function getPaymentPluginName() {
		$isProInstalled = AddonHelper::checkIsInstalledPlugin('bookit-pro/bookit-pro.php');
		$isProActive = bookit_pro_active();

		$isPaymentsInstalled = AddonHelper::checkIsInstalledPlugin('bookit-payments/bookit-payments.php');
		$isPaymentsActive = defined("BOOKIT_PAYMENTS_VERSION");;

		if ( $isPaymentsInstalled && $isPaymentsActive ) {
			return 'BookitPayments';
		}

		if ( $isProInstalled && $isProActive ) {
			return 'BookitPro';
		}
	}

	/**
	 * WooCommerce
	 */
	public function woocommerce() {
		$className  = $this->className;

		$paypal     = new $className(
			$this->appointment['price'],
			$this->appointment['id'],
			$this->service->title
		);

		$this->redirect_url = $paypal->generate_payment_url();
	}

}