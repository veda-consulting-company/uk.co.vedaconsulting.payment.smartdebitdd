<?php

require_once 'UK_Direct_Debit/Form/Main.php';
require_once 'CRM/Core/Payment.php';
include("smart_debit_includes.php");

/* @todo Calculate Collection Date
 * @todo Need to Store the SUN somewhere
 * @todo
 *
 *
 */


class uk_co_vedaconsulting_payment_smartdebitdd extends CRM_Core_Payment {

  /**
   * We only need one instance of this object. So we use the singleton
   * pattern and cache the instance in this variable
   *
   * @var object
   * @static
   */
  static private $_singleton = null;

  /**
   * mode of operation: live or test
   *
   * @var object
   * @static
   */
  static protected $_mode = null;

  /**
   * Constructor
   *
   * @param string $mode the mode of operation: live or test
   * @param $paymentProcessor
   */
  function __construct( $mode, &$paymentProcessor ) {
    $this->_mode             = $mode;
    $this->_paymentProcessor = $paymentProcessor;
    $this->_processorName    = ts('Smart Debit Processor');
  }

  /**
   * singleton function used to manage this object
   *
   * @param string $mode the mode of operation: live or test
   * @param $paymentProcessor
   * @return object
   * @static
   *
   */
  static function &singleton( $mode, &$paymentProcessor ) {
      $processorName = $paymentProcessor['name'];
      if (self::$_singleton[$processorName] === null ) {
          self::$_singleton[$processorName] = new self( $mode, $paymentProcessor );
      }
      return self::$_singleton[$processorName];
  }

  /**
   * This function checks to see if we have the right config values
   *
   * @return string the error message if any
   * @public
   */
  function checkConfig() {
    $config = CRM_Core_Config::singleton();
    $error  = array();

    if ( empty( $this->_paymentProcessor['user_name'] ) ) {
      $error[] = ts( 'The "Bill To ID" is not set in the Administer CiviCRM Payment Processor.' );
    }

    /* TO DO
     * Add check to ensure password is also set
     * Also the URL's for api site
     */

    if ( !empty( $error ) ) {
      return implode( '<p>', $error );
    }
    else {
      return NULL;
    }
  }

  function getUserEmail( &$params ) {
    // Set email
    if ( !empty( $params['email-Primary'] ) ) {
      $useremail = $params['email-Primary'];
    } else {
      $useremail = $params['email-5'];
    }
    return $useremail;
  }

  protected function getCRMVersion() {
    $crmversion = explode( '.'
                         , ereg_replace( '[^0-9\.]', '', CRM_Utils_System::version() ) );
    return floatval( $crmversion[0] . '.' . $crmversion[1] );
  }

  /*
   * From the selected collection day determine when the actual collection start date could be
   * For direct debit we need to allow 10 working days prior to collection for cooling off
   * We also may need to send them a letter etc
   *
   */
  function getCollectionStartDate( &$params ) {

    $preferredCollectionDay = $params['preferred_collection_day'];

    return UK_Direct_Debit_Form_Main::firstCollectionDate( $preferredCollectionDay, null );
  }

  /*
   * Determine the frequency based on the recurring params if set
   * Should check the [frequency_unit] and if set use that
   * If not set then default to D
   */
  function getCollectionFrequency( &$params ) {
    $frequencyUnit = $params['frequency_unit'];

    if ( strtolower( $frequencyUnit ) == 'year' ) {
      $collectionFrequency = 'Y';
    }
    else {
      $collectionFrequency = 'M';
    }

    return $collectionFrequency;
  }

  function preparePostArray( $fields, $self = null ) {

    /*
     * TO DO
     * Promotion - Need to get the page ID
     */

    $collectionDate      = self::getCollectionStartDate( $fields );
    $amount              = 0;
    $serviceUserId       = null;
    if ( isset( $fields['amount'] ) ) {

        // Set amount in pence if not already set that way.
        $amount = $fields['amount'];

        // $amount might be a string (?) e.g. £12.00, so try just in case
        try {
            $amount = $amount * 100;
        } catch ( Exception $e ) {
            //Leave amount as it was
            $amount = $fields['amount'];
        }

    }

    if ( isset( $self->_paymentProcessor['signature'] ) ) {
        $serviceUserId = $self->_paymentProcessor['signature'];
    }

    if ( isset( $fields['contactID'] ) ) {
        $payerReference = $fields['contactID'];
    }
    else {
        $payerReference = 'CIVICRMEXT';
    }

    // Construct params list to send to Smart Debit ...
    $smartDebitParams = array(
//      'variable_ddi[service_user][pslid]' => $self->_paymentProcessor['signature'],
      'variable_ddi[service_user][pslid]' => $serviceUserId,
      'variable_ddi[reference_number]'    => $fields['ddi_reference'],
      'variable_ddi[payer_reference]'     => $payerReference,
      'variable_ddi[first_name]'          => $fields['billing_first_name'],
      'variable_ddi[last_name]'           => $fields['billing_last_name'],
      'variable_ddi[address_1]'           => $fields['billing_street_address-5'],
      'variable_ddi[town]'                => $fields['billing_city-5'],
      'variable_ddi[postcode]'            => $fields['billing_postal_code-5'],
      'variable_ddi[country]'             => $fields['billing_country_id-5'], //*** $params['billing_country-5']
      'variable_ddi[account_name]'        => $fields['account_holder'],
      'variable_ddi[sort_code]'           => $fields['bank_identification_number'],
      'variable_ddi[account_number]'      => $fields['bank_account_number'],
      'variable_ddi[regular_amount]'      => $amount,
      'variable_ddi[first_amount]'        => $amount,
      'variable_ddi[default_amount]'      => $amount,
      'variable_ddi[start_date]'          => $collectionDate->format("Y-m-d"),
//      'variable_ddi[promotion]'           => $fields['page_id'], //*** contributionPageID
      'variable_ddi[email_address]'       => self::getUserEmail( $fields ),
      'variable_ddi[company_name]'        => UK_Direct_Debit_Form_Main::getDomainName(),
      'variable_ddi[frequency_type]'      => self::getCollectionFrequency( $fields )
    );

    return $smartDebitParams;
  }

  function test_validatePayment( &$params ) {
      self::getMandateDetails( 'ABC123' );
  }

  /**
   * Sets appropriate parameters and calls Sage Pay Direct Payment Processor Version 2.23
   *
   * @param array $params  name value pair of contribution data
   *
   * @return array $result
   * @access public
   *
   */

  function validatePayment( $fields, $files, $self ) {

    $validateParams = $fields;
//    $validateParams['bank_account_number'] = null;

    /* First thing to do is check if the DD has already been submitted */
    if ( UK_Direct_Debit_Form_Main::isDDSubmissionComplete($fields['ddi_reference'] ) ) {
        $response[] = "PreviouslySubmitted";
        return self::invalid( $response, $validateParams );
    }

    $smartDebitParams = self::preparePostArray( $validateParams, $self );

    // Construct post string
    $post = '';
    foreach ( $smartDebitParams as $key => $value ) {
      $post .= ( $key != 'variable_ddi[service_user][pslid]' ? '&' : '' ) . $key . '=' . urlencode( $value );
    }

    // Get the API Username and Password
    $username = $self->_paymentProcessor['user_name'];
    $password = $self->_paymentProcessor['password'];

    // Send payment POST to the target URL
    $url            = $self->_paymentProcessor['url_api'];
    $request_path   = 'api/ddi/variable/validate';

    $response = requestPost( $url, $post, $username, $password, $request_path );

    $direct_debit_response = array();
    $direct_debit_response['data_type']                = 'recurring';
    $direct_debit_response['entity_type']              = 'contribution_recur';
    $direct_debit_response['first_collection_date']    = $smartDebitParams['variable_ddi[start_date]'];
    $direct_debit_response['preferred_collection_day'] = $fields['preferred_collection_day'];
    $direct_debit_response['confirmation_method']      = $fields['confirmation_method'];
    $direct_debit_response['ddi_reference']            = $fields['ddi_reference'];
    $direct_debit_response['response_status']          = $response['Status'];
    $direct_debit_response['response_raw']             = null;
    $direct_debit_response['entity_id']                = null;
    $direct_debit_response['bank_name']                = null;
    $direct_debit_response['branch']                   = null;
    $direct_debit_response['address1']                 = null;
    $direct_debit_response['address2']                 = null;
    $direct_debit_response['address3']                 = null;
    $direct_debit_response['address4']                 = null;
    $direct_debit_response['town']                     = null;
    $direct_debit_response['county']                   = null;
    $direct_debit_response['postcode']                 = null;

    if ( !empty( $response['error'] ) ) {
        $direct_debit_response['response_raw'] = $response['error'];
    }

    // Take action based upon the response status
    switch ( strtoupper( $response["Status"] ) ) {
        case 'OK':
            $direct_debit_response['entity_id']   = isset( $fields['entity_id'] ) ? $fields['entity_id'] : 0;
            $direct_debit_response['bank_name']   = $response['success'][2]["@attributes"]["bank_name"];
            $direct_debit_response['branch']      = $response['success'][2]["@attributes"]["branch"];
            $direct_debit_response['address1']    = $response['success'][2]["@attributes"]["address1"];
            $direct_debit_response['address2']    = $response['success'][2]["@attributes"]["address2"];
            $direct_debit_response['address3']    = $response['success'][2]["@attributes"]["address3"];
            $direct_debit_response['address4']    = $response['success'][2]["@attributes"]["address4"];
            $direct_debit_response['town']        = $response['success'][2]["@attributes"]["town"];
            $direct_debit_response['county']      = $response['success'][2]["@attributes"]["county"];
            $direct_debit_response['postcode']    = $response['success'][2]["@attributes"]["postcode"];

            UK_Direct_Debit_Form_Main::record_response( $direct_debit_response );
            return self::validate_succeed( $response, $fields );
        case 'REJECTED':
            UK_Direct_Debit_Form_Main::record_response( $direct_debit_response );
            $_SESSION['contribution_attempt'] = 'failed';
            return self::rejected( $response, $fields );
        case 'INVALID':
            UK_Direct_Debit_Form_Main::record_response( $direct_debit_response );
            $_SESSION['contribution_attempt'] = 'failed';
            return self::invalid( $response, $fields );
        default:
            UK_Direct_Debit_Form_Main::record_response( $direct_debit_response );
            $_SESSION['contribution_attempt'] = 'failed';
            return self::error( $response, $fields );
    }
  }

  /**
   * Sets appropriate parameters and calls Sage Pay Direct Payment Processor Version 2.23
   *
   * @param array $params  name value pair of contribution data
   *
   * @return array $result
   * @access public
   *
   */
  function doDirectPayment( &$params ) {
    $validateParams   = $params;
    $smartDebitParams = self::preparePostArray( $validateParams );
    $serviceUserId    = $this->_paymentProcessor['signature'];

    // Construct post string
    $post = '';
    foreach ( $smartDebitParams as $key => $value ) {
      $post .= ( $key != 'variable_ddi[service_user][pslid]' ? '&' : '' ) . $key . '=' . ( $key != 'variable_ddi[service_user][pslid]' ? urlencode( $value ) : $serviceUserId );
   //  $post .= ($key != 'variable_ddi[service_user][pslid]' ? '&' : '') . $key . '=' . urlencode($value);
    }
    // Get the API Username and Password
    $username = $this->_paymentProcessor['user_name'];
    $password = $this->_paymentProcessor['password'];

    // Send payment POST to the target URL
    $url            = $this->_paymentProcessor['url_api'];
    $request_path   = 'api/ddi/variable/create';

    $response = requestPost( $url, $post, $username, $password, $request_path );
    $response['reference_number'] = $smartDebitParams['variable_ddi[reference_number]'];

    // Take action based upon the response status
    switch ( strtoupper( $response["Status"] ) ) {
        case 'OK':
            return self::succeed( $response, $params );
        case 'REJECTED':
            $_SESSION['contribution_attempt'] = 'failed';
            return self::rejected( $response, $params );
        case 'INVALID':
            $_SESSION['contribution_attempt'] = 'failed';
            return self::invalid( $response, $params );
        default:
            $_SESSION['contribution_attempt'] = 'failed';
            return self::error( $response, $params );
    }
  }

  /**
   * SagePay payment has succeeded
   * @param $response
   * @return array
   */
  private function validate_succeed( $response, &$params ) {

    // Clear any old error messages from stack
    drupal_get_messages();

    $response['trxn_id'] = $params['ddi_reference'];
 //   return $response;
    return true;
  }
 /*
  static function validatePayment($fields, $files, $self) {
    $errors = array( );
    if (empty($fields['account_holder'])) {
      $errors['account_holder'] = ts('This field cannot be empty');
    }

    if (($fields['account_holder']  && $fields['bank_account_number']) != 10) {
      $errors['bank_account_number'] = ts('The magic number does ot match');
    }

    return empty($errors) ? TRUE : $errors;
  }
*/


  /**
   * SagePay payment has succeeded
   * @param $response
   * @return array
   */
  private function succeed( $response, &$params ) {
CRM_Core_Error::debug_log_message('UK_Direct_Debit_Form_Main.succeed params[contributionID]=' .$params['contributionID']);

    // Clear any old error messages from stack
    drupal_get_messages();

    $response['trxn_id'] = $response['reference_number'];
CRM_Core_Error::debug_log_message('UK_Direct_Debit_Form_Main.succeed response[reference_number]=' .$response['reference_number']);

    //Moved this to IPN
    //UK_Direct_Debit_Form_Main::completeDirectDebitSetup( $response, $params );

    return $response;
  }
  /**
   * SagePay payment has failed
   * @param $response
   * @param $params
   * @return array
   */
  private function invalid( $response, $params ) {
    $msg = "Unfortunately, it seems the details provided are invalid – please double check your billing address and direct debit details and try again.";
    $msg .= "<ul>";

    foreach( $response as $key => $value ):
        if ( is_array( $value ) ) {
            foreach( $value as $errorItem ):
                $msg .= "<li>";
                $msg .= $errorItem;
                $msg .= "</li>";
            endforeach;
        }
        else {
            if ($key == 'error') {
                $msg .= "<li>";
                $msg .= $value;
                $msg .= "</li>";
            }
        }
    endforeach;
    $msg .= "</ul>";
    drupal_set_message( $msg, 'error', false );
    watchdog( 'CiviCRM DD Error', $_SESSION["rawresponse"] );
//    self::createFailedContribution($response, $params); //SRH createFailedContribution errors because $this->_paymentForm doesn't exist
    return CRM_Core_Error::createAPIError( $msg, $response );
  }

  /**
   * SagePay payment has returned a status we do not understand
   * @param $response
   * @param $params
   * @return array
   */
  private function error( $response, $params ) {
    $msg = "Unfortunately, it seems there was a problem with your direct debit details – please double check your billing address and card details and try again";
    drupal_set_message( $msg, 'error', false );
    watchdog( 'SmartDebit', $response["StatusDetail"], $response, WATCHDOG_ERROR );
//    self::createFailedContribution($response, $params); //SRH createFailedContribution errors because $this->_paymentForm doesn't exist
    return CRM_Core_Error::createAPIError( $msg, $response );
  }

  /**
   * SagePay payment has failed
   * @param $response
   * @param $params
   * @return array
   */
  private function rejected( $response, $params ) {
    $msg = "Unfortunately, it seems the authorisation was a rejected – please double check your billing address and card details and try again.";
    drupal_set_message( $msg, 'error', false );
    watchdog( 'SmartDebit', $response["StatusDetail"], $response, WATCHDOG_ERROR );
    return CRM_Core_Error::createAPIError( $msg, $response );
  }

  /**
   * Create a contribution record for CC transactions that fail.
   *
   * @param $response
   * @param $params
   */
  private function createFailedContribution( &$response, &$params ) {
    // Set value to 0 so that CRM/Event/Registration/Confirm->postProcess()
    // does not later also create a Contribution and Transaction
    $response['amount'] = 0;

    // Retrieve or create a Contact object
    require_once 'api/api.php';
    $defaults                 = $params;
    $defaults['version']      = 3;
    $defaults['contact_type'] = 'Individual';
    if ( $params['contact_id'] ) {
      $contact = civicrm_api( 'Contact', 'Get', array( 'id' => $params['contact_id'], 'version' => 3 ) );
    } else {
      $contact              = civicrm_api( 'Contact', 'Create', $defaults );
      $params['contact_id'] = $contact['id'];
    }

    $contribution_values = array(
                                 'contact_id'             => $contact['id'],
                                 'contribution_status_id' => 4,
                                 'cancel_reason'          => $response['StatusDetail'],
                                 'cancel_date'            => CRM_Utils_Date::getToday(),
                                 'version'                => 3
                                );

    // Add event data if this is an event payment
    if ( $this->_paymentForm && $this->_paymentForm->_values['event'] ) {
      $contribution_values['contribution_type_id'] = $this->_paymentForm->_values['event']['contribution_type_id'];
      $contribution_values['campaign_id']          = $this->_paymentForm->_values['event']['campaign_id'];
      $contribution_values['source']               = $this->_paymentForm->_values['event']['title'];
    }

    // Create the contribution. We don't need to do anything with it, but it's here for inspection if required.
    $contribution = civicrm_api( 'Contribution', 'Create', $contribution_values );

  }

  /**
   * Sets appropriate parameters for checking out to UCM Payment Collection
   *
   * @param array $params  name value pair of contribution datat
   * @param $component
   * @access public
   *
   */
  function doTransferCheckout( &$params, $component ) {
    CRM_Core_Error::fatal( ts( 'This function is not implemented' ) );
  }

/*
  function buildForm(&$form) {
    require_once 'UK_Direct_Debit/Form/Main.php';
    UK_Direct_Debit_Form_Main::buildDirectDebitForm($form);
  }
*/

  function buildForm( &$form ) {
    UK_Direct_Debit_Form_Main::buildDirectDebit( $form );

    $form->addFormRule( array( 'uk_co_vedaconsulting_payment_smartdebitdd', 'validatePayment' ), $form );
    if (self::getCRMVersion() >= 4.2) {
        CRM_Core_Region::instance('billing-block')->update( 'default', array( 'disabled' => TRUE ) );
        CRM_Core_Region::instance('billing-block')->add( array( 'template' => 'CRM/Core/MyPayPalBlock.tpl',
                                                                'weight'   => -1
                                                               )
                                                       );
    }
  }

  public function handlePaymentNotification() {
CRM_Core_Error::debug_log_message( 'uk_co_vedaconsulting_payment_smartdebitdd handlePaymentNotification' );
CRM_Core_Error::debug_log_message( '$_GET[]:'  . print_r( $_GET, true ) );
CRM_Core_Error::debug_log_message( '$_POST[]:' . print_r( $_POST, true ) );

    CRM_Core_Error::debug( 'Smart Debit handlePaymentNotification');

    require_once 'CRM/Utils/Array.php';
    require_once 'CRM/Core/Payment/SmartDebitIPN.php';

    $module = CRM_Utils_Array::value( 'module', $_GET );
    if ( empty( $_GET ) ) {
        $rpInvoiceArray = array();
        $rpInvoiceArray = explode( '&' , $_POST['rp_invoice_id'] );
        foreach ( $rpInvoiceArray as $rpInvoiceValue ) {
            $rpValueArray = explode ( '=' , $rpInvoiceValue );
            if ( $rpValueArray[0] == 'm' ) {
                $value = $rpValueArray[1];
            }
        }
        CRM_Core_Error::debug_log_message('uk_co_vedaconsulting_payment_smartdebitdd handlePaymentNotification #2');

        $SmartDebitIPN = new CRM_Core_Payment_SmartDebitIPN();
    } else {
        CRM_Core_Error::debug_log_message('uk_co_vedaconsulting_payment_smartdebitdd handlePaymentNotification #3');
        $value         = CRM_Utils_Array::value( 'module', $_GET );
        $SmartDebitIPN = new CRM_Core_Payment_SmartDebitIPN();
    }
    CRM_Core_Error::debug_log_message('uk_co_vedaconsulting_payment_smartdebitdd handlePaymentNotification value='.$value);

    switch ( strtolower( $value ) ) {
        case 'contribute':
            $SmartDebitIPN->main( 'contribute' );
            break;
        case 'event':
            $SmartDebitIPN->main( 'event' );
            break;
        default     :
            require_once 'CRM/Core/Error.php';
            CRM_Core_Error::debug_log_message( "Could not get module name from request url" );
            echo "Could not get module name from request url<p>";
            break;
    }
  }

  /**
   * Sets appropriate parameters and calls Sage Pay Direct Payment Processor Version 2.23
   *
   * @mandateID is the mandate reference for Smart Debit
   *
   * @return array $result
   * @access public
   *
   */
  function getMandateDetails( $mandateID ) {

    // Construct post string
    $get = '';

    // Get the API Username and Password
    $username = $this->_paymentProcessor['user_name'];
    $password = $this->_paymentProcessor['password'];

    // Send payment POST to the target URL
    $url          = $this->_paymentProcessor['url_api'];
    $request_path = 'api/ddi/variable/ABC123456';
    $response     = requestPost( $url, $post, $username, $password, $request_path );

    // Take action based upon the response status
    switch ( strtoupper( $response["Status"] ) ) {
        case 'OK':
            /* TO DO This needs fixing, not sure how to get the information from the validate call back to the main TPLs so this is my hack */
            $_SESSION['uk_direct_debit']['bank_name']                 = $response['success'][2]["@attributes"]["bank_name"];
            $_SESSION['uk_direct_debit']['branch']                    = $response['success'][2]["@attributes"]["branch"];
            $_SESSION['uk_direct_debit']['address1']                  = $response['success'][2]["@attributes"]["address1"];
            $_SESSION['uk_direct_debit']['address2']                  = $response['success'][2]["@attributes"]["address2"];
            $_SESSION['uk_direct_debit']['address3']                  = $response['success'][2]["@attributes"]["address3"];
            $_SESSION['uk_direct_debit']['address4']                  = $response['success'][2]["@attributes"]["address4"];
            $_SESSION['uk_direct_debit']['town']                      = $response['success'][2]["@attributes"]["town"];
            $_SESSION['uk_direct_debit']['county']                    = $response['success'][2]["@attributes"]["county"];
            $_SESSION['uk_direct_debit']['postcode']                  = $response['success'][2]["@attributes"]["postcode"];
            $_SESSION['uk_direct_debit']['first_collection_date']     = $smartDebitParams['variable_ddi[start_date]'];
            $_SESSION['uk_direct_debit']['preferred_collection_day']  = $params['preferred_collection_day'];
            $_SESSION['uk_direct_debit']['confirmation_method']       = $params['confirmation_method'];
            return self::validate_succeed($response, $params);
        case 'REJECTED':
            $_SESSION['contribution_attempt'] = 'failed';
            return self::rejected($response, $params);
        case 'INVALID':
            $_SESSION['contribution_attempt'] = 'failed';
            return self::invalid($response, $params);
        default:
            $_SESSION['contribution_attempt'] = 'failed';
            return self::error($response, $params);
    }
  }

}
