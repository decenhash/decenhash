<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Counter - File Ranking</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
        }
        
        body {
            background: #f8f9fa;
            color: #212529;
            min-height: 100vh;
            padding: 20px;
            line-height: 1.5;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1a1a1a;
        }
        
        .subtitle {
            color: #6c757d;
            margin-bottom: 30px;
            font-size: 0.9rem;
        }
        
        .message {
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 24px;
            border-left: 4px solid;
        }
        
        .error {
            background: #fff8f8;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .info {
            background: #f8fdff;
            border-color: #17a2b8;
            color: #0c5460;
        }
        
        .file-list {
            list-style: none;
            margin-top: 20px;
        }
        
        .file-item {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 16px;
            border-bottom: 1px solid #dee2e6;
            transition: background 0.2s;
        }
        
        .file-item:hover {
            background: #f8f9fa;
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .rank {
            font-weight: 600;
            color: #495057;
            width: 40px;
            text-align: center;
        }
        
        .file-link {
            flex: 1;
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        
        .file-link:hover {
            text-decoration: underline;
        }
        
        .file-value {
            font-weight: 600;
            color: #28a745;
            background: #f8fff8;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .header {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 16px;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            background: #f8f9fa;
            border-radius: 6px 6px 0 0;
        }
        
        .header-rank {
            width: 40px;
            text-align: center;
        }
        
        .header-name {
            flex: 1;
        }
        
        .header-value {
            width: 100px;
            text-align: right;
        }
        
        footer {
            text-align: center;
            margin-top: 30px;
            color: #6c757d;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Data Counter - File Ranking</h1>
        <p class="subtitle">Arquivos ordenados por valor numérico (decrescente)</p>
        
        <?php
         include("data_count.php");

        // Define the data_count directory
        $dataCountDir = 'data_count';
        $jsonDir = 'json';
        
        // Check if directories exist
        if (!is_dir($dataCountDir)) {
            echo '<div class="message error">';
            echo 'Diretório ' . htmlspecialchars($dataCountDir) . ' não encontrado.';
            echo '</div>';
        } elseif (!is_dir($jsonDir)) {
            echo '<div class="message error">';
            echo 'Diretório ' . htmlspecialchars($jsonDir) . ' não encontrado.';
            echo '</div>';
        } else {
            // Get all files from data_count directory
            $files = scandir($dataCountDir);
            $fileData = [];
            
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                
                $filePath = $dataCountDir . '/' . $file;
                
                // Read file content
                $content = file_get_contents($filePath);
                $number = trim($content);
                
                // Check if content is a valid number
                if (is_numeric($number)) {
                    $fileData[] = [
                        'filename' => $file,
                        'value' => (float)$number,
                        'json_link' => $jsonDir . '/' . pathinfo($file, PATHINFO_FILENAME) . '.json'
                    ];
                }
            }
            
            // Sort files by value in descending order
            usort($fileData, function($a, $b) {
                return $b['value'] <=> $a['value'];
            });
            
            if (empty($fileData)) {
                echo '<div class="message info">';
                echo 'Nenhum arquivo com conteúdo numérico encontrado no diretório ' . htmlspecialchars($dataCountDir) . '.';
                echo '</div>';
            } else {
                echo '<div class="header">';
                echo '<div class="header-rank">#</div>';
                echo '<div class="header-name">Arquivo</div>';
                echo '<div class="header-value">Valor</div>';
                echo '</div>';
                
                echo '<ul class="file-list">';
                
                foreach ($fileData as $index => $data) {
                    $rank = $index + 1;
                    $filename = htmlspecialchars($data['filename']);
                    $value = htmlspecialchars($data['value']);
                    $jsonLink = htmlspecialchars($data['json_link']);
                    
                    echo '<li class="file-item">';
                    echo '<div class="rank">' . $rank . '</div>';
                    echo '<a href="' . $jsonLink . '" class="file-link" target="_blank">';
                    echo $filename;
                    echo '</a>';
                    echo '<div class="file-value">' . $value . '</div>';
                    echo '</li>';
                }
                
                echo '</ul>';
                
                echo '<footer>';
                echo 'Total de arquivos: ' . count($fileData);
                echo '</footer>';
            }
        }
        ?>
    </div>
</body>
</html>