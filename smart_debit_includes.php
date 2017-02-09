<?php
/**************************************************************************************************
* Smart Direct PHP Kit Includes File
***************************************************************************************************

***************************************************************************************************
* Change history
* ==============
*
* 10/08/2012 - Parvez Saleh - Created
* 
***************************************************************************************************
* Description
* ===========
*
* Functions to allow communication with Smart Debit REST API's
***************************************************************************************************/

ob_start();
//session_start();

/**************************************************************************************************
* Useful functions for all pages in this kit
**************************************************************************************************/

/* Base 64 Encoding function **
** PHP does it natively but just for consistency and ease of maintenance, let's declare our own function **/

function base64Encode($plain) {
  // Initialise output variable
  $output = "";
  
  // Do encoding
  $output = base64_encode($plain);
  
  // Return the result
  return $output;
}

/* Base 64 decoding function **
** PHP does it natively but just for consistency and ease of maintenance, let's declare our own function **/

function base64Decode($scrambled) {
  // Initialise output variable
  $output = "";
  
  // Fix plus to space conversion issue
  $scrambled = str_replace(" ","+",$scrambled);
  
  // Do encoding
  $output = base64_decode($scrambled);
  
  // Return the result
  return $output;
}

// Function to check validity of email address entered in form fields
function is_valid_email($email) {
  $result = TRUE;
  if(!eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$", $email)) {
    $result = FALSE;
  }
  return $result;
}

/*************************************************************
  Send a post request with cURL
    $url = URL to send request to
    $data = POST data to send (in URL encoded Key=value pairs)
*************************************************************/
function requestPost($url, $data, $username, $password, $path){
  // Set a one-minute timeout for this script
  set_time_limit(160);

  // Initialise output variable
  $output = array();

  $options = array(
                    CURLOPT_RETURNTRANSFER => true, // return web page
                    CURLOPT_HEADER => false, // don't return headers
                    CURLOPT_POST => true,
                    CURLOPT_USERPWD => $username . ':' . $password,
                    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                    CURLOPT_HTTPHEADER => array("Accept: application/xml"),
                    CURLOPT_USERAGENT => "XYZ Co's PHP iDD Client", // Let SmartDebit see who we are
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_SSL_VERIFYPEER => false,
                  );
  
  $session = curl_init( $url . $path );
  
  curl_setopt_array( $session, $options );
  

  // Tell curl that this is the body of the POST
  curl_setopt ($session, CURLOPT_POSTFIELDS, $data);
  
  // $output contains the output string
  $output = curl_exec($session);
  $header = curl_getinfo( $session );

  //Store the raw response for later as it's useful to see for integration and understanding 
  $_SESSION["rawresponse"] = $output;
  
  if(curl_errno($session)) {
    $resultsArray["Status"] = "FAIL";  
    $resultsArray['StatusDetail'] = curl_error($session);
  }
  else {
    // Results are XML so turn this into a PHP Array
    $resultsArray = json_decode(json_encode((array) simplexml_load_string($output)),1);  

    // Determine if the call failed or not
    switch ($header["http_code"]) {
      case 200:
        $resultsArray["Status"] = "OK";
        break;
      default:
        $resultsArray["Status"] = "INVALID";
        //echo "HTTP Error: " . $header["http_code"];
    }
  }
   
  // Return the output
  return $resultsArray;
  
} // END function requestPost()

?>