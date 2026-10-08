<?php

require_once('/var/www/authlib/rabbitMQLib.inc');
require_once('/var/www/authlib/get_host_info.inc');

$type = $_POST["type"] ?? "";

$client = new rabbitMQClient(
    "/var/www/authlib/rabbitmq.local.ini",
    "testServer"
);

switch ($type)
{
    case "login":

        $request = [
            "type" => "login",
            "email" =>
                $_POST["email"] ?? "",
            "password" =>
                $_POST["password"] ?? ""
        ];

        $response =
            $client->send_request($request);

        if (!empty($response["success"])) {

            setcookie(
                "auth_token",
                $response["sessionId"],
                [
                    "expires" =>
                        time() + 86400,

                    "path" => "/",
                    "httponly" => true,
                    "samesite" => "Lax"
                ]
            );
        }

        echo json_encode($response);
        break;


    case "register":

        $request = [
            "type" => "register",

            "username" =>
                $_POST["username"] ?? "",

            "password" =>
                $_POST["password"] ?? "",

            "first_name" =>
                $_POST["first_name"] ?? null,

            "last_name" =>
                $_POST["last_name"] ?? null,

            "email" =>
                $_POST["email"] ?? null
        ];

        echo json_encode(
            $client->send_request($request)
        );

        break;


    default:

        echo json_encode([
            "success" => false,
            "message" =>
                "Unsupported request type"
        ]);
}
