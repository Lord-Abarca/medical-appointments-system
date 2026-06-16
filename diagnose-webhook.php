<?php
/**
 * Herramienta de diagnóstico para webhook de MercadoPago
 * Este archivo te ayudará a entender por qué MercadoPago no está enviando notificaciones
 */

// Cargar WordPress
require_once(__DIR__ . '/wp-load.php');

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Diagnóstico de Webhook MercadoPago</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; max-width: 1200px; }
        .section { background: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .ok { color: #046b00; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #721c24; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .code { background: #000; color: #0f0; padding: 15px; border-radius: 5px; overflow-x: auto; font-family: monospace; }
        button { background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        button:hover { background: #005177; }
        .test-result { margin-top: 20px; padding: 15px; border: 2px solid #ccc; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>🔍 Diagnóstico Completo de Webhook MercadoPago</h1>
    
    <?php
    global $wpdb;
    
    // 1. Verificar credenciales
    $access_token = get_option('mas_mp_access_token', '');
    $public_key = get_option('mas_mp_public_key', '');
    
    echo '<div class="section">';
    echo '<h2>1. Credenciales de MercadoPago</h2>';
    if (empty($access_token) || empty($public_key)) {
        echo '<div class="error">❌ Credenciales NO configuradas</div>';
    } else {
        echo '<div class="ok">✅ Credenciales configuradas</div>';
        echo '<p><strong>Access Token:</strong> ' . substr($access_token, 0, 20) . '...</p>';
        echo '<p><strong>Public Key:</strong> ' . substr($public_key, 0, 20) . '...</p>';
        
        // Detectar tipo
        if (strpos($access_token, 'TEST') !== false) {
            echo '<div class="warning">⚠️ Credenciales de PRUEBA detectadas</div>';
        } else {
            echo '<div class="ok">✅ Credenciales de PRODUCCIÓN</div>';
        }
    }
    echo '</div>';
    
    // 2. URL del Webhook
    $webhook_url = get_site_url() . '/mercadopago-webhook.php';
    echo '<div class="section">';
    echo '<h2>2. URL del Webhook</h2>';
    echo '<p><strong>URL configurada en el código:</strong></p>';
    echo '<div class="code">' . $webhook_url . '</div>';
    echo '<p>Esta es la URL que debes configurar en el panel de MercadoPago</p>';
    echo '<button onclick="navigator.clipboard.writeText(\'' . $webhook_url . '\'); alert(\'URL copiada\');">📋 Copiar URL</button>';
    echo '</div>';
    
    // 3. Verificar último arriendo creado
    $last_rental = $wpdb->get_row("
        SELECT * FROM {$wpdb->prefix}mas_box_rentals 
        ORDER BY id DESC LIMIT 1
    ");
    
    echo '<div class="section">';
    echo '<h2>3. Último Arriendo Creado</h2>';
    if ($last_rental) {
        echo '<div class="ok">✅ Último arriendo encontrado</div>';
        echo '<table border="1" cellpadding="10" style="width:100%; margin-top:10px;">';
        echo '<tr><th>Campo</th><th>Valor</th></tr>';
        echo '<tr><td>ID</td><td>' . $last_rental->id . '</td></tr>';
        echo '<tr><td>External Reference</td><td><strong>' . ($last_rental->external_reference ?? 'N/A') . '</strong></td></tr>';
        echo '<tr><td>Preference ID</td><td>' . ($last_rental->preference_id ?? 'N/A') . '</td></tr>';
        echo '<tr><td>Payment URL</td><td>' . ($last_rental->payment_url ?? 'N/A') . '</td></tr>';
        echo '<tr><td>MP Payment ID</td><td>' . ($last_rental->mp_payment_id ?? 'N/A') . '</td></tr>';
        echo '<tr><td>Status</td><td>' . $last_rental->status . '</td></tr>';
        echo '<tr><td>Payment Status</td><td>' . $last_rental->payment_status . '</td></tr>';
        echo '</table>';
        
        $external_ref = $last_rental->external_reference;
    } else {
        echo '<div class="error">❌ No hay arriendos en el sistema</div>';
        $external_ref = null;
    }
    echo '</div>';
    
    // 4. Verificar logs de webhook
    $webhook_logs = $wpdb->get_results("
        SELECT * FROM {$wpdb->prefix}mas_webhook_logs 
        ORDER BY id DESC LIMIT 10
    ");
    
    echo '<div class="section">';
    echo '<h2>4. Logs de Webhook (Últimos 10)</h2>';
    if ($webhook_logs) {
        echo '<div class="ok">✅ ' . count($webhook_logs) . ' logs encontrados</div>';
        echo '<table border="1" cellpadding="10" style="width:100%; margin-top:10px;">';
        echo '<tr><th>Fecha</th><th>Tipo</th><th>MP ID</th><th>Estado</th><th>Mensaje</th></tr>';
        foreach ($webhook_logs as $log) {
            $status_class = $log->status === 'processed' ? 'ok' : 'warning';
            echo '<tr>';
            echo '<td>' . $log->received_at . '</td>';
            echo '<td>' . $log->type . '</td>';
            echo '<td>' . $log->mp_id . '</td>';
            echo '<td><span class="' . $status_class . '">' . $log->status . '</span></td>';
            echo '<td>' . substr($log->message ?? '', 0, 50) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="error">❌ NO HAY LOGS DE WEBHOOK - MercadoPago nunca ha llamado al webhook</div>';
        echo '<p>Esto confirma que el problema es la configuración en el panel de MercadoPago, NO tu código.</p>';
    }
    echo '</div>';
    
    // 5. Probar webhook manualmente
    echo '<div class="section">';
    echo '<h2>5. Prueba Manual del Webhook</h2>';
    echo '<p>Simula una notificación de MercadoPago para verificar que el webhook funciona:</p>';
    echo '<div id="test-result" class="test-result" style="display:none;"></div>';
    echo '<button onclick="testWebhook()">🧪 Probar Webhook Ahora</button>';
    echo '</div>';
    
    // 6. Instrucciones paso a paso
    echo '<div class="section">';
    echo '<h2>6. Pasos para Configurar el Webhook en MercadoPago</h2>';
    echo '<ol>';
    echo '<li>Ve a: <a href="https://www.mercadopago.cl/developers/panel" target="_blank">https://www.mercadopago.cl/developers/panel</a></li>';
    echo '<li>Si tienes credenciales de PRUEBA: Activa el modo PRUEBA con el toggle que está arriba</li>';
    echo '<li>Haz clic en "Webhooks" en el menú lateral</li>';
    echo '<li>Haz clic en "Configurar notificaciones" o "Agregar webhook"</li>';
    echo '<li>Pega esta URL: <strong>' . $webhook_url . '</strong></li>';
    echo '<li>Selecciona el evento: <strong>"Pagos"</strong> o <strong>"payment"</strong></li>';
    echo '<li>Guarda los cambios</li>';
    echo '</ol>';
    echo '</div>';
    
    // 7. Verificación de archivos
    echo '<div class="section">';
    echo '<h2>7. Verificación de Archivos</h2>';
    $files_to_check = array(
        'mercadopago-webhook.php',
        'wp-load.php',
        'includes/class-mercadopago.php'
    );
    foreach ($files_to_check as $file) {
        if (file_exists(__DIR__ . '/' . $file)) {
            echo '<div class="ok">✅ ' . $file . ' existe</div>';
        } else {
            echo '<div class="error">❌ ' . $file . ' NO existe</div>';
        }
    }
    echo '</div>';
    ?>
    
    <script>
    function testWebhook() {
        const resultDiv = document.getElementById('test-result');
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<p>⏳ Probando webhook...</p>';
        
        // Simular notificación de MercadoPago
        const testData = {
            action: 'payment.updated',
            data: {
                id: '1234567890'
            },
            type: 'payment'
        };
        
        fetch('<?php echo $webhook_url; ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'User-Agent': 'MercadoPago Webhook Test'
            },
            body: JSON.stringify(testData)
        })
        .then(response => response.text())
        .then(data => {
            resultDiv.innerHTML = '<div class="ok"><strong>✅ Webhook respondió:</strong><pre>' + data + '</pre></div>';
            resultDiv.innerHTML += '<p>El webhook está funcionando. Si ves esto, el problema es que MercadoPago no está enviando notificaciones.</p>';
        })
        .catch(error => {
            resultDiv.innerHTML = '<div class="error"><strong>❌ Error:</strong> ' + error + '</div>';
        });
    }
    </script>
</body>
</html>
