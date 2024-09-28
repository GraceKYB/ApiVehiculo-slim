<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/api/busqueda/placa', function (Request $request, Response $response) {
    $db = new db();
    $conexion = $db->connectDB();

    $placa = $request->getQueryParams()['placa'] ?? '';

    if (!empty($placa)) {
        // Consulta SQL para obtener toda la información del usuario y del vehículo
        $sql = "SELECT u.id_cliente, u.nombre, u.apellido, u.cedula, u.correo, u.edad, u.direccion, u.estado,
                       v.id_vehiculo, v.nom_vehiculo, v.mod_vehiculo, v.mar_vehiculo, v.col_vehiculo, v.anio_vehiculo, v.pre_vehiculo, v.stock,
                       cd.placa, cd.cantidad, cd.v_unitario, cd.v_total, 
                       com.id_comp, com.fecha_comp
                FROM compra_detalle cd
                INNER JOIN vehiculo v ON cd.id_vehiculo = v.id_vehiculo
                INNER JOIN compra com ON cd.id_comp = com.id_comp
                INNER JOIN usuario u ON com.id_cliente = u.id_cliente
                WHERE cd.placa LIKE :placa";

        $stmt = $conexion->prepare($sql);
        $placa_param = $placa . '%';
        $stmt->bindParam(':placa', $placa_param);

        try {
            $stmt->execute();
            $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($datos) > 0) {
                // Organizar los datos en una estructura más clara
                $result = [];
                foreach ($datos as $dato) {
                    $result[] = [
                        'usuario' => [
                            'id_cliente' => $dato['id_cliente'],
                            'nombre' => $dato['nombre'],
                            'apellido' => $dato['apellido'],
                            'cedula' => $dato['cedula'],
                            'correo' => $dato['correo'],
                            'edad' => $dato['edad'],
                            'direccion' => $dato['direccion'],
                            'estado' => $dato['estado']
                        ],
                        'vehiculo' => [
                            'id_vehiculo' => $dato['id_vehiculo'],
                            'nom_vehiculo' => $dato['nom_vehiculo'],
                            'mod_vehiculo' => $dato['mod_vehiculo'],
                            'mar_vehiculo' => $dato['mar_vehiculo'],
                            'col_vehiculo' => $dato['col_vehiculo'],
                            'anio_vehiculo' => $dato['anio_vehiculo'],
                            'pre_vehiculo' => $dato['pre_vehiculo'],
                            'stock' => $dato['stock'],
                            'placa' => $dato['placa'],
                            'cantidad' => $dato['cantidad'],
                            'v_unitario' => $dato['v_unitario'],
                            'v_total' => $dato['v_total'],
                            'id_comp' => $dato['id_comp'],
                            'fecha_comp' => $dato['fecha_comp']
                        ]
                    ];
                }

                $response->getBody()->write(json_encode($result));
                return $response->withHeader('Content-Type', 'application/json');
            } else {
                $error = ["error" => "Datos no encontrados"];
                $response->getBody()->write(json_encode($error));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
        } catch (PDOException $e) {
            $error = ["error" => $e->getMessage()];
            $response->getBody()->write(json_encode($error));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

    } else {
        $error = ["error" => "Placa no proporcionada"];
        $response->getBody()->write(json_encode($error));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
});
