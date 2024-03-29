<?php
/*
* 2007-2014 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2014 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * @since 1.6.0
 */
require_once (dirname(__FILE__) . '../../../classes/Transaccion.php');
class TodoPagoValidationModuleFrontController extends ModuleFrontController
{
	//valida que todo este bien
	public function postProcess()
	{
		$prefijo= $this->module->getPrefijo('CONFIG_ESTADOS');
		$orderState = Configuration::get($prefijo.'_APROBADA');//Order State si la transaccion es aprobada
		$cart = $this->context->cart;//recupero el carrito
		$transaccion = TPTransaccion::getRespuesta($cart->id);
		
		//si no hay un cliente registrado, o una direccion de entrega, o direccion de contacto o el modulo no esta activo
		if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active || 
				 !$this->module->isActivo())
			Tools::redirect('index.php?controller=order&step=1');//redirecciona al primer paso

		// Verifica que la opcion de pago este disponible
		$authorized = false;
		foreach (Module::getPaymentModules() as $module)
		{
			if ($module['name'] == $this->module->name)
			{
				$authorized = true;
				break;
			}
		}

		if (!$authorized)//si no esta disponible la opcion de pago
			die($this->module->l('Este modo de pago no esta disponible.', 'validation'));//avisa

		$customer = new Customer($cart->id_customer);//recupera al objeto cliente

		if (!Validate::isLoadedObject($customer))//si no hay un cliente
			Tools::redirect('index.php?controller=order&step=1');//redirecciona al primer paso

		$currency = $this->context->currency;//recupero la moneda de la compra
		$total = (float)$cart->getOrderTotal(true, Cart::BOTH);//recupero el total de la compra
		
		/* VERIFICACION DE LA ORDEN.
			Los parametros enviados a la funcion validateOrder son:
				* id del carrito
				* Order Status correspondiente a este metodo de pago (sacado de la tabla configuration)
				* monto total de la orden
				* metodo de pago / nombre del modulo
				* mensaje : null
				* variables extra: null
				* moneda en la que se hace el pago
				* dont_touch_amount
				* secure_key del cliente
				* shop / tienda: null
		*/
		
		$this->module->validateOrder(	
										(int)$cart->id,
										$orderState,
										$total,
										$this->module->displayName,
										NULL,
										NULL,
										(int)$currency->id,
										false,
										$customer->secure_key);
	
		$this->module->log->info('Creada orden id '.(int)$this->module->currentOrder.' para carro id '.$cart->id);
		$this->module->log->info('Status: '.json_encode($transaccion));
		$this->module->log->info('Actualizando registro OrderPayment para orden id '.(int)$this->module->currentOrder.' con OPERATIONID='.$transaccion['OPERATIONID'].' CARDNUMBERVISIBLE='.$transaccion['CARDNUMBERVISIBLE'].' PAYMENTMETHODNAME='.$transaccion['PAYMENTMETHODNAME']);
		
		try
		{
			$this->_addPaymentDetalle((int)$this->module->currentOrder, $transaccion);
		}
		catch (Exception $e)
		{
			$this->module->log->error('EXCEPCION',$e);//guardo el mensaje
		}
				
		Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
	}
	
	/**
	 * Agrego los detalles propios de la transaccion al registro OrderPayment correspondiente
	 * @param int $id_order id de la orden creada
	 * @param array $transaccion respuesta de la transaccion
	 */
	private function _addPaymentDetalle($id_order, $transaccion)
	{
		$orden = new Order($id_order);

		$detalles = array(
			'transaction_id' => $transaccion['OPERATIONID'],
			'card_number' => $transaccion['CARDNUMBERVISIBLE'],
			'card_brand' => $transaccion['PAYMENTMETHODNAME']
		);

		Db::getInstance()->update(OrderPayment::$definition['table'], $detalles, 'order_reference=\''.$orden->reference.'\'');
	}
}