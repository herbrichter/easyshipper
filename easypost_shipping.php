<?php
// let's debug
/*
ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);
*/
/*
require( $_SERVER['DOCUMENT_ROOT'].'/php-console/src/PhpConsole/__autoload.php'); 
$handler = PhpConsole\Handler::getInstance();
*/
/* $registered = PhpConsole\Helper::register(); */


require_once('lib/easypost-php/lib/easypost.php');
class ES_WC_EasyPost extends WC_Shipping_Method {
  function __construct() {
    $this->id = 'easypost';
    $this->has_fields      = true;
    $this->init_form_fields();   
    $this->init_settings();   

    $this->title = __('Easy Post Integration', 'woocommerce');
   
    $this->usesandboxapi      = strcmp($this->settings['test'], 'yes') == 0;
    $this->testApiKey 		    = $this->settings['test_api_key'  ];
    $this->liveApiKey 		    = $this->settings['live_api_key'  ];
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
        'title' => 'Customs Signer',
        'type' => 'text',
        'label' => __( 'Customs Signature', 'woocommerce' ),
        'default' => ''
      ),


    );

  }

  function calculate_shipping($packages = array())
  {	
  
  
	if(class_exists("Handler")) {
		break;
	 } else {
	    // ... any PHP Console initialization & configuration code
		require( $_SERVER['DOCUMENT_ROOT'].'/php-console/src/PhpConsole/__autoload.php'); 
		$handler = PhpConsole\Handler::getInstance();
		$handler->setHandleErrors(false);  // disable errors handling
		$handler->start(); // initialize handlers
	    $connector = PhpConsole\Connector::getInstance();
	    $registered = PhpConsole\Helper::register();
	}
		    
	    
    global $woocommerce;

    $customer = $woocommerce->customer;
    
    /* PC::debug($chass); */
    
    try
    {
    
    // Get a name from the form
    $poststring = parse_str($_POST['post_data'],$addressform);
    $namebilling = $addressform['billing_first_name'].' '.$addressform['billing_last_name'];
    $nameshipping = $addressform['shipping_first_name'].' '.$addressform['shipping_last_name'];
    
    if($addressform['shiptobilling']){ 
    	$fullname = $namebilling;
    } else { 
    	$fullname = $nameshipping; 
    }
 
    $billphone = $addressform['billing_phone'];

    
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
      
      $length = array();
      $width  = array();
      $height = array();
      foreach($woocommerce->cart->get_cart() as $package)
      {
        $item = get_product($package['product_id']);
        $dimensions = explode('x', trim(str_replace('cm','',$item->get_dimensions())));
        $length[] = $dimensions[0]; 
        $width[]  = $dimensions[1];
        $height[] = $dimensions[2] * $package['quantity'];

      }
      $parcel = \EasyPost\Parcel::create(
        array(
          "length"             => max($length),
          "width"              => max($width),
          "height"             => array_sum($height),
          "predefined_package" => null,
          "weight"             => $cart_weight
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
			$multisale = array();
			$multicust = array();
			$epcustomsitems = array();
		
			foreach($cart_group as $c)
				{
					// create customs items from everything in the cart
					$itemid = $c['product_id'];
					$itemdesc = get_post_meta($itemid, 'contents_description');
					$totaldesc .= $itemdesc[0]. '. ';				
					$tariff = get_post_meta($itemid, 'tariff_number');
					$tariff = (string) $tariff[0];
					//take out periods from tariff declarations if they exist
					$tariff = strtr($tariff, '.','');
					
					$cart_howmany = $c['quantity'];
					$weight = get_post_meta( $itemid, '_weight', true);
					$price = get_post_meta( $itemid, '_price', true);
						
						
					// create a customs item array for each item in the cart.						
					$params = array(
						"description"      => $itemdesc[0],
						"quantity"         => $cart_howmany,
						"value"            => $price,
						"weight"           => $weight,
						"hs_tariff_number" => $tariff,
						"origin_country"   => $from_country,
						);
					
					
										
					$customs_item = \EasyPost\CustomsItem::create($params);
					
					$multicust[] = $customs_item;
					
					//create an array of customs items if shipping more than one item
					array_push($epcustomsitems, $customs_item->id);
					/* array_push($params, id=>$customs_item->id); */
					$params['id'] =  $customs_item->id;
					
					/* array_push($params, end($epcustomsitems)); */
					// $paramsarray = $params;
					$multisale[] = (array) $params;	
						
				} // endforeach		
			
			/* $customs_item = \EasyPost\CustomsItem::create($params); */
			/* $all = \EasyPost\CustomsItem::retrieve($customs_item->id); */
		
			// PC::debug($multisale);
			// $flat = json_encode($multisale);
			$tell = \EasyPost\Util::convertEasyPostObjectToArray($multisale);
			// $tell = json_encode($tell);
			// $tell = \EasyPost\Util::convertEasyPostObjectToArray($multicust);
			// PC::debug($tell);
			
			/*
			foreach ($multicust as $h) {
	    		$tell = \EasyPost\Util\::convertEasyPostObjectToArray($h);
			
				PC::debug($tell);
	    		}
			*/
			
			
			
			//$flat = $epcustomsitems;
			
			// $flat = json_encode($multisale);
			
			
			//PC::debug($epcustomsitems, "customsitems");
			
			// PC::debug($flat);
			
			// PC::debug($multicust[0]->id);
			// PC::debug($params);

			// smart customs opbject
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
						

			
			// PC::debug($infoparams,'before create customs');
			
			/* array_push($infoparams->customs_items, $params); */
			$customs_info = \EasyPost\CustomsInfo::create($infoparams);
			
			 
			/* PC::debug($customs_info); */
			
	
			} // end if (foreign) section
		

		
		// creating shipment with customs form
		$shipment =\EasyPost\Shipment::create(array(
		  "to_address" => $to_address,
		  "from_address" => $from_address,
		  "parcel" => $parcel,
		  "customs_info" => $customs_info
		));
		
		
		/* PC::debug($shipment, 'customs items normal'); */
			   
	    // create conditional clause here based on $shipping_abroad
	    if($shipping_abroad) {
	    	// abroad
	    	$shippingservice = array('FirstClassPackageInternationalService', 'PriorityMailInternational');
	    	
	    	// for some reason the CustomsItem object fails when the shipment is created with multiple items
	    	// reinserting item value
	    	$shipment->customs_info->customs_items = $tell;
	    	

	    	   	} else {
	    	// domestic
	    	$shippingservice = array('First', 'Priority');   	
		}

	    $created_rates = \EasyPost\Rate::create($shipment);

    
	    /* PC::debug($shippingservice, 'after shipping abroad'); */
    
    	foreach($created_rates as $r)
		{
			if (!in_array($r->service, $shippingservice)) {
				continue;
			}
				$rate = array(
				'id' => sprintf("%s-%s|%s", $r->carrier, $r->service, $shipment->id),
				'label' => sprintf("%s %s", $r->carrier , $r->service),
				'cost' => $r->rate,
				'calc_tax' => 'per_item'
				);
			// Register the rate
			$this->add_rate( $rate );
			}
		
		
		
		/* PC::debug($user_lastname); */
		$_SESSION['storecustomsitems'] = $params;
		$_SESSION['storefromvar'] = $shipment;
		$_SESSION['shipmentid'] = $shipment->id;
		
		/* PC::debug($_SESSION['shipmentid']); */
		PC::debug($shipment->customs_info->customs_items, 'after rates');
		// PC::debug($shipment, 'after rates');
		

		}
      catch(Exception $e)
      {
        // EasyPost Error - Lets Log.
        error_log(var_export($e,1));
       // mail('raafi.rivero@gmail.com', 'Error from WordPress - EasyPost', var_export($e,1)); 

      }		

  }

  function purchase_order($order_id)
  {
	   // debugger
	   	if(class_exists("Handler")) {
		break;
	 } else {
	    // ... any PHP Console initialization & configuration code
		require( $_SERVER['DOCUMENT_ROOT'].'/php-console/src/PhpConsole/__autoload.php'); 
		$handler = PhpConsole\Handler::getInstance();
		$handler->setHandleErrors(false);  // disable errors handling
		$handler->start(); // initialize handlers
	    $connector = PhpConsole\Connector::getInstance();
	    $registered = PhpConsole\Helper::register();
	}
	
    try
    {
      $order        = &new WC_Order($order_id);
      $shipping     = $order->get_shipping_address();
      if($ship_arr = explode('|',$order->shipping_method))
      {
		
        $shipmentid = $_SESSION['shipmentid'];      
        $shipment = \EasyPost\Shipment::retrieve($shipmentid);
        				
		PC::debug($shipment);
		
        $rates = $shipment->get_rates();
        foreach($shipment->rates as $idx => $r)
        {
          if(sprintf("%s-%s", $r->carrier , $r->service) == $ship_arr[0])
          {
            $index = $idx;
            break;
          }
        }
        $shipment->buy($shipment->rates[$index]);
        update_post_meta( $order_id, 'easypost_shipping_label', $shipment->postage_label->label_url);
        $order->add_order_note(
          sprintf(
              "Shipping label available at: '%s'",
              $shipment->postage_label->label_url
          )
        );
      }
      
    }
    
    catch(Exception $e)
    {
      error_log(var_export($e,1));
		//  mail('raafi.rivero@gmail.com', 'Error from Buy Rate - EasyPost', var_export($e,1));
    }
  }
}
function add_easypost_method( $methods ) {
  $methods[] = 'ES_WC_EasyPost'; return $methods;
}

add_filter('woocommerce_shipping_methods',         'add_easypost_method' );


