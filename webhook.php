<?php

include_once 'db-config.php';

ini_set("log_errors", 1);
ini_set("error_log", "error.log");

try {
	if (isset($_GET["hub_mode"]) && $_GET["hub_mode"] == "subscribe") {
        echo ($_GET["hub_challenge"]);
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    	$input_json = file_get_contents('php://input');
        $input = json_decode($input_json, true);

        // error_log("FB Input :");
        // error_log(print_r($input, TRUE));

        // log all request
        $sql = " INSERT INTO log (type, payload)
            VALUES ('fb msg', '$input_json') ";

	    if ($conn->query($sql) === TRUE){
	    }else {
	        echo "Error: " . $sql . "<br>" . $conn->error;
	    }
        
        $sender_id = ( isset($input['entry'][0]['messaging'][0]['sender']['id']) ) 
        				? $input['entry'][0]['messaging'][0]['sender']['id'] 
        				: FALSE;
        
        // check sender id have or not in request
        if ( ! $sender_id ) {
            // sender id not found
            echo json_encode(array(
                'status' => 'fail',
                'messege' => 'Unauthorized access.'
            ));
            return;
        }

        $message_echo = ( isset($input['entry'][0]['messaging'][0]['message']['is_echo']) ) ? trim($input['entry'][0]['messaging'][0]['message']['is_echo']) : '';
            
        $message_delivery = ( isset($input['entry'][0]['messaging'][0]['delivery']['mids'][0]) ) ? trim($input['entry'][0]['messaging'][0]['delivery']['mids'][0]) : '';
        
        $message_read = ( isset($input['entry'][0]['messaging'][0]['read']['watermark']) ) ? trim($input['entry'][0]['messaging'][0]['read']['watermark']) : '';
        
        $message_ref = ( isset($input['entry'][0]['messaging'][0]['referral']['ref']) ) ? trim($input['entry'][0]['messaging'][0]['referral']['ref']) : '';
        
        $message_linking = ( isset($input['entry'][0]['messaging'][0]['account_linking']['status']) ) ? trim($input['entry'][0]['messaging'][0]['account_linking']['status']) : '';

        // msgs seen etc
        if ($message_echo != '' || $message_delivery != '' || $message_read != '' || $message_ref != '' || $message_linking != '') {
            // nothing to do
            exit();
        }
        
        $received_messege = ( isset($input['entry'][0]['messaging'][0]['message']['text']) ) ? trim($input['entry'][0]['messaging'][0]['message']['text']) : '';

        $postback_payload = '';
        if ( isset($input['entry'][0]['messaging'][0]['postback']['payload']) ) {
        	$postbackjson = $input['entry'][0]['messaging'][0]['postback']['payload'];
        	$postbackdata = json_decode($postbackjson, TRUE);
        	if ( $postbackdata != '') {
        		$postback_payload = $postbackdata['value'];
        		$id 		  = $postbackdata['id'];
        	}else {
        		$postback_payload = $input['entry'][0]['messaging'][0]['postback']['payload'];
        	}

        }
        
        //$postback_payload = ( isset($input['entry'][0]['messaging'][0]['postback']['payload']) ) ? trim($input['entry'][0]['messaging'][0]['postback']['payload']) : '';
        
        $quick_postback_payload = ( isset($input['entry'][0]['messaging'][0]['message']['quick_reply']['payload']) ) ? trim($input['entry'][0]['messaging'][0]['message']['quick_reply']['payload']) : '';
        
        $received_location = ( isset($input['entry'][0]['messaging'][0]['message']['attachments'][0]['payload']['coordinates']) ) ? $input['entry'][0]['messaging'][0]['message']['attachments'][0]['payload']['coordinates'] : FALSE;

        $msg_data = array("platform" => "messenger", "sender_id" => $sender_id);
        
        if ($postback_payload != '') {
            // button click
            $msg_data["type"] = "postback";
            $msg_data["data"] = $postback_payload;
        } else if ($quick_postback_payload != '') {
            // quick button click
            $msg_data["type"] = "postback";
            $msg_data["data"] = $quick_postback_payload;
        } else if ($received_location) {
            // quick button location
            $msg_data["type"] = "location_postback";
            $msg_data["data"] = $received_location;
        } else if ($received_messege != '') {
            $msg_data["type"] = "text";
            $msg_data["data"] = $received_messege;
        } else {
            // not identify which type of request or delivery or read msg.
            exit();
        }

        process_request($msg_data);
    } else {
        echo "whats on browser";
    }
}

// catch exception
catch(Exception $e) {
}


function process_request($msg_data) {
	global $conn;
	global $id;

    $sql = " SELECT * FROM bot_user WHERE sender_id = '" . $msg_data['sender_id'] . "' " ;
    $result = $conn->query($sql);

    if ( $result->num_rows == 0 ) {
        $access_token = "EAACZAXPhpGGUBADhFmtKGme4ZAqIXZA05b4iH13sIUBUcZAVBXs44CDLG1rMZBOSRK2fMjH9X92czRTuZBZAxW3bOPE0T3JHoTm4hwt8yLAd4Rxuf4qnC9dMy3BsEMJqienrDvkHQaEG0nBGWPse0JULlzqM5j0kyaz7piGMqKqoI1xFZC99mIgT";

        $url = "https://graph.facebook.com/v2.6/" . $msg_data['sender_id'] . "?fields=first_name,last_name&access_token=" .$access_token;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);

        $result = curl_exec($ch);
        curl_close($ch);

        $obj = json_decode($result, TRUE);
        error_log(print_r($obj, TRUE));

        $sql = " INSERT INTO bot_user (sender_id, fname, lname, expected) VALUES ('". $msg_data['sender_id'] ."' , '". $obj['first_name'] ."' , '" .$obj['last_name']. "' , '') ";

        if ($conn->query($sql) === TRUE) {
            error_log("New record created successfully") ;

            $sql = " SELECT * FROM bot_user WHERE sender_id = '" . $msg_data['sender_id'] . "' " ;
            $result = $conn->query($sql);
            while($row = $result->fetch_assoc()) {
                $user = $row;
            }
        } else {
            error_log("Error: " . $sql . "<br>" . $conn->error) ;
        }

    } else {
        while($row = $result->fetch_assoc()) {
            $user = $row;
        }
    }

	//error_log(print_r($msg_data, TRUE));
	switch ($msg_data["type"]) {
		case 'postback':
			switch ($msg_data["data"]) {

				case 'Info':
					$sql = " SELECT * FROM product WHERE id = '" .$id. "' ";
					$result = $conn->query($sql);

					$info = array();
					if ($result->num_rows > 0 ) {
						while ($row = $result->fetch_assoc()) {
							$info[] = $row ;
							
						}
					}
					$text = send_info($info);
					//$text = str_replace("\r\n", ", ", $text);
					//error_log($text);
					send_text($msg_data["sender_id"], $text);
					break;

				case 'Cart':
					$sql = " SELECT * FROM product WHERE id = '" .$id. "' ";
					$result = $conn->query($sql);
                    
                    $cart = array();
					
					if ($result->num_rows > 0 ) {
						while ($row = $result->fetch_assoc()) {
							$data = array();
							$data['id'] 	     = $row['id'];
							$data['name'] 	     = $row['name'];
							$data['price'] 	     = $row['price'];
							$data['image']       = $row['image'];
                            $data['description'] = $row['description'];
							$cart[] = $data;

                            error_log(print_r($cart, TRUE));
						}
                       
                        $sql = "INSERT INTO cart (sender_id, product_id, product_name, product_price, product_image) VALUES 
                                   ('".$msg_data['sender_id']."', '".$cart[0]['id']."', '".$cart[0]['name']."', '".$cart[0]['price']."', '".$cart[0]['image']."') ";

                        if ($conn->query($sql) === TRUE) {
                            error_log("New record created successfully") ;
                        } else {
                            error_log("Error: " . $sql . "<br>" . $conn->error) ;
                        }
                    }
                        
					$text = addcart($cart);
					send_text($msg_data["sender_id"], $text);
					break;

                case 'Remove':
                    $sql = " SELECT * FROM cart WHERE id = '" .$id. "' ";
                    $result = $conn->query($sql);

                    if ($result->num_rows > 0 ) {
                        while ($row = $result->fetch_assoc()) {
                            $text = 'Product removed from CART:
Product : ' .$row["product_name"]. '
Price : ' .$row["product_price"] ;
                        }
                    }

                    $sql = " DELETE FROM cart WHERE id ='" .$id. "' ";
                    if ($conn->query($sql) === TRUE) {
                        error_log("Record has Been Deleted");
                    } else {
                        error_log("Error deleting record: " . $conn->error);
                    }

                    send_text($msg_data["sender_id"], $text);
                    break;

                case 'proceed':
                    update_expected($msg_data['sender_id'], "address");
                    send_text($msg_data["sender_id"], "Provide delivery Address :");

                    break;
				
				default:
					# code...
					break;
			}
			break;

		case 'text':

            $expected = $user['expected'];
            if ($expected != '') {
                switch ($expected) {
                    case 'address':
                        update_expected($msg_data['sender_id'], '');
                        $sender_id = $msg_data["sender_id"];
                        $user_id = $user['id'];
                        $name = $user['fname'] . " " . $user['lname'];
                        $address = $msg_data['data'];

                        $sql = "INSERT INTO bot_order (user_id, name, email, address) VALUES ($user_id, '$name', '', '$address')";
                        if ($conn->query($sql) === TRUE) {
                            $last_id = $conn->insert_id;
                    
                            $sql1 = " SELECT * FROM cart WHERE sender_id = '$sender_id' ";
                            $result = $conn->query($sql1);
                            if ( $result->num_rows > 0 ) {
                                while ($row = $result->fetch_assoc()) {
                                    $product_id = $row['product_id'];
                                    
                                    $sql2 = "INSERT INTO bot_order_details (order_id, product_id, quantity) VALUES ($last_id, $product_id, 1)";
                                    if ($conn->query($sql2) === TRUE) {
                                        error_log("New record created successfully") ;
                                    } else {
                                        error_log("Error: " . $sql2 . "<br>" . $conn->error) ;
                                    }

                                    $sql3 = "DELETE FROM cart WHERE product_id= '$product_id' ";
                                    if ($conn->query($sql3) === TRUE) {
                                        error_log("Record deleted successfully");
                                    } else {
                                        error_log("Error deleting record: " . $conn->error);
                                    }

                                }
                            } else {
                                error_log("Error: " . $sql1 . "<br>" . $conn->error) ;
                            }
                        } else {
                            error_log("Error: " . $sql . "<br>" . $conn->error) ;
                        }

                        update_expected($msg_data['sender_id'], "email");
                        send_text($msg_data["sender_id"], "Provide Email :");
                        break;
                    
                    case 'email':
                        $sender_id = $msg_data["sender_id"];
                        $user_id = $user['id'];
                        $name = $user['fname'] . " " . $user['lname'];
                        $email = $msg_data['data'];

                        update_expected($sender_id, '');

                        $sql = " UPDATE bot_order SET email='$email', order_detail= 1 WHERE user_id= $user_id AND order_detail= 0 " ;
                        
                        if ($conn->query($sql) === TRUE) {
                            return TRUE;
                        } else {
                            error_log($conn->error);
                            return FALSE;
                        }

                        break;
                    
                    default:
                        # code...
                        break;
                }
            } 
            else {

    			$wit_entity = get_wit_entity($msg_data["data"]);

    			if ( isset($wit_entity['entities']['greetings'])) {
    				$greet = "Hello, Welcome to E-Commerce Chat!";
    				send_text($msg_data["sender_id"], $greet);
    			}
    			else if ( $wit_entity['entities']['intent'][0]['value'] == "show" ) 
    			{	
    				if ( isset($wit_entity['entities']['watch'][0]['value'])) {
    					$txt = $wit_entity['entities']['watch'][0]['value'];
    					
    					$sql = " SELECT * FROM product WHERE brand = '" .$txt. "' ";
    					$result = $conn->query($sql);

    					$data = array();
    					if ($result->num_rows > 0) {
    						while ($row = $result->fetch_assoc()) {
    							$data[] = $row;
    						}
    		
    						send_watch_template($msg_data["sender_id"], $data);	
    					} 
    				} 
                    else {
                        send_text($msg_data["sender_id"], "Showing you some watch");
                    } 
    			}
                else if ( $wit_entity['entities']['intent'][0]['value'] == "checkout" ) {

                    $sql = " SELECT * FROM cart WHERE sender_id = '" .$msg_data['sender_id']. "' ";
                    $result = $conn->query($sql);

                    $data = array();
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $data[] = $row;
                        }
                        send_cart_template($msg_data["sender_id"], $data);
                        send_quick_button($msg_data["sender_id"], "If Confirm, Kindly click below button");
                    }
                }
            }
		    break;

	    case 'location_postback':
		     $text = 'lat: ' . $msg_data["data"]["lat"];
		     $text .= '\nlong: ' . $msg_data["data"]["long"];

		     send_text($msg_data["sender_id"], $text);
		     break;

		  default:
			# code...
			break;

    }
}

function update_expected($sender_id, $value) {
    // update expected column in user table
    global $conn;

    $sql = "UPDATE bot_user SET expected='$value' WHERE sender_id='$sender_id'";

    if ($conn->query($sql) === TRUE) {
        return TRUE;
    } else {
        error_log($conn->error);
        return FALSE;
    }
}

function send_text($sender_id, $text) {
	$access_token = "EAACZAXPhpGGUBADhFmtKGme4ZAqIXZA05b4iH13sIUBUcZAVBXs44CDLG1rMZBOSRK2fMjH9X92czRTuZBZAxW3bOPE0T3JHoTm4hwt8yLAd4Rxuf4qnC9dMy3BsEMJqienrDvkHQaEG0nBGWPse0JULlzqM5j0kyaz7piGMqKqoI1xFZC99mIgT";

	$url = "https://graph.facebook.com/v2.6/me/messages?access_token=" . $access_token;

	/*initialize curl*/
    $ch = curl_init($url);

    /*prepare response*/
    $jsonData = '{
	    "recipient": {
	        "id": "' . $sender_id . '"
	    },
	    "message":{
	        "text": "' . $text . '"
	    }
    }';

    $dataaa = array(
        "recipient" => array(
            "id" => $sender_id
        ),
        "message" => array(
            "text" => $text
        )
    );
    $jsonData = json_encode($dataaa);

    /* curl setting to send a json post data */
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    $result = curl_exec($ch); // user will get the message

    error_log($result);

}

function send_image($sender_id, $text) {
	$access_token = "EAACZAXPhpGGUBADhFmtKGme4ZAqIXZA05b4iH13sIUBUcZAVBXs44CDLG1rMZBOSRK2fMjH9X92czRTuZBZAxW3bOPE0T3JHoTm4hwt8yLAd4Rxuf4qnC9dMy3BsEMJqienrDvkHQaEG0nBGWPse0JULlzqM5j0kyaz7piGMqKqoI1xFZC99mIgT";

	$url = "https://graph.facebook.com/v2.6/me/messages?access_token=" . $access_token;

	/*initialize curl*/
    $ch = curl_init($url);

    /*prepare response*/
    $jsonData = '{
	    "recipient":{
	        "id":"' . $sender_id . '"
	    },
	    "message":{
    		"attachment":{
      			"type":"image",
     			"payload":{
        			"url":"'.$text.'"
      			}
    		}
  		}
    }';

    /* curl setting to send a json post data */
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    $result = curl_exec($ch); // user will get the message

    error_log($result);

}

function send_quick_button($sender_id, $text) {
	$access_token = "EAACZAXPhpGGUBADhFmtKGme4ZAqIXZA05b4iH13sIUBUcZAVBXs44CDLG1rMZBOSRK2fMjH9X92czRTuZBZAxW3bOPE0T3JHoTm4hwt8yLAd4Rxuf4qnC9dMy3BsEMJqienrDvkHQaEG0nBGWPse0JULlzqM5j0kyaz7piGMqKqoI1xFZC99mIgT";

	$url = "https://graph.facebook.com/v2.6/me/messages?access_token=" . $access_token;

	/*initialize curl*/
    $ch = curl_init($url);

    /*prepare response*/
    $jsonData = '{
	    "recipient":{
	        "id":"' . $sender_id . '"
	    },
	    "message":{
	        "text":"' . $text . '",
	        "quick_replies":[
		      {
		        "content_type":"text",
		        "title":"Proceed..",
		        "payload":"proceed"
		      }
		    ]
	    }
    }';

    /* curl setting to send a json post data */
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    $result = curl_exec($ch); // user will get the message

    error_log($result);

}

function send_location($sender_id, $text) {
	$access_token = "EAACZAXPhpGGUBADhFmtKGme4ZAqIXZA05b4iH13sIUBUcZAVBXs44CDLG1rMZBOSRK2fMjH9X92czRTuZBZAxW3bOPE0T3JHoTm4hwt8yLAd4Rxuf4qnC9dMy3BsEMJqienrDvkHQaEG0nBGWPse0JULlzqM5j0kyaz7piGMqKqoI1xFZC99mIgT";

	$url = "https://graph.facebook.com/v2.6/me/messages?access_token=" . $access_token;

	/*initialize curl*/
    $ch = curl_init($url);

    /*prepare response*/
    $jsonData = '{
	    "recipient":{
    		"id":"' . $sender_id . '"
  		},
  		"message":{
    		"text":"'.$text.':",
    		"quick_replies":[
      			{
        			"content_type":"location"
      			}
    		]
  		}
    }';

    /* curl setting to send a json post data */
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    $result = curl_exec($ch); // user will get the message

    error_log($result);

}

function send_watch_template($sender_id, $data) {
	$access_token = "EAACZAXPhpGGUBADhFmtKGme4ZAqIXZA05b4iH13sIUBUcZAVBXs44CDLG1rMZBOSRK2fMjH9X92czRTuZBZAxW3bOPE0T3JHoTm4hwt8yLAd4Rxuf4qnC9dMy3BsEMJqienrDvkHQaEG0nBGWPse0JULlzqM5j0kyaz7piGMqKqoI1xFZC99mIgT";

	$url = "https://graph.facebook.com/v2.6/me/messages?access_token=" . $access_token;
	$asset_url = "http://masood.localtunnel.me/ecommerce/img/";

	/*initialize curl*/
    $ch = curl_init($url);


    $elements = array();

    foreach ($data as $key => $value) {
    	$elem = array();
    	$elem["title"] 		= $value["name"];
    	$elem["image_url"] 	= $asset_url . $value["image"];
    	$elem["subtitle"] 	= 'Rs. '. $value["price"];
    	$cartjson = json_encode(array( "id" => $value["id"],	"value" => "Cart"));
    	$infojson = json_encode(array( "id" => $value["id"],	"value" => "Info"));

    	$elem["buttons"] = 	array(
    		array(
    			"type" 		=> "postback",
    			"title" 	=> "Add to cart",
    			"payload" 	=>  $cartjson
    		),
    		array(
    			"type" 		=> "postback",
    			"title" 	=> "Information",
    			"payload" 	=> $infojson
    		)
    	);

    	$elements[] = $elem;
    }



    $fb_msg = array (
  		'recipient' => array (
    		'id' => $sender_id,
    	),
  		'message' => array (
    		'attachment' => array (
      			'type' => 'template',
      			'payload' => array (
        			'template_type' => 'generic',
        			'elements' => $elements,
				),
			),
		),
	);

	$jsonData = json_encode($fb_msg);

    /* curl setting to send a json post data */
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    $result = curl_exec($ch); // user will get the message
}

function send_cart_template($sender_id, $data) {
    $access_token = "EAACZAXPhpGGUBADhFmtKGme4ZAqIXZA05b4iH13sIUBUcZAVBXs44CDLG1rMZBOSRK2fMjH9X92czRTuZBZAxW3bOPE0T3JHoTm4hwt8yLAd4Rxuf4qnC9dMy3BsEMJqienrDvkHQaEG0nBGWPse0JULlzqM5j0kyaz7piGMqKqoI1xFZC99mIgT";

    $url = "https://graph.facebook.com/v2.6/me/messages?access_token=" . $access_token;
    $asset_url = "http://masood.localtunnel.me/ecommerce/img/";

    /*initialize curl*/
    $ch = curl_init($url);


    $elements = array();

    foreach ($data as $key => $value) {
        $elem = array();
        $elem["title"]      = $value["product_name"];
        $elem["image_url"]  = $asset_url . $value["product_image"];
        $elem["subtitle"]   = 'Rs. '. $value["product_price"];
        $btnjson = json_encode(array( "id" => $value["id"],    "value" => "Remove"));

        $elem["buttons"] =  array(
            array(
                "type"      => "postback",
                "title"     => "Remove",
                "payload"   =>  $btnjson
            )
        );

        $elements[] = $elem;
    }



    $fb_msg = array (
        'recipient' => array (
            'id' => $sender_id,
        ),
        'message' => array (
            'attachment' => array (
                'type' => 'template',
                'payload' => array (
                    'template_type' => 'generic',
                    'elements' => $elements,
                ),
            ),
        ),
    );

    $jsonData = json_encode($fb_msg);

    /* curl setting to send a json post data */
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    $result = curl_exec($ch); // user will get the message
}

function send_info($info) {
	$info = $info[0];

	$text = 'Product Info :
Price :  ' . $info["price"] . '
Description :  '.$info["description"] ;

	return $text;
}

function addcart($cart) {
	$cart = $cart[0];

	$text = 'successfully Added to Cart :

Prdct Id : ' .$cart["id"] . '
Prdct Name : ' . $cart["name"] . '
Price : ' . $cart["price"] . '
Dscrptn : ' .$cart["description"];

	return $text;

}

function get_wit_entity($user_input){

	$user_text = urlencode($user_input);
	$witURL = 'https://api.wit.ai/message?v=10/07/2017&q='.$user_text ;

	$ch = curl_init();
	$header = array('Authorization: Bearer PEVVZM57FCT5SN2CID7HKPJSAWDOMAPA');

	curl_setopt($ch, CURLOPT_URL, $witURL);
	curl_setopt($ch, CURLOPT_POST, 1);  //sets method to POST (1 = TRUE)
	curl_setopt($ch, CURLOPT_HTTPHEADER,$header); //sets the header value above - required for wit.ai authentication
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //inhibits the immediate display of the returned data

	$server_output = curl_exec ($ch); //call the URL and store the data in $server_output

	curl_close ($ch);

	$output = json_decode($server_output, true);
	error_log(print_r($output, TRUE));

	return $output;
}

?>
