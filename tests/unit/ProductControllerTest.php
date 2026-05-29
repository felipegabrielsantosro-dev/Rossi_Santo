<?php


declare(strict_types=1);

use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

test('insertProduct com dados validos retorna 200 com status true', function () {
    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/produto/insert')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'nome' => 'Pelucia',
            'codigo_barra' => '2026071307676',
            'unidade' => 'panda',
            'preco_compra' => '20.00',
            'preco_venda' => '27.00',
            'descricao' => 'Pequena',
            'ativo' => 'true'
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new app\controller\Product())->insert($request, $response);

    $result->getBody()->rewind();


    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(201);

    expect($json['status'])->toBeTrue();

    expect($json['msg'])->toContain('Salvo com sucesso!');


});