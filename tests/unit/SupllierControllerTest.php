<?php


declare(strict_types=1);

use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

test('insertSupplier com dados validos retorna 200 com status true', function () {
    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/supplier/insert')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'nomeExibicao' => 'PANINI LTDA',
            'nomeLegal' => 'tamo colecionando as figurinhas',
            'numeroDocumento' => '654.321.238-09',
            'registroSecundario' => '234.567.890-1',
            'dataRegistro' => '2020-02-09',
            'ativo' => 'true'
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new app\controller\Supplier())->insert($request, $response);

    $result->getBody()->rewind();


    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(201);

    expect($json['status'])->toBeTrue();

    expect($json['msg'])->toContain('Fornecedor salvo com sucesso!');


});