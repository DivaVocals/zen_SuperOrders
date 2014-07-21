<?php
/*
//////////////////////////////////////////////////////////////////////////////////
//  SUPER ORDERS v3.0								//
//                                                                  		//
//  Based on Super Order 2.0                                        		//
//  By Frank Koehl - PM: BlindSide (original author)                		//
//                                                                  		//
//  Super Orders Updated by:							//
//  ~ JT of GTICustom								//
//  ~ C Jones Over the Hill Web Consulting (http://overthehillweb.com)		//
//  ~ Loose Chicken Software Development, david@loosechicken.com		//
//                                                      			//
//  Powered by Zen-Cart (www.zen-cart.com)              			//
//  Portions Copyright (c) 2005 The Zen-Cart Team       			//
//                                                     				//
//  Released under the GNU General Public License       			//
//  available at www.zen-cart.com/license/2_0.txt       			//
//  or see "license.txt" in the downloaded zip          			//
//////////////////////////////////////////////////////////////////////////////////
//DESCRIPTION: Language file definitions for super_payment_types.php		//
//////////////////////////////////////////////////////////////////////////////////
// $Id: super_payment_types.php v 2010-10-24 $
*/

define('HEADING_TITLE', 'Manage Payment Types');

define('TABLE_HEADING_PAYMENT_NAME', 'Payment Name');
define('TABLE_HEADING_PAYMENT_CODE', 'Payment Code');
define('TABLE_HEADING_LANGUAGE', 'Language');
define('TABLE_HEADING_ACTION', 'Action');

define('BOX_HEADING_NEW_PAYMENT_TYPE', 'New Payment Type');
define('BOX_HEADING_EDIT_PAYMENT_TYPE', 'Edit Payment Type');
define('BOX_HEADING_DELETE_PAYMENT_TYPE', 'Delete Payment Type');

define('BOX_NEW_INTRO', 'Enter the name and code that you will use to refer to this payment type.<br><br>Note that the code for the payment <strong>must be unique</strong>.');
define('BOX_EDIT_INTRO', 'Make any changes to the payment type, then click <strong>Update</strong>');
define('BOX_DELETE_INTRO', 'Are you sure you want to delete this payment type?');

define('BOX_PAYMENT_TYPE_FULL', 'Payment Name');
define('BOX_PAYMENT_TYPE_CODE', 'Payment Code');

define('TEXT_CANT_DELETE', 'Delete Prohibited');
define('BOX_TEXT_CANT_DELETE_INFO', 'This payment type currently has <strong>%s</strong> payments and/or refunds attached to it.  Those records must first be deleted or moved to another payment type before you can delete this one.');


define('TEXT_DISPLAY_NUMBER_OF_PAYMENT_TYPES', 'Displaying <b>%d</b> to <b>%d</b> (of <b>%d</b> types)');
