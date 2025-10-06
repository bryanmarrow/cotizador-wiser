<?php
// Script para corregir las contraseñas en la base de datos
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/functions.php';

echo "<h3>Corrigiendo contraseñas en la base de datos</h3>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix'])) {
    try {
        $conn = obtenerConexionBaseDatos();
        
        // Contraseña por defecto
        $defaultPassword = 'password123';
        $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
        
        echo "Nuevo hash generado: " . $hashedPassword . "<br><br>";
        
        // Actualizar todos los usuarios
        $sql = "UPDATE users SET password = ?";
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([$hashedPassword]);
        
        if ($result) {
            echo "<div style='color: green; font-weight: bold;'>✓ Contraseñas actualizadas correctamente</div><br>";
            
            // Verificar
            $sqlCheck = "SELECT username, password FROM users";
            $stmtCheck = $conn->prepare($sqlCheck);
            $stmtCheck->execute();
            $users = $stmtCheck->fetchAll();
            
            echo "<h4>Verificación:</h4>";
            foreach ($users as $user) {
                $isValid = password_verify($defaultPassword, $user['password']);
                echo "Usuario: " . $user['username'] . " - " . ($isValid ? '✓ OK' : '✗ Error') . "<br>";
            }
            
            echo "<br><strong>Ahora puedes usar:</strong><br>";
            echo "- Usuario: admin, Contraseña: password123<br>";
            echo "- Usuario: vendedor1, Contraseña: password123<br>";
            echo "- Usuario: cliente1, Contraseña: password123<br>";
            
        } else {
            echo "<div style='color: red;'>✗ Error actualizando contraseñas</div>";
        }
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "<p>Este script corregirá todas las contraseñas de usuarios para que sean 'password123' con hash correcto.</p>";
    echo "<form method='POST'>";
    echo "<button type='submit' name='fix' style='background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Corregir Contraseñas</button>";
    echo "</form>";
}
?>