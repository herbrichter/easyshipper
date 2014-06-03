<?php
require_once('lib/easypost-php/lib/easypost.php');
class ES_WC_EasyPost extends WC_Shipping_Method {
	function __construct() {
		$this->id = 'easypost';
		$this->has_fields      = true;
		$this->init_form_fields();
		$this->init_settings();

		$this->title = __('Easy Post Integration', 'woocommerce');

		$this->usesandboxapi      = strcmp($this->settings['test'], 'yes') == 0;
		$this->testApiKey       = $this->settings['test_api_key'  ];
		$this->liveApiKey       = $this->settings['live_api_key'  ];
		$this->secret_key         = $this->usesandboxapi ? $this->testApiKey : $this->liveApiKey;

		\EasyPost\EasyPost::setApiKey($this->secret_key);

		$this->enabled = $this->settings['enabled'];


		add_action('woocommerce_update_options_shipping_' . $this->id , array($this, 'process_admin_options'));
		add_action('woocommerce_checkout_order_processed', array(&$this, 'purchase_order' ));

	}
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce' ),
				'type' => 'checkbox',
				'label' => __( 'Enabled', 'woocommerce' ),
				'default' => 'yes'
			),
			'test' => array(
				'title' => __( 'Test Mode', 'woocommerce' ),
				'type' => 'checkbox',
				'label' => __( 'Enabled', 'woocommerce' ),
				'default' => 'yes'
			),
			'test_api_key' => array(
				'title' => "Test Api Key",
				'type' => 'text',
				'label' => __( 'Test Api Key', 'woocommerce' ),
				'default' => ''
			),
			'live_api_key' => array(
				'title' => "Live Api Key",
				'type' => 'text',
				'label' => __( 'Live Api Key', 'woocommerce' ),
				'default' => ''
			),

			'company' => array(
				'title' => "Company",
				'type' => 'text',
				'label' => __( 'Company', 'woocommerce' ),
				'default' => ''
			),
			'street1' => array(
				'title' => 'Address',
				'type' => 'text',
				'label' => __( 'Address', 'woocommerce' ),
				'default' => ''
			),
			'street2' => array(
				'title' => 'Address2',
				'type' => 'text',
				'label' => __( 'Address2', 'woocommerce' ),
				'default' => ''
			),
			'city' => array(
				'title' => 'City',
				'type' => 'text',
				'label' => __( 'City', 'woocommerce' ),
				'default' => ''
			),
			'state' => array(
				'title' => 'State',
				'type' => 'text',
				'label' => __( 'State', 'woocommerce' ),
				'default' => ''
			),
			'zip' => array(
				'title' => 'Zip',
				'type' => 'text',
				'label' => __( 'ZipCode', 'woocommerce' ),
				'default' => ''
			),
			'phone' => array(
				'title' => 'Phone',
				'type' => 'text',
				'label' => __( 'Phone', 'woocommerce' ),
				'default' => ''
			),
			'country' => array(
				'title' => 'Two-Letter Country Code',
				'type' => 'text',
				'label' => __( 'Country', 'woocommerce' ),
				'default' => 'US'
			),
			'customs_signer' => array(
				'title' => 'Customs Signature',
				'type' => 'text',
				'label' => __( 'Customs Signature', 'woocommerce' ),
				'default' => ''
			),
			'round_to_nearest' => array(
				'title' => __( 'Round to Nearest 5', 'woocommerce' ),
				'type' => 'checkbox',
				'label' => __( 'Enabled', 'woocommerce' ),
				'description' => __( 'Rounds the customer-facing shipping price to next highest 5.
				<br />For example, a label that costs $1.93 will display to customer as $5.
				<br />Plugin will still purchase the unrounded amount from USPS.', 'woocommerce' ),
				'default' => 'yes'
			),

		);

	}

	function calculate_shipping($packages = array())
	{
		
// debugger
        //if(class_exists("PC")) {
        //    null;
        //} else {
        //    // ... any PHP Console initialization & configuration code
        //    require( $_SERVER['DOCUMENT_ROOT'].'/php-console/src/PhpConsole/__autoload.php');
        //    $handler = PhpConsole\Handler::getInstance();
        //    $handler->setHandleErrors(false);  // disable errors handling
        //    $handler->start(); // initialize handlers
        //    $connector = PhpConsole\Connector::getInstance();
        //    $registered = PhpConsole\Helper::register();
        //}
		
				
		
		global $woocommerce;

		$customer = $woocommerce->customer;

		try
		{

			// Get a name from the form
			$poststring = parse_str($_POST['post_data'],$addressform);
            
            $billphone = $addressform['billing_phone'];  
            
            if($addressform['ship_to_different_address']){            
                $fullname = $addressform['shipping_first_name'].' '.$addressform['shipping_last_name'];
                $street1 = $addressform['shipping_address_1'];
                $street2 = $addressform['shipping_address_2'];
                $city = $addressform['shipping_city'];
                $state = $addressform['shipping_state'];
                $zip = $addressform['shipping_postcode'];
                $country = $addressform['shipping_country'];
                
                $to_address = \EasyPost\Address::create(
				    array(
					    "name"    => $fullname,
					    "street1" => $street1,
					    "street2" => $street2,
					    "city"    => $city,
					    "state"   => $state,
					    "zip"     => $zip,
					    "country" => $country,
					    "phone"   => $billphone
				    )
			    );
                
            } else {
                $to_address = \EasyPost\Address::create(
                    array(
                        "name"    => $fullname,
                        "street1" => $customer->get_address(),
                        "street2" => $customer->get_address_2(),
                        "city"    => $customer->get_city(),
                        "state"   => $customer->get_state(),
                        "zip"     => $customer->get_postcode(),
                        "country" => $customer->get_country(),
                        "phone"   => $billphone
                    )
                );            
            }

            
			$from_address = \EasyPost\Address::create(
				array(
					"company" => $this->settings['company'],
					"street1" => $this->settings['street1'],
					"street2" => $this->settings['street2'],
					"city"    => $this->settings['city'],
					"state"   => $this->settings['state'],
					"zip"     => $this->settings['zip'],
					"phone"   => $this->settings['phone'],
					"country" => $this->settings['country']
				)
			);
			$cart_weight = $woocommerce->cart->cart_contents_weight;
			$cart_weightint = ceil($cart_weight);
			
            $numberofitems=0;
			$length = array();
			$width  = array();
			$height = array();
			foreach($woocommerce->cart->get_cart() as $package)
			{
				$item = get_product($package['product_id']);
				$dimensions = explode('x', trim(str_replace('in','',$item->get_dimensions())));
				$length[] = $dimensions[0];
				$width[]  = $dimensions[1];
				$height[] = $dimensions[2] * $package['quantity'];
                $numberofitems=$numberofitems+1;
                $totalpurchase=$totalpurchase+$item->price*$package['quantity'];
			}
			$parcel = \EasyPost\Parcel::create(
				array(
					"length"             => max($length),
					"width"              => max($width),
					"height"             => array_sum($height),
					"predefined_package" => null,
					"weight"             => $cart_weightint
				)
			);

			$shipping_abroad = false;
			$customs_info = null;

			if($to_address->country != $from_address->country)
			{

				//create customs form
				$shipping_abroad = true;
				$signature = $this->settings['customs_signer'];

				// Get the Customs item descriptions and tarrif numbers entered on product pages.
				$cart_group = $woocommerce->cart->cart_contents;
				$tariff = '';
				$from_country = $from_address->country;
				$customs_item = array();
				$multicust = array();

				foreach($cart_group as $c)
				{
					// create customs items from everything in the cart
					$itemid = $c['product_id'];
					$itemdesc = get_post_meta($itemid, 'contents_description');
					$totaldesc .= $itemdesc[0]. '. ';

					// pull tariff no. from the db, convert to string
					$tariff = get_post_meta($itemid, 'tariff_number');
					$tariff = (string) $tariff[0];

					// get rid of periods
					$cleantariff = str_replace('.','',$tariff);

					// make tariff number 6-digits long by adding zeros if short
					$cleantariff = str_pad( $cleantariff , 6 , "0" , STR_PAD_RIGHT);

					$cart_howmany = $c['quantity'];
					$weight = get_post_meta( $itemid, '_weight', true);
					$price = get_post_meta( $itemid, '_price', true);
					
					// convert weight value from float to integer for Customs Item
					$weightint = ceil($weight);
                    
                    $totalvalue=$price * $cart_howmany;

					// create a customs item array for each item in the cart.
					$params = array(
						"description"      => $itemdesc[0],
						"quantity"         => $cart_howmany,
						"value"            => $totalvalue,
						"weight"           => $weightint,
						"hs_tariff_number" => $cleantariff,
						"origin_country"   => $from_country,
					);
                    
					$customs_item = \EasyPost\CustomsItem::create($params);

					// Array of all CustomsItem objects
					$multicust[] = $customs_item;

                    $declaredvalue=$declaredvalue+$totalvalue;

				} // endforeach


				// smart customs object
				$infoparams = array(
					"eel_pfc" => 'NOEEI 30.37(a)',
					"customs_certify" => true,
					"customs_signer" => $signature,
					"contents_type" => 'merchandise',
					"contents_explanation" => '', // only necessary for contents_type=other
					"restriction_type" => 'none',
					"non_delivery_option" => 'return',
					"customs_items" => $multicust
				);
								
				$customs_info = \EasyPost\CustomsInfo::create($infoparams);
                
                $shipmentOptions = null;
                
                // do not include signature if (foreign)
                //$shipmentOptions = array(
                //    "declared_value" => $declaredvalue,
                //    "residential_to_address" => '1',
                //    "saturday_delivery" => '1'
                //);
                
            // end if (foreign) section    
            } else { // include signature if not (foreign)        
				$cart_group = $woocommerce->cart->cart_contents;
				foreach($cart_group as $c)
				{
					$cart_howmany = $c['quantity'];
					$weight = get_post_meta( $itemid, '_weight', true);
					$price = get_post_meta( $itemid, '_price', true);
					
                    $totalvalue=$price * $cart_howmany;
                    
                    //error_log('CALCULATINGTOTALVALUE='.$totalvalue);
                    
					// convert weight value from float to integer for Customs Item
					$weightint = ceil($weight);
                    
                    $declaredvalue=$declaredvalue+$totalvalue;

				} // endforeach
                
                // $shipmentOptions = array(
                //    "delivery_confirmation"      => 'ADULT_SIGNATURE',
                //    "declared_value" => $declaredvalue,
                //    "residential_to_address" => '1',
                //    "saturday_delivery" => '1'
                //);                
                
                           
 			    $shipmentOptions = array(
				    "delivery_confirmation"      => 'SIGNATURE'
			    );           
			} 
            
            $ShippingInsurance=$declaredvalue*.01;
            
			// create shipment with customs form
			$shipment =\EasyPost\Shipment::create(array(
					"to_address" => $to_address,
					"from_address" => $from_address,
					"parcel" => $parcel,
					"customs_info" => $customs_info,
                    "options" => $shipmentOptions
				));	            
				
            //$shippingservice = array(
            //    'First',
            //    'Priority',
            //    'FirstClassPackageInternationalService',
            //    'PriorityMailInternational'
            //);

			// cuter names for the shipping services. Ideally user can set these. Raw names are too long.
            //$shortnames = array(
            //    'First' => 'First Class',
            //    'Priority' => 'Priority',
            //    'FirstClassPackageInternationalService'  => 'First Class Int\'l',
            //    'PriorityMailInternational' => 'Priority Int\'l'
            //);

            //error_log ('Calculate Shipping BEFORE - shipment object= '.print_r( $shipment, true ));
            
			// Unset, then reset rates in case user has changed country (by accident)
			unset($shipment->rates);	
			$created_rates = \EasyPost\Rate::create($shipment);
			$shipment = \EasyPost\Shipment::retrieve($shipment);
			
			// PC::debug($shipment, 'shipment');
			// PC::debug($created_rates, 'after unset shipment-rates');

			//error_log ('Calculate Shipping AFTER - shipment object= '.print_r( $shipment, true ));
            
			$roundset = $this->settings['round_to_nearest'];
			
            // function to round up to nearest 5
            function roundUpToAny($n,$x=5) {
                    return round(($n+$x/2)/$x)*$x;
                }
                
			foreach($created_rates as $r)
			{
                //if (!in_array($r->service, $shippingservice)) {
                //    continue;
                //}
				
                
				if ( $roundset === 'yes' ) {
					// round the price
					$roundednum = roundUpToAny($r->rate);
				} else {
					// don't round
					$roundednum = $r->rate;
				}
                
				$rate = array(
					'id' => sprintf("%s-%s|%s", $r->carrier, $r->service, $shipment->id),
					'label' => sprintf("%s %s", $r->carrier , $r->service),
					'cost' => $roundednum,
					'calc_tax' => 'per_item'
				);

				// Register the rate
				$this->add_rate( $rate );
			}

            //error_log ('Calculate Shipping RATES - shipment object= '.print_r( $shipment, true ));
            
			// store shipment id to call when ready to purchase
			$_SESSION['shipmentid'] = $shipment->id;

		}
		catch(Exception $e)
		{
			error_log('EASYPOST_SHIPPING - calculating shipping ERROR Order ID='.$order_id.'DUMP='.var_export($e,1));
			mail('teardroperrors@tmssys.com', 'Error from WordPress calculating shipping - Order ID='.$order_id, var_export($e,1));
            wc_add_notice( __( 'ERROR='.$e->getMessage(). ' Please try again by changing one item in your address. Contact Teardrop support if the errors continue.' ), 'error' );
            wc_print_notices();
            // Send output to client
            flush ();
            ob_flush ();
            exit;
            } // end catch

	}



	function purchase_order($order_id)
	{

// debugger
        //if(class_exists("PC")) {
        //    null;
        //} else {
        //    // ... any PHP Console initialization & configuration code
        //    require( $_SERVER['DOCUMENT_ROOT'].'/php-console/src/PhpConsole/__autoload.php');
        //    $handler = PhpConsole\Handler::getInstance();
        //    $handler->setHandleErrors(false);  // disable errors handling
        //    $handler->start(); // initialize handlers
        //    $connector = PhpConsole\Connector::getInstance();
        //    $registered = PhpConsole\Helper::register();
        //}
        global $woocommerce;
        
		try
		{
			            
            //error_log ('Purchase order - order id= '.$order_id);
            $order        = new WC_Order($order_id);
			$shipping     = $order->get_shipping_address();
            
            $method = $order->get_shipping_methods();
            $method = array_values($method);
            $shipping_method = $method[0]['method_id'];  
            

            
            //error_log ('Purchase order - shipping method= '.print_r( $shipping_method, true ));
             if ($shipping_method=='free_shipping'){
                error_log('Free shipping detected. Skipping purchase of label');
                
            } else {           
                if($ship_arr = explode('|',$shipping_method));
			    //if($ship_arr = explode('|',$order->shipping_method))
			    {
                    //error_log ('Purchase order - order object= '.print_r( $order, true )); 
                    //error_log ('Purchase order - shipping object= '.print_r( $shipping, true )); 
                    //error_log ('Purchase order -  selected shipping methood= '.$ship_arr[0]);
				    // pull in shipment from session, reactivate it with API
                    $shipmentid = $_SESSION['shipmentid'];
                    $shipment = \EasyPost\Shipment::retrieve($shipmentid);                

                    //error_log ('Purchase order - shipment id='.$shipmentid);
                
                    //error_log ('Purchase order - shipment object= '.print_r( $shipment, true )); 
                
				    $rates = $shipment->get_rates();
                
                    //error_log('Rates Objects ='.print_r($rates,true));
                
				    foreach($shipment->rates as $idx => $r)
				    {
					    //error_log ('Purchase order - checking rates in shipment. rate carrier= '.$r->carrier.' and rate service= '.$r->service.' and ship carrier-service= '.$ship_arr[0]);
                        if(sprintf("%s-%s", $r->carrier , $r->service) == $ship_arr[0])
					    {
						    $index = $idx;
                            $selectedShippingCarrier=$r->carrier;
                            $selectedShippingService=$r->service;
						    break;
					    }
				    }


                    foreach($woocommerce->cart->get_cart() as $package)
			        {
				        $item = get_product($package['product_id']);
                        $cart_howmany = $package['quantity'];
                        $numberofitems=$numberofitems+$cart_howmany;
                        $totalpurchase=$totalpurchase+($item->price*$cart_howmany);                    
			        }
                
                    // call EasyPost to generate label                
                
                    $buylabelparams = array(
				        'rate'      => $shipment->rates[$index],
                        'insurance' => $totalpurchase
			        ); 
                
                    $shipment->buy($buylabelparams);
                
				    update_post_meta( $order_id, 'easypost_shipping_label', $shipment->postage_label->label_url);
				    $order->add_order_note(
					    sprintf(
						    "Shipping label available at: '%s'",
						    $shipment->postage_label->label_url
					    )
				    );
                
                    // save tracking code
                    $tracking_code=$shipment->tracking_code;
                    update_post_meta( $order_id, 'easypost_shipping_tracking_number', $tracking_code);

                    // save shipping carrier
                    update_post_meta( $order_id, 'easypost_shipping_carrier', $selectedShippingCarrier);
 
                    // save shipping method
                    update_post_meta( $order_id, 'easypost_shipping_method', $selectedShippingService);
 
                    // save insured value
                    update_post_meta( $order_id, 'easypost_insured_value', $totalpurchase);
                
                    // save tracking url
                    if ($selectedShippingCarrier=='USPS'){
                        // USPS Tracking url
                        $tracking_url='https://tools.usps.com/go/TrackConfirmAction_input?qtc_tLabels1='.$tracking_code;
                    } else {
                        $tracking_url='http://www.fedex.com/Tracking?action=track&tracknumbers='.$tracking_code;
                    }
                
                    update_post_meta( $order_id, 'easypost_shipping_tracking_url', $tracking_url);
                
                    // save initial shipping status
                    update_post_meta( $order_id, 'easypost_shipping_status', 'Pending Shipment');
                                
                
			    } // end if($ship_arr = explode
            } // end check for free shipping 

		}  // end try

		catch(Exception $e)
		{
			error_log('EASYPOST_SHIPPING - purchase_order ERROR Order ID='.$order_id.'DUMP='.var_export($e,1));
			mail('teardroperrors@tmssys.com', 'Error from WordPress - Creating purchase order - Order ID='.$order_id, var_export($e,1));
            wc_add_notice( __( 'ERROR='.$e->getMessage(). ' Please try again by changing one item in your address. Contact Teardrop support if the errors continue.' ), 'error' );
            wc_print_notices();
            // Send output to client
            flush ();
            ob_flush ();
            exit;
            } // end catch
        
	} // end purchase order function
} // end  class
function add_easypost_method( $methods ) {
	$methods[] = 'ES_WC_EasyPost'; return $methods;
}

add_filter('woocommerce_shipping_methods',         'add_easypost_method' );
