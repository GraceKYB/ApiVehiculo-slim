<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;



$app->post('/api/realizar-compra', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $cliente_id = $data['id_cliente'] ?? null;
    $vehiculos = $data['vehiculos'] ?? [];
    $placas = $data['placas'] ?? [];
    $fecha_comp = $data['fecha_comp'] ?? date('Y-m-d');

    if (is_null($cliente_id) || empty($vehiculos)) {
        $error = ["error" => "Datos insuficientes para realizar la compra"];
        $response->getBody()->write(json_encode($error));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    try {
        $db = new db(); // Crea una instancia de la clase db
        $conexion = $db->connectDB(); // Obtiene la conexión
        
        $conexion->beginTransaction();

        // Inicializar la variable para el total de la compra
        $compra_total = 0;

        // Insertar la compra y obtener el ID de la compra
        $sqlCompra = "INSERT INTO compra (id_cliente, compra_total, fecha_comp, estado) 
                      VALUES (:id_cliente, :compra_total, :fecha_comp, 'A')";
        $stmtCompra = $conexion->prepare($sqlCompra);
        $stmtCompra->bindParam(':id_cliente', $cliente_id);
        $stmtCompra->bindValue(':compra_total', 0); // Inicialmente 0, se actualizará después
        $stmtCompra->bindParam(':fecha_comp', $fecha_comp);
        $stmtCompra->execute();
        $com_id = $conexion->lastInsertId(); // Obtener el ID de la compra

        foreach ($vehiculos as $vehiculo_id => $cantidad) {
            $sqlVehiculo = "SELECT pre_vehiculo, stock FROM vehiculo WHERE id_vehiculo = :id_vehiculo";
            $stmtVehiculo = $conexion->prepare($sqlVehiculo);
            $stmtVehiculo->bindParam(':id_vehiculo', $vehiculo_id);
            $stmtVehiculo->execute();
            $vehiculo = $stmtVehiculo->fetch(PDO::FETCH_ASSOC);

            if (!$vehiculo) {
                $error = ["error" => "Vehículo con ID $vehiculo_id no encontrado"];
                $conexion->rollBack();
                $response->getBody()->write(json_encode($error));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $valor_unitario = $vehiculo['pre_vehiculo'];
            $stock = $vehiculo['stock'];

            if ($stock < $cantidad) {
                $error = ["error" => "No hay suficiente stock para el vehículo con ID $vehiculo_id"];
                $conexion->rollBack();
                $response->getBody()->write(json_encode($error));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $valor_total = $valor_unitario * $cantidad;
            $compra_total += $valor_total;

            // Reducir stock
            $nuevo_stock = $stock - $cantidad;
            $sqlActualizarStock = "UPDATE vehiculo SET stock = :nuevo_stock WHERE id_vehiculo = :id_vehiculo";
            $stmtActualizarStock = $conexion->prepare($sqlActualizarStock);
            $stmtActualizarStock->bindParam(':nuevo_stock', $nuevo_stock);
            $stmtActualizarStock->bindParam(':id_vehiculo', $vehiculo_id);
            $stmtActualizarStock->execute();

            // Insertar en compra_detalle
            if (isset($placas[$vehiculo_id])) {
                foreach ($placas[$vehiculo_id] as $placa) {
                    $sqlDetalle = "INSERT INTO compra_detalle (id_comp, id_vehiculo, v_unitario, cantidad, v_total, placa) 
                                   VALUES (:id_comp, :id_vehiculo, :v_unitario, :cantidad, :v_total, :placa)";
                    $stmtDetalle = $conexion->prepare($sqlDetalle);
                    $stmtDetalle->bindParam(':id_comp', $com_id);
                    $stmtDetalle->bindParam(':id_vehiculo', $vehiculo_id);
                    $stmtDetalle->bindParam(':v_unitario', $valor_unitario);
                    $stmtDetalle->bindParam(':cantidad', $cantidad);
                    $stmtDetalle->bindParam(':v_total', $valor_total);
                    $stmtDetalle->bindParam(':placa', $placa);
                    $stmtDetalle->execute();
                }
            } else {
                // Manejar el caso cuando no hay placas proporcionadas
                $sqlDetalle = "INSERT INTO compra_detalle (id_comp, id_vehiculo, v_unitario, cantidad, v_total, placa) 
                               VALUES (:id_comp, :id_vehiculo, :v_unitario, :cantidad, :v_total, '')";
                $stmtDetalle = $conexion->prepare($sqlDetalle);
                $stmtDetalle->bindParam(':id_comp', $com_id);
                $stmtDetalle->bindParam(':id_vehiculo', $vehiculo_id);
                $stmtDetalle->bindParam(':v_unitario', $valor_unitario);
                $stmtDetalle->bindParam(':cantidad', $cantidad);
                $stmtDetalle->bindParam(':v_total', $valor_total);
                $stmtDetalle->bindValue(':placa', ''); // O puedes usar un valor nulo o un string vacío
                $stmtDetalle->execute();
            }
        }

        // Actualizar el total de la compra
        $sqlActualizarCompra = "UPDATE compra SET compra_total = :compra_total WHERE id_comp = :id_comp";
        $stmtActualizarCompra = $conexion->prepare($sqlActualizarCompra);
        $stmtActualizarCompra->bindParam(':compra_total', $compra_total);
        $stmtActualizarCompra->bindParam(':id_comp', $com_id);
        $stmtActualizarCompra->execute();

        $conexion->commit();
        $response->getBody()->write(json_encode(["message" => "Compra realizada y placas asignadas exitosamente."]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        $conexion->rollBack();
        $error = ["error" => $e->getMessage()];
        $response->getBody()->write(json_encode($error));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});


$app->put('/api/compra/editar-placas', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $placas = $data['placas'] ?? [];

    if (empty($placas)) {
        $error = ["error" => "No se proporcionaron placas para actualizar"];
        $response->getBody()->write(json_encode($error));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    try {
        $db = new db();
        $conexion = $db->connectDB();

        foreach ($placas as $detalle) {
            $id_comp_det = $detalle['id_comp_det'];
            $id_comp = $detalle['id_comp'];
            $id_vehiculo = $detalle['id_vehiculo'];
            $placa = $detalle['placa'];

            // Consulta SQL para actualizar la placa basada en id_comp_det, id_comp, y id_vehiculo
            $sql = "UPDATE compra_detalle SET placa = :placa WHERE id_comp_det = :id_comp_det AND id_comp = :id_comp AND id_vehiculo = :id_vehiculo";
            $stmt = $conexion->prepare($sql);
            $stmt->bindParam(':placa', $placa);
            $stmt->bindParam(':id_comp_det', $id_comp_det);
            $stmt->bindParam(':id_comp', $id_comp);
            $stmt->bindParam(':id_vehiculo', $id_vehiculo);
            $stmt->execute();
        }

        $response->getBody()->write(json_encode(["message" => "Placas actualizadas exitosamente."]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $error = ["error" => $e->getMessage()];
        $response->getBody()->write(json_encode($error));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

$app->get('/api/compra_detalle/listar', function (Request $request, Response $response) {
    try {
        $db = new db();
        $conexion = $db->connectDB();
        
        $sql = "
        SELECT 
            c.fecha_comp AS fecha_compra,
            u.nombre AS nombre_cliente,
            u.apellido AS apellido_cliente,
            u.cedula AS cedula_cliente,
            v.nom_vehiculo AS nombre_vehiculo,
            v.mod_vehiculo AS modelo_vehiculo,
            v.mar_vehiculo AS marca_vehiculo,
            v.pre_vehiculo AS precio_vehiculo,
            cd.placa AS placa_vehiculo,
            iv.ruta_img_veh AS imagen_vehiculo,
            c.contrato AS contrato
        FROM 
            compra_detalle cd
        JOIN 
            compra c ON cd.id_comp = c.id_comp
        JOIN 
            usuario u ON c.id_cliente = u.id_cliente
        JOIN 
            vehiculo v ON cd.id_vehiculo = v.id_vehiculo
        LEFT JOIN 
            imagen_vehiculo iv ON v.id_vehiculo = iv.id_vehiculo
        WHERE 
            cd.estado = 'A' AND c.estado = 'A';
        ";

        $stmt = $conexion->query($sql);
        $compraDetalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode($compraDetalle));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $error = ["error" => $e->getMessage()];
        $response->getBody()->write(json_encode($error));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

$app->get('/api/compra_detalle/listar/{id_comp_det}', function (Request $request, Response $response, $args) {
    $id_comp_det = $args['id_comp_det'];

    try {
        $db = new db();
        $conexion = $db->connectDB();
        
        $sql = "
        SELECT 
            c.fecha_comp AS fecha_compra,
            u.nombre AS nombre_cliente,
            u.apellido AS apellido_cliente,
            u.cedula AS cedula_cliente,
            v.nom_vehiculo AS nombre_vehiculo,
            v.mod_vehiculo AS modelo_vehiculo,
            v.mar_vehiculo AS marca_vehiculo,
            v.pre_vehiculo AS precio_vehiculo,
            cd.placa AS placa_vehiculo,
            iv.ruta_img_veh AS imagen_vehiculo
        FROM 
            compra_detalle cd
        JOIN 
            compra c ON cd.id_comp = c.id_comp
        JOIN 
            usuario u ON c.id_cliente = u.id_cliente
        JOIN 
            vehiculo v ON cd.id_vehiculo = v.id_vehiculo
        LEFT JOIN 
            imagen_vehiculo iv ON v.id_vehiculo = iv.id_vehiculo
        WHERE 
            cd.id_comp_det = :id_comp_det AND cd.estado = 'A' AND c.estado = 'A';
        ";

        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':id_comp_det', $id_comp_det, PDO::PARAM_INT);
        $stmt->execute();
        $compraDetalle = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($compraDetalle) {
            $response->getBody()->write(json_encode($compraDetalle));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $error = ["error" => "No se encontró un detalle de compra con el ID proporcionado."];
            $response->getBody()->write(json_encode($error));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
    } catch (PDOException $e) {
        $error = ["error" => $e->getMessage()];
        $response->getBody()->write(json_encode($error));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

$app->get('/api/compra/contrato/{id}', function (Request $request, Response $response, $args) {
    try {
        $id_comp = $args['id'];
        $db = new db();
        $conexion = $db->connectDB();

        // Consulta SQL para obtener el contrato en Base64
        $sql = "SELECT contrato FROM compra WHERE id_comp = :id_comp";
        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':id_comp', $id_comp, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $response->withStatus(404)->write('Compra no encontrada');
        }

        // Decodifica Base64 y envía como archivo PDF
        $pdfContent = base64_decode($row['contrato']);
        $response->getBody()->write($pdfContent);
        
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="contrato.pdf"')
            ->withStatus(200);
    } catch (PDOException $e) {
        $error = ["error" => $e->getMessage()];
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500)
            ->write(json_encode($error));
    }
});
