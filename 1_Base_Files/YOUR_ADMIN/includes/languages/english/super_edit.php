<?php
/*
//////////////////////////////////////////////////////////////////////////
//  SUPER ORDERS v3.0                                               	//
//                                                                  	//
//  Based on Super Order 2.0                                        	//
//  By Frank Koehl - PM: BlindSide (original author)                	//
//                                                                  	//
//  Super Orders Updated by:						//
//  ~ JT of GTICustom							//
//  ~ C Jones Over the Hill Web Consulting (http://overthehillweb.com)	//
//  ~ Loose Chicken Software Development, david@loosechicken.com	//
//                                                      		//
//  Powered by Zen-Cart (www.zen-cart.com)              		//
//  Portions Copyright (c) 2005 The Zen-Cart Team       		//
//                                                     			//
//  Released under the GNU General Public License       		//
//  available at www.zen-cart.com/license/2_0.txt       		//
//  or see "license.txt" in the downloaded zip          		//
//////////////////////////////////////////////////////////////////////////
//  DESCRIPTION: Language file definitions for super_edit.php		//
//////////////////////////////////////////////////////////////////////////
// $Id: super_edit.php v 2010-10-24 $
*/

// include the language file for super_orders.php since they overlap so much
require(DIR_WS_LANGUAGES . $_SESSION['language'] . '/' . FILENAME_SUPER_ORDERS . '.php');

define('HEADER_EDIT_ORDER', 'Edit Admin Comments for Order #');
define('HEADER_SPLIT_ORDER', 'Split Products from Order #');

define('TABLE_HEADING_DELETE_COMMENTS', 'Delete');

define('BUTTON_CANCEL', 'Cancel');
define('BUTTON_SUBMIT', 'Submit');

define('SUCCESS_ORDER_SPLIT', 'Success: This order has been split, and a new order created. New (child) order # is: ');
define('TEXT_SPLIT_EXPLAIN', 'Selected products will move to Order #');
define('COMMENTS_SPLIT_OLD', 'This order has been split.  New (child) order # is: ');
define('COMMENTS_SPLIT_NEW', 'New order was created from split order.  Original (parent) order # is: ');

define('NL', "\n");

