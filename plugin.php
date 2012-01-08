<?php
define('MULTICOLLECTIONS_DIR', dirname(__FILE__) );
require_once MULTICOLLECTIONS_DIR . '/helpers/functions.php';
require_once(MULTICOLLECTIONS_DIR. "/MultiCollectionsPlugin.php");
$mc = new MultiCollectionsPlugin;
$mc->setUp();
