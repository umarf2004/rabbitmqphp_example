<?php

require_once("login.php.inc");

try {
	$db = new loginDB();
	echo "Database connection successful\n";

   }
catch (Exception $e) {
	echo "ERROR: " . $e->getMessage() . "\n";
}
