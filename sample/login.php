<?php


if (!isset($_POST))
{
	$msg = "NO POST MESSAGE SET, POLITELY FUCK OFF";
	echo json_encode($msg);
	exit(0);
}
$request = $_POST;
$response = "unsupported request type, politely FUCK OFF";
switch ($request["type"])
{
	case "signup":
		$response = 
		[
			"username" => $request["username"],
			"first_name" => $request["first_name"],
			"last_name" => $request["last_name"],
			"email" => $request["email"],
			"successt" => true,
			"message" => "Signup data received."
		];
	break;
}
echo json_encode($response);
exit(0);

?>
