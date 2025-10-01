<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirect</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: system-ui, sans-serif; }
        body { background: #f8f9fa; min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .container { max-width: 400px; width: 100%; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 30px; }
        .message { padding: 12px; border-radius: 6px; margin-bottom: 20px; text-align: center; }
        .success { background: #f8fff8; color: #155724; }
        .error { background: #fff8f8; color: #721c24; }
        .info { background: #f8fdff; color: #0c5460; }
        .link { display: block; padding: 12px; background: #007bff; color: white; text-decoration: none; border-radius: 6px; text-align: center; margin: 20px 0; }
        .countdown { text-align: center; margin: 20px 0; color: #6c757d; }
        input { width: 100%; padding: 12px; border: 1px solid #dee2e6; border-radius: 6px; margin-bottom: 12px; }
        button { width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 6px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <?php
        include("click_counter.php");
        if (!is_dir("json")) mkdir("json", 0777, true);
        if (!is_dir("files")) mkdir("files", 0777, true);
        
        $hash = $_GET['hash'] ?? '';
        $jsonFile = '';
        $data = null;
        $message = 'Digite um hash para verificar';
        $messageType = 'info';
        $redirectUrl = '';
        
        if (!empty($hash)) {
            $hash = preg_replace('/[^a-zA-Z0-9]/', '', $hash);
            $jsonFile = "json/" . $hash . ".json";
            
            if (file_exists($jsonFile)) {
                $jsonContent = file_get_contents($jsonFile);
                $data = json_decode($jsonContent, true);
                
                if ($data) {
                    if (!empty($data['size']) && !empty($data['type'])) {
                        $redirectUrl = "files/" . $hash . "." . $data['type'];
                    } else {
                        $redirectUrl = $data['url'] ?? '';
                    }
                    
                    if (!empty($redirectUrl)) {
                        $message = "Redirecionando...";
                        $messageType = 'success';
                    } else {
                        $message = "Hash sem URL de redirecionamento";
                        $messageType = 'error';
                    }
                } else {
                    $message = "Erro ao ler arquivo";
                    $messageType = 'error';
                }
            } else {
                $message = "Hash não encontrado";
                $messageType = 'error';
            }
        }
        ?>
        
        <div class="message <?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
        
        <?php if (!empty($redirectUrl)): ?>
            <a href="<?php echo htmlspecialchars($redirectUrl); ?>" class="link">
                Acessar agora
            </a>
            
            <div class="countdown" id="countdown">
                Redirecionando em <span id="count">3</span>s
            </div>
            
            <script>
                let count = 3;
                const countElement = document.getElementById('count');
                const redirectUrl = "<?php echo $redirectUrl; ?>";
                
                const countdown = setInterval(() => {
                    count--;
                    countElement.textContent = count;
                    if (count <= 0) {
                        clearInterval(countdown);
                        window.location.href = redirectUrl;
                    }
                }, 1000);
            </script>
        <?php endif; ?>
    </div>

    <script>
        function checkHash() {
            const hash = document.querySelector('input[name="hash"]').value.trim();
            if (hash) window.location.href = '?hash=' + encodeURIComponent(hash);
        }
        document.querySelector('input[name="hash"]').focus();
    </script>
</body>
</html>