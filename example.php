<?php
$request_host = 'https://secure.ddprocessing.co.uk';
$request_path = '/api/ddi/variable/validate';
//$request_path = '/api/system_status';
$user = 'vedaapi';
$password = 'RUBgvr*R';

// The request parameters
$pslid = 'vedatest';
$reference_number = 'ABC123456';
$payer_ref = 'PHP-12345';
$first_name = 'John';
$last_name = 'Smith';
$address_1 = "123 Fake St";
$town = "London";
$postcode = "se3 3ed";
$country = "United Kingdom";
$account_name = "John Smith";
$sort_code = "40-12-23";
$account_number = "12345678";
$regular_amount = 1000;
$frequency_type = "M";

// urlencode and concatenate the POST arguments
$postargs = 'variable_ddi[service_user][pslid]=' . $pslid;
$postargs .= '&variable_ddi[reference_number]=' . urlencode($reference_number);
$postargs .= '&variable_ddi[payer_reference]=' . urlencode($payer_ref);
$postargs .= '&variable_ddi[first_name]=' . urlencode($first_name);
$postargs .= '&variable_ddi[last_name]=' . urlencode($last_name);
$postargs .= '&variable_ddi[address_1]=' . urlencode($address_1);
$postargs .= '&variable_ddi[town]=' . urlencode($town);
$postargs .= '&variable_ddi[postcode]=' . urlencode($postcode);
$postargs .= '&variable_ddi[country]=' . urlencode($country);
$postargs .= '&variable_ddi[account_name]=' . urlencode($account_name);
$postargs .= '&variable_ddi[sort_code]=' . urlencode($sort_code);
$postargs .= '&variable_ddi[account_number]=' . urlencode($account_number);
$postargs .= '&variable_ddi[regular_amount]=' . urlencode($regular_amount);
$postargs .= '&variable_ddi[frequency_type]=' . urlencode($frequency_type);

$options = array(
        CURLOPT_RETURNTRANSFER => false,         // return web page 
        CURLOPT_HEADER         => false,        // don't return headers 
        CURLOPT_FOLLOWLOCATION => false,         // follow redirects 
        CURLOPT_ENCODING       => "",           // handle all encodings 
        CURLOPT_USERAGENT      => "CiviCRM Veda",     // who am i 
        CURLOPT_AUTOREFERER    => false,         // set referer on redirect 
        CURLOPT_CONNECTTIMEOUT => 120,          // timeout on connect 
        CURLOPT_TIMEOUT        => 120,          // timeout on response 
        CURLOPT_MAXREDIRS      => 10,           // stop after 10 redirects 
        CURLOPT_POST            => 1,            // i am sending post data 
        CURLOPT_POSTFIELDS     => $postargs,    // this are my post vars 
        CURLOPT_SSL_VERIFYHOST => 0,            // don't verify ssl 
        CURLOPT_SSL_VERIFYPEER => false,        // 
        CURLOPT_VERBOSE        => 1,                //     
        CURLOPT_HTTPAUTH => CURLAUTH_ANY,    
        CURLOPT_USERPWD => $user . ":" . $password,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array("Accept: application/xml"),
);

$options = array(
        CURLOPT_RETURNTRANSFER => true, // return web page
        CURLOPT_HEADER => false, // don't return headers
        CURLOPT_POST => true,
        CURLOPT_USERPWD => $user . ":" . $password,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_HTTPHEADER => array("Accept: application/xml"),
        CURLOPT_USERAGENT => "XYZ Co's PHP iDD Client", // Let SmartDebit see who we are
        CURLOPT_SSL_VERIFYHOST => false,            // don't verify ssl 
        CURLOPT_SSL_VERIFYPEER => false,        // 
        CURLOPT_POSTFIELDS     => $postargs,
        CURLOPT_VERBOSE        => 1,
);

$session = curl_init( $request_host . $request_path );
curl_setopt_array( $session, $options );

echo "**************<br>";
echo base64_encode ( "vedaapi:RUBgvr*R")."<br>";
echo "**************<br>";

$output = curl_exec($session);
$header = curl_getinfo( $session );
// close curl resource to free up system resources
if(curl_errno($session)) {
  echo 'Curl error: ' . curl_error($session);
}
else {
  switch ($header["http_code"]) {
     case 200:
       echo "Variable DDI created";
       break;
     default:
       echo "HTTP Error: " . $header["http_code"];
}
curl_close($session);
}
?>