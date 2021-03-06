<?php defined('SYSPATH') or die('No direct script access.');

/**
* paypal class
*
* @package Open Classifieds
* @subpackage Core
* @category Helper
* @author Chema Garrido <chema@garridodiaz.com>, Slobodan Josifovic <slobodan.josifovic@gmail.com>
* @license GPL v3
*/

class Controller_Payment_Paypal extends Controller{
	

	public function after()
	{

	}
	
	public function action_ipn()
	{
        //todo delete
        paypal::validate_ipn();

		$this->auto_render = FALSE;

		//START PAYPAL IPN
		//manual checks
		$id_order         = Core::post('item_number');
		$paypal_amount    = Core::post('mc_gross');
		$payer_id         = Core::post('payer_id');

		//retrieve info for the item in DB
		$order = new Model_Order();
		$order = $order->where('id_order', '=', $id_order)
					   ->where('status', '=', Model_Order::STATUS_CREATED)
					   ->limit(1)->find();
		
		if($order->loaded())
		{
			// detect product to be processed 
			if (is_numeric($order->id_product))
			{
				$id_category = new Model_Category();
				$id_category = $id_category->where('id_category', '=', $order->id_product)->limit(1)->find();
				$product_id  = $id_category->id_category;
			}
			else
			{
				$product_id = $order->id_product;
			} 

			if (	Core::post('mc_gross')          == number_format($order->amount, 2, '.', '')
				&&  Core::post('mc_currency')       == core::config('paypal.paypal_currency') 
				&& (Core::post('receiver_email')    == core::config('paypal.paypal_account') 
					|| Core::post('business')       == core::config('paypal.paypal_account')))
			{//same price , currency and email no cheating ;)
				if (paypal::validate_ipn()) 
				{
					$order->confirm_payment($id_order, core::config('general.moderation'));	
				} //payment succeed and we confirm the post ;) (CALL TO LOGIC PUT IN ctrl AD)

				else
				{
					// Email::send("slobodan.josifovic@gmail.com",'qwe','xxxxxxx',"reply",'replyName', NULL); // @TODO EMAIL
					// Log an invalid request to look into
					// PAYMENT INVALID & INVESTIGATE MANUALY!
					$subject = 'Invalid Payment';
					$message = 'Dear Administrator,<br />
								A payment has been made but is flagged as INVALID.<br />
								Please verify the payment manualy and contact the buyer. <br /><br />Here is all the posted info:';
					email::send(core::config('general.notify_email'),'',$subject,$message.'<br />'.print_r($_POST,true)); // @TODO EMAIL
				}	

			} 
			else //trying to cheat....
			{
				$subject = 'Cheat Payment !?';
				$message = 'Dear Administrator,<br />
							A payment has been made but is flagged as Cheat.<br />
							We suspect some forbiden or illegal actions have been made with this transaction.<br />
							Please verify the payment manualy and contact the buyer. <br /><br />Here is all posted info:';
				email::send(core::config('general.notify_email'),$subject,$message.'<br />'.print_r($_POST,true)); // @TODO EMAIL
			}
		}// END order loaded
		else
		{
			//order not loaded
            $subject = 'Order not loaded';
            $message = 'Dear Administrator,<br />
                        Someone is trying to pay an inexistent Order...
                        Please verify the payment manually and contact the buyer. <br /><br />Here is all posted info:';
            // email::send(Core::config('common.email'),Core::config('common.email'),$subject,$message.'<br />'.print_r($_POST,true)); // @TODO EMAIL
		}

		$this->response->body('OK');
	} 

	/**
	 * [action_form] generates the form to pay at paypal
	 */
	public function action_form()
	{ 
		$this->auto_render = FALSE;

		$order_id = $this->request->param('id');


		$order = new Model_Order();

        $order->where('id_order','=',$order_id)
            ->where('status','=',Model_Order::STATUS_CREATED)
            ->limit(1)->find();

        if ($order->loaded())
        {
        	// dependant on product we have different names
        	if($order->id_product == Paypal::to_featured)
        		$item_name = __('Advertisement to top');
        	else if ($order->id_product == Paypal::to_top)
        		$item_name = __('Advertisement to featured');
        	else
        		$item_name = $order->description.__(' category');

			$paypal_url = (Core::config('paypal.sandbox')) ? Paypal::url_sandbox_gateway : Paypal::url_gateway;

		 	$paypal_data = array('order_id'            	=> $order_id,
	                             'amount'            	=> number_format($order->amount, 2, '.', ''),
	                             'site_name'        	=> core::config('general.site_name'),
	                             'site_url'            	=> URL::base(TRUE),
	                             'paypal_url'        	=> $paypal_url,
	                             'paypal_account'    	=> core::config('payment.paypal_account'),
	                             'paypal_currency'    	=> core::config('payment.paypal_currency'),
	                             'item_name'			=> $item_name);
			
			$this->template = View::factory('paypal', $paypal_data);
            $this->response->body($this->template->render());
			
		}
		else
		{
			Alert::set(Alert::INFO, __('Order could not be loaded'));
            $this->request->redirect(Route::url('default'));
		}
	}

}