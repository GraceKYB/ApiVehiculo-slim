<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require '../vendor/autoload.php';
require '../src/config/db.php';

$app = new \Slim\App();

require '../src/rutas/clientes.php';
require '../src/rutas/autos.php';
require '../src/rutas/venta.php';
require '../src/rutas/cedula.php';
require '../src/rutas/placa.php';


$app->run();
