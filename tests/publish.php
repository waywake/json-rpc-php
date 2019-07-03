<?php
include_once dirname(__DIR__) . '/vendor/autoload.php';

$client = new \JsonRpc\Client([
    'app' => 'abc',
    'client' => [
        'default' => [
            'base_uri' => 'http://localhost:8080',
        ]
    ],
]);

$client->endpoint('default');

$json = json_encode([
    'order_id' => '123',
    'user_id' => '456',
]);

try {
    $res = $client->call('topic.produce', ['abc', $json]);
    var_dump($res);
} catch (Exception $e) {
    var_dump($e->getCode(),$e->getMessage());
}
