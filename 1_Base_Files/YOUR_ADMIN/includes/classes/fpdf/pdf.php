<?php

/*
 * 
 */

class PDF extends FPDF {

  private $firstOrderPageNumber = 1;
  private $shipTo = '';

  function __construct($orientation = 'P', $unit = 'mm', $size = 'A4') {
    $this->FPDF($orientation, $unit, $size);
    $this->SetMargins(18, 63, 18);
    $this->SetFont('Arial', '', 10);
  }

  /* Page header */

  function Header() {
    if (file_exists(DIR_WS_IMAGES . LCSD_PACKING_LOGO_LARGE)) {
      $max_image_width = 252;
      $max_image_height = 82;
      list($image_width, $image_height) = getimagesize(DIR_WS_IMAGES . LCSD_PACKING_LOGO_LARGE);
      if (($image_width / $max_image_width) > ($image_height / $max_image_height)) {
        $image_width = min($max_image_width, $image_width);
        $image_height = 0;
      } else {
        $image_height = min($max_image_height, $image_height);
        $image_width = 0;
      }
      $this->Image(DIR_WS_IMAGES . LCSD_PACKING_LOGO_LARGE, 27, 63, $image_width, $image_height);
    }
    $this->SetFont('Arial', 'B', 12);
    $this->SetXY(18, 166);
    $storeNameArray = explode("\n", STORE_NAME_ADDRESS);
    foreach ($storeNameArray as $storNameLine) {
      if ((strpos($storNameLine, STORE_NAME) === false) || (LCSD_SHOW_STORE_NAME == 'True')) {
        $this->MultiCell(270, 14, $storNameLine, 0, 'J');
      }
    }
    if (TAX_ID_NUMBER != '') {
      $this->MultiCell(270, 20, HEADER_TAX_ID . ':  ' . TAX_ID_NUMBER, 0, 'J');
    }

    if (LCSD_SHOW_SHIPPING_LABEL == 'False') {
      $this->SetFont('Arial', 'B', 28);
      $this->SetXY(350, 85);
      $this->MultiCell(270, 24, 'Packing Slip');
    } else {
      $this->Rect(306, 63, 288, 180);
      if (file_exists(DIR_WS_IMAGES . LCSD_PACKING_LOGO_SMALL)) {
        $this->Image(DIR_WS_IMAGES . LCSD_PACKING_LOGO_SMALL, 507, 72, 78);
      }
      $this->SetFont('Arial', 'B', 12);
      $this->SetXY(339, 155);

      if ($this->PageNo() == $this->firstOrderPageNumber) {
        $this->MultiCell(220, 14, $this->shipTo);
      } else {
        $this->SetXY(339, 155);
        $this->SetFont('Arial', '', 56);
        $this->SetTextColor(128, 128, 128);
        $this->MultiCell(220, 54, 'Page ' . ($this->PageNo() - $this->firstOrderPageNumber + 1));

        $this->SetXY(339, 155);
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0, 0, 0);
        $this->MultiCell(220, 14, $this->shipTo);
      }
    }
    $this->SetLineWidth(.5);
    $this->Line(18, 252, 594, 252);
    $this->SetXY(18, 261);
  }

  function SetHeaderVars($order, $merge_selected_oids = true) {
    $this->firstOrderPageNumber = $this->PageNo() + 1;
    $this->shipTo = zen_address_format($order->delivery['format_id'], $order->delivery, 0, '', "\n");
    if ($order->delivery['street_address'] == '') {
      $this->shipTo = zen_address_format($order->customer['format_id'], $order->customer, 0, '', "\n");
    }
  }

}
