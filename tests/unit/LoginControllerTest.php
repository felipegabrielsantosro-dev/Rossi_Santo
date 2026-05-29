<?php


declare(strict_types=1);

use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;


test('preRegister com dados validos retorna 200 com status true', function () {
    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/authentication/preregister')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'nome' => 'Felipe',
            'sobrenome' => 'Gabriel Santos De Jesus',
            'cpf' => '999.999.999-99',
            'rg' => '98765',
            'senhaCadastro' => '123456',
            'email' => 'felipegabrielsantosro@gmail.com',
            'telefone' => '6999999988'
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new app\controller\Login())->preRegister($request, $response);

    $result->getBody()->rewind();


    $json = json_decode($result->getBody()->getContents(), true);
    #Capturamos o codigo de resposta e o status do json
    #Foi criado.
    expect($result->getStatusCode())->toBe(201);

    expect($json['status'])->toBeTrue();

    expect($json['msg'])->toContain('Usuário criado com sucesso');


});