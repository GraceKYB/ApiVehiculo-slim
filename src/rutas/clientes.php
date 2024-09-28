<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app = new \Slim\App;

//Listar todos los clientes
$app->get('/api/listar/clientes', function (Request $request, Response $response) {
    $sql = "SELECT * FROM usuario WHERE estado = 'A'";
    try {
        $db = new db();
        $db = $db->connectDB();
        $resultado = $db->query($sql);
        $clientes = $resultado->fetchAll(PDO::FETCH_OBJ);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON encoding error: " . json_last_error_msg());
        }

        if (count($clientes) > 0) {
            return $response->withJson($clientes);
        } else {
            return $response->withJson(["message" => "No existen registros de clientes activos en la BD"], 404);
        }
    } catch (Exception $e) {
        return $response->withJson(["error" => $e->getMessage()], 500);
    }
});


$app->get('/api/clientes/{id}', function (Request $request, Response $response) {
    $idCliente = $request->getAttribute('id');
    $sql = "SELECT * FROM usuario WHERE id_cliente = :id";
    try {
        $db = new db();
        $db = $db->connectDB();
        $resultado = $db->prepare($sql);
        $resultado->bindParam(':id', $idCliente);
        $resultado->execute();
        if ($resultado->rowCount() > 0) {
            $cliente = $resultado->fetch(PDO::FETCH_OBJ);
            return $response->withJson($cliente, 200);
        } else {
            return $response->withJson("No existen registros de clientes en la BD por ID", 404);
        }
    } catch (PDOException $e) {
        return $response->withJson(['error' => $e->getMessage()], 500);
    }
});

// Búsqueda por cédula
$app->get('/api/clientes/cedula/{cedula}', function (Request $request, Response $response) {
    $cedula = $request->getAttribute('cedula');
    $sql = "SELECT usuario.*, vehiculo.nom_vehiculo, vehiculo.mod_vehiculo, vehiculo.mar_vehiculo, vehiculo.anio_vehiculo 
            FROM usuario 
            LEFT JOIN compra ON usuario.id_cliente = compra.id_cliente
            LEFT JOIN compra_detalle ON compra.id_comp = compra_detalle.id_comp
            LEFT JOIN vehiculo ON compra_detalle.id_vehiculo = vehiculo.id_vehiculo
            WHERE usuario.cedula = :cedula";
    try {
        $db = new db();
        $db = $db->connectDB();
        $resultado = $db->prepare($sql);
        $resultado->bindParam(':cedula', $cedula);
        $resultado->execute();
        if ($resultado->rowCount() > 0) {
            $clienteConAuto = $resultado->fetchAll(PDO::FETCH_OBJ);
            return $response->withJson($clienteConAuto, 200);
        } else {
            return $response->withJson("No existen registros de clientes con esa cédula en la BD", 404);
        }
    } catch (PDOException $e) {
        return $response->withJson(['error' => $e->getMessage()], 500);
    }
});

// Agregar un cliente
$app->post('/api/clientes/nuevo', function (Request $request, Response $response) {
    $nombre = $request->getParam('nombre');
    $apellido = $request->getParam('apellido');
    $cedula = $request->getParam('cedula');
    $correo = $request->getParam('correo');
    $edad = $request->getParam('edad');
    $direccion = $request->getParam('direccion');
    $estado = $request->getParam('estado');

    $sql = "INSERT INTO usuario (nombre, apellido, cedula, correo, edad, direccion, estado) 
            VALUES (:nombre, :apellido, :cedula, :correo, :edad, :direccion, :estado)";
    try {
        $db = new db();
        $db = $db->connectDB();
        $resultado = $db->prepare($sql);

        $resultado->bindParam(':nombre', $nombre);
        $resultado->bindParam(':apellido', $apellido);
        $resultado->bindParam(':cedula', $cedula);
        $resultado->bindParam(':correo', $correo);
        $resultado->bindParam(':edad', $edad);
        $resultado->bindParam(':direccion', $direccion);
        $resultado->bindParam(':estado', $estado);

        $resultado->execute();
        return $response->withJson("Nuevo cliente registrado con éxito", 201);

    } catch (PDOException $e) {
        return $response->withJson(['error' => $e->getMessage()], 500);
    }
});

$app->put('/api/clientes/editar/{id}', function (Request $request, Response $response) {
    $idCliente = $request->getAttribute('id');
    $data = $request->getParsedBody();
    
    $nombre = $data['nombre'] ?? null;
    $apellido = $data['apellido'] ?? null;
    $cedula = $data['cedula'] ?? null;
    $correo = $data['correo'] ?? null;
    $edad = $data['edad'] ?? null;
    $direccion = $data['direccion'] ?? null;
    $estado = $data['estado'] ?? null;

    $sql = "UPDATE usuario SET 
            nombre = :nombre, 
            apellido = :apellido, 
            cedula = :cedula, 
            correo = :correo, 
            edad = :edad, 
            direccion = :direccion, 
            estado = :estado 
            WHERE id_cliente = :id";
    try {
        $db = new db();
        $db = $db->connectDB();
        $resultado = $db->prepare($sql);

        $resultado->bindParam(':nombre', $nombre);
        $resultado->bindParam(':apellido', $apellido);
        $resultado->bindParam(':cedula', $cedula);
        $resultado->bindParam(':correo', $correo);
        $resultado->bindParam(':edad', $edad);
        $resultado->bindParam(':direccion', $direccion);
        $resultado->bindParam(':estado', $estado);
        $resultado->bindParam(':id', $idCliente);

        $resultado->execute();

        if ($resultado->rowCount() > 0) {
            return $response->withJson("Cliente actualizado con éxito", 200);
        } else {
            return $response->withJson("No se encontró el cliente o no se hicieron cambios", 404);
        }
    } catch (PDOException $e) {
        return $response->withJson(['error' => $e->getMessage()], 500);
    }
});

$app->delete('/api/clientes/eliminar/{id}', function (Request $request, Response $response) {
    $idCliente = $request->getAttribute('id');
    $estado = 'I'; // 'I' para indicar inactivo

    $sql = "UPDATE usuario SET estado = :estado WHERE id_cliente = :id";
    try {
        $db = new db();
        $db = $db->connectDB();
        $resultado = $db->prepare($sql);

        $resultado->bindParam(':estado', $estado);
        $resultado->bindParam(':id', $idCliente);

        $resultado->execute();

        if ($resultado->rowCount() > 0) {
            return $response->withJson("Cliente eliminado lógicamente con éxito", 200);
        } else {
            return $response->withJson("No se encontró el cliente o no se hizo ningún cambio", 404);
        }
    } catch (PDOException $e) {
        return $response->withJson(['error' => $e->getMessage()], 500);
    }
});
