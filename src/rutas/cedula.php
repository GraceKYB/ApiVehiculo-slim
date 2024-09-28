<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/api/busqueda/cedula', function (Request $request, Response $response) {
    $db = new db();
    $conexion = $db->connectDB();

    $cedula = $request->getQueryParams()['cedula'] ?? '';

    if (!empty($cedula)) {
        $sql_cliente = "SELECT u.cedula, u.nombre, u.correo, u.direccion
                        FROM usuario u
                        WHERE u.cedula LIKE :cedula";

        $stmt_cliente = $conexion->prepare($sql_cliente);
        $cedula_param = $cedula . '%';
        $stmt_cliente->bindParam(':cedula', $cedula_param);

        try {
            $stmt_cliente->execute();
            $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

            if ($cliente) {
                $sql_vehiculos = "SELECT v.nom_vehiculo, v.mod_vehiculo, v.mar_vehiculo, v.pre_vehiculo, v.stock, 
                                  cd.placa, cd.cantidad, cd.v_unitario, cd.v_total, com.id_comp, com.fecha_comp
                                  FROM compra_detalle cd
                                  INNER JOIN vehiculo v ON cd.id_vehiculo = v.id_vehiculo
                                  INNER JOIN compra com ON cd.id_comp = com.id_comp
                                  INNER JOIN usuario u ON com.id_cliente = u.id_cliente
                                  WHERE u.cedula LIKE :cedula";
                                  
                $stmt_vehiculos = $conexion->prepare($sql_vehiculos);
                $stmt_vehiculos->bindParam(':cedula', $cedula_param);

                $stmt_vehiculos->execute();
                $vehiculos = $stmt_vehiculos->fetchAll(PDO::FETCH_ASSOC);

                $datos = [
                    'cliente' => $cliente,
                    'vehiculos' => $vehiculos
                ];
                
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json');
            } else {
                $error = ["error" => "Cliente no encontrado"];
                $response->getBody()->write(json_encode($error));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
        } catch (PDOException $e) {
            $error = ["error" => $e->getMessage()];
            $response->getBody()->write(json_encode($error));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        
    } else {
        $error = ["error" => "Cédula no proporcionada"];
        $response->getBody()->write(json_encode($error));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
});
