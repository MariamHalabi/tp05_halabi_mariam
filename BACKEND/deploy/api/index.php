<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Firebase\JWT\JWT;

require "client.php";
require __DIR__ . '/../vendor/autoload.php';

const JWT_SECRET = "makey1234567";

$app = AppFactory::create();

$app->post('/api/login', function (Request $request, Response $response) {
   /* $body = (array)$request->getParsedBody();
    $login = $body['login'];
    $password = $body['password'];

    $jwt = createJWT($login, $password);
    return $response->withHeader("Authorization", "Bearer $jwt");*/

    $err=false;
    $inputJSON = file_get_contents('php://input');
    $body = json_decode( $inputJSON, TRUE );
    $login = $body['login'] ?? "";
    $password = $body['password'] ?? "";

    //check format login and password
    if (empty($login) || empty($password)|| !preg_match("/^[a-zA-Z0-9]+$/", $login) || !preg_match("/^[a-zA-Z0-9]+$/", $password)) {
        $err=true;
    }

    global $entityManager;
    $user = $entityManager->getRepository('client')->findOneBy(array('username' => $login, 'password' => $password));
    $id = $user->getId();

    if (!$err && $user) {
        $response = createJwT($response, $login, $password);
       // $response = addHeaders($response);
        $data = array('login' => $login, 'id' => $id);
        $response->getBody()->write(json_encode($data));
    }
    else{
        $response = $response->withStatus(401);
    }
    return $response;
});

function createJWT($login, $password) : string
{
    $issuedAt = time();
    $expirationTime = $issuedAt + 600;
    $payload = array(
        'login' => $login,
        'password' => $password,
        'iat' => $issuedAt,
        'exp' => $expirationTime
    );

    return JWT::encode($payload, JWT_SECRET);
}

$app->get('/api/products', function (Request $request, Response $response) {
    $json = getProductsJSON();
    $response->getBody()->write($json);
    return $response;
});

function getProductsJSON() {
    return file_get_contents(__DIR__ . '\mock\catalogueMock.json');
}

$app->get('/api/product/{id}', function (Request $request, Response $response, $args) {
    $id = $args ['id'];
    $json = getProductsJSON();
    $products = json_decode($json, true);

    $product = filterArrayById($products, $id);
    $response->getBody()->write(json_encode($product));
    return $response;
});

function filterArrayById($array, $id)
{
    $filtered_array = array_filter($array, function ($elem) use ($id) {
        if (isset($elem['id'])) {
            return $elem['id'] == $id;
        }
        return false;
    });
    return current($filtered_array);
}

$app->post('/api/inscription', function (Request $request, Response $response) {
    $body = (array)$request->getParsedBody();
    $client = createClientFromBody($body);
    $response->getBody()->write(json_encode($client));
    return $response;
});

function createClientFromBody($body): Client {
    $client = new Client();
    $client->firstname = $body['firstname'];
    $client->lastname = $body['lastname'];
    $client->email = $body['email'];
    $client->login = $body['login'];
    $client->password = $body['password'];
    $client->phone = $body['phone'];
    $client->locale = $body['locale'];
    $client->address = $body['address'];
    $client->city = $body['city'];
    $client->zip = $body['cp'];
    $client->country = $body['country'];
    $client->civility = $body['civility'];
    return $client;
}

$options = [
    "attribute" => "token",
    "header" => "Authorization",
    "regexp" => "/Bearer\s+(.*)$/i",
    "secure" => false,
    "algorithm" => ["HS256"],
    "secret" => JWT_SECRET,
    "path" => ["/api"],
    "ignore" => ["/api/login", "/api/register"],
    "error" => function ($response) {
        $data = array('ERREUR' => 'Connexion', 'ERREUR' => 'JWT Non valide');
        $response = $response->withStatus(401);
        return $response->withHeader("Content-Type", "application/json")->getBody()->write(json_encode($data));
    }
];

$app->addBodyParsingMiddleware();
$app->add(new Tuupola\Middleware\JwtAuthentication($options));
$app->add(new Tuupola\Middleware\CorsMiddleware([
    "origin" => ["*"],
    "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE"],
    "headers.allow" => ["Authorization", "Content-Type"],
    "headers.expose" => ["Authorization"],
]));

$app->run();