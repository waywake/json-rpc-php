<?php
include_once dirname(__DIR__).'/vendor/autoload.php';

$client = new \JsonRpc\Client();

$res = $client->call('math.subtract',['12','23',500]);

var_dump($res);