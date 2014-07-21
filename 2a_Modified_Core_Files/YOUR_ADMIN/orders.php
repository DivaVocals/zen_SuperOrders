<?php
/**
 * @package admin
//////////////////////////////////////////////////////////////////////////
//  Based on Super Order 2.0                                        	//
//  By Frank Koehl - PM: BlindSide (original author)                	//
//                                                                  	//
//  Super Orders Updated by:						//
//  ~ JT of GTICustom							//
//  ~ C Jones Over the Hill Web Consulting (http://overthehillweb.com)	//
//  ~ Loose Chicken Software Development, david@loosechicken.com	//
//////////////////////////////////////////////////////////////////////////
// DESCRIPTION:   Enhanced admin/orders.php. Features include:		//
//  ~ Improved navigation options					//
//  ~ An advanced payment management system.				//
//  ~ EZ Integration with Ty Package Tracker and Edit Orders		//
//  ~ Admin comment editing						//
//  ~ Improved HTML and look & feel					//
//////////////////////////////////////////////////////////////////////////
 * @copyright Copyright 2003-2011 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: orders.php 19530 2011-09-19 13:52:37Z ajeh $
*/

  require('includes/application_top.php');
  
  require(DIR_WS_CLASSES . 'currencies.php');
  $currencies = new currencies();
   
  if (isset($_GET['oID'])) $_GET['oID'] = (int)$_GET['oID'];
  if (isset($_GET['download_reset_on'])) $_GET['download_reset_on'] = (int)$_GET['download_reset_on'];
  if (isset($_GET['download_reset_off'])) $_GET['download_reset_off'] = (int)$_GET['download_reset_off'];

  include(DIR_WS_CLASSES . 'order.php');
  $order = new order ($oID);
  // prepare order-status pulldown list
  $orders_statuses = array();
  $orders_status_array = array();
  $orders_status = $db->Execute("select orders_status_id, orders_status_name
                                 from " . TABLE_ORDERS_STATUS . "
                                 where language_id = '" . (int)$_SESSION['languages_id'] . "' order by orders_status_id");
  while (!$orders_status->EOF) {
    $orders_statuses[] = array('id' => $orders_status->fields['orders_status_id'],
                               'text' => $orders_status->fields['orders_status_name'] . ' [' . $orders_status->fields['orders_status_id'] . ']');
    $orders_status_array[$orders_status->fields['orders_status_id']] = $orders_status->fields['orders_status_name'];
    $orders_status->MoveNext();
  }
  $action = (isset($_GET['action']) ? $_GET['action'] : '');
  $order_exists = false;
  if (isset($_GET['oID']) && trim($_GET['oID']) == '') unset($_GET['oID']);
  if ($action == 'edit' && !isset($_GET['oID'])) $action = '';

  $oID = FALSE;
  if (isset($_POST['oID'])) {
    $oID = zen_db_prepare_input(trim($_POST['oID']));
  } elseif (isset($_GET['oID'])) {
    $oID = zen_db_prepare_input(trim($_GET['oID']));
  }
  if ($oID) {
    $orders = $db->Execute("select orders_id from " . TABLE_ORDERS . "
                            where orders_id = '" . (int)$oID . "'");
    $order_exists = true;
    if ($orders->RecordCount() <= 0) {
      $order_exists = false;
      if ($action != '') $messageStack->add_session(ERROR_ORDER_DOES_NOT_EXIST . ' ' . $oID, 'error');
      zen_redirect(zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('oID', 'action')), 'NONSSL'));
    }
  }

    if ($oID) {
    require_once(DIR_WS_CLASSES . 'super_order.php');
    $so = new super_order($oID);
  }
  
  if (zen_not_null($action) && $order_exists == true) {
    switch ($action) {
      case 'mark_completed':
        $so->mark_completed();
        $messageStack->add_session(sprintf(SUCCESS_MARK_COMPLETED, $oID), 'success');
        zen_redirect(zen_href_link(FILENAME_ORDERS, 'action=edit&oID=' . $oID, 'NONSSL'));
      break;
      case 'mark_cancelled':
        $so->mark_cancelled();
        $messageStack->add_session(sprintf(WARNING_MARK_CANCELLED, $oID), 'warning');
        zen_redirect(zen_href_link(FILENAME_ORDERS, 'action=edit&oID=' . $oID, 'NONSSL'));
      break;
      case 'reopen':
        $so->reopen();
        $messageStack->add_session(sprintf(WARNING_ORDER_REOPEN, $oID), 'warning');
        zen_redirect(zen_href_link(FILENAME_ORDERS, 'action=edit&oID=' . $oID, 'NONSSL'));
      break;
      case 'edit':
        // reset single download to on
        if ($_GET['download_reset_on'] > 0) {
          // adjust download_maxdays based on current date
          $check_status = $db->Execute("select customers_name, customers_email_address, orders_status, 
				      date_purchased from " . TABLE_ORDERS . "
                                      where orders_id = '" . $_GET['oID'] . "'");

          // check for existing product attribute download days and max
          $chk_products_download_query = "SELECT orders_products_id, orders_products_filename, products_prid from " . TABLE_ORDERS_PRODUCTS_DOWNLOAD . " WHERE orders_products_download_id='" . $_GET['download_reset_on'] . "'";
          $chk_products_download = $db->Execute($chk_products_download_query);

          $chk_products_download_time_query = "SELECT pa.products_attributes_id, pa.products_id, pad.products_attributes_filename, pad.products_attributes_maxdays, pad.products_attributes_maxcount
          from " . TABLE_PRODUCTS_ATTRIBUTES . " pa, " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
          WHERE pa.products_attributes_id = pad.products_attributes_id
          and pad.products_attributes_filename = '" . $chk_products_download->fields['orders_products_filename'] . "'
          and pa.products_id = '" . (int)$chk_products_download->fields['products_prid'] . "'";

          $chk_products_download_time = $db->Execute($chk_products_download_time_query);

          if ($chk_products_download_time->EOF) {
            $zc_max_days = (DOWNLOAD_MAX_DAYS == 0 ? 0 : zen_date_diff($check_status->fields['date_purchased'], date('Y-m-d H:i:s', time())) + DOWNLOAD_MAX_DAYS);
            $update_downloads_query = "update " . TABLE_ORDERS_PRODUCTS_DOWNLOAD . " set download_maxdays='" . $zc_max_days . "', download_count='" . DOWNLOAD_MAX_COUNT . "' where orders_id='" . $_GET['oID'] . "' and orders_products_download_id='" . $_GET['download_reset_on'] . "'";
          } else {
            $zc_max_days = ($chk_products_download_time->fields['products_attributes_maxdays'] == 0 ? 0 : zen_date_diff($check_status->fields['date_purchased'], date('Y-m-d H:i:s', time())) + $chk_products_download_time->fields['products_attributes_maxdays']);
            $update_downloads_query = "update " . TABLE_ORDERS_PRODUCTS_DOWNLOAD . " set download_maxdays='" . $zc_max_days . "', download_count='" . $chk_products_download_time->fields['products_attributes_maxcount'] . "' where orders_id='" . $_GET['oID'] . "' and orders_products_download_id='" . $_GET['download_reset_on'] . "'";
          }

          $db->Execute($update_downloads_query);
          unset($_GET['download_reset_on']);
          $messageStack->add_session(SUCCESS_ORDER_UPDATED_DOWNLOAD_ON, 'success');
          zen_redirect(zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=edit', 'NONSSL'));
        }
        // reset single download to off
        if ($_GET['download_reset_off'] > 0) {
          // adjust download_maxdays based on current date
          // *** fix: adjust count not maxdays to cancel download
//          $update_downloads_query = "update " . TABLE_ORDERS_PRODUCTS_DOWNLOAD . " set download_maxdays='0', download_count='0' where orders_id='" . $_GET['oID'] . "' and orders_products_download_id='" . $_GET['download_reset_off'] . "'";
          $update_downloads_query = "update " . TABLE_ORDERS_PRODUCTS_DOWNLOAD . " set download_count='0' where orders_id='" . $_GET['oID'] . "' and orders_products_download_id='" . $_GET['download_reset_off'] . "'";
          $db->Execute($update_downloads_query);
          unset($_GET['download_reset_off']);

          $messageStack->add_session(SUCCESS_ORDER_UPDATED_DOWNLOAD_OFF, 'success');
          zen_redirect(zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=edit', 'NONSSL'));
        }
      break;
      case 'update_order':
        // demo active test
        if (zen_admin_demo()) {
          $_GET['action']= '';
          $messageStack->add_session(ERROR_ADMIN_DEMO, 'caution');
          zen_redirect(zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=edit', 'NONSSL'));
        }
// BEGIN TY TRACKER/SUPER ORDERS INTEGRATION  ----------------------------------
if (TY_TRACKER == 'True') {
        $oID = zen_db_prepare_input($_GET['oID']);
        $comments = zen_db_prepare_input($_POST['comments']);
        $status = (int)zen_db_prepare_input($_POST['status']);
        if ($status < 1) break;
// BEGIN TY TRACKER 1 - DEFINE TRACKING VALUES, INCLUDE DATABASE FIELDS IN STATUS QUERY, & E-MAIL TRACKING INFORMATION  ----------------------------------------------
		$check_extra_fields = '';
		$track_id = array();
			$track_id = zen_db_prepare_input($_POST['track_id']);

		$check_status = $db->Execute(
			'SELECT customers_name, customers_email_address, orders_status,' . $check_extra_fields .
			'date_purchased FROM `' . TABLE_ORDERS . '` WHERE orders_id = \'' . (int)$oID . '\''
		);
		unset($check_extra_fields);

		if(($check_status->fields['orders_status'] != $status) || zen_not_null($track_id)) {
			$customer_notified = '0';
			if(isset($_POST['notify']) && ($_POST['notify'] == '1')) {

				$notify_comments = '';
				if (isset($_POST['notify_comments']) && ($_POST['notify_comments'] == 'on')) {
					if (zen_not_null($comments)) {
						$notify_comments = EMAIL_TEXT_COMMENTS_UPDATE . $comments . "\n\n";
					}
					else if (zen_not_null($track_id)) {
						$notify_comments = EMAIL_TEXT_COMMENTS_TRACKING_UPDATE . "\n\n";
						$comment = EMAIL_TEXT_COMMENTS_TRACKING_UPDATE;
					}
					foreach($track_id as $id => $track) {
						if(zen_not_null($track) && constant('CARRIER_STATUS_' . $id) == 'True') {
							$notify_comments .= "Your " . constant('CARRIER_NAME_' . $id) . " Tracking ID is " . $track . " \n<br /><a href=" . constant('CARRIER_LINK_' . $id) . $track . ">Click here</a> to track your package. \n<br />If the above link does not work, copy the following URL address and paste it into your Web browser. \n<br />" . constant('CARRIER_LINK_' . $id) . $track . "\n\n<br /><br />It may take up to 24 hours for the tracking information to appear on the website." . "\n<br />";
						}
					}
					unset($id); unset($track);
				}
// END TY TRACKER 1 - DEFINE TRACKING VALUES, INCLUDE DATABASE FIELDS IN STATUS QUERY, & E-MAIL TRACKING INFORMATION  ----------------------------------------------
            //send emails
            $message =
//<!-- Begin Ty Package Tracker Modification (Minor formatting change) //-->
      	    STORE_NAME . " " . EMAIL_TEXT_ORDER_NUMBER . ' ' . $oID . "\n\n" .
//<!-- End Ty Package Tracker Modification (Minor formatting change) //-->
            EMAIL_TEXT_INVOICE_URL . ' ' . zen_catalog_href_link(FILENAME_CATALOG_ACCOUNT_HISTORY_INFO, 'order_id=' . $oID, 'SSL') . "\n\n" .
            EMAIL_TEXT_DATE_ORDERED . ' ' . zen_date_long($check_status->fields['date_purchased']) . "\n\n" .
            strip_tags($notify_comments) .
            EMAIL_TEXT_STATUS_UPDATED . sprintf(EMAIL_TEXT_STATUS_LABEL, $orders_status_array[$status] ) .
            EMAIL_TEXT_STATUS_PLEASE_REPLY;

            $html_msg['EMAIL_CUSTOMERS_NAME']    = $check_status->fields['customers_name'];
            $html_msg['EMAIL_TEXT_ORDER_NUMBER'] = EMAIL_TEXT_ORDER_NUMBER . ' ' . $oID;
            $html_msg['EMAIL_TEXT_INVOICE_URL']  = '<a href="' . zen_catalog_href_link(FILENAME_CATALOG_ACCOUNT_HISTORY_INFO, 'order_id=' . $oID, 'SSL') .'">'.str_replace(':','',EMAIL_TEXT_INVOICE_URL).'</a>';
            $html_msg['EMAIL_TEXT_DATE_ORDERED'] = EMAIL_TEXT_DATE_ORDERED . ' ' . zen_date_long($check_status->fields['date_purchased']);
            $html_msg['EMAIL_TEXT_STATUS_COMMENTS'] = nl2br($notify_comments);
            $html_msg['EMAIL_TEXT_STATUS_UPDATED'] = str_replace('\n','', EMAIL_TEXT_STATUS_UPDATED);
            $html_msg['EMAIL_TEXT_STATUS_LABEL'] = str_replace('\n','', sprintf(EMAIL_TEXT_STATUS_LABEL, $orders_status_array[$status] ));
            $html_msg['EMAIL_TEXT_NEW_STATUS'] = $orders_status_array[$status];
            $html_msg['EMAIL_TEXT_STATUS_PLEASE_REPLY'] = str_replace('\n','', EMAIL_TEXT_STATUS_PLEASE_REPLY);
            $html_msg['EMAIL_PAYPAL_TRANSID'] = '';

            zen_mail($check_status->fields['customers_name'], $check_status->fields['customers_email_address'], EMAIL_TEXT_SUBJECT . ' #' . $oID, $message, STORE_NAME, EMAIL_FROM, $html_msg, 'order_status');
            $customer_notified = '1';
  
            // PayPal Trans ID, if any
            $sql = "select txn_id, parent_txn_id from " . TABLE_PAYPAL . " where order_id = :orderID order by last_modified DESC, date_added DESC, parent_txn_id DESC, paypal_ipn_id DESC ";
            $sql = $db->bindVars($sql, ':orderID', $oID, 'integer');
            $result = $db->Execute($sql);
            if ($result->RecordCount() > 0) {
              $message .= "\n\n" . ' PayPal Trans ID: ' . $result->fields['txn_id'];
              $html_msg['EMAIL_PAYPAL_TRANSID'] = $result->fields['txn_id'];
            }

            //send extra emails
            if (SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO_STATUS == '1' and SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO != '') {
              zen_mail('', SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO, SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO_SUBJECT . ' ' . EMAIL_TEXT_SUBJECT . ' #' . $oID, $message, STORE_NAME, EMAIL_FROM, $html_msg, 'order_status_extra');
            }
          } elseif (isset($_POST['notify']) && ($_POST['notify'] == '-1')) {
            // hide comment
            $customer_notified = '-1';
          }

// BEGIN TY TRACKER 2 - INCLUDE DATABASE FIELDS IN STATUS UPDATE ----------------------------------------------
			$sql_data_array = array(
				'orders_id' => (int)$oID,
				'orders_status_id' => zen_db_input($status),
				'date_added' => 'now()',
				'customer_notified' => zen_db_input($customer_notified),
				'comments' => zen_db_input($comments),
			);
			foreach($track_id as $id => $track) {
				$sql_data_array['track_id' . $id] = zen_db_input($track);
			}
			unset($id); unset($track);
			zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

			$sql_data_array = array(
				'orders_status' => zen_db_input($status),
				'last_modified' => 'now()'
			);
			zen_db_perform(TABLE_ORDERS, $sql_data_array, 'update', 'orders_id = \'' . (int)$oID . '\'');
			unset($sql_data_array);
			$order_updated = true;
		}
// END TY TRACKER 2 - INCLUDE DATABASE FIELDS IN STATUS UPDATE ------------------------------------------------------------------
        // trigger any appropriate updates which should be sent back to the payment gateway:
        $order = new order((int)$oID);
        if ($order->info['payment_module_code']) {
          if (file_exists(DIR_FS_CATALOG_MODULES . 'payment/' . $order->info['payment_module_code'] . '.php')) {
            require_once(DIR_FS_CATALOG_MODULES . 'payment/' . $order->info['payment_module_code'] . '.php');
            require_once(DIR_FS_CATALOG_LANGUAGES . $_SESSION['language'] . '/modules/payment/' . $order->info['payment_module_code'] . '.php');
            $module = new $order->info['payment_module_code'];
            if (method_exists($module, '_doStatusUpdate')) {
              $response = $module->_doStatusUpdate($oID, $status, $comments, $customer_notified, $check_status->fields['orders_status']);
            }
          }
        }

        if ($order_updated == true) {
         if ($status == DOWNLOADS_ORDERS_STATUS_UPDATED_VALUE) {

            // adjust download_maxdays based on current date
  
            $chk_downloads_query = "SELECT opd.*, op.products_id from " . TABLE_ORDERS_PRODUCTS_DOWNLOAD . " opd, " . TABLE_ORDERS_PRODUCTS . " op
                                    WHERE op.orders_id='" . (int)$oID . "'
                                    and opd.orders_products_id = op.orders_products_id";
            $chk_downloads = $db->Execute($chk_downloads_query);

            while (!$chk_downloads->EOF) {
              $chk_products_download_time_query = "SELECT pa.products_attributes_id, pa.products_id, pad.products_attributes_filename, pad.products_attributes_maxdays, pad.products_attributes_maxcount
                                                    from " . TABLE_PRODUCTS_ATTRIBUTES . " pa, " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                                    WHERE pa.products_attributes_id = pad.products_attributes_id
                                                    and pad.products_attributes_filename = '" . $chk_downloads->fields['orders_products_filename'] . "'
                                                    and pa.products_id = '" . $chk_downloads->fields['products_id'] . "'";

              $chk_products_download_time = $db->Execute($chk_products_download_time_query);

              if ($chk_products_download_time->EOF) {
                $zc_max_days = (DOWNLOAD_MAX_DAYS == 0 ? 0 : zen_date_diff($check_status->fields['date_purchased'], date('Y-m-d H:i:s', time())) + DOWNLOAD_MAX_DAYS);
                $update_downloads_query = "update " . TABLE_ORDERS_PRODUCTS_DOWNLOAD . " set download_maxdays='" . $zc_max_days . "', download_count='" . DOWNLOAD_MAX_COUNT . "' where orders_id='" . (int)$oID . "' and orders_products_download_id='" . $_GET['download_reset_on'] . "'";
              } else {
                $zc_max_days = ($chk_products_download_time->fields['products_attributes_maxdays'] == 0 ? 0 : zen_date_diff($check_status->fields['date_purchased'], date('Y-m-d H:i:s', time())) + $chk_products_download_time->fields['products_attributes_maxdays']);
                $update_downloads_query = "update " . TABLE_ORDERS_PRODUCTS_DOWNLOAD . " set download_maxdays='" . $zc_max_days . "', download_count='" . $chk_products_download_time->fields['products_attributes_maxcount'] . "' where orders_id='" . (int)$oID . "' and orders_products_download_id='" . $chk_downloads->fields['orders_products_download_id'] . "'";
              }

              $db->Execute($update_downloads_query);

              $chk_downloads->MoveNext();
            }
          }
          $messageStack->add_session(SUCCESS_ORDER_UPDATED, 'success');
        } else {
          $messageStack->add_session(WARNING_ORDER_NOT_UPDATED, 'warning');
        }
        zen_redirect(zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=edit', 'NONSSL'));

} else {
// END TY TRACKER/SUPER ORDERS INTEGRATION  ----------------------------------

// BEGIN STOCK COMMENTS
        $oID = zen_db_prepare_input($_GET['oID']);
        $comments = zen_db_prepare_input($_POST['comments']);
        $status = (int)zen_db_prepare_input($_POST['status']);
        if ($status < 1) break;

        $order_updated = false;
        $check_status = $db->Execute("select customers_name, customers_email_address, orders_status,
                                      date_purchased from " . TABLE_ORDERS . "
                                      where orders_id = '" . (int)$oID . "'");

        if ( ($check_status->fields['orders_status'] != $status) || zen_not_null($comments)) {
          $db->Execute("update " . TABLE_ORDERS . "
                        set orders_status = '" . zen_db_input($status) . "', last_modified = now()
                        where orders_id = '" . (int)$oID . "'");

          $customer_notified = '0';
          if (isset($_POST['notify']) && ($_POST['notify'] == '1')) {

            $notify_comments = '';
            if (isset($_POST['notify_comments']) && ($_POST['notify_comments'] == 'on') && zen_not_null($comments)) {
              $notify_comments = EMAIL_TEXT_COMMENTS_UPDATE . $comments . "\n\n";
            }
            //send emails
            $message =
//<!-- Begin Super Orders Modification (Minor formatting change) //-->
            STORE_NAME . " " . EMAIL_TEXT_ORDER_NUMBER . ' ' . $oID . "\n\n" .
//<!-- Begin Super Orders Modification (Minor formatting change) //-->
            EMAIL_TEXT_INVOICE_URL . ' ' . zen_catalog_href_link(FILENAME_CATALOG_ACCOUNT_HISTORY_INFO, 'order_id=' . $oID, 'SSL') . "\n\n" .
            EMAIL_TEXT_DATE_ORDERED . ' ' . zen_date_long($check_status->fields['date_purchased']) . "\n\n" .
            strip_tags($notify_comments) .
            EMAIL_TEXT_STATUS_UPDATED . sprintf(EMAIL_TEXT_STATUS_LABEL, $orders_status_array[$status] ) .
            EMAIL_TEXT_STATUS_PLEASE_REPLY;

            $html_msg['EMAIL_CUSTOMERS_NAME']    = $check_status->fields['customers_name'];
            $html_msg['EMAIL_TEXT_ORDER_NUMBER'] = EMAIL_TEXT_ORDER_NUMBER . ' ' . $oID;
            $html_msg['EMAIL_TEXT_INVOICE_URL']  = '<a href="' . zen_catalog_href_link(FILENAME_CATALOG_ACCOUNT_HISTORY_INFO, 'order_id=' . $oID, 'SSL') .'">'.str_replace(':','',EMAIL_TEXT_INVOICE_URL).'</a>';
            $html_msg['EMAIL_TEXT_DATE_ORDERED'] = EMAIL_TEXT_DATE_ORDERED . ' ' . zen_date_long($check_status->fields['date_purchased']);
            $html_msg['EMAIL_TEXT_STATUS_COMMENTS'] = nl2br($notify_comments);
            $html_msg['EMAIL_TEXT_STATUS_UPDATED'] = str_replace('\n','', EMAIL_TEXT_STATUS_UPDATED);
            $html_msg['EMAIL_TEXT_STATUS_LABEL'] = str_replace('\n','', sprintf(EMAIL_TEXT_STATUS_LABEL, $orders_status_array[$status] ));
            $html_msg['EMAIL_TEXT_NEW_STATUS'] = $orders_status_array[$status];
            $html_msg['EMAIL_TEXT_STATUS_PLEASE_REPLY'] = str_replace('\n','', EMAIL_TEXT_STATUS_PLEASE_REPLY);
            $html_msg['EMAIL_PAYPAL_TRANSID'] = '';

            zen_mail($check_status->fields['customers_name'], $check_status->fields['customers_email_address'], EMAIL_TEXT_SUBJECT . ' #' . $oID, $message, STORE_NAME, EMAIL_FROM, $html_msg, 'order_status');
            $customer_notified = '1';
  
            // PayPal Trans ID, if any
            $sql = "select txn_id, parent_txn_id from " . TABLE_PAYPAL . " where order_id = :orderID order by last_modified DESC, date_added DESC, parent_txn_id DESC, paypal_ipn_id DESC ";
            $sql = $db->bindVars($sql, ':orderID', $oID, 'integer');
            $result = $db->Execute($sql);
            if ($result->RecordCount() > 0) {
              $message .= "\n\n" . ' PayPal Trans ID: ' . $result->fields['txn_id'];
              $html_msg['EMAIL_PAYPAL_TRANSID'] = $result->fields['txn_id'];
            }

            //send extra emails
            if (SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO_STATUS == '1' and SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO != '') {
              zen_mail('', SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO, SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO_SUBJECT . ' ' . EMAIL_TEXT_SUBJECT . ' #' . $oID, $message, STORE_NAME, EMAIL_FROM, $html_msg, 'order_status_extra');
            }
          } elseif (isset($_POST['notify']) && ($_POST['notify'] == '-1')) {
            // hide comment
            $customer_notified = '-1';
          }

          $db->Execute("insert into " . TABLE_ORDERS_STATUS_HISTORY . "
                      (orders_id, orders_status_id, date_added, customer_notified, comments)
                      values ('" . (int)$oID . "',
                      '" . zen_db_input($status) . "',
                      now(),
                      '" . zen_db_input($customer_notified) . "',
                      '" . zen_db_input($comments)  . "')");
          $order_updated = true;
        }
        // trigger any appropriate updates which should be sent back to the payment gateway:
        $order = new order((int)$oID);
        if ($order->info['payment_module_code']) {
          if (file_exists(DIR_FS_CATALOG_MODULES . 'payment/' . $order->info['payment_module_code'] . '.php')) {
            require_once(DIR_FS_CATALOG_MODULES . 'payment/' . $order->info['payment_module_code'] . '.php');
            require_once(DIR_FS_CATALOG_LANGUAGES . $_SESSION['language'] . '/modules/payment/' . $order->info['payment_module_code'] . '.php');
            $module = new $order->info['payment_module_code'];
            if (method_exists($module, '_doStatusUpdate')) {
              $response = $module->_doStatusUpdate($oID, $status, $comments, $customer_notified, $check_status->fields['orders_status']);
            }
          }
        }

        if ($order_updated == true) {
         if ($status == DOWNLOADS_ORDERS_STATUS_UPDATED_VALUE) {
            // adjust download_maxdays based on current date
            $chk_downloads_query = "SELECT opd.*, op.products_id from " . TABLE_ORDERS_PRODUCTS_DOWNLOAD . " opd, " . TABLE_ORDERS_PRODUCTS . " op
                                    WHERE op.orders_id='" . (int)$oID . "'
                                    and opd.orders_products_id = op.orders_products_id";
            $chk_downloads = $db->Execute($chk_downloads_query);

            while (!$chk_downloads->EOF) {
              $chk_products_download_time_query = "SELECT pa.products_attributes_id, pa.products_id, pad.products_attributes_filename, pad.products_attributes_maxdays, pad.products_attributes_maxcount
                                                    from " . TABLE_PRODUCTS_ATTRIBUTES . " pa, " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                                    WHERE pa.products_attributes_id = pad.products_attributes_id
                                                    and pad.products_attributes_filename = '" . $chk_downloads->fields['orders_products_filename'] . "'
                                                    and pa.products_id = '" . $chk_downloads->fields['products_id'] . "'";

              $chk_products_download_time = $db->Execute($chk_products_download_time_query);

              if ($chk_products_download_time->EOF) {
                $zc_max_days = (DOWNLOAD_MAX_DAYS == 0 ? 0 : zen_date_diff($check_status->fields['date_purchased'], date('Y-m-d H:i:s', time())) + DOWNLOAD_MAX_DAYS);
                $update_downloads_query = "update " . TABLE_ORDERS_PRODUCTS_DOWNLOAD . " set download_maxdays='" . $zc_max_days . "', download_count='" . DOWNLOAD_MAX_COUNT . "' where orders_id='" . (int)$oID . "' and orders_products_download_id='" . $_GET['download_reset_on'] . "'";
              } else {
                $zc_max_days = ($chk_products_download_time->fields['products_attributes_maxdays'] == 0 ? 0 : zen_date_diff($check_status->fields['date_purchased'], date('Y-m-d H:i:s', time())) + $chk_products_download_time->fields['products_attributes_maxdays']);
                $update_downloads_query = "update " . TABLE_ORDERS_PRODUCTS_DOWNLOAD . " set download_maxdays='" . $zc_max_days . "', download_count='" . $chk_products_download_time->fields['products_attributes_maxcount'] . "' where orders_id='" . (int)$oID . "' and orders_products_download_id='" . $chk_downloads->fields['orders_products_download_id'] . "'";
              }

              $db->Execute($update_downloads_query);

              $chk_downloads->MoveNext();
            }
          }
          $messageStack->add_session(SUCCESS_ORDER_UPDATED, 'success');
        } else {
          $messageStack->add_session(WARNING_ORDER_NOT_UPDATED, 'warning');
        }
        zen_redirect(zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=edit', 'NONSSL'));
}
// END STOCK COMMENTS

    break;
   case 'deleteconfirm':
        // demo active test
        if (zen_admin_demo()) {
          $_GET['action']= '';
          $messageStack->add_session(ERROR_ADMIN_DEMO, 'caution');
          zen_redirect(zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('oID', 'action')), 'NONSSL'));
        }
        $oID = zen_db_prepare_input($_POST['oID']);

        zen_remove_order($oID, $_POST['restock']);
        $so->delete_all_data();
        zen_redirect(zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('oID', 'action')), 'NONSSL'));
      break;
      case 'delete_cvv':
        $delete_cvv = $db->Execute("update " . TABLE_ORDERS . " set cc_cvv = '" . TEXT_DELETE_CVV_REPLACEMENT . "' where orders_id = '" . (int)$_GET['oID'] . "'");
        zen_redirect(zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=edit', 'NONSSL'));
        break;
      case 'mask_cc':
        $result  = $db->Execute("select cc_number from " . TABLE_ORDERS . " where orders_id = '" . (int)$_GET['oID'] . "'");
        $old_num = $result->fields['cc_number'];
        $new_num = substr($old_num, 0, 4) . str_repeat('*', (strlen($old_num) - 8)) . substr($old_num, -4);
        $mask_cc = $db->Execute("update " . TABLE_ORDERS . " set cc_number = '" . $new_num . "' where orders_id = '" . (int)$_GET['oID'] . "'");
        zen_redirect(zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=edit', 'NONSSL'));
        break;
      case 'doRefund':
        $order = new order($oID);
        if ($order->info['payment_module_code']) {
          if (file_exists(DIR_FS_CATALOG_MODULES . 'payment/' . $order->info['payment_module_code'] . '.php')) {
            require_once(DIR_FS_CATALOG_MODULES . 'payment/' . $order->info['payment_module_code'] . '.php');
            require_once(DIR_FS_CATALOG_LANGUAGES . $_SESSION['language'] . '/modules/payment/' . $order->info['payment_module_code'] . '.php');
            $module = new $order->info['payment_module_code'];
            if (method_exists($module, '_doRefund')) {
              $module->_doRefund($oID);
            }
          }
        }
        zen_redirect(zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=edit', 'NONSSL'));
        break;
      case 'doAuth':
        $order = new order($oID);
        if ($order->info['payment_module_code']) {
          if (file_exists(DIR_FS_CATALOG_MODULES . 'payment/' . $order->info['payment_module_code'] . '.php')) {
            require_once(DIR_FS_CATALOG_MODULES . 'payment/' . $order->info['payment_module_code'] . '.php');
            require_once(DIR_FS_CATALOG_LANGUAGES . $_SESSION['language'] . '/modules/payment/' . $order->info['payment_module_code'] . '.php');
            $module = new $order->info['payment_module_code'];
            if (method_exists($module, '_doAuth')) {
              $module->_doAuth($oID, $order->info['total'], $order->info['currency']);
            }
          }
        }
        zen_redirect(zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=edit', 'NONSSL'));
        break;
      case 'doCapture':
        $order = new order($oID);
        if ($order->info['payment_module_code']) {
          if (file_exists(DIR_FS_CATALOG_MODULES . 'payment/' . $order->info['payment_module_code'] . '.php')) {
            require_once(DIR_FS_CATALOG_MODULES . 'payment/' . $order->info['payment_module_code'] . '.php');
            require_once(DIR_FS_CATALOG_LANGUAGES . $_SESSION['language'] . '/modules/payment/' . $order->info['payment_module_code'] . '.php');
            $module = new $order->info['payment_module_code'];
            if (method_exists($module, '_doCapt')) {
              $module->_doCapt($oID, 'Complete', $order->info['total'], $order->info['currency']);
            }
          }
        }
        zen_redirect(zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=edit', 'NONSSL'));
        break;
      case 'doVoid':
        $order = new order($oID);
        if ($order->info['payment_module_code']) {
          if (file_exists(DIR_FS_CATALOG_MODULES . 'payment/' . $order->info['payment_module_code'] . '.php')) {
            require_once(DIR_FS_CATALOG_MODULES . 'payment/' . $order->info['payment_module_code'] . '.php');
            require_once(DIR_FS_CATALOG_LANGUAGES . $_SESSION['language'] . '/modules/payment/' . $order->info['payment_module_code'] . '.php');
            $module = new $order->info['payment_module_code'];
            if (method_exists($module, '_doVoid')) {
              $module->_doVoid($oID);
            }
          }
        }
        zen_redirect(zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=edit', 'NONSSL'));
        break;
    }
  }
?>
<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
<title><?php echo TITLE; ?></title>
<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
<link rel="stylesheet" type="text/css" href="includes/super_stylesheet.css">
<?php if (TY_TRACKER == 'True') { ?>
<link rel="stylesheet" type="text/css" href="includes/typt_stylesheet.css">
<?php } ?>
<?php if (SO_EDIT_ORDERS_SWITCH == 'True') { ?>
<link rel="stylesheet" type="text/css" href="includes/edit_orders.css">
<?php } ?>
<link rel="stylesheet" type="text/css" media="print" href="includes/stylesheet_print.css">
<link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
<script language="javascript" src="includes/menu.js"></script>
<script language="javascript" src="includes/general.js"></script>
<script type="text/javascript">
  <!--
  function init()
  {
    cssjsmenu('navbar');
    if (document.getElementById)
    {
      var kill = document.getElementById('hoverJS');
      kill.disabled = true;
    }
  }

  function popupWindow(url, features) {
    window.open(url,'popupWindow',features)
  }
  // -->
</script>
<script language="javascript" type="text/javascript"><!--
function couponpopupWindow(url) {
  window.open(url,'popupWindow','toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=yes,copyhistory=no,width=450,height=280,screenX=150,screenY=150,top=150,left=150')
}
//--></script>
</head>
<body onLoad="init()">
<!-- header //-->
<div class="header-area">
<?php 
	require(DIR_WS_INCLUDES . 'header.php'); 
?>
</div>
<!-- header_eof //-->
<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
<!-- body_text //-->

<?php if ($action == '') { ?>
<!-- search -->
  <tr>
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
         <tr><?php echo zen_draw_form('search', FILENAME_ORDERS, '', 'get', '', true); ?>
            <td width="65%" class="pageHeading" align="right"><?php echo zen_draw_separator('pixel_trans.gif', 1, HEADING_IMAGE_HEIGHT); ?></td>
            <td colspan="2" class="smallText" align="right">
<?php
// show reset search
  if ((isset($_GET['search']) && zen_not_null($_GET['search'])) or $_GET['cID'] !='') {
    echo '<a href="' . zen_href_link(FILENAME_ORDERS, '', 'NONSSL') . '">' . zen_image_button('button_reset.gif', IMAGE_RESET) . '</a><br />';
  }
?>
<?php
  echo HEADING_TITLE_SEARCH_DETAIL . ' ' . zen_draw_input_field('search') . zen_hide_session_id();
  if (isset($_GET['search']) && zen_not_null($_GET['search'])) {
    $keywords = zen_db_input(zen_db_prepare_input($_GET['search']));
    echo '<br />' . TEXT_INFO_SEARCH_DETAIL_FILTER . $keywords;
  }
?>
            </td>
          </form>


         <?php echo zen_draw_form('search_orders_products', FILENAME_ORDERS, '', 'get', '', true); ?>
            <td class="pageHeading" align="right"><?php echo zen_draw_separator('pixel_trans.gif', 1, HEADING_IMAGE_HEIGHT); ?></td>
            <td colspan="2" class="smallText" align="right">
<?php
// show reset search orders_products
  if ((isset($_GET['search_orders_products']) && zen_not_null($_GET['search_orders_products'])) or $_GET['cID'] !='') {
    echo '<a href="' . zen_href_link(FILENAME_ORDERS, '', 'NONSSL') . '">' . zen_image_button('button_reset.gif', IMAGE_RESET) . '</a><br />';
  }
?>
<?php
  echo HEADING_TITLE_SEARCH_DETAIL_ORDERS_PRODUCTS . ' ' . zen_draw_input_field('search_orders_products') . zen_hide_session_id();
  if (isset($_GET['search_orders_products']) && zen_not_null($_GET['search_orders_products'])) {
    $keywords_orders_products = zen_db_input(zen_db_prepare_input($_GET['search_orders_products']));
    echo '<br />' . TEXT_INFO_SEARCH_DETAIL_FILTER_ORDERS_PRODUCTS . zen_db_prepare_input($keywords_orders_products);
  }
?>
            </td>
          </form>

        </table></td>
      </tr>
<!-- search -->
<?php } ?>


  <?php
  /*
  ** ORDER DETAIL DISPLAY
  */
  if (($action == 'edit') && ($order_exists == true)) {
    $order = new order ($oID);
    if ($order->info['payment_module_code']) {
      if (file_exists(DIR_FS_CATALOG_MODULES . 'payment/' . $order->info['payment_module_code'] . '.php')) {
        require(DIR_FS_CATALOG_MODULES . 'payment/' . $order->info['payment_module_code'] . '.php');
        require(DIR_FS_CATALOG_LANGUAGES . $_SESSION['language'] . '/modules/payment/' . $order->info['payment_module_code'] . '.php');
        $module = new $order->info['payment_module_code'];
//        echo $module->admin_notification($oID);
      }
    }
// BEGIN - Add Super Orders Order Navigation Functionality
    $get_prev = $db->Execute("SELECT orders_id FROM " . TABLE_ORDERS . " WHERE orders_id < '" . $oID . "' ORDER BY orders_id DESC LIMIT 1");

    if (zen_not_null($get_prev->fields['orders_id'])) {
      $prev_button = '            <INPUT class="normal_button button" TYPE="BUTTON" VALUE="<<< ' . $get_prev->fields['orders_id'] . '" ONCLICK="window.location.href=\'' . zen_href_link(FILENAME_ORDERS, 'oID=' . $get_prev->fields['orders_id'] . '&action=edit') . '\'">';
    }
    else {
      $prev_button = '            <INPUT class="normal_button button" TYPE="BUTTON" VALUE="' . BUTTON_TO_LIST . '" ONCLICK="window.location.href=\'' . zen_href_link(FILENAME_ORDERS) . '\'">';
    }


    $get_next = $db->Execute("SELECT orders_id FROM " . TABLE_ORDERS . " WHERE orders_id > '" . $oID . "' ORDER BY orders_id ASC LIMIT 1");

    if (zen_not_null($get_next->fields['orders_id'])) {
      $next_button = '            <INPUT class="normal_button button" TYPE="BUTTON" VALUE="' . $get_next->fields['orders_id'] . ' >>>" ONCLICK="window.location.href=\'' . zen_href_link(FILENAME_ORDERS, 'oID=' . $get_next->fields['orders_id'] . '&action=edit') . '\'">';
    }
    else {
      $next_button = '            <INPUT class="normal_button button" TYPE="BUTTON" VALUE="' . BUTTON_TO_LIST . '" ONCLICK="window.location.href=\'' . zen_href_link(FILENAME_ORDERS) . '\'">';
  }
// END - Add Super Orders Order Navigation Functionality
?>
      <tr>
        <td width="100%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
<!-- BEGIN - Add Super Orders Order Navigation Functionality -->
            <td class="pageHeading"><?php echo HEADING_TITLE_ORDER_DETAILS . $oID; ?></td>
            <td class="pageHeading" align="right"><?php echo zen_draw_separator('pixel_trans.gif', 1, HEADING_IMAGE_HEIGHT); ?></td>
            <?php if ($so->status) { ?>
            <td class="main" valign="middle"><?php echo
              '<span class="status-' . $so->status . '">' . zen_datetime_short($so->status_date) . '</span>&nbsp;' .
              '<a href="' . zen_href_link(FILENAME_ORDERS, 'action=reopen&oID=' . $oID) . '">' . zen_image(DIR_WS_IMAGES . 'icon_red_x.gif', '', '', '', '') . HEADING_REOPEN_ORDER . '</a>';
            ?></td>
            <?php } ?>
            <td align="center">
	    <table border="0" cellspacing="3" cellpadding="0">
              <tr>
                <td class="main" align="center" valign="bottom"><?php echo $prev_button; ?></td>
                <td class="smallText" align="center" valign="bottom"><?php
                  echo SELECT_ORDER_LIST . '<br />';
                  echo zen_draw_form('input_oid', FILENAME_ORDERS, '', 'get', '', true);
                  echo zen_draw_input_field('oID', '', 'size="6"');
                  echo zen_draw_hidden_field('action', 'edit');
                  echo '</form>';
                ?></td>
                <td class="main" align="center" valign="bottom"><?php echo $next_button; ?></td>
              </tr>
            </table>
	    </td>
<!-- END - Add Super Orders Order Navigation Functionality -->
            <td align="right"><?php
//Begin Add Edit Order button to edit order page
              if (SO_EDIT_ORDERS_SWITCH == 'True') {
              echo '<a href="' . zen_href_link(FILENAME_EDIT_ORDERS, 'oID=' . $oID) . '">' . zen_image_button('button_edit.gif', ICON_ORDER_EDIT) . '</a>&nbsp;&nbsp;'; 
              }
//End Add Edit Order button to edit order page
              echo '<a href="' . zen_href_link(FILENAME_SUPER_DATA_SHEET, 'oID=' . $oID) . '" target="_blank">' . zen_image_button('btn_print.gif', ICON_ORDER_PRINT) . '</a>&nbsp;&nbsp;';
              echo '<a href="' . zen_href_link(FILENAME_INVOICE, 'oID=' . $oID) . '" target="_blank">' . zen_image_button('button_invoice.gif', ICON_ORDER_INVOICE) . '</a>&nbsp;&nbsp;';
              echo '<a href="' . zen_href_link(FILENAME_PACKINGSLIP, 'oID=' . $oID) . '" target="_blank">' . zen_image_button('button_packingslip.gif', ICON_ORDER_PACKINGSLIP) . '</a>&nbsp;&nbsp;';
              echo '<a href="javascript:history.back()">' . zen_image_button('button_back.gif', IMAGE_BACK) . '</a>';
            ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table width="100%" border="0" cellspacing="0" cellpadding="2">
          <tr>
            <td colspan="3"><?php echo zen_draw_separator(); ?></td>
          </tr>
<!-- Begin Customer & Payment Information //-->
         <tr>
            <td valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="2">
              <tr>
                <td class="main" valign="top"><strong><?php echo ENTRY_CUSTOMER_ADDRESS . '<br />' . zen_image(DIR_WS_IMAGES . 'icon_customers.png', ENTRY_CUSTOMER_ADDRESS); ?></strong></td>
                <td class="main"><?php echo zen_address_format($order->customer['format_id'], $order->customer, 1, '', '<br />'); ?></td>
              </tr>
              <tr>
                <td colspan="2"><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
              </tr>
              <tr>
                <td class="main"><strong><?php echo ENTRY_TELEPHONE_NUMBER; ?></strong></td>
                <td class="main"><?php echo $order->customer['telephone']; ?></td>
              </tr>
              <tr>
                <td class="main"><strong><?php echo ENTRY_EMAIL_ADDRESS; ?></strong></td>
                <td class="main"><?php echo '<a href="mailto:' . $order->customer['email_address'] . '">' . $order->customer['email_address'] . '</a>'; ?></td>
              </tr>
              <tr>
                <td class="main"><strong><?php echo TEXT_INFO_IP_ADDRESS; ?></strong></td>
                <?php if ($order->info['ip_address'] != '') { ?>
                <td class="main"><?php echo $order->info['ip_address'] . '&nbsp;[<a target="_blank" href="http://www.dnsstuff.com/tools/whois.ch?ip=' . $order->info['ip_address'] . '">' . TEXT_WHOIS_LOOKUP . '</a>]'; ?></td>
                <?php } else { ?>
                <td class="main"><?php echo TEXT_NONE; ?></td>
                <?php } ?>
              </tr>
            </table></td>
            <td valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="2">
              <tr>
                <td class="main" valign="top"><strong><?php echo ENTRY_SHIPPING_ADDRESS . '<br />' . zen_image(DIR_WS_IMAGES . 'icon_shipping.png', ENTRY_SHIPPING_ADDRESS); ?></strong></td>
                <td class="main"><?php echo zen_address_format($order->delivery['format_id'], $order->delivery, 1, '', '<br />'); ?></td>
              </tr>
            </table></td>
            <td valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="2">
              <tr>
                <td class="main" valign="top"><strong><?php echo ENTRY_BILLING_ADDRESS . '<br />' . zen_image(DIR_WS_IMAGES . 'icon_billing.png', ENTRY_BILLING_ADDRESS); ?></strong></td>
                <td class="main"><?php echo zen_address_format($order->billing['format_id'], $order->billing, 1, '', '<br />'); ?></td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
      </tr>
      <tr>
        <td class="main"><strong><?php echo ENTRY_ORDER_ID . $oID; ?></strong></td>
      </tr>
      <tr>
     <td><table border="0" cellspacing="0" cellpadding="2">
        <tr>
           <td class="main"><strong><?php echo ENTRY_DATE_PURCHASED; ?></strong></td>
           <td class="main"><?php echo zen_date_long($order->info['date_purchased']); ?></td>
        </tr>
        <tr>
           <td class="main"><strong><?php echo ENTRY_PAYMENT_METHOD; ?></strong></td>
           <td class="main"><?php echo $order->info['payment_method']; ?></td>
        </tr>
<?php
    if (zen_not_null($order->info['cc_type']) || zen_not_null($order->info['cc_owner']) || zen_not_null($order->info['cc_number'])) {
?>
          <tr>
            <td colspan="2"><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo ENTRY_CREDIT_CARD_TYPE; ?></td>
            <td class="main"><?php echo $order->info['cc_type']; ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo ENTRY_CREDIT_CARD_OWNER; ?></td>
            <td class="main"><?php echo $order->info['cc_owner']; ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo ENTRY_CREDIT_CARD_NUMBER; ?></td>
            <td class="main"><?php echo $order->info['cc_number'] . (zen_not_null($order->info['cc_number']) && !strstr($order->info['cc_number'],'X') && !strstr($order->info['cc_number'],'********') ? '&nbsp;&nbsp;<a href="' . zen_href_link(FILENAME_ORDERS, '&action=mask_cc&oID=' . $oID, 'NONSSL') . '" class="noprint">' . TEXT_MASK_CC_NUMBER . '</a>' : ''); ?><td>
          </tr>
<?php if (zen_not_null($order->info['cc_cvv'])) { ?>
          <tr>
            <td class="main"><?php echo ENTRY_CREDIT_CARD_CVV; ?></td>
            <td class="main"><?php echo $order->info['cc_cvv'] . (zen_not_null($order->info['cc_cvv']) && !strstr($order->info['cc_cvv'],TEXT_DELETE_CVV_REPLACEMENT) ? '&nbsp;&nbsp;<a href="' . zen_href_link(FILENAME_ORDERS, '&action=delete_cvv&oID=' . $oID, 'NONSSL') . '" class="noprint">' . TEXT_DELETE_CVV_FROM_DATABASE . '</a>' : ''); ?><td>
          </tr>
<?php } ?>
          <tr>
            <td class="main"><?php echo ENTRY_CREDIT_CARD_EXPIRES; ?></td>
            <td class="main"><?php echo $order->info['cc_expires']; ?></td>
          </tr>
<?php
    }
?>
        </table></td>
<!-- End Customer & Payment Information //-->
<!-- Begin Super Order Payments Section //-->

      <tr>
        <td colspan="2"><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
      </tr>
<?php
      if (!$so->payment && !$so->refund && !$so->purchase_order && !$so->po_payment) {
?>
      <tr>
        <td><table width="100%" border="0" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main"><strong><?php echo zen_image(DIR_WS_IMAGES . 'icon_money_add.png', TEXT_NO_PAYMENT_DATA) . '&nbsp;' . TEXT_NO_PAYMENT_DATA; ?></strong></td>
            <td align="right" class="main"><?php $so->button_add('payment'); $so->button_add('purchase_order'); $so->button_add('refund'); ?></td>
          </tr>
        </table></td>
      </tr>
<?php
      } else {
?>
      <tr>
        <td><table border="0" cellspacing="0" cellpadding="2" width="100%">
          <tr>
            <td class="main"><strong><?php echo zen_image(DIR_WS_IMAGES . 'icon_money_add.png', TEXT_PAYMENT_DATA) . '&nbsp;' . TEXT_PAYMENT_DATA; ?></strong></td>
            <td align="right" colspan="7"><?php $so->button_add('payment'); $so->button_add('purchase_order'); $so->button_add('refund'); ?></td>
          </tr>
          <tr class="dataTableHeadingRow">
            <td class="dataTableHeadingContent" align="left" width="15%"><?php echo PAYMENT_TABLE_NUMBER; ?></td>
            <td class="dataTableHeadingContent" align="left" width="15%"><?php echo PAYMENT_TABLE_NAME; ?></td>
            <td class="dataTableHeadingContent" align="right" width="15%"><?php echo PAYMENT_TABLE_AMOUNT; ?></td>
            <td class="dataTableHeadingContent" align="center" width="15%"><?php echo PAYMENT_TABLE_TYPE; ?></td>
            <td class="dataTableHeadingContent" align="left" width="15%"><?php echo PAYMENT_TABLE_POSTED; ?></td>
            <td class="dataTableHeadingContent" align="left" width="15%"><?php echo PAYMENT_TABLE_MODIFIED; ?></td>
            <td class="dataTableHeadingContent" align="right" width="10%"><?php echo PAYMENT_TABLE_ACTION; ?></td>
          </tr>
<?php
	$original_grand_total_paid=0;
        if ($so->payment) {
          for($a = 0; $a < sizeof($so->payment); $a++) {
            if ($a != 0) {
?>
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
<?php
            }
			$original_grand_total_paid =$original_grand_total_paid + $so->payment[$a]['amount'];
?>
          <tr class="paymentRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" <?php echo 'onclick="popupWindow(\'' . zen_href_link(FILENAME_SUPER_PAYMENTS, 'oID=' . $so->oID . '&payment_mode=payment&index=' . $so->payment[$a]['index'] . '&action=my_update', 'NONSSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')"'; ?>>
            <td class="paymentContent" align="left"><?php echo $so->payment[$a]['number']; ?></td>
            <td class="paymentContent" align="left"><?php echo $so->payment[$a]['name']; ?></td>
            <td class="paymentContent" align="right"><strong><?php echo $currencies->format($so->payment[$a]['amount']); ?></strong></td>
            <td class="paymentContent" align="center"><?php echo $so->full_type($so->payment[$a]['type']); ?></td>
            <td class="paymentContent" align="left"><?php echo zen_datetime_short($so->payment[$a]['posted']); ?></td>
            <td class="paymentContent" align="left"><?php echo zen_datetime_short($so->payment[$a]['modified']); ?></td>
            <td class="paymentContent" align="right"><?php $so->button_update('payment', $so->payment[$a]['index']); $so->button_delete('payment', $so->payment[$a]['index']);?></td>
          
          </tr>
<?php
            if ($so->refund) {
              for($b = 0; $b < sizeof($so->refund); $b++) {
                if ($so->refund[$b]['payment'] == $so->payment[$a]['index']) {
?>
          <tr class="refundRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" <?php echo 'onclick="popupWindow(\'' . zen_href_link(FILENAME_SUPER_PAYMENTS, 'oID=' . $so->oID . '&payment_mode=refund&index=' . $so->refund[$b]['index'] . '&action=my_update', 'NONSSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')"'; ?>>
            <td class="refundContent" align="left"><?php echo $so->refund[$b]['number']; ?></td>
            <td class="refundContent" align="left"><?php echo $so->refund[$b]['name']; ?></td>
            <td class="refundContent" align="right"><strong><?php echo '-' . $currencies->format($so->refund[$b]['amount']); ?></strong></td>
            <td class="refundContent" align="center"><?php echo $so->full_type($so->refund[$b]['type']); ?></td>
            <td class="refundContent" align="left"><?php echo zen_datetime_short($so->refund[$b]['posted']); ?></td>
            <td class="refundContent" align="left"><?php echo zen_datetime_short($so->refund[$b]['modified']); ?></td>
            <td class="refundContent" align="right"><?php $so->button_update('refund', $so->refund[$b]['index']); $so->button_delete('refund', $so->refund[$b]['index']); ?></td>
          </tr>
<?php
                }  // END if ($so->refund[$b]['payment'] == $so->payment[$a]['index'])
              }  // END for($b = 0; $b < sizeof($so->refund); $b++)
            }  // END if ($so->refund)
          }  // END for($a = 0; $a < sizeof($payment); $a++)
        }  // END if ($so->payment)
        if ($so->purchase_order) {
          for($c = 0; $c < sizeof($so->purchase_order); $c++) {
            if ($c < 1 && $so->payment) {
?>
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
          <tr>
            <td colspan="7"><?php echo zen_black_line(); ?></td>
          </tr>
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
<?php
            }
            elseif ($c > 1) {
?>
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
<?php
            }
?>
          <tr class="purchaseOrderRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" <?php echo 'onclick="popupWindow(\'' . zen_href_link(FILENAME_SUPER_PAYMENTS, 'oID=' . $so->oID . '&payment_mode=purchase_order&index=' . $so->purchase_order[$c]['index'] . '&action=my_update', 'NONSSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')"'; ?>>
            <td class="purchaseOrderContent" colspan="4" align="left"><?php echo $so->purchase_order[$c]['number']; ?></td>
            <td class="purchaseOrderContent" align="left"><?php echo zen_datetime_short($so->purchase_order[$c]['posted']); ?></td>
            <td class="purchaseOrderContent" align="left"><?php echo zen_datetime_short($so->purchase_order[$c]['modified']); ?></td>
            <td class="purchaseOrderContent" align="right"><?php $so->button_update('purchase_order', $so->purchase_order[$c]['index']); $so->button_delete('purchase_order', $so->purchase_order[$c]['index']);?></td>
          </tr>
<?php
            if ($so->po_payment) {
              for($d = 0; $d < sizeof($so->po_payment); $d++) {
                if ($so->po_payment[$d]['assigned_po'] == $so->purchase_order[$c]['index']) {
                  if ($d != 0) {
?>
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
<?php
                  }
?>
          <tr class="paymentRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" <?php echo 'onclick="popupWindow(\'' . zen_href_link(FILENAME_SUPER_PAYMENTS, 'oID=' . $so->oID . '&payment_mode=payment&index=' . $so->po_payment[$d]['index'] . '&action=my_update', 'NONSSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')"'; ?>>
            <td class="paymentContent" align="left"><?php echo $so->po_payment[$d]['number']; ?></td>
            <td class="paymentContent" align="left"><?php echo $so->po_payment[$d]['name']; ?></td>
            <td class="paymentContent" align="right"><strong><?php echo $currencies->format($so->po_payment[$d]['amount']); ?></strong></td>
            <td class="paymentContent" align="center"><?php echo $so->full_type($so->po_payment[$d]['type']); ?></td>
            <td class="paymentContent" align="left"><?php echo zen_datetime_short($so->po_payment[$d]['posted']); ?></td>
            <td class="paymentContent" align="left"><?php echo zen_datetime_short($so->po_payment[$d]['modified']); ?></td>
            <td class="paymentContent" align="right"><?php $so->button_update('payment', $so->po_payment[$d]['index']); $so->button_delete('payment', $so->po_payment[$d]['index']); ?></td>
          </tr>
<?php
                  if ($so->refund) {
                    for($e = 0; $e < sizeof($so->refund); $e++) {
                      if ($so->refund[$e]['payment'] == $so->po_payment[$d]['index']) {
?>
          <tr class="refundRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" <?php echo 'onclick="popupWindow(\'' . zen_href_link(FILENAME_SUPER_PAYMENTS, 'oID=' . $so->oID . '&payment_mode=refund&index=' . $so->refund[$e]['index'] . '&action=my_update', 'NONSSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')"'; ?>>
            <td class="refundContent" align="left"><?php echo $so->refund[$e]['number']; ?></td>
            <td class="refundContent" align="left"><?php echo $so->refund[$e]['name']; ?></td>
            <td class="refundContent" align="right"><strong><?php echo '-' . $currencies->format($so->refund[$e]['amount']); ?></strong></td>
            <td class="refundContent" align="center"><?php echo $so->full_type($so->refund[$e]['type']); ?></td>
            <td class="refundContent" align="left"><?php echo zen_datetime_short($so->refund[$e]['posted']); ?></td>
            <td class="refundContent" align="left"><?php echo zen_datetime_short($so->refund[$e]['modified']); ?></td>
            <td class="refundContent" align="right"><?php $so->button_update('refund', $so->refund[$e]['index']); $so->button_delete('refund', $so->refund[$e]['index']); ?></td>
          </tr>
<?php
                      }  // END if ($so->refund[$e]['payment'] == $so->po_payment[$d]['index'])
                    }  // END for($e = 0; $e < sizeof($so->refund); $e++)
                  }  // END if ($so->refund)
                }  // END if ($so->po_payment[$d]['assigned_po'] == $so->purchase_order[$c]['index'])
              }  // END for($d = 0; $d < sizeof($so->po_payment); $d++)
            }  // END if ($so->po_payment)
          }  // END for($c = 0; $c < sizeof($so->purchase_order); $c++)
        }  // END if ($so->purchase_order)
        // display any refunds not tied directly to a payment
        if ($so->refund) {
          for ($f = 0; $f < sizeof($so->refund); $f++) {
            if ($so->refund[$f]['payment'] == 0) {
              if ($f < 1) {
?>
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
          <tr>
            <td colspan="7"><?php echo zen_black_line(); ?></td>
          </tr>
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
<?php
              } else {
?>
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
<?php
              }
?>
          <tr class="refundRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" <?php echo 'onclick="popupWindow(\'' . zen_href_link(FILENAME_SUPER_PAYMENTS, 'oID=' . $so->oID . '&payment_mode=refund&index=' . $so->refund[$f]['index'] . '&action=my_update', 'NONSSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')"'; ?>>
            <td class="refundContent" align="left"><?php echo $so->refund[$f]['number']; ?></td>
            <td class="refundContent" align="left"><?php echo $so->refund[$f]['name']; ?></td>
            <td class="refundContent" align="right"><strong><?php echo '-' . $currencies->format($so->refund[$f]['amount']); ?></strong></td>
            <td class="refundContent" align="center"><?php echo $so->full_type($so->refund[$f]['type']); ?></td>
            <td class="refundContent" align="left"><?php echo zen_datetime_short($so->refund[$f]['posted']); ?></td>
            <td class="refundContent" align="left"><?php echo zen_datetime_short($so->refund[$f]['modified']); ?></td>
            <td class="refundContent" align="right"><?php $so->button_update('refund', $so->refund[$f]['index']); $so->button_delete('refund', $so->refund[$f]['index']); ?></td>
          </tr>
<?php
            }
          }
        }  // END if ($so->refund)
?>
        </table></td>
      </tr>
<?php
      }  // END else
      if ($so->payment || $so->refund || $so->purchase_order || $so->po_payment) {
?>
      </tr>
        <td><table border="0" cellspacing="0" cellpadding="0">
          <tr>
            <td class="main" align="center"><?php echo HEADING_COLOR_KEY; ?></td>
            <td><table border="0" cellspacing="2" cellpadding="3">
              <tr class="purchaseOrderRow">
                <td class="dataTableContent" align="center" width="120"><?php echo TEXT_PURCHASE_ORDERS; ?></td>
              </tr>
            </table></td>
            <td><table border="0" cellspacing="2" cellpadding="3">
              <tr class="paymentRow">
                <td class="dataTableContent" align="center" width="120"><?php echo TEXT_PAYMENTS; ?></td>
              </tr>
            </table></td>
            <td><table border="0" cellspacing="2" cellpadding="3">
              <tr class="refundRow">
                <td class="dataTableContent" align="center" width="120"><?php echo TEXT_REFUNDS; ?></td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
<!-- End Super Order Payments Section //-->

<!-- Begin PayPal or CreditCard Notification Panel //-->
<?php
      }
	 $parent_child= $db->Execute("select split_from_order, is_parent
							from " . TABLE_ORDERS . "
							where orders_id = '" . $oID . "'");
    if (method_exists($module, 'admin_notification')&&($parent_child->fields['is_parent'])) {
?>
      <tr>
        <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
      </tr>
      <tr>
        <?php echo $module->admin_notification($oID); ?>
      </tr>
      <tr>
        <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
      </tr>
<?php
}
?>
<!-- End PayPal or CreditCard Notification Panel //-->
<!-- Begin Split Order Details //-->
      <tr>
        <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
      </tr>
<?php

          if($parent_child->fields['split_from_order']){
		  	$another = new super_order($parent_child->fields['split_from_order']);
			 if ($another->payment&&$so->payment) {
              for ($i = 0; $i < sizeof($another->payment); $i++) {
			   $payment = $another->payment[$i];
			   $original_grand_total_paid = $original_grand_total_paid + $payment['amount'];
			   }
		  ?>
	  <tr>
        <td><strong><?php echo zen_image(DIR_WS_IMAGES . 'icon_money_add.png', ENTRY_ORIGINAL_PAYMENT_AMOUNT) . '&nbsp;' . ENTRY_ORIGINAL_PAYMENT_AMOUNT; ?></strong>
		    <?php echo $currencies->format($original_grand_total_paid);?><br /></td>
      </tr>
	  <tr>
        <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
      </tr>
	  
      <?php 
	    }
	  }
	  if (!$so->status && !$parent_child->fields['split_from_order'] && sizeof($order->products) > 1) {  
	  ?>
 
    <tr>
        <td class="main">
		<?php echo zen_image(DIR_WS_IMAGES . 'icon_edit3.gif', ICON_EDIT_PRODUCT) . '&nbsp;'; ?><?php echo '<a href="javascript:popupWindow(\'' . zen_href_link(FILENAME_SUPER_EDIT, 'oID=' . $oID . '&target=product', 'NONSSL') . '\', \'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=yes,copyhistory=no,width=725,height=450,screenX=150,screenY=100,top=100,left=150\')">' . ICON_EDIT_PRODUCT . '</a>';?>
		</td>
    </tr>
      <?php 
	   
	  } ?>
<!-- End Split Order Details //-->
<!-- Begin Products Detail //-->
      <tr>
        <td><table border="0" cellspacing="0" cellpadding="2" width="100%">
          <tr class="dataTableHeadingRow">
            <td class="dataTableHeadingContent" width="30%"><?php echo TABLE_HEADING_PRODUCTS; ?></td>
            <td class="dataTableHeadingContent" width="25%"><?php echo TABLE_HEADING_PRODUCTS_MODEL; ?></td>
            <td class="dataTableHeadingContent" align="right" width="5%"><?php echo TABLE_HEADING_TAX; ?></td>
            <td class="dataTableHeadingContent" align="right" width="10%"><?php echo TABLE_HEADING_PRICE_EXCLUDING_TAX; ?></td>
            <td class="dataTableHeadingContent" align="right" width="10%"><?php echo TABLE_HEADING_PRICE_INCLUDING_TAX; ?></td>
            <td class="dataTableHeadingContent" align="right" width="10%"><?php echo TABLE_HEADING_TOTAL_EXCLUDING_TAX; ?></td>
            <td class="dataTableHeadingContent" align="right" width="10%"><?php echo TABLE_HEADING_TOTAL_INCLUDING_TAX; ?></td>
          </tr>
<?php
      echo '          ' . zen_draw_form('split_packing', FILENAME_PACKINGSLIP, '', 'get', 'target="_blank"', true) . "\n";
      echo '          ' . zen_draw_hidden_field('oID', (int)$oID) . "\n";
      echo '          ' . zen_draw_hidden_field('split', 'true') . "\n";
      echo '          ' . zen_draw_hidden_field('reverse_count', 0) . "\n";
    for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
      if (DISPLAY_PRICE_WITH_TAX_ADMIN == 'true')
      {
        $priceIncTax = $currencies->format(zen_round(zen_add_tax($order->products[$i]['final_price'], $order->products[$i]['tax']),$currencies->get_decimal_places($order->info['currency'])) * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']);
      } else
      {
        $priceIncTax = $currencies->format(zen_add_tax($order->products[$i]['final_price'], $order->products[$i]['tax']) * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']);
      }
      echo '          <tr class="dataTableRow">' . "\n";
      echo '            <td class="dataTableContent" valign="middle" align="left">' . $order->products[$i]['qty'] . '&nbsp;x&nbsp;' . $order->products[$i]['name'];
      if (isset($order->products[$i]['attributes']) && (sizeof($order->products[$i]['attributes']) > 0)) {
        for ($j = 0, $k = sizeof($order->products[$i]['attributes']); $j < $k; $j++) {
          echo '<br /><nobr><small>&nbsp;<i> - ' . $order->products[$i]['attributes'][$j]['option'] . ': ' . nl2br(zen_output_string_protected($order->products[$i]['attributes'][$j]['value']));
          if ($order->products[$i]['attributes'][$j]['price'] != '0') echo ' (' . $order->products[$i]['attributes'][$j]['prefix'] . $currencies->format($order->products[$i]['attributes'][$j]['price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . ')';
          if ($order->products[$i]['attributes'][$j]['product_attribute_is_free'] == '1' and $order->products[$i]['product_is_free'] == '1') echo TEXT_INFO_ATTRIBUTE_FREE;
          echo '</i></small></nobr>';
        }
      }
      echo '            </td>' . "\n" .
           '            <td class="dataTableContent" valign="middle">' . $order->products[$i]['model'] . '</td>' . "\n" .
           '            <td class="dataTableContent" align="right" valign="middle">' . zen_display_tax_value($order->products[$i]['tax']) . '%</td>' . "\n" .
           '            <td class="dataTableContent" align="right" valign="middle"><strong>' .
                          $currencies->format($order->products[$i]['final_price'], true, $order->info['currency'], $order->info['currency_value']) .
// (Formating modified for Super Orders)
                          ($order->products[$i]['onetime_charges'] != 0 ? '<br />' . $currencies->format($order->products[$i]['onetime_charges'], true, $order->info['currency'], $order->info['currency_value']) : '') .
                        '</strong></td>' . "\n" .
           '            <td class="dataTableContent" align="right" valign="middle"><strong>' .
                          $currencies->format(zen_add_tax($order->products[$i]['final_price'], $order->products[$i]['tax']), true, $order->info['currency'], $order->info['currency_value']) .
                          ($order->products[$i]['onetime_charges'] != 0 ? '<br />' . $currencies->format(zen_add_tax($order->products[$i]['onetime_charges'], $order->products[$i]['tax']), true, $order->info['currency'], $order->info['currency_value']) : '') .
                        '</strong></td>' . "\n" .
           '            <td class="dataTableContent" align="right" valign="middle"><strong>' .
                          $currencies->format(zen_round($order->products[$i]['final_price'], $currencies->get_decimal_places($order->info['currency']))  * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) .
                          ($order->products[$i]['onetime_charges'] != 0 ? '<br />' . $currencies->format($order->products[$i]['onetime_charges'], true, $order->info['currency'], $order->info['currency_value']) : '') .
                        '</strong></td>' . "\n" .
           '            <td class="dataTableContent" align="right" valign="middle"><strong>' .
                          $priceIncTax .
                          ($order->products[$i]['onetime_charges'] != 0 ? '<br />' . $currencies->format(zen_add_tax($order->products[$i]['onetime_charges'], $order->products[$i]['tax']), true, $order->info['currency'], $order->info['currency_value']) : '') .
// (Formating modified for Super Orders)
                        '</strong></td>' . "\n";
      echo '          </tr>' . "\n";
    }
?>
<!-- End Products Detail //-->
          <tr>
            <?php 
	    		if ($parent_child->fields['split_from_order']) {
			 ?>
	<td valign="top" colspan="2">
	    <table border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td valign="top">&nbsp;&nbsp;<?php echo zen_image(DIR_WS_IMAGES . 'arrow_south_east.gif'); ?></td>
                <td valign="bottom" class="main"><input type="submit" value="<?php echo BUTTON_SPLIT; ?>"></td>
              </tr>
              <tr>
                <td class="smallText">&nbsp;</td>
                <td class="smallText" valign="top" align="center"><?php echo TEXT_DISPLAY_ONLY; ?></td>
              </tr>
            </table></td>

<!-- Begin Order Totals Modified for Super Orders//-->
<?php
             $colspan = 7;
           } else {
             $colspan = 8;
           }
?>
            </form> 
	<td align="right" colspan="<?php echo $colspan; ?>"><table border="0" cellspacing="0" cellpadding="2">
<?php 
	for ($i = 0, $n = sizeof($order->totals); $i < $n; $i++) {
	  echo ' <tr>' . "\n" .
' <td align="right" class="'. str_replace('_', '-', $order->totals[$i]['class']) . '-Text">' . $order->totals[$i]['title'] . '</td>' . "\n" .
'                <td align="right" class="'. str_replace('_', '-', $order->totals[$i]['class']) . '-Amount">' . $currencies->format($order->totals[$i]['value'], false) . '</td>' . "\n" .
' </tr>' . "\n";
	}

    // determine what to display on the "Amount Applied" and "Balance Due" lines
    $amount_applied = $currencies->format($so->amount_applied);
    $balance_due = $currencies->format($so->balance_due);

    // determine display format of the number
    // 'balanceDueRem' = customer still owes money
    // 'balanceDueNeg' = customer is due a refund
    // 'balanceDueNone' = order is all paid up
    // 'balanceDueNull' = balance nullified by order status
    switch ($so->status) {
      case 'completed':
        switch ($so->balance_due) {
          case 0:
            $class = 'balanceDueNone';
          break;
          case $so->balance_due < 0:
            $class = 'balanceDueNeg';
          break;
          case $so->balance_due > 0:
            $class = 'balanceDueRem';
          break;
        }
      break;

      case 'cancelled':
        switch ($so->balance_due) {
          case 0:
            $class = 'balanceDueNone';
          break;
          case $so->balance_due < 0:
            $class = 'balanceDueNeg';
          break;
          case $so->balance_due > 0:
            $class = 'balanceDueRem';
          break;
        }
      break;

      default:
        switch ($so->balance_due) {
          case 0:
            $class = 'balanceDueNone';
          break;
          case $so->balance_due < 0:
            $class = 'balanceDueNeg';
          break;
          case $so->balance_due > 0:
            $class = 'balanceDueRem';
          break;
        }
      break;
    }
?>
			<tr>
				<td align="right">&nbsp;</td>
				<td> 
<!-- BOF added to get currency type and value for totals -->
                <?php $dbc="select currency, currency_value from " . TABLE_ORDERS . " where orders_id ='" . $_GET['oID'] . "'";
                $result = mysql_query($dbc);
                $row = mysql_fetch_array ($result, MYSQL_NUM);
                $cu = $row[0];
                $cv = $row[1];
                ?>
<!-- EOF added to get currency type and value for totals -->
				</td>
			</tr>
			<tr>               
                <td align="right" class="ot-tax-Text"><?php echo ENTRY_AMOUNT_APPLIED_CUST .' (' . $cu.')' ; ?></td>
                <td align="right" class="ot-tax-Amount"><?php echo $currencies->format($so->amount_applied, true, $order->info['currency'], $order->info['currency_value']); ?></td>
			</tr>
			<tr>
                <td align="right" class="ot-tax-Text"><?php echo ENTRY_BALANCE_DUE_CUST .' (' . $cu.')'; ?></td>
                <td align="right" class="ot-tax-Amount"><?php echo $currencies->format($so->balance_due, true, $order->info['currency'], $order->info['currency_value']); ?></td>
			</tr>
			<tr>
				<td align="right">&nbsp;</td>
				<td align="right">&nbsp;</td>
			</tr>
			<tr>               
                <td align="right" class="ot-tax-Text"><?php echo ENTRY_AMOUNT_APPLIED_SHOP; ?></td>
                <td align="right" class="ot-tax-Amount"><?php echo $amount_applied; ?></td>
			</tr>
			<tr>
                <td align="right" class="ot-tax-Text"><?php echo ENTRY_BALANCE_DUE_SHOP ; ?></td>
                <td align="right" class="ot-tax-Amount"><?php echo $balance_due; ?></td>
			</tr>
        <table>
			<tr>
				<td align="right">&nbsp;</td>
			</tr>
        <?php if (!$so->status) { ?>
			<tr>
				<td align="right" valign="bottom">
					<table border="1" bgcolor="#ffff99" rules="none" frame="box" cellspacing="2" cellpadding="2">
						<tr>
							<td class="invoiceHeading" align="center"><strong><?php echo TABLE_HEADING_FINAL_STATUS; ?></strong></td>
						</tr>
						<tr>
							<td align="right"><?php echo '<a href="' . zen_href_link(FILENAME_ORDERS, 'action=mark_completed&oID=' . $oID) . '">' . zen_image_button('btn_completed.gif', ICON_MARK_COMPLETED) . '</a>'; ?></td>
						</tr>
						<tr>
							<td align="right"><?php echo '<a href="' . zen_href_link(FILENAME_ORDERS, 'action=mark_cancelled&oID=' . $oID) . '">' . zen_image_button('btn_cancelled.gif', ICON_MARK_CANCELLED) . '</a>'; ?></td>
						</tr>
					</table>
				</td>
			</tr>
        <?php } ?>
		</table>
	</td>
</tr>
</table>
</td>
</tr>
<!-- End Order Totals  Modified for Super Orders //-->

<!-- Begin Downloads //-->
<?php
  // show downloads
  require(DIR_WS_MODULES . 'orders_download.php');
?>
<!-- End Downloads //-->

      <tr>
        <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
      </tr>
<!-- Begin Super Orders Comments Header //-->
      <tr>
        <td class="main">
	<strong><?php echo zen_image(DIR_WS_IMAGES . 'icon_comment_add.png', TABLE_HEADING_STATUS_HISTORY) . '&nbsp;' . TABLE_HEADING_STATUS_HISTORY; ?></strong>
	</td>
      </tr>
<!-- End Super Orders Comments Header //-->
      <tr>
<!-- Begin Ty Package Tracker Modification (Minor formatting changes) //-->
        <td class="main" valign="top"><table border="1" cellspacing="0" cellpadding="5" width="60%">
<?php if (TY_TRACKER == 'True') { ?>

          <tr class="dataTableHeadingRow">
            <td class="dataTableHeadingContent smallText" valign="top"  width="15%"><strong><?php echo TABLE_HEADING_DATE_ADDED; ?></strong></td>
            <td class="dataTableHeadingContent smallText" align="center" valign="top" width="12%"><strong><?php echo TABLE_HEADING_CUSTOMER_NOTIFIED; ?></strong></td>
            <td class="dataTableHeadingContent smallText" valign="top" width="10%"><strong><?php echo TABLE_HEADING_STATUS; ?></strong></td>
<!-- End Ty Package Tracker Modification (Minor formatting changes) //-->
<!-- BEGIN TY TRACKER 3 - DISPLAY TRACKING ID IN COMMENTS TABLE ------------------------------->
	    <td class="dataTableHeadingContent smallText" valign="top" width="23%"><strong><?php echo TABLE_HEADING_TRACKING_ID; ?></strong></td>
<!-- END TY TRACKER 3 - DISPLAY TRACKING ID IN COMMENTS TABLE ------------------------------------------------------------>
<!-- Begin Ty Package Tracker Modification (Minor formatting changes) //-->
            <td class="dataTableHeadingContent smallText" valign="top" width="40%"><strong><?php echo TABLE_HEADING_COMMENTS; ?></strong></td>
<!-- End Ty Package Tracker Modification (Minor formatting changes) //-->
          </tr>
<?php 
// BEGIN TY TRACKER 4 - INCLUDE DATABASE FIELDS IN STATUS TABLE ------------------------------
    $orders_history = $db->Execute("select orders_status_id, date_added, customer_notified, track_id1, track_id2, track_id3, track_id4, track_id5, comments
                                    from " . TABLE_ORDERS_STATUS_HISTORY . "
                                    where orders_id = '" . zen_db_input($oID) . "'
                                    order by date_added");
// END TY TRACKER 4 - INCLUDE DATABASE FIELDS IN STATUS TABLE -----------------------------------------------------------

    if ($orders_history->RecordCount() > 0) {
      while (!$orders_history->EOF) {
        echo '          <tr>' . "\n" .
//<!-- Begin Ty Package Tracker Modification (Minor formatting changes) //-->
             '            <td class="smallText" valign="top">' . zen_datetime_short($orders_history->fields['date_added']) . '</td>' . "\n" .
//<!-- End Ty Package Tracker Modification (Minor formatting changes) //-->
             '            <td class="smallText" align="center">';
        if ($orders_history->fields['customer_notified'] == '1') {
          echo zen_image(DIR_WS_ICONS . 'tick.gif', TEXT_YES) . "</td>\n";
        } else if ($orders_history->fields['customer_notified'] == '-1') {
          echo zen_image(DIR_WS_ICONS . 'locked.gif', TEXT_HIDDEN) . "</td>\n";
        } else {
          echo zen_image(DIR_WS_ICONS . 'unlocked.gif', TEXT_VISIBLE) . "</td>\n";
        }
//<!-- Begin Ty Package Tracker Modification (Minor formatting changes) //-->
        echo '            <td class="smallText" valign="top">' . $orders_status_array[$orders_history->fields['orders_status_id']] . '</td>' . "\n";
//<!-- End Ty Package Tracker Modification (Minor formatting changes) //-->
// BEGIN TY TRACKER 5 - DEFINE TRACKING INFORMATION ----------------
        $display_track_id = '&nbsp;';
	$display_track_id .= (empty($orders_history->fields['track_id1']) ? '' : CARRIER_NAME_1 . ": <a href=" . CARRIER_LINK_1 . nl2br(zen_output_string_protected($orders_history->fields['track_id1'])) . ' target="_blank">' . nl2br(zen_output_string_protected($orders_history->fields['track_id1'])) . "</a>&nbsp;" );
	$display_track_id .= (empty($orders_history->fields['track_id2']) ? '' : CARRIER_NAME_2 . ": <a href=" . CARRIER_LINK_2 . nl2br(zen_output_string_protected($orders_history->fields['track_id2'])) . ' target="_blank">' . nl2br(zen_output_string_protected($orders_history->fields['track_id2'])) . "</a>&nbsp;" );
	$display_track_id .= (empty($orders_history->fields['track_id3']) ? '' : CARRIER_NAME_3 . ": <a href=" . CARRIER_LINK_3 . nl2br(zen_output_string_protected($orders_history->fields['track_id3'])) . ' target="_blank">' . nl2br(zen_output_string_protected($orders_history->fields['track_id3'])) . "</a>&nbsp;" );
	$display_track_id .= (empty($orders_history->fields['track_id4']) ? '' : CARRIER_NAME_4 . ": <a href=" . CARRIER_LINK_4 . nl2br(zen_output_string_protected($orders_history->fields['track_id4'])) . ' target="_blank">' . nl2br(zen_output_string_protected($orders_history->fields['track_id4'])) . "</a>&nbsp;" );
	$display_track_id .= (empty($orders_history->fields['track_id5']) ? '' : CARRIER_NAME_5 . ": <a href=" . CARRIER_LINK_5 . nl2br(zen_output_string_protected($orders_history->fields['track_id5'])) . ' target="_blank">' . nl2br(zen_output_string_protected($orders_history->fields['track_id5'])) . "</a>&nbsp;" );
        echo '            <td class="smallText" align="left" valign="top">' . $display_track_id . '</td>' . "\n";
// END TY TRACKER 5 - DEFINE TRACKING INFORMATION -------------------------------------------------------------------
//<!-- Begin Ty Package Tracker Modification (Minor formatting changes) //-->
        echo '            <td class="smallText" valign="top">' . nl2br(zen_db_output($orders_history->fields['comments'])) . '&nbsp;</td>' . "\n" .
//<!-- End Ty Package Tracker Modification (Minor formatting changes) //-->
             '          </tr>' . "\n";
        $orders_history->MoveNext();
      }
    } else {
        echo '          <tr>' . "\n" .
             '            <td class="smallText" colspan="5">' . TEXT_NO_ORDER_HISTORY . '</td>' . "\n" .
             '          </tr>' . "\n";
    }
?>

<?php } else { ?>
          <tr class="dataTableHeadingRow">
            <td class="dataTableHeadingContent smallText" valign="top"  width="20%"><strong><?php echo TABLE_HEADING_DATE_ADDED; ?></strong></td>
            <td class="dataTableHeadingContent smallText" align="center" valign="top" width="15%"><strong><?php echo TABLE_HEADING_CUSTOMER_NOTIFIED; ?></strong></td>
            <td class="dataTableHeadingContent smallText" valign="top" width="15%"><strong><?php echo TABLE_HEADING_STATUS; ?></strong></td>
            <td class="dataTableHeadingContent smallText" valign="top" width="50%"><strong><?php echo TABLE_HEADING_COMMENTS; ?></strong></td>
          </tr>
<?php
    $orders_history = $db->Execute("select orders_status_id, date_added, customer_notified, comments
                                    from " . TABLE_ORDERS_STATUS_HISTORY . "
                                    where orders_id = '" . zen_db_input($oID) . "'
                                    order by date_added");

    if ($orders_history->RecordCount() > 0) {
      while (!$orders_history->EOF) {
        echo '          <tr>' . "\n" .
             '            <td class="smallText" valign="top">' . zen_datetime_short($orders_history->fields['date_added']) . '</td>' . "\n" .
             '            <td class="smallText" align="center">';
        if ($orders_history->fields['customer_notified'] == '1') {
          echo zen_image(DIR_WS_ICONS . 'tick.gif', TEXT_YES) . "</td>\n";
        } else if ($orders_history->fields['customer_notified'] == '-1') {
          echo zen_image(DIR_WS_ICONS . 'locked.gif', TEXT_HIDDEN) . "</td>\n";
        } else {
          echo zen_image(DIR_WS_ICONS . 'unlocked.gif', TEXT_VISIBLE) . "</td>\n";
        }
        echo '            <td class="smallText" valign="top">' . $orders_status_array[$orders_history->fields['orders_status_id']] . '</td>' . "\n";
        echo '            <td class="smallText" valign="top">' . nl2br(zen_db_output($orders_history->fields['comments'])) . '&nbsp;</td>' . "\n" .
             '          </tr>' . "\n";
        $orders_history->MoveNext();
      }
    } else {
        echo '          <tr>' . "\n" .
             '            <td class="smallText" colspan="5">' . TEXT_NO_ORDER_HISTORY . '</td>' . "\n" .
             '          </tr>' . "\n";
    }
?>
<?php } ?>
        </table></td>
      </tr>
<!-- Begin Super Orders Edit Status History //-->
      <?php if (!$so->status) { ?>
    <tr>
        <td class="main">
		<?php echo zen_image(DIR_WS_IMAGES . 'icon_edit3.gif', ICON_EDIT_HISTORY) . '&nbsp;'; ?>
		<?php echo '<a href="javascript:popupWindow(\'' . zen_href_link(FILENAME_SUPER_EDIT, 'oID=' . $oID . '&target=history', 'NONSSL') . '\', \'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=yes,copyhistory=no,width=725,height=450,screenX=150,screenY=100,top=100,left=150\')">' . ICON_EDIT_HISTORY . '</a>';?>
		</td>
    </tr>
      <?php } ?>
<?php
    // hide status-updating code and cancel/complete buttons
    // if the order is already closed
    if (!$so->status) {
?>
<!-- End Super Orders Edit Status History //-->

     <tr>
        <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '3'); ?></td>
      </tr>
      <tr>
        <td>
        <table width="60%" border="0"  cellspacing="0" cellpadding="0">
        <tr>
              <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '3'); ?></td>
            </tr>
      <tr><?php echo zen_draw_form('status', FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=update_order', 'post', '', true); ?>
        <td class="main noprint">
		<strong><?php echo TABLE_HEADING_COMMENTS; ?></strong></td>
      </tr>
      <tr>
        <td class="noprint"><?php echo zen_draw_separator('pixel_trans.gif', '1', '3'); ?></td>
      </tr>
      <tr>
      <td class="main noprint"><?php echo zen_draw_textarea_field('comments', 'soft', '60', '5'); ?>
      </td>
      </tr>
      <tr>
        <td class="noprint"><?php echo zen_draw_separator('pixel_trans.gif', '1', '20'); ?></td>
      </tr>      
    </table>

<!-- BEGIN TY TRACKER 6 - ENTER TRACKING INFORMATION -->
<?php if (TY_TRACKER == 'True') { ?>
	<table border="0" cellpadding="3" cellspacing="0">          
		<tr>
			<td class="main"><strong><?php echo zen_image(DIR_WS_IMAGES . 'icon_track_add.png', ENTRY_ADD_TRACK) . '&nbsp;' . ENTRY_ADD_TRACK; ?></strong></td>
	    </tr>
		<tr valign="top">
			<td width="400">
				<table border="1" cellpadding="3" cellspacing="0" width="100%">
					<tr class="dataTableHeadingRow">
						<td class="dataTableHeadingContent smallText"><strong><?php echo TABLE_HEADING_CARRIER_NAME; ?></strong></td>
						<td class="dataTableHeadingContent smallText"><strong><?php echo TABLE_HEADING_TRACKING_ID; ?></strong></td>
					</tr>
							<?php for($i=1;$i<=5;$i++) {
								if(constant('CARRIER_STATUS_' . $i) == 'True') { ?>
							<tr>
							<td><?php echo constant('CARRIER_NAME_' . $i); ?></td><td valign="top"><?php echo zen_draw_input_field('track_id[' . $i . ']', '', 'size="50"'); ?></td>
							</tr>
							<?php } } ?>
				</table>
			</td>
		</tr>
	</table>      
<?php } ?>
<!-- END TY TRACKER 6 - ENTER TRACKING INFORMATION -->

       <table class="noprint" border="0" cellpadding="2" cellspacing="0">
			<tr>
				<td td colspan="3" class="noprint"><?php echo zen_draw_separator('pixel_trans.gif', '1', '20'); ?></td>
			</tr> 
			<tr>
				<td class="current_status"><strong><?php echo ENTRY_STATUS; ?></strong> <?php echo zen_draw_pull_down_menu('status', $orders_statuses, $order->info['orders_status']); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>&#124;</strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
				<td class="current_status"><strong><?php echo ENTRY_NOTIFY_CUSTOMER; ?></strong> [<?php echo zen_draw_radio_field('notify', '1', true) . '-' . TEXT_EMAIL . ' ' . zen_draw_radio_field('notify', '0', FALSE) . '-' . TEXT_NOEMAIL . ' ' . zen_draw_radio_field('notify', '-1', FALSE) . '-' . TEXT_HIDE; ?>]&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>&#124;</strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
				<td class="current_status"><strong><?php echo ENTRY_NOTIFY_COMMENTS; ?></strong> <?php echo zen_draw_checkbox_field('notify_comments', '', true); ?></td>
			</tr>        
			<tr>
				<td colspan="3" valign="top"><br /><?php echo zen_image_submit('button_update.gif', IMAGE_UPDATE); ?></td>
			</tr>
        </table>
		</td>
      </form></tr>
          </table></td>
<?php } ?>
        </tr></table></td>
      </tr>
<?php
// check if order has open gv
        $gv_check = $db->Execute("select order_id, unique_id
                                  from " . TABLE_COUPON_GV_QUEUE ."
                                  where order_id = '" . $_GET['oID'] . "' and release_flag='N' limit 1");
        if ($gv_check->RecordCount() > 0) {
          $goto_gv = '<a href="' . zen_href_link(FILENAME_GV_QUEUE, 'order=' . $_GET['oID']) . '">' . zen_image_button('button_gift_queue.gif',IMAGE_GIFT_QUEUE) . '</a>';
          echo '      <tr><td align="right"><table width="225"><tr>';
          echo '        <td align="center">';
          echo $goto_gv . '&nbsp;&nbsp;';
          echo '        </td>';
          echo '      </tr></table></td></tr>';
        }
?>
<?php
  /*
  ** BEGIN ORDER LISTING DISPLAY
  */
  } else {
?>
      <tr>
        <td width="100%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading">
	    <?php 
              echo HEADING_TITLE_ORDERS_LISTING . '&nbsp;&nbsp;' .
              '<INPUT class="normal_button button" TYPE="BUTTON" VALUE="' . BOX_CUSTOMERS_SUPER_BATCH_STATUS . '" ONCLICK="window.location.href=\'' . zen_href_link(FILENAME_SUPER_BATCH_STATUS, '') . '\'">' .
              '&nbsp;&nbsp;' .
              '<INPUT class="normal_button button" TYPE="BUTTON" VALUE="' . BOX_CUSTOMERS_SUPER_BATCH_FORMS . '" ONCLICK="window.location.href=\'' . zen_href_link(FILENAME_SUPER_BATCH_FORMS, '') . '\'">';
            ?>
	    </td>
            <td class="pageHeading" align="right"><?php echo zen_draw_separator('pixel_trans.gif', 1, HEADING_IMAGE_HEIGHT); ?></td>
            <td align="right"><table border="0" width="100%" cellspacing="0" cellpadding="0">
              <tr><?php echo zen_draw_form('orders', FILENAME_ORDERS, '', 'get', '', true); ?>
                <td class="smallText" align="right"><?php echo HEADING_TITLE_SEARCH . ' ' . zen_draw_input_field('oID', '', 'size="12"') . zen_draw_hidden_field('action', 'edit') . zen_hide_session_id(); ?></td>
              </form></tr>
              <tr><?php echo zen_draw_form('status', FILENAME_ORDERS, '', 'get', '', true); ?>
                <td class="smallText" align="right">
		<?php
                  echo HEADING_TITLE_STATUS . ' ' . zen_draw_pull_down_menu('status', array_merge(array(array('id' => '', 'text' => TEXT_ALL_ORDERS)), $orders_statuses), $_GET['status'], 'onChange="this.form.submit();"');
                    echo zen_hide_session_id();
                ?>
		</td>
              </form></tr>
            </table></td>
              </tr>
            </table></td>
          </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="smallText"><?php echo TEXT_LEGEND . ' ' . zen_image(DIR_WS_IMAGES . 'icon_status_red.gif', TEXT_BILLING_SHIPPING_MISMATCH, 10, 10) . ' ' . TEXT_BILLING_SHIPPING_MISMATCH; ?>
          </td>
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
<?php
// Sort Listing
          switch ($_GET['list_order']) {
              case "id-asc":
              $disp_order = "c.customers_id";
              break;
              case "firstname":
              $disp_order = "c.customers_firstname";
              break;
              case "firstname-desc":
              $disp_order = "c.customers_firstname DESC";
              break;
              case "lastname":
              $disp_order = "c.customers_lastname, c.customers_firstname";
              break;
              case "lastname-desc":
              $disp_order = "c.customers_lastname DESC, c.customers_firstname";
              break;
              case "company":
              $disp_order = "a.entry_company";
              break;
              case "company-desc":
              $disp_order = "a.entry_company DESC";
              break;
              default:
              $disp_order = "c.customers_id DESC";
          }
?>
                <td class="dataTableHeadingContent" align="left"><?php echo TABLE_HEADING_ORDERS_ID; ?></td>
                <td class="dataTableHeadingContent" align="left"><?php echo TABLE_HEADING_PAYMENT_METHOD; ?></td>
                <td class="dataTableHeadingContent" align="left"><?php echo TABLE_HEADING_CUSTOMERS; ?></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_ORDER_TOTAL; ?></td>
                <td class="dataTableHeadingContent" align="center"><?php echo TABLE_HEADING_DATE_PURCHASED; ?></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_STATUS; ?></td>
				<td class="dataTableHeadingContent" align="center"><?php echo TABLE_HEADING_CUSTOMER_COMMENTS; ?></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_ACTION; ?>&nbsp;</td>
              </tr>

<?php
// Only one or the other search
// create search_orders_products filter
  $search = '';
  $new_table = '';
  $new_fields = '';
  if (isset($_GET['search_orders_products']) && zen_not_null($_GET['search_orders_products'])) {
    $new_fields = '';
    $search_distinct = ' distinct ';
    $new_table = " left join " . TABLE_ORDERS_PRODUCTS . " op on (op.orders_id = o.orders_id) ";
    $keywords = zen_db_input(zen_db_prepare_input($_GET['search_orders_products']));
    $search = " and (op.products_model like '%" . $keywords . "%' or op.products_name like '" . $keywords . "%')";
    if (substr(strtoupper($_GET['search_orders_products']), 0, 3) == 'ID:') {
      $keywords = TRIM(substr($_GET['search_orders_products'], 3));
      $search = " and op.products_id ='" . (int)$keywords . "'";
    }
  } else {
?>
<?php
// create search filter
  $search = '';
  if (isset($_GET['search']) && zen_not_null($_GET['search'])) {
    $search_distinct = ' ';
    $keywords = zen_db_input(zen_db_prepare_input($_GET['search']));
    $search = " and (o.customers_city like '%" . $keywords . "%' or o.customers_postcode like '%" . $keywords . "%' or o.date_purchased like '%" . $keywords . "%' or o.billing_name like '%" . $keywords . "%' or o.billing_company like '%" . $keywords . "%' or o.billing_street_address like '%" . $keywords . "%' or o.delivery_city like '%" . $keywords . "%' or o.delivery_postcode like '%" . $keywords . "%' or o.delivery_name like '%" . $keywords . "%' or o.delivery_company like '%" . $keywords . "%' or o.delivery_street_address like '%" . $keywords . "%' or o.billing_city like '%" . $keywords . "%' or o.billing_postcode like '%" . $keywords . "%' or o.customers_email_address like '%" . $keywords . "%' or o.customers_name like '%" . $keywords . "%' or o.customers_company like '%" . $keywords . "%' or o.customers_street_address  like '%" . $keywords . "%' or o.customers_telephone like '%" . $keywords . "%' or o.ip_address  like '%" . $keywords . "%')";
    $new_table = '';
//    $new_fields = ", o.customers_company, o.customers_email_address, o.customers_street_address, o.delivery_company, o.delivery_name, o.delivery_street_address, o.billing_company, o.billing_name, o.billing_street_address, o.payment_module_code, o.shipping_module_code, o.ip_address ";
  }
} // eof: search orders or orders_products
    $new_fields = ", o.customers_company, o.customers_email_address, o.customers_street_address, o.delivery_company, o.delivery_name, o.delivery_street_address, o.billing_company, o.billing_name, o.billing_street_address, o.payment_module_code, o.shipping_module_code, o.ip_address ";
?>
<?php
    if (isset($_GET['cID'])) {
      $cID = zen_db_prepare_input($_GET['cID']);
      $orders_query_raw =   "select o.orders_id, o.customers_id, o.customers_name, o.payment_method, o.shipping_method, o.date_purchased, o.last_modified, o.currency, o.currency_value, s.orders_status_name, ot.text as order_total" .
                            $new_fields . "
                            from (" . TABLE_ORDERS_STATUS . " s, " .
                            TABLE_ORDERS . " o " .
                            $new_table . ")
                            left join " . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id and ot.class = 'ot_total') " . "
                            where o.customers_id = '" . (int)$cID . "' and o.orders_status = s.orders_status_id and s.language_id = '" . (int)$_SESSION['languages_id'] . "' order by orders_id DESC";

//echo '<BR><BR>I SEE A: ' . $orders_query_raw . '<BR><BR>';

    } elseif ($_GET['status'] != '') {
      $status = zen_db_prepare_input($_GET['status']);
      $orders_query_raw = "select o.orders_id, o.customers_id, o.customers_name, o.payment_method, o.shipping_method, o.date_purchased, o.last_modified, o.currency, o.currency_value, s.orders_status_name, ot.text as order_total" .
                          $new_fields . "
                          from (" . TABLE_ORDERS_STATUS . " s, " .
                          TABLE_ORDERS . " o " .
                          $new_table . ")
                          left join " . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id and ot.class = 'ot_total') " . "
                          where o.orders_status = s.orders_status_id and s.language_id = '" . (int)$_SESSION['languages_id'] . "' and s.orders_status_id = '" . (int)$status . "'  " .
                          $search . " order by o.orders_id DESC";

//echo '<BR><BR>I SEE B: ' . $orders_query_raw . '<BR><BR>';

    } else {
      $orders_query_raw = "select " . $search_distinct . " o.orders_id, o.customers_id, o.customers_name, o.payment_method, o.shipping_method, o.date_purchased, o.last_modified, o.currency, o.currency_value, s.orders_status_name, ot.text as order_total" .
                          $new_fields . "
                          from (" . TABLE_ORDERS_STATUS . " s, " .
                          TABLE_ORDERS . " o " .
                          $new_table . ")
                          left join " . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id and ot.class = 'ot_total') " . "
                          where (o.orders_status = s.orders_status_id and s.language_id = '" . (int)$_SESSION['languages_id'] . "')  " .
                          $search . " order by o.orders_id DESC";

//echo '<BR><BR>I SEE C: ' . $orders_query_raw . '<BR><BR>';

    }

// Split Page
// reset page when page is unknown
if (($_GET['page'] == '' or $_GET['page'] <= 1) and $_GET['oID'] != '') {
  $check_page = $db->Execute($orders_query_raw);
  $check_count=1;
  if ($check_page->RecordCount() > MAX_DISPLAY_SEARCH_RESULTS_ORDERS) {
    while (!$check_page->EOF) {
      if ($check_page->fields['orders_id'] == $_GET['oID']) {
        break;
      }
      $check_count++;
      $check_page->MoveNext();
    }
    $_GET['page'] = round((($check_count/MAX_DISPLAY_SEARCH_RESULTS_ORDERS)+(fmod_round($check_count,MAX_DISPLAY_SEARCH_RESULTS_ORDERS) !=0 ? .5 : 0)),0);
  } else {
    $_GET['page'] = 1;
  }
}

//    $orders_query_numrows = '';
  $orders_split = new splitPageResults($_GET['page'], MAX_DISPLAY_SEARCH_RESULTS_ORDERS, $orders_query_raw, $orders_query_numrows);
  $orders = $db->Execute($orders_query_raw);
  while (!$orders->EOF) {
    if ((!isset($_GET['oID']) || (isset($_GET['oID']) && ($_GET['oID'] == $orders->fields['orders_id']))) && !isset($oInfo)) {
      $oInfo = new objectInfo($orders->fields);
    }

    if (isset($oInfo) && is_object($oInfo) && ($orders->fields['orders_id'] == $oInfo->orders_id)) {
      echo '              <tr id="defaultSelected" class="dataTableRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('oID', 'action')) . 'oID=' . $oInfo->orders_id . '&action=edit', 'NONSSL') . '\'">' . "\n";
    } else {
        echo '              <tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('oID')) . 'oID=' . $orders->fields['orders_id'], 'NONSSL') . '\'">' . "\n";
    }
    $show_difference = '';
    if (($orders->fields['delivery_name'] != $orders->fields['billing_name'] and $orders->fields['delivery_name'] != '')) {
        $show_difference = zen_image(DIR_WS_IMAGES . 'icon_status_red.gif', TEXT_BILLING_SHIPPING_MISMATCH, 10, 10) . '&nbsp;';
      }
      if (($orders->fields['delivery_street_address'] != $orders->fields['billing_street_address'] and $orders->fields['delivery_street_address'] != '')) {
        $show_difference = zen_image(DIR_WS_IMAGES . 'icon_status_red.gif', TEXT_BILLING_SHIPPING_MISMATCH, 10, 10) . '&nbsp;';
    }
      $show_payment_type = $orders->fields['payment_module_code'] . '<br />' . $orders->fields['shipping_module_code'];
    $close_status = so_close_status($orders->fields['orders_id']);
    if ($close_status) $class = "status-" . $close_status['type'];
    else $class = "dataTableContent";
?>
               <td class="dataTableContent" align="left"><?php echo $show_difference . $orders->fields['orders_id']; ?></td>
               <td class="dataTableContent" align="left"><?php echo $show_payment_type; ?></td>
<!-- 
                <td class="dataTableContent"><?php echo '<a href="' . zen_href_link(FILENAME_CUSTOMERS, 'cID=' . $orders->fields['customers_id'], 'NONSSL') . '">' . zen_image(DIR_WS_ICONS . 'preview.gif', ICON_PREVIEW . ' ' . TABLE_HEADING_CUSTOMERS) . '</a>&nbsp;' . $orders->fields['customers_name'] . ($orders->fields['customers_company'] != '' ? '<br />' . $orders->fields['customers_company'] : ''); ?></td>
 //-->
               <td class="dataTableContent">
		<?php 
		  echo '<a href="' . zen_href_link(FILENAME_CUSTOMERS, 'cID=' . $orders->fields['customers_id'] . '&action=edit', 'NONSSL') . '">' . zen_image(DIR_WS_IMAGES . 'icon_cust_info.gif', MINI_ICON_INFO) . '</a>&nbsp;';
                  echo '<a href="' . zen_href_link(FILENAME_ORDERS, 'cID=' . $orders->fields['customers_id'], 'NONSSL') . '">' . zen_image(DIR_WS_IMAGES . 'icon_cust_orders.gif', MINI_ICON_ORDERS) . '</a>&nbsp;';
                  echo '<a href="' . zen_href_link(FILENAME_MAIL, 'origin=super_orders.php&mode=NONSSL&selected_box=tools&customer=' . $orders->fields['customers_email_address'] . '&cID=' . $orders->fields['customers_id'], 'NONSSL') . '">' . $orders->fields['customers_name'] . '</a>';
                ?>
		</td>
                <td class="dataTableContent" align="right"><?php echo strip_tags($orders->fields['order_total']); ?></td>
                <td class="dataTableContent" align="center"><?php echo zen_datetime_short($orders->fields['date_purchased']); ?></td>
                <td class="dataTableContent" align="right"><?php echo $orders->fields['orders_status_name']; ?></td>
             
	 
 	
	  <td class="dataTableContent" align="center"><?php echo (zen_get_orders_comments($orders->fields['orders_id']) == '' ? '' : zen_image(DIR_WS_IMAGES . 'icon_yellow_on.gif', TEXT_COMMENTS_YES, 16, 16)); ?></td>
	
<!-- 
                <td class="dataTableContent" align="right"><?php echo '<a href="' . zen_href_link(FILENAME_EDIT_ORDERS, zen_get_all_get_params(array('oID', 'action')) . 'oID=' . $orders->fields['orders_id'] . '&action=edit', 'NONSSL') . '">' . zen_image(DIR_WS_IMAGES . 'icon_edit.gif', ICON_EDIT) . '</a>'; ?><?php echo '<a href="' . zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('oID', 'action')) . 'oID=' . $orders->fields['orders_id'] . '&action=edit', 'NONSSL') . '">' . zen_image(DIR_WS_IMAGES . 'icon_details.gif', IMAGE_DETAILS) . '</a>'; ?><?php if (isset($oInfo) && is_object($oInfo) && ($orders->fields['orders_id'] == $oInfo->orders_id)) { echo zen_image(DIR_WS_IMAGES . 'icon_arrow_right.gif', ''); } else { echo '<a href="' . zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('oID')) . 'oID=' . $orders->fields['orders_id'], 'NONSSL') . '">' . zen_image(DIR_WS_IMAGES . 'icon_info.gif', IMAGE_ICON_INFO) . '</a>'; } ?>&nbsp;</td>
 //-->
			    <td class="dataTableContent" align="right"><?php 
		  if (isset($oInfo) && is_object($oInfo) && ($orders->fields['orders_id'] == $oInfo->orders_id)) { echo zen_image(DIR_WS_IMAGES . 'icon_arrow_right.gif', '');
                  } else {
                    echo '<a href="' . zen_href_link(FILENAME_ORDERS, 'oID=' . $orders->fields['orders_id'] . '&action=edit', 'NONSSL') . '">' . zen_image(DIR_WS_IMAGES . 'icon_details.gif', ICON_ORDER_DETAILS) . '</a>&nbsp';
                    echo '<a href="' . zen_href_link(FILENAME_SUPER_SHIPPING_LABEL, 'oID=' . $orders->fields['orders_id']) . '" TARGET="_blank">' . zen_image(DIR_WS_IMAGES . 'icon_shipping_label.gif', ICON_ORDER_SHIPPING_LABEL) . '</a>&nbsp;';
                    echo '<a href="' . zen_href_link(FILENAME_INVOICE, 'oID=' . $orders->fields['orders_id']) . '" TARGET="_blank">' . zen_image(DIR_WS_IMAGES . 'icon_invoice.gif', ICON_ORDER_INVOICE) . '</a>&nbsp;';
                    echo '<a href="' . zen_href_link(FILENAME_PACKINGSLIP, 'oID=' . $orders->fields['orders_id']) . '" TARGET="_blank">' . zen_image(DIR_WS_IMAGES . 'icon_packingslip.gif', ICON_ORDER_PACKINGSLIP) . '</a>&nbsp;';
//		Begin - add Edit Orders link to order list icons
              if (SO_EDIT_ORDERS_SWITCH == 'True') {
                    echo '<a href="' . zen_href_link(FILENAME_EDIT_ORDERS, 'oID=' . $orders->fields['orders_id'] . '&action=edit', 'NONSSL') . '">' . zen_image(DIR_WS_IMAGES . 'icon_edit.gif', ICON_ORDER_EDIT) . '</a>&nbsp';
              }
//		End - add Edit Orders link to order list icons
                    echo '<a href="' . zen_href_link(FILENAME_SUPER_DATA_SHEET, 'oID=' . $orders->fields['orders_id']) . '" target="_blank">' . zen_image(DIR_WS_IMAGES . 'icon_print.gif', ICON_ORDER_PRINT) . '</a>&nbsp;';
                    echo '<a href="' . zen_href_link(FILENAME_ORDERS, 'oID=' . $orders->fields['orders_id'] . '&action=delete', 'NONSSL') . '">' . zen_image(DIR_WS_IMAGES . 'icon_delete2.gif', ICON_ORDER_DELETE) . '</a>&nbsp;';
                    echo '<a href="' . zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('oID')) . 'oID=' . $orders->fields['orders_id'], 'NONSSL') . '">' . zen_image(DIR_WS_IMAGES . 'icon_info.gif', IMAGE_ICON_INFO) . '</a>';
                  }
                ?>&nbsp;</td>
              </tr>
<?php
      $orders->MoveNext();
    }
?>
              <tr>
                <td colspan="5"><table border="0" width="100%" cellspacing="0" cellpadding="2">
                  <tr>
                    <td class="smallText" valign="top"><?php echo $orders_split->display_count($orders_query_numrows, MAX_DISPLAY_SEARCH_RESULTS_ORDERS, $_GET['page'], TEXT_DISPLAY_NUMBER_OF_ORDERS); ?></td>
                    <td class="smallText" align="right"><?php echo $orders_split->display_links($orders_query_numrows, MAX_DISPLAY_SEARCH_RESULTS_ORDERS, MAX_DISPLAY_PAGE_LINKS, $_GET['page'], zen_get_all_get_params(array('page', 'oID', 'action'))); ?></td>
                  </tr>
<?php
  if (isset($_GET['search']) && zen_not_null($_GET['search'])) {
?>
                  <tr>
                    <td class="smallText" align="right" colspan="2">
                      <?php 
		        echo '<a href="' . zen_href_link(FILENAME_ORDERS, '', 'NONSSL') . '">' . zen_image_button('button_reset.gif', IMAGE_RESET) . '</a>';
                        if (isset($_GET['search']) && zen_not_null($_GET['search'])) {
                          $keywords = zen_db_input(zen_db_prepare_input($_GET['search']));
                          echo '<br />' . TEXT_INFO_SEARCH_DETAIL_FILTER . $keywords;
                        }
                      ?>
</td>
                  </tr>
<?php
  }
?>
                </table></td>
              </tr>
            </table></td>
<?php
  $heading = array();
  $contents = array();

  switch ($action) {
    case 'delete':
      $heading[] = array('text' => '<strong>' . TEXT_INFO_HEADING_DELETE_ORDER . $oInfo->orders_id . '</strong>');
      $contents = array('form' => zen_draw_form('orders', FILENAME_ORDERS, zen_get_all_get_params(array('oID', 'action')) . '&action=deleteconfirm', 'post', '', true) . zen_draw_hidden_field('oID', $oInfo->orders_id));
//      $contents[] = array('text' => TEXT_INFO_DELETE_INTRO . '<br /><br /><strong>' . $cInfo->customers_firstname . ' ' . $cInfo->customers_lastname . '</strong>');
      $contents[] = array('text' => TEXT_INFO_DELETE_INTRO . '<br /><br /><strong>' . ENTRY_ORDER_ID . $oInfo->orders_id . '<br />' . $oInfo->order_total . '<br />' . $oInfo->customers_name . ($oInfo->customers_company != '' ? '<br />' . $oInfo->customers_company : '') . '</strong>');
      $contents[] = array('text' => '<br />' . zen_draw_checkbox_field('restock') . ' ' . TEXT_INFO_RESTOCK_PRODUCT_QUANTITY);
      $contents[] = array('align' => 'center', 'text' => '<br />' . zen_image_submit('button_delete.gif', ICON_ORDER_DELETE) . ' <a href="' . zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('oID', 'action')) . 'oID=' . $oInfo->orders_id, 'NONSSL') . '">' . zen_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>');
      break;
    default:
      if (isset($oInfo) && is_object($oInfo)) {
        $heading[] = array('text' => '<strong>[' . $oInfo->orders_id . ']&nbsp;&nbsp;' . zen_datetime_short($oInfo->date_purchased) . '</strong>');
        $contents[] = array('align' => 'center', 'text' => '<a href="' . zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('oID', 'action')) . 'oID=' . $oInfo->orders_id . '&action=edit', 'NONSSL') . '">' . zen_image_button('button_details.gif', IMAGE_ORDER_DETAILS) . '</a>&nbsp;<a href="' . zen_href_link(FILENAME_SUPER_SHIPPING_LABEL, 'oID=' . $oInfo->orders_id) . '" TARGET="_blank">' . zen_image_button('button_shippinglabel.gif', SUPER_IMAGE_SHIPPING_LABEL) . '</a>');
        $contents[] = array('align' => 'center', 'text' => '<a href="' . zen_href_link(FILENAME_INVOICE, 'oID=' . $oInfo->orders_id) . '" TARGET="_blank">' . zen_image_button('button_invoice.gif', SUPER_IMAGE_ORDERS_INVOICE) . '</a> <a href="' . zen_href_link(FILENAME_PACKINGSLIP, 'oID=' . $oInfo->orders_id) . '" TARGET="_blank">' . zen_image_button('button_packingslip.gif', SUPER_IMAGE_ORDERS_PACKINGSLIP) . '</a>');
// Begin - Add Edit Order button to order order list page
    if (SO_EDIT_ORDERS_SWITCH == 'True') {
        $contents[] = array('align' => 'center', 'text' => '<a href="' . zen_href_link(FILENAME_EDIT_ORDERS, zen_get_all_get_params(array('oID', 'action')) . 'oID=' . $oInfo->orders_id . '&action=edit', 'NONSSL') . '">' . zen_image_button('button_edit.gif', ICON_ORDER_EDIT) . '</a>&nbsp;<a href="' . zen_href_link(FILENAME_SUPER_DATA_SHEET, 'oID=' . $oInfo->orders_id) . '" target="_blank">' . zen_image_button('btn_print.gif', SUPER_IMAGE_ORDER_PRINT) . '</a>&nbsp;<a href="' . zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('oID', 'action')) . 'oID=' . $oInfo->orders_id . '&action=delete', 'NONSSL') . '">' . zen_image_button('button_delete.gif', ICON_ORDER_DELETE) . '</a>');
    } 
    else { 
        $contents[] = array('align' => 'center', 'text' => '<a href="' . zen_href_link(FILENAME_SUPER_DATA_SHEET, 'oID=' . $oInfo->orders_id) . '" target="_blank">' . zen_image_button('btn_print.gif', SUPER_IMAGE_ORDER_PRINT) . '</a>&nbsp;<a href="' . zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('oID', 'action')) . 'oID=' . $oInfo->orders_id . '&action=delete', 'NONSSL') . '">' . zen_image_button('button_delete.gif', ICON_ORDER_DELETE) . '</a>');
    }
// End - Add Edit Order button to order order list page
	$contents[] = array('text' => '<br />' . TEXT_DATE_ORDER_CREATED . ' ' . zen_date_short($oInfo->date_purchased));
        $contents[] = array('text' => '<br />' . $oInfo->customers_email_address);
	$contents[] = array('text' => TEXT_INFO_IP_ADDRESS . ' ' . $oInfo->ip_address);
        if (zen_not_null($oInfo->last_modified)) $contents[] = array('text' => TEXT_DATE_ORDER_LAST_MODIFIED . ' ' . zen_date_short($oInfo->last_modified));
        $contents[] = array('text' => '<br />' . TEXT_INFO_PAYMENT_METHOD . ' '  . $oInfo->payment_method);
	$contents[] = array('text' => TEXT_INFO_SHIPPING_METHOD . ' '  . $oInfo->shipping_method);

// check if order has open gv
        $gv_check = $db->Execute("select order_id, unique_id
                                  from " . TABLE_COUPON_GV_QUEUE ."
                                  where order_id = '" . $oInfo->orders_id . "' and release_flag='N' limit 1");
        if ($gv_check->RecordCount() > 0) {
          $goto_gv = '<a href="' . zen_href_link(FILENAME_GV_QUEUE, 'order=' . $oInfo->orders_id) . '">' . zen_image_button('button_gift_queue.gif',IMAGE_GIFT_QUEUE) . '</a>';
          $contents[] = array('text' => '<br />' . zen_image(DIR_WS_IMAGES . 'pixel_black.gif','','100%','3'));
          $contents[] = array('align' => 'center', 'text' => $goto_gv);
        }
      }

// indicate if comments exist
      $orders_history_query = $db->Execute("select orders_status_id, date_added, customer_notified, comments from " . TABLE_ORDERS_STATUS_HISTORY . " where orders_id = '" . $oInfo->orders_id . "' and comments !='" . "'" );
      if ($orders_history_query->RecordCount() > 0) {
        $contents[] = array('align' => 'left', 'text' => '<br />' . TABLE_HEADING_COMMENTS);
      }

      $contents[] = array('text' => '<br />' . zen_image(DIR_WS_IMAGES . 'pixel_black.gif','','100%','3'));
      $order = new order($oInfo->orders_id);
      $contents[] = array('text' => 'Products Ordered: ' . sizeof($order->products) );
      for ($i=0; $i<sizeof($order->products); $i++) {
        $contents[] = array('text' => $order->products[$i]['qty'] . '&nbsp;x&nbsp;' . $order->products[$i]['name']);

        if (sizeof($order->products[$i]['attributes']) > 0) {
          for ($j=0; $j<sizeof($order->products[$i]['attributes']); $j++) {
            $contents[] = array('text' => '&nbsp;<i> - ' . $order->products[$i]['attributes'][$j]['option'] . ': ' . nl2br(zen_output_string_protected($order->products[$i]['attributes'][$j]['value'])) . '</i></nobr>' );
          }
        }
        if ($i > MAX_DISPLAY_RESULTS_ORDERS_DETAILS_LISTING and MAX_DISPLAY_RESULTS_ORDERS_DETAILS_LISTING != 0) {
          $contents[] = array('align' => 'left', 'text' => TEXT_MORE);
          break;
        }
      }

      if (sizeof($order->products) > 0) {
// Begin add Edit Orders button to lower buttons
    if (SO_EDIT_ORDERS_SWITCH == 'True') {
        $contents[] = array('align' => 'center', 'text' => '<a href="' . zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('oID', 'action')) . 'oID=' . $oInfo->orders_id . '&action=edit', 'NONSSL') . '">' . zen_image_button('button_details.gif', IMAGE_ORDER_DETAILS) . '</a>&nbsp;<a href="' . zen_href_link(FILENAME_EDIT_ORDERS, zen_get_all_get_params(array('oID', 'action')) . 'oID=' . $oInfo->orders_id . '&action=edit', 'NONSSL') . '">' . zen_image_button('button_edit.gif', ICON_ORDER_EDIT) . '</a>');
    } 
    else { 
        $contents[] = array('align' => 'center', 'text' => '<a href="' . zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('oID', 'action')) . 'oID=' . $oInfo->orders_id . '&action=edit', 'NONSSL') . '">' . zen_image_button('button_details.gif', ICON_ORDER_DETAILS) . '</a>');
    }
// End add Edit Orders button to lower buttons
      }
      break;
  }

  if ( (zen_not_null($heading)) && (zen_not_null($contents)) ) {
?>
            <td width="25%" valign="top"><table border="0" cellspacing="0" cellpadding="0" width="100%" valign="top">
              <tr>
                <td colspan="2" valign="top">
<?php
    $box = new box;
    echo $box->infoBox($heading, $contents);
?>
</td>
              </tr>
<!-- SHORTCUT ICON LEGEND BOF-->
              <tr>
                <td><table border="0" cellspacing="0" cellpadding="2" width="100%" valign="top">
                  <tr>
                    <td colspan="2">&nbsp;</td>
                  </tr>
                  <tr>
                    <td class="smallText" colspan="2"><strong><?php echo TEXT_ICON_LEGEND; ?></strong><br />&nbsp;</td>
                  </tr>
                  <tr>
                    <td align="center"><?php echo zen_image(DIR_WS_IMAGES . 'icon_status_red.gif', TEXT_BILLING_SHIPPING_MISMATCH, 10, 10); ?></td>
                    <td class="smallText"><?php echo TEXT_BILLING_SHIPPING_MISMATCH; ?></td>
                  </tr>
                  <tr>
                    <td align="center"><?php echo zen_image(DIR_WS_IMAGES . 'icon_cust_info.gif', MINI_ICON_INFO); ?></td>
                    <td class="smallText"><?php echo MINI_ICON_INFO; ?></td>
                  </tr>
                  <tr>
                    <td align="center"><?php echo zen_image(DIR_WS_IMAGES . 'icon_cust_orders.gif', MINI_ICON_ORDERS); ?></td>
                    <td class="smallText"><?php echo MINI_ICON_ORDERS; ?></td>
                  </tr>
                  <tr>
                    <td colspan="2"><?php echo zen_draw_separator('pixel_black.gif'); ?></td>
                  </tr>
                  <tr>
                    <td align="center"><?php echo zen_image(DIR_WS_IMAGES . 'icon_details.gif', ICON_ORDER_DETAILS); ?></td>
                    <td class="smallText"><?php echo ICON_ORDER_DETAILS; ?></td>
                  </tr>					
                  <tr>
                    <td align="center"><?php echo zen_image(DIR_WS_IMAGES . 'icon_shipping_label.gif', ICON_ORDER_SHIPPING_LABEL); ?></td>
                    <td class="smallText"><?php echo ICON_ORDER_SHIPPING_LABEL; ?></td>
                  </tr>
                  <tr>
                    <td align="center"><?php echo zen_image(DIR_WS_IMAGES . 'icon_invoice.gif', ICON_ORDER_INVOICE); ?></td>
                    <td class="smallText"><?php echo ICON_ORDER_INVOICE; ?></td>
                  </tr>
                  <tr>
                    <td align="center"><?php echo zen_image(DIR_WS_IMAGES . 'icon_packingslip.gif', ICON_ORDER_PACKINGSLIP); ?></td>
                    <td class="smallText"><?php echo ICON_ORDER_PACKINGSLIP; ?></td>
                  </tr>
<!-- Begin - add Edit Orders to legend icons -->
<?php if (SO_EDIT_ORDERS_SWITCH == 'True') { ?>
                  <tr>
                    <td align="center"><?php echo zen_image(DIR_WS_IMAGES . 'icon_edit.gif', ICON_ORDER_EDIT); ?></td>
                    <td class="smallText"><?php echo ICON_ORDER_EDIT; ?></td>
                  </tr>	
<?php } ?>
<!-- End - add Edit Orders to legend icons -->
                  <tr>
                    <td align="center"><?php echo zen_image(DIR_WS_IMAGES . 'icon_print.gif', ICON_ORDER_PRINT); ?></td>
                    <td class="smallText"><?php echo ICON_ORDER_PRINT; ?></td>
                  </tr>	
                  <tr>
                    <td align="center"><?php echo zen_image(DIR_WS_IMAGES . 'icon_delete2.gif', ICON_ORDER_DELETE); ?></td>
                    <td class="smallText"><?php echo ICON_ORDER_DELETE; ?></td>
                  </tr>	
                  <tr>
                    <td align="center"><?php echo zen_image(DIR_WS_IMAGES . 'icon_info.gif', IMAGE_ICON_INFO); ?></td>
                    <td class="smallText"><?php echo IMAGE_ICON_INFO; ?></td>
                  </tr>	
                </table></td>
              </tr>
<!-- SHORTCUT ICON LEGEND EOF -->
            </table></td>
<?php
  }  // END if ( (zen_not_null($heading)) && (zen_not_null($contents)) )
?>
          </tr>
        </table></td>
      </tr>
<?php
  }
?>
    </table></td>
<!-- body_text_eof //-->
  </tr>
</table>
<!-- body_eof //-->

<!-- footer //-->
<div class="footer-area">
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
</div>
<!-- footer_eof //-->
<br />
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>