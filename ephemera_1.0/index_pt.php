<?php
// Ensure json and files folders exist
if (!is_dir("json")) {
    mkdir("json", 0777, true);
}
if (!is_dir("files")) {
    mkdir("files", 0777, true);
}

$message = "";
$linkToShow = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $url = trim($_POST['url'] ?? '');

    if (empty($url)) {
        $message = "Error: The 'url' field is mandatory.";
    } else {
        $fileUploaded = false;
        $uploadedSize = "";
        $uploadedType = "";
        $uploadedTitle = "";
        $fileHash = "";
        $jsonFilename = "";

        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $fileTmp  = $_FILES['file']['tmp_name'];
            $fileName = $_FILES['file']['name'];
            $fileSize = $_FILES['file']['size'];
            $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if ($fileSize > 10 * 1024 * 1024) {
                $message = "Error: File too large (max 10MB).";
            } elseif ($fileExt === "php") {
                $message = "Error: PHP files are not allowed.";
            } else {
                $fileHash = hash_file("sha256", $fileTmp);
                $newFileName = $fileHash . "." . $fileExt;
                $filePath = "files/" . $newFileName;

                if (file_exists($filePath)) {
                    $message = "Error: This file already exists.";
                } else {
                    if (move_uploaded_file($fileTmp, $filePath)) {
                        $fileUploaded = true;
                        $uploadedSize = $fileSize;
                        $uploadedType = $fileExt;
                        $uploadedTitle = pathinfo($fileName, PATHINFO_FILENAME);
                        $message = "Success: File uploaded successfully!";
                    } else {
                        $message = "Error: Failed to move uploaded file.";
                    }
                }
            }
        }

        if (strpos($message, "Error:") === false) {
            if ($fileUploaded) {
                $jsonFilename = "json/" . $fileHash . ".json";
            } else {
                $jsonFilename = "json/" . hash("sha256", $url) . ".json";
            }

            if (file_exists($jsonFilename)) {
                $message = "Error: This entry already exists.";
            } else {
                $titleInput = trim($_POST['title'] ?? "");
                $data = [
                    "user"        => $_POST['user'] ?? "",
                    "title"       => $titleInput !== "" ? $titleInput : ($fileUploaded ? $uploadedTitle : ""),
                    "description" => $_POST['description'] ?? "",
                    "date"        => date("Y-m-d H:i:s"),
                    "category"    => $_POST['category'] ?? "",
                    "size"        => $fileUploaded ? $uploadedSize : ($_POST['size'] ?? ""),
                    "type"        => $fileUploaded ? $uploadedType : ($_POST['type'] ?? ""),
                    "url"         => $url,
                    "PIX"         => $_POST['PIX'] ?? "",
                    "SOL"         => $_POST['SOL'] ?? "",
                    "PAYPAL"      => $_POST['PAYPAL'] ?? "",
                    "BTC"         => $_POST['BTC'] ?? ""
                ];

                $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $fp = fopen($jsonFilename, "w");
                if ($fp) {
                    fwrite($fp, $jsonData);
                    fclose($fp);
                    
                    // Set the appropriate link based on whether a file was uploaded or not
                    if ($fileUploaded) {
                        $message .= " Data saved successfully!";
                        $linkToShow = "<a href=\"files/{$newFileName}\" target=\"_blank\">Open Uploaded File</a>";
                    } else {
                        $message = "Success: Data saved successfully (no file uploaded).";
                        $linkToShow = "<a href=\"{$jsonFilename}\" target=\"_blank\">Open JSON File</a>";
                    }
                } else {
                    $message = "Error: Could not write JSON file.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Decenhash - Sistema de Compartilhamento Simples</title>
    <link rel="icon" type="image/jpeg" href="logo.jpg">
    <style>
        /* Minimalist CSS Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
            min-height: 100vh;
        }

        /* Header with top menu */
        header {
            background-color: #fff;
            border-bottom: 1px solid #eaeaea;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            font-weight: 600;
            font-size: 1.25rem;
            color: #2e7d32;
        }

        nav {
            display: flex;
            gap: 1.5rem;
        }

        nav a {
            text-decoration: none;
            color: #555;
            font-size: 0.9rem;
            transition: color 0.2s;
        }

        nav a:hover {
            color: #2e7d32;
        }

        .top-right-image {
            width: 32px;
            height: 32px;
            border-radius: 4px;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .top-right-image:hover {
            opacity: 0.8;
        }

        /* Main content layout */
        main {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 768px) {
            main {
                grid-template-columns: 1fr;
            }
        }

        /* Form and info sections */
        .form-section, .info-section {
            background: #fff;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .form-section h3, .info-section h1 {
            margin-bottom: 1.5rem;
            color: #2e7d32;
        }

        .info-section h1 {
            font-size: 2rem;
            font-weight: 700;
        }

        /* Form elements */
        form label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: #555;
        }

        form input {
            width: 100%;
            padding: 0.75rem;
            margin-bottom: 1.25rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        form input:focus {
            outline: none;
            border-color: #2e7d32;
        }

        form button {
            width: 100%;
            padding: 0.75rem;
            background-color: #2e7d32;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        form button:hover {
            background-color: #256628;
        }

        /* Message styling */
        .message {
            padding: 0.75rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
            font-weight: 500;
        }

        .message.success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .message.error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        /* Info section content */
        .info-section p {
            margin-bottom: 1.5rem;
            color: #555;
        }

        .info-section ul {
            padding-left: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .info-section li {
            margin-bottom: 0.75rem;
            color: #555;
        }

        .info-section code {
            background-color: #f5f5f5;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-family: monospace;
            font-size: 0.9rem;
        }

        /* Search section */
        .search-section {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eaeaea;
        }

        .search-section h3 {
            margin-bottom: 1rem;
            color: #2e7d32;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: #2e7d32;
        }

        .search-button {
            width: 100%;
            padding: 0.75rem;
            background-color: #2e7d32;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .search-button:hover {
            background-color: #256628;
        }

        /* Account Purchase Section */
        .account-section {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eaeaea;
        }

        .account-section h3 {
            margin-bottom: 1.5rem;
            color: #2e7d32;
            text-align: center;
        }

        .account-tiers {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .account-tier {
            background: #f8f9fa;
            border: 1px solid #eaeaea;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .account-tier:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .account-tier.junior {
            border-top: 4px solid #4caf50;
        }

        .account-tier.pro {
            border-top: 4px solid #2196f3;
        }

        .account-tier.master {
            border-top: 4px solid #ff9800;
        }

        .tier-name {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .tier-price {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #2e7d32;
        }

        .tier-features {
            list-style: none;
            padding: 0;
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .tier-features li {
            margin-bottom: 0.5rem;
            padding-left: 1.5rem;
            position: relative;
            color: #555;
        }

        .tier-features li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #4caf50;
            font-weight: bold;
        }

        .purchase-button {
            width: 100%;
            padding: 0.75rem;
            background-color: #2e7d32;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .purchase-button:hover {
            background-color: #256628;
        }

        .payment-methods {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eaeaea;
        }

        .payment-methods h4 {
            margin-bottom: 1rem;
            color: #555;
        }

        .payment-icons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .payment-icon {
            padding: 0.5rem 1rem;
            background: #f5f5f5;
            border-radius: 4px;
            font-weight: 500;
            color: #555;
        }

        /* Footer */
        footer {
            text-align: center;
            padding: 1.5rem;
            margin-top: 3rem;
            color: #777;
            font-size: 0.9rem;
            border-top: 1px solid #eaeaea;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">DECENHASH</div>
        <nav>
            <a href='index.php'>início</a> 
            <a href='rank_file.php'>rank de arquivos</a>
            <a href='rank_user.php'>rank de usuários</a>
            <a href='search.php'>pesquisar</a>
        </nav>
        <!-- Substitua pela sua imagem e link reais -->
        <a href="https://t.me/decenhash" target="_blank">
            <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiByeD0iNCIgZmlsbD0iIzJlN2QzMiIvPgo8cGF0aCBkPSJNMTYgMTBMMTAgMTZMMTYgMjJNMjIgMTZIMTBNMTYgMTBMMjIgMTZMMTYgMjJNMjIgMTZIMTBaIiBzdHJva2U9IndoaXRlIiBzdHJva2Utd2lkdGg9IjIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCIvPgo8L3N2Zz4K" 
                 alt="Link Externo" 
                 class="top-right-image">
        </a>
    </header>

    <main>
        <section class="form-section">
            <h3>Criar Entrada</h3>
            
<?php if (!empty($linkToShow)): ?>
    <div class="success-link">
        <?php echo "<div class='message success'>Sucesso: Arquivo enviado com sucesso! " . $linkToShow . "</div>"; ?>
    </div>
<?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <label>Usuário: <input type="text" name="user"></label>
                <label>Título: <input type="text" name="title"></label>
                <label>Descrição: <input type="text" name="description"></label>
                <label>Categoria: <input type="text" name="category"></label>                
                <label>URL (obrigatório): <input type="url" name="url" required></label>
                <label>BTC: <input type="text" name="BTC"></label>
                <label>SOL: <input type="text" name="SOL"></label> 
                <label>PIX: <input type="text" name="PIX"></label>                
                <label>PAYPAL: <input type="text" name="PAYPAL"></label>                
                <label>Enviar Arquivo (máx. 10MB): <input type="file" name="file"></label>
                <button type="submit">Salvar</button>
            </form>
        </section>

        <section class="info-section">
            <h1>DECENHASH</h1>
            <p>Decenhash é um sistema simples de compartilhamento que permite recompensas baseadas no número de acessos de um arquivo ou URL.</p>
            <ul>
                <li>Funciona sem MySQL</li>
                <li>Você pode enviar uma URL ou um arquivo (se enviar ambos, o usuário será redirecionado para o arquivo).</li>
                <li>O sistema evita entradas duplicadas usando <strong>hashes SHA-256</strong>.</li>
                <li>As informações do arquivo são salvas em formato <strong>JSON</strong></li>
            </ul>
            
            <!-- Seção de pesquisa -->
            <div class="search-section">
                <h3>Pesquisar</h3>
                <input type="text" class="search-input" placeholder="Digite os termos de pesquisa...">
                <button class="search-button" onclick="performSearch()">Pesquisar</button>
            </div>

            <!-- Seção de Compra de Conta -->
            <div class="account-section">
                <h3>Atualize Sua Conta</h3>
                <div class="account-tiers">
                    <!-- Conta Junior -->
                    <div class="account-tier junior">
                        <div class="tier-name">Conta Junior</div>
                        <div class="tier-price">$1.00</div>
                        <ul class="tier-features">
                            <li>Armazenamento persistente e sem expiração</li>                            
                        </ul>
                    </div>
                    
                    <!-- Conta Pro -->
                    <div class="account-tier pro">
                        <div class="tier-name">Conta Pro</div>
                        <div class="tier-price">$10.00</div>
                        <ul class="tier-features">
                            <li>Receba recompensas por arquivos ou links populares que você enviou</li>
                            <li>Aumente seu limite de inserção diário</li> 
                        </ul>
                    </div>
                    
                    <!-- Conta Master -->
                    <div class="account-tier master">
                        <div class="tier-name">Conta Master</div>
                        <div class="tier-price">$40.00</div>
                        <ul class="tier-features">
                            <li>Programa de compartilhamento de lucros</li>
                            <li>Atualizações exclusivas</li>
                            <li>Suporte personalizado</li>
                        </ul>
                    </div>
                </div>
                
                <div class="payment-methods">
                    <div class="payment-icons">                       
                        <a href="https://t.me/devsacramento" style="text-decoration: none;" class="purchase-button" >Comprar</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <p>Todos os direitos reservados</p>
    </footer>

    <script>
        function performSearch() {
            // Obtém o valor da entrada de pesquisa
            const searchInput = document.querySelector('.search-input');
            const searchTerm = searchInput.value.trim();
            
            if (searchTerm) {
                // Faz uma requisição GET para search.php com o termo de pesquisa
                window.location.href = `search.php?search=${encodeURIComponent(searchTerm)}`;
            } else {
                alert('Por favor, digite um termo de pesquisa');
            }
        }
        
        // Permite pressionar Enter para pesquisar
        document.querySelector('.search-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });

    </script>
</body>
</html>