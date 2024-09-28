<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;



//listar autos
$app->get('/api/autos', function (Request $request, Response $response) {
    $sql = "SELECT * FROM vehiculo WHERE estado = 'A'";
    try {
        $db = new db();
        $db = $db->connectDB();
        $resultado = $db->query($sql);
        if ($resultado->rowCount() > 0) {
            $autos = $resultado->fetchAll(PDO::FETCH_OBJ);
            $response->getBody()->write(json_encode($autos));
        } else {
            $response->getBody()->write(json_encode("No existen registros de autos activos en la BD"));
        }
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(['text' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});


// listar autos por id
$app->get('/api/autos/{id}', function (Request $request, Response $response) {
    $idVehiculo = $request->getAttribute('id');
    $sql = "SELECT * FROM vehiculo WHERE id_vehiculo = :id_vehiculo AND estado = 'A'";
    try {
        $db = new db();
        $db = $db->connectDB();
        $resultado = $db->prepare($sql);
        $resultado->bindParam(':id_vehiculo', $idVehiculo);
        $resultado->execute();
        if ($resultado->rowCount() > 0) {
            $auto = $resultado->fetch(PDO::FETCH_OBJ);
            $response->getBody()->write(json_encode($auto));
        } else {
            $response->getBody()->write(json_encode("No existe un vehículo activo con ese ID en la BD"));
        }
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(['text' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});


$app->post('/api/autos/nuevo', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $veh_nombre = $data['nombre'];
    $veh_modelo = $data['modelo'];
    $veh_marca = $data['marca'];
    $veh_color = $data['color'];
    $veh_anio = $data['anio']; // Año del vehículo
    $veh_precio = $data['precio'];
    $veh_stock = $data['stock'];
    $imagenes = $data['imagenes'] ?? []; // Array de rutas de imágenes

    $sql = "INSERT INTO vehiculo (nom_vehiculo, mod_vehiculo, mar_vehiculo, col_vehiculo, anio_vehiculo, pre_vehiculo, stock, estado) 
            VALUES (:nom_vehiculo, :mod_vehiculo, :mar_vehiculo, :col_vehiculo, :anio_vehiculo, :pre_vehiculo, :stock, :estado)";

    try {
        // Crear una instancia de la clase db y conectar
        $db = new db();
        $conexion = $db->connectDB();

        // Preparar y ejecutar la consulta para insertar el vehículo
        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':nom_vehiculo', $veh_nombre);
        $stmt->bindParam(':mod_vehiculo', $veh_modelo);
        $stmt->bindParam(':mar_vehiculo', $veh_marca);
        $stmt->bindParam(':col_vehiculo', $veh_color);
        $stmt->bindParam(':anio_vehiculo', $veh_anio); // Bind del año
        $stmt->bindParam(':pre_vehiculo', $veh_precio);
        $stmt->bindParam(':stock', $veh_stock);
        $stmt->bindValue(':estado', 'A'); // Establecer el estado por defecto como 'A'
        $stmt->execute();

        // Obtener el ID del vehículo recién insertado
        $vehiculo_id = $conexion->lastInsertId();

        // Insertar las imágenes asociadas
        if (!empty($imagenes)) {
            $sql_imagen = "INSERT INTO imagen_vehiculo (id_vehiculo, ruta_img_veh) VALUES (:id_vehiculo, :ruta_img_veh)";
            foreach ($imagenes as $ruta_imagen) {
                $stmt_imagen = $conexion->prepare($sql_imagen);
                $stmt_imagen->bindParam(':id_vehiculo', $vehiculo_id);
                $stmt_imagen->bindParam(':ruta_img_veh', $ruta_imagen);
                $stmt_imagen->execute();
            }
        }

        // Responder con el ID del vehículo
        $response->getBody()->write(json_encode(["vehiculo_id" => $vehiculo_id]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    } catch (PDOException $e) {
        // Manejo de errores
        $error = ["error" => $e->getMessage()];
        $response->getBody()->write(json_encode($error));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
    
    
});

$app->put('/api/autos/editar/{id}', function (Request $request, Response $response) {
    $idVehiculo = $request->getAttribute('id');
    $data = $request->getParsedBody();
    $veh_nombre = $data['nombre'];
    $veh_modelo = $data['modelo'];
    $veh_marca = $data['marca'];
    $veh_color = $data['color'];
    $veh_anio = $data['anio']; // Año del vehículo
    $veh_precio = $data['precio'];
    $veh_stock = $data['stock'];
    $imagenes = $data['imagenes'] ?? []; // Array de rutas de imágenes

    $sql = "UPDATE vehiculo SET nom_vehiculo = :nom_vehiculo, mod_vehiculo = :mod_vehiculo, mar_vehiculo = :mar_vehiculo, 
            col_vehiculo = :col_vehiculo, anio_vehiculo = :anio_vehiculo, pre_vehiculo = :pre_vehiculo, stock = :stock 
            WHERE id_vehiculo = :id_vehiculo";

    try {
        // Crear una instancia de la clase db y conectar
        $db = new db();
        $conexion = $db->connectDB();

        // Actualizar los datos del vehículo
        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':nom_vehiculo', $veh_nombre);
        $stmt->bindParam(':mod_vehiculo', $veh_modelo);
        $stmt->bindParam(':mar_vehiculo', $veh_marca);
        $stmt->bindParam(':col_vehiculo', $veh_color);
        $stmt->bindParam(':anio_vehiculo', $veh_anio); // Bind del año
        $stmt->bindParam(':pre_vehiculo', $veh_precio);
        $stmt->bindParam(':stock', $veh_stock);
        $stmt->bindParam(':id_vehiculo', $idVehiculo);
        $stmt->execute();

        // Actualizar las imágenes asociadas
        if (!empty($imagenes)) {
            // Eliminar imágenes anteriores
            $sqlDeleteImg = "DELETE FROM imagen_vehiculo WHERE id_vehiculo = :id_vehiculo";
            $stmtDeleteImg = $conexion->prepare($sqlDeleteImg);
            $stmtDeleteImg->bindParam(':id_vehiculo', $idVehiculo);
            $stmtDeleteImg->execute();

            // Insertar nuevas imágenes
            $sqlInsertImg = "INSERT INTO imagen_vehiculo (id_vehiculo, ruta_img_veh) VALUES (:id_vehiculo, :ruta_img_veh)";
            foreach ($imagenes as $ruta_imagen) {
                $stmtInsertImg = $conexion->prepare($sqlInsertImg);
                $stmtInsertImg->bindParam(':id_vehiculo', $idVehiculo);
                $stmtInsertImg->bindParam(':ruta_img_veh', $ruta_imagen);
                $stmtInsertImg->execute();
            }
        }

        echo json_encode("Vehículo y sus imágenes actualizados correctamente");
    } catch (PDOException $e) {
        echo '{"text": ' . $e->getMessage() . '}';
    }
});

$app->delete('/api/autos/eliminar/{id}', function (Request $request, Response $response, $args) {
    $id_vehiculo = $args['id'];
    $sql = "UPDATE vehiculo SET estado = 'I' WHERE id_vehiculo = :id_vehiculo";

    try {
        $db = new db();
        $conexion = $db->connectDB();
        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':id_vehiculo', $id_vehiculo);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $response->getBody()->write(json_encode(["message" => "Vehículo eliminado lógicamente."]));
        } else {
            $response->getBody()->write(json_encode(["error" => "No se encontró el vehículo con ese ID."]));
        }

        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $error = ["error" => $e->getMessage()];
        $response->getBody()->write(json_encode($error));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});
