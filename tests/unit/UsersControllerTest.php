<?php


declare(strict_types=1);

use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/*
Testa a criação de um usuário.
Envia um POST com nome, sobrenome, CPF, RG e ativo.
Espera que a resposta retorne status HTTP 201,
que o campo status seja true e a mensagem "Usuário salvo com sucesso".
*/
test('insertUsers com dados validos retorna 200 com status true', function () {
    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/usuario/insert')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'nome' => 'lionel',
            'sobrenome' => 'oanao',
            'cpf' => '753.967.532-09',
            'rg' => '4546676',
            'ativo' => 'true',
        ]);

    $response = (new ResponseFactory())->createResponse();
    $result = (new app\controller\Users())->insert($request, $response);

    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(201);
    expect($json['status'])->toBeTrue();
    expect($json['msg'])->toContain('Usuário salvo com sucesso!');
});

/*
Testa a atualização de um usuário. Primeiro insere um usuário para obter o ID gerado,
depois faz um POST de update com esse ID e novos dados.
Espera HTTP 201, status true e a mensagem "Usuário alterado com sucesso!".
*/
test('updateUsers com dados validos retorna 201 com status true', function () {
    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/usuario/insert')
        ->withParsedBody([
            'nome' => 'messiasano',
            'sobrenome' => 'oet',
            'cpf' => '876.341.423-34',
            'rg' => '56365432',
            'ativo' => 'true',
        ]);

    $response = (new ResponseFactory())->createResponse();
    $result = (new app\controller\Users())->insert($request, $response);
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);
    $id = $json['id'];

    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/usuario/update')
        ->withParsedBody([
            'id' => $id,
            'nome' => 'marcianooet',
            'sobrenome' => 'anaozudo',
            'cpf' => '876.341.423-34',
            'rg' => '56365432',
            'ativo' => 'true',
        ]);

    $response = (new ResponseFactory())->createResponse();
    $result = (new app\controller\Users())->update($request, $response);
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(201);
    expect($json['status'])->toBeTrue();
    expect($json['msg'])->toContain('Usuário alterado com sucesso!');
});

/*
Testa o erro de update sem informar o ID. Envia um POST de update sem o campo id.
Espera HTTP 403, status false e a mensagem "Por favor informe o ID do registro".
*/
test('updateUsers sem ID retorna 403 com status false', function () {
    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/usuario/update')
        ->withParsedBody([
            'nome' => 'Sem ID',
        ]);

    $response = (new ResponseFactory())->createResponse();
    $result = (new app\controller\Users())->update($request, $response);
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(403);
    expect($json['status'])->toBeFalse();
    expect($json['msg'])->toContain('Por favor informe o ID do registro');
});

/*
Testa a remoção de um usuário existente. Primeiro insere um usuário,
captura o ID e depois faz o delete com esse ID. Espera HTTP 200,
status true e a mensagem "Usuário removido com sucesso!".
*/
test('deleteUsers com ID valido retorna 200 com status true', function () {
    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/usuario/insert')
        ->withParsedBody([
            'nome' => 'oliso',
            'sobrenome' => 'ingress',
            'cpf' => '098.578.766-56',
            'rg' => '5576633',
            'ativo' => 'true',
        ]);

    $response = (new ResponseFactory())->createResponse();
    $result = (new app\controller\Users())->insert($request, $response);
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);
    $id = $json['id'];

    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/usuario/delete')
        ->withParsedBody(['id' => $id]);

    $response = (new ResponseFactory())->createResponse();
    $result = (new app\controller\Users())->delete($request, $response);
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(200);
    expect($json['status'])->toBeTrue();
    expect($json['msg'])->toContain('Usuário removido com sucesso!');
});

/*
Testa o erro de delete sem informar o ID. Envia um POST de delete com body vazio.
Espera HTTP 403, status false e a mensagem "Informe o código do usuário".
*/
test('deleteUsers sem ID retorna 403 com status false', function () {
    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/usuario/delete')
        ->withParsedBody([]);

    $response = (new ResponseFactory())->createResponse();
    $result = (new app\controller\Users())->delete($request, $response);
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(403);
    expect($json['status'])->toBeFalse();
    expect($json['msg'])->toContain('Informe o código do usuário');
});

/*
Testa a listagem paginada de usuários.
Envia parâmetros típicos de um DataTable (start, length, order, search vazio).
Espera HTTP 200 e que o JSON contenha as chaves recordsTotal, recordsFiltered e data (array).
*/
test('listingdata retorna estrutura correta com status 200', function () {
    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/usuario/listingdata')
        ->withParsedBody([
            'start' => 0,
            'length' => 10,
            'order' => [['column' => '0', 'dir' => 'DESC']],
            'search' => ['value' => ''],
        ]);

    $response = (new ResponseFactory())->createResponse();
    $result = (new app\controller\Users())->listingdata($request, $response);
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(200);
    expect($json)->toHaveKey('recordsTotal');
    expect($json)->toHaveKey('recordsFiltered');
    expect($json)->toHaveKey('data');
    expect($json['data'])->toBeArray();
});

/*
Testa a busca/filtro na listagem. Igual ao anterior, mas com search: 'lionel'.
Espera HTTP 200, recordsFiltered como inteiro e data como array,
verificando que o filtro funciona sem erros.
*/
test('listingdata com termo de busca retorna resultado filtrado', function () {
    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/usuario/listingdata')
        ->withParsedBody([
            'start' => 0,
            'length' => 10,
            'order' => [['column' => '1', 'dir' => 'ASC']],
            'search' => ['value' => 'lionel'],
        ]);

    $response = (new ResponseFactory())->createResponse();
    $result = (new app\controller\Users())->listingdata($request, $response);
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(200);
    expect($json['recordsFiltered'])->toBeInt();
    expect($json['data'])->toBeArray();
});

/*
Todos os testes seguem o fluxo:
monta request → chama o controller →
lê o JSON da response →
valida status HTTP e campos do JSON.
*/