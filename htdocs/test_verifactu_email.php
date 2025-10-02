<?php
// Test script para verificar la clase VerifactuNotifyError
require_once 'conf.php';
require_once 'custom/verifactu/class/verifactunotifyerror.class.php';

echo "Testing VerifactuNotifyError class...\n";

// Crear instancia
$notifier = new VerifactuNotifyError($db);

// Probar envío de notificación de prueba
$result = $notifier->sendErrorNotification(
    "Este es un email de prueba desde el sistema Verifactu",
    "test",
    array(
        "Test ID" => "12345",
        "Timestamp" => time()
    )
);

if ($result) {
    echo "✅ Email enviado correctamente\n";
} else {
    echo "❌ Error al enviar email\n";
}

echo "Test completed.\n";
?>