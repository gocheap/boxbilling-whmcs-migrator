<?php

require_once __DIR__ . "/vendor/autoload.php";

DB::$dbName = "boxbilling";
DB::$host = "172.28.128.1";
DB::$user = "root";
DB::$password = "root";

function sendApiCommand($data)
{
    $whmcsUser = "";
    $whmcsPass = "";
    $client = new \GuzzleHttp\Client();

    $data = array_merge($data, [
        'username' => $whmcsUser,
        'password' => md5($whmcsPass),
        'responsetype' => "json",
    ]);

    $response = $client->post('http://grendelhosting.lcl/portal/includes/api.php', [
        'body' => $data
    ]);

    return $response->json();
}

function randomString($length = 12)
{
    return substr(md5(rand()), 0, $length);
}

function importHostingOrder($clientId, $order)
{
    $service = DB::queryFirstRow("SELECT * FROM service_hosting WHERE id = %i", $order['service_id']);

    list($regperiod, $billingcycle) = parsePeriod($order['period']);

    $response = sendApiCommand([
        'action' => 'addorder',
        'pid' => 1,
        'domain' => $service['sld'] . $service['tld'],
        'billingcycle' => $billingcycle,
        'regperiod' => $regperiod,
        'clientid' => $clientId,
        'paymentmethod' => 'paypal',
    ]);

    if($response['result'] != 'success'){
        echo "Error: ".$response['message'] . PHP_EOL;
    }
}

function parsePeriod($period)
{
    $firstChar = substr($period, 0, 1);
    $secondChar = substr($period, 1, 1);

    if($secondChar == 'M'){
        return [$firstChar, "monthly"];
    } else {
        return [$firstChar, "annually"];
    }
}

function importDomainOrder($clientId, $order)
{
//    $service = DB::queryFirstRow("SELECT * FROM service_domain WHERE id = %i", $order['service_id']);
//
//    list($regperiod, $billingcycle) = parsePeriod($order['period']);
//
//    $response = sendApiCommand([
//        'action' => 'addorder',
//        'domain' => $service['sld'] . $service['tld'],
//        'billingcycle' => $billingcycle,
//        'regperiod' => $regperiod,
//        'clientid' => $clientId,
//        'domaintype' => 'register',
//        'paymentmethod' => 'paypal',
//    ]);
//
//    if($response['result'] != 'success'){
//        echo "Error: ".$response['message'] . PHP_EOL;
//    }
}

$clients = DB::query('SELECT * FROM client WHERE id IN (SELECT client_id FROM client_order WHERE status = "active")');

foreach($clients as $client){
    $response = sendApiCommand([
        'action' => 'addclient',
        'firstname' => $client['first_name'],
        'lastname' => $client['last_name'],
        'companyname' => $client['company'],
        'email' => $client['email'],
        'address1' => $client['address_1'],
        'address2' => $client['address_2'],
        'city' => $client['city'],
        'state' => $client['state'],
        'postcode' => $client['postcode'],
        'country' => $client['country'],
        'phonenumber' => $client['phone'],
        'password2' => randomString(),
        'currency' => 1,
        'clientip' => $client['ip'],
        'noemail' => true,
        'skipvalidation' => true,
    ]);

    if($response['result'] != 'success'){
        echo "Error: ".$response['message'] . PHP_EOL;
        continue;
    }

    $clientId = $response['clientid'];

    $orders = DB::query("SELECT * FROM client_order WHERE client_id = ".$client['id']." AND status = 'active'");
    foreach($orders as $order){
        if($order['service_type'] == 'domain'){
            importDomainOrder($clientId, $order);
            continue;
        }

        if($order['service_type'] == 'hosting'){
            importHostingOrder($clientId, $order);
            continue;
        }

        echo "Undefined service type: ".$order['service_type'] . PHP_EOL;
    }
}

echo "done";