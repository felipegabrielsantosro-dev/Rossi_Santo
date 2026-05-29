<?php

require 'vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$request = (new Slim\Psr7\Factory\ServerRequestFactory())
    ->createServerRequest('POST', '/customer/insert')
    ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
    ->withParsedBody([
        'nomeExibicao' => 'felipe gabriel',
        'nomeLegal' => 'anderlate',
        'numeroDocumento' => '432.109.877-00',
        'registroSecundario' => '1230987',
        'dataRegistro' => '05/03/1995',
        'ativo' => 'true',
    ]);

$response = (new Slim\Psr7\Factory\ResponseFactory())->createResponse();
$result = (new app\controller\Customer())->insert($request, $response);

$result->getBody()->rewind();

echo 'STATUS=' . $result->getStatusCode() . PHP_EOL;
echo 'BODY=' . $result->getBody()->getContents() . PHP_EOL;
