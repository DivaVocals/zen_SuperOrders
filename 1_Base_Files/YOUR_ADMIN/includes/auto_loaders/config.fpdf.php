<?php
// FPDF

if (!defined('IS_ADMIN_FLAG')) {
  die('Illegal Access');
}

$autoLoadConfig[999][] = array('autoType'=>'class',
                               'loadFile'=>'fpdf.php',
                               'classPath'=>DIR_WS_CLASSES . 'fpdf/');

$autoLoadConfig[999][] = array('autoType'=>'class',
                               'loadFile'=>'pdf.php',
                               'classPath'=>DIR_WS_CLASSES . 'fpdf/');
