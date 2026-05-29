<?php


declare(strict_types=1);

use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

test('insertEnterprise com dados validos retorna 200 com status true', function () {
    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/enterprise/insert')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'nomeExibicao' => 'Verstappen',
            'nomeLegal' => 'dan dan dan LTD',
            'numeroDocumento' => '932.109.876-00',
            'registroSecundario' => '9654321',
            'ativo' => 'true'
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new app\controller\Company())->insert($request, $response);

    $result->getBody()->rewind();


    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(201);
    
    expect($json['status'])->toBeTrue();

    expect($json['msg'])->toContain('Salvo com sucesso!');


});