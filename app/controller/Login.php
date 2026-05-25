<?php

namespace app\controller;

final class Login extends Base
{
    public function login($request, $response)
    {
        try {
            return $this->getTwig()
                ->render($response, $this->setView('login'), [
                    'titulo' => 'Início',
                ])
                ->withHeader('Content-Type', 'text/html')
                ->withStatus(200);
        } catch (\Exception $e) {
            var_dump($e->getMessage());
        }
    }
    public function authenticate($request, $response)
    {
        # Recupera as credenciais enviadas no corpo da requisição
        $form = $request->getParsedBody();
        $login = $form['login'] ?? null;
        $senha = $form['senha'] ?? null;
        # Bloqueia se algum campo veio vazio
        if (is_null($login) || is_null($senha)) {
            return $this->json($response, ['status' => false, 'msg' => 'Por favor informe seu usuário e senha!', 'id' => 0]);
        }
        # Verifica se a sessão está em "lockout" por excesso de tentativas falhas
        if (isset($_SESSION['login_locked_until']) && $_SESSION['login_locked_until'] > time()) {
            return $this->json($response, ['status' => false, 'msg' => 'Muitas tentativas. Tente novamente em alguns minutos.', 'id' => 0], 429);
        }
        try {
            # Começa a montar a query: SELECT * FROM vw_user
            $qb = \app\database\DB::select('*')
                ->from('vw_user');

            # Define o valor que será procurado nos três campos
            # O Doctrine cria um "placeholder seguro" no lugar do valor real,
            # protegendo a aplicação contra SQL injection.
            $login = $qb->createNamedParameter($login);

            # Monta a cláusula WHERE com três condições ligadas por OR:
            # WHERE cpf = :login OR email = :login OR whatsapp = :login
            $qb->where('cpf = ' . $login)
                ->orWhere('email = '    . $login)
                ->orWhere('telefone = ' . $login);

            # Executa a query e busca um único registro (a primeira linha encontrada)
            $user = $qb->fetchAssociative();

            # Hash bcrypt pré-computado e inválido, usado quando o usuário não existe (proteção contra timing attack)
            $dummyHash = '$2y$10$CwTycUXWue0Thq9StjUM0uJ8.k3.kK1m3Sv7lJ1uG9N9Yvb.MqYsa';

            # Sempre executa password_verify, mesmo sem usuário, para manter tempo de resposta constante
            $senhaValida = password_verify($senha, $user['senha'] ?? $dummyHash);

            # Falha de autenticação: mensagem genérica + contador de tentativas
            if (!$user || !$senhaValida) {
                # Incrementa o contador de tentativas falhas da sessão atual
                $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                # Após 5 falhas, bloqueia novas tentativas por 15 minutos (rate limiting básico)
                if ($_SESSION['login_attempts'] >= 5) {
                    $_SESSION['login_locked_until'] = time() + 900;
                    $_SESSION['login_attempts'] = 0;
                }
                return $this->json($response, ['status' => false, 'msg' => 'Verifique seu e-mail e senha e tente novamente!', 'id' => 0], 403);
            }

            # Verifica se o usuário está ativo
            if (!$user['ativo']) {
                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'Seu acesso ainda não foi liberado. Aguarde a aprovação do administrador.',
                    'id'     => 0,
                ], 403);
            }

            # Login válido: zera contadores de tentativa e lockout
            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);

            # Regenera o ID da sessão para mitigar session fixation
            session_regenerate_id(true);

            # Renova o hash da senha se o algoritmo/custo padrão tiver mudado
            if (password_needs_rehash($user['senha'], PASSWORD_DEFAULT)) {
                \app\database\DB::connection()->update(
                    'users',
                    [
                        'senha'         => password_hash($senha, PASSWORD_DEFAULT),
                        'atualizado_em' => date('Y-m-d H:i:s'),
                    ],
                    ['id' => $user['id']],
                );
            }

            # Remove o hash da senha antes de gravar o usuário na sessão (evita expor credencial)
            unset($user['senha']);

            # Persiste o usuário autenticado na sessão (fonte de verdade do estado)
            $_SESSION['user'] = $user;
            $_SESSION['user']['logado'] = true;

            # Calcula o tempo de vida da sessão a partir do php.ini, com fallback de 3600s
            $lifetime = (int) (ini_get('session.gc_maxlifetime') ?: 3600);

            # Monta o payload do JWT usando o ID do usuário como subject (identificador estável e único)
            $payload = [
                'iat' => time(),                 # Momento de emissão
                'exp' => time() + $lifetime,     # Expiração alinhada à sessão
                'sub' => (string) $user['id'], # Subject = ID do usuário
            ];

            # Assina o token JWT com a chave secreta da aplicação
            $jwt = \Firebase\JWT\JWT::encode($payload, SECRET_KEY, 'HS256');

            # Determina se a conexão está em HTTPS (define o atributo Secure do cookie)
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

            # Define o cookie auth_token usando COOKIE_DOMAIN (constante de configuração, imune a Host Header Injection)
            setcookie('auth_token', $jwt, [
                'expires'  => time() + $lifetime,
                'path'     => '/',
                'domain' => $_SERVER['HTTP_HOST'], #Usa dinamicamente o domínio correto
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            # Registra na sessão o horário de criação e o horário previsto de expiração (formato H:i:s correto)
            $_SESSION['user']['sessao_criada_em'] = (new \DateTime())->format('Y-m-d H:i:s');
            $_SESSION['user']['sessao_expira_em'] = (new \DateTime())->modify("+{$lifetime} seconds")->format('Y-m-d H:i:s');

            # Retorna a resposta de sucesso ao cliente
            return $this->json($response, [
                'status'           => true,
                'msg'              => 'Seja bem vindo de volta!',
                'id'               => $user['id'],
                'sessao_expira_em' => $_SESSION['user']['sessao_expira_em']
            ], 200);
        } catch (\PDOException $e) {
            # Erro de banco: loga internamente e responde de forma genérica
            error_log('[auth][DB] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Não foi possível concluir o login. Tente novamente.', 'id' => 0], 500);
        } catch (\UnexpectedValueException | \DomainException $e) {
            # Erro específico do Firebase JWT (chave inválida, payload malformado, etc.)
            error_log('[auth][JWT] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Não foi possível concluir o login. Tente novamente.', 'id' => 0], 500);
        } catch (\Throwable $e) {
            error_log('[auth][GERAL] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro inesperado. Tente novamente.', 'id' => 0], 500);
        }
    }
    public function logout($request, $response)
    {
        $_SESSION = [];
        session_destroy();

        setcookie('auth_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => $_SERVER['HTTP_HOST'],
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        return $response
            ->withHeader('Location', '/login')
            ->withStatus(302);
    }

    public function preRegister($request, $response)
    {
        $form = $request->getParsedBody();

        // Captura os dados informados pelo usuário no formulário de pré-cadastro
        $nome      = $form['nome']      ?? null;
        $sobrenome = $form['sobrenome'] ?? null;
        $cpf       = $form['cpf']       ?? null;
        $rg        = $form['rg']        ?? null;
        $senha     = $form['senhaCadastro']  ?? null;
        $email     = $form['email']     ?? null;
        $telefone  = $form['telefone']  ?? null;

        // Criamos o array associativo com os dados do usuário, onde a
        // chave é o nome da coluna no banco de dados e o valor é o dado
        // informado pelo usuário.
        $DataUser = [
            'nome'         => $nome . ' ' . $sobrenome,
            'cpf'          => $cpf,
            'rg'           => $rg,
            'senha'        => password_hash($senha, PASSWORD_DEFAULT),
            'criado_em'    => date('Y-m-d H:i:s'),
            'atualizado_em' => date('Y-m-d H:i:s'),
        ];

        try {
            $conn = \app\database\DB::connection();
            $conn->beginTransaction();

            // Insere os dados no database com o Doctrine e recebe o ID do usuário criado.
            $conn->insert('users', $DataUser);
            $id_usuario = (int) $conn->lastInsertId();

            // Insere os dados do email do usuário na base.
            if (!empty($email)) {
                $DataEmail = [
                    'id_usuario'   => $id_usuario,
                    'tipo'         => 'EMAIL',
                    'contato'      => $email,
                    'criado_em'    => date('Y-m-d H:i:s'),
                    'atualizado_em' => date('Y-m-d H:i:s'),
                ];
                $conn->insert('contact', $DataEmail);
            }

            // Insere os dados do telefone do usuário na base.
            if (!empty($telefone)) {
                $DataTel = [
                    'id_usuario'   => $id_usuario,
                    'tipo'         => 'TELEFONE',
                    'contato'      => $telefone,
                    'criado_em'    => date('Y-m-d H:i:s'),
                    'atualizado_em' => date('Y-m-d H:i:s'),
                ];
                $conn->insert('contact', $DataTel);
            }

            $conn->commit();

            return $this->json($response, [
                'status' => true,
                'msg'    => 'Pré-cadastro realizado com sucesso!',
                'id'     => $id_usuario,
            ], 201);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            $conn->rollBack();
            return $this->json($response, [
                'status' => false,
                'msg'    => 'CPF ou contato já cadastrado.',
                'id'     => 0,
            ], 409);
        } catch (\Throwable $e) {
            $conn->rollBack();
            error_log('[preRegister] ' . $e->getMessage());
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Erro ao realizar pré-cadastro. Tente novamente.' . $e->getMessage(),
                'id'     => 0,
            ], 500);
        }
    }
    public function google($request, $response)
    {
        $form = $request->getParsedBody();
        $credential = $form['credential'] ?? null;
        $form_g_csrf_token = $form['g_csrf_token'] ?? null;
        $cookie_g_csrf_token = $_COOKIE['g_csrf_token'] ?? null;
        $google_client_id = $_ENV['GOOGLE_CLIENT_ID'] ?? null;

        if (is_null($credential) || is_null($form_g_csrf_token) || is_null($cookie_g_csrf_token)) {
            return $this->json($response, ['status' => false, 'msg' => 'Credential do Google ausente', 'id' => 0], 400);
        }

        try {
            $provider = new \League\OAuth2\Client\Provider\Google([
                'clientId'     => $google_client_id,
                'clientSecret' => '',
                'redirectUri'  => '',
            ]);

            $httpResponse = $provider->getHttpClient()->request(
                'GET',
                'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential),
                ['timeout' => 3, 'connect_timeout' => 2]
            );

            $claims = json_decode((string) $httpResponse->getBody(), true, flags: JSON_THROW_ON_ERROR);

            $nome = (string) ($claims['given_name'] ?? '');
            $sobrenome = (string) ($claims['family_name'] ?? '');
            $email = (string) ($claims['email'] ?? '');
            $google_id = (string) ($claims['sub'] ?? '');

            if ($email === '') {
                return $this->json($response, ['status' => false, 'msg' => 'E-mail não informado na resposta do Google', 'id' => 0], 400);
            }

            // 1) Encontrar usuário existente via contact (EMAIL)
            $qb = \app\database\DB::select('*')
                ->from('vw_user');

            $placeholder = $qb->createNamedParameter($email);
            $user = $qb->where('email = ' . $placeholder)->fetchAssociative();

            if (!$user) {
                // 2) Se não existir: inserir em users + contact(EMAIL)
                $dummyHash = '$2y$10$CwTycUXWue0Thq9StjUM0uJ8.k3.kK1m3Sv7lJ1uG9N9Yvb.MqYsa';

                $DataUser = [
                    'nome'          => $nome,
                    'sobrenome'     => $sobrenome,
                    'cpf'           => '',
                    'rg'            => '',
                    'senha'         => $dummyHash,
                    'ativo'         => 'false',
                    'administrador' => 'false',
                ];


                \app\database\DB::connection()->insert('users', $DataUser);
                $userId = \app\database\DB::connection()->lastInsertId();

                $DataEmail = [
                    'id_usuario' => $userId,
                    'tipo' => 'EMAIL',
                    'contato' => $email,
                ];
                \app\database\DB::connection()->insert('contact', $DataEmail);

                return $response
                    ->withHeader('Location', '/login')
                    ->withStatus(302);
            }

            if (!$user['ativo']) {
                return $this->json($response, [
                    'status' => false,
                    'msg' => 'Por enquanto você ainda não está autorizado, aguarde liberação',
                    'id' => $user['id']
                ], 403);
            }
            // 3) Login: seguir o mesmo fluxo do authenticate()
            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
            session_regenerate_id(true);

            $lifetime = (int) (ini_get('session.gc_maxlifetime') ?: 3600);
            $now = time();
            $jti = bin2hex(random_bytes(16));

            $payload = [
                'iat' => $now,
                'nbf' => $now,
                'exp' => $now + $lifetime,
                'sub' => (string) $user['id'],
                'jti' => $jti
            ];

            $jwt = \Firebase\JWT\JWT::encode($payload, SECRET_KEY, 'HS256');


            setcookie('auth_token', $jwt, [
                'expires'  => time() + $lifetime,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            $_SESSION['user'] = $user;
            $_SESSION['user']['logado'] = true;

            $agora = (new \DateTimeImmutable())->setTimestamp($now);
            $_SESSION['user']['sessao_criada_em'] = $agora->format('Y-m-d H:i:s');
            $_SESSION['user']['sessao_expira_em'] = $agora->modify("+{$lifetime} seconds")->format('Y-m-d H:i:s');

            return $response
                ->withHeader('Location', '/home')
                ->withStatus(302);
        } catch (\PDOException $e) {
            error_log('[google][DB] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao consultar/gravar usuário', 'id' => 0], 500);
        } catch (\UnexpectedValueException | \DomainException $e) {
            error_log('[google][JWT/VAL] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Falha ao processar autenticação do Google', 'id' => 0], 500);
        } catch (\Throwable $e) {
            error_log('[google][GERAL] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro inesperado na autenticação Google: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
}
