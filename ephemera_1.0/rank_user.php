<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Data Aggregator</title>
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
        
        .user-list {
            list-style: none;
            margin-top: 20px;
        }
        
        .user-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            border-bottom: 1px solid #dee2e6;
            transition: background 0.2s;
        }
        
        .user-item:hover {
            background: #f8f9fa;
        }
        
        .user-item:last-child {
            border-bottom: none;
        }
        
        .rank {
            font-weight: 600;
            color: #495057;
            width: 40px;
            text-align: center;
        }
        
        .user-info {
            flex: 1;
        }
        
        .user-name {
            font-weight: 500;
            color: #007bff;
            margin-bottom: 4px;
        }
        
        .user-files {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .file-link {
            color: #6c757d;
            text-decoration: none;
            margin-right: 12px;
            white-space: nowrap;
        }
        
        .file-link:hover {
            text-decoration: underline;
            color: #0056b3;
        }
        
        .user-value {
            font-weight: 600;
            color: #28a745;
            background: #f8fff8;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            min-width: 80px;
            text-align: center;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
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
            width: 120px;
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
        <h1>User Data Aggregator</h1>
        <p class="subtitle">Usuários ordenados pela soma total de valores</p>
        
        <?php
        include("data_count.php");

        // Define the data_count and json directories
        $dataCountDir = 'data_count';
        $jsonDir = 'json';
        
        // Check if directories exist
        if (!is_dir($dataCountDir)) {
            echo '<div class="message error">';
            echo 'Diretório ' . htmlspecialchars($dataCountDir) . ' não encontrado.';
            echo '</div>';
            exit;
        } elseif (!is_dir($jsonDir)) {
            echo '<div class="message error">';
            echo 'Diretório ' . htmlspecialchars($jsonDir) . ' não encontrado.';
            echo '</div>';
            exit;
        }
        
        // Get all files from data_count directory
        $files = scandir($dataCountDir);
        $userData = [];
        $processedFiles = 0;
        $filesWithoutUser = 0;
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $filePath = $dataCountDir . '/' . $file;
            $jsonFilename = pathinfo($file, PATHINFO_FILENAME) . '.json';
            $jsonPath = $jsonDir . '/' . $jsonFilename;
            
            // Read numeric value from data_count file
            $content = file_get_contents($filePath);
            $number = trim($content);
            
            if (!is_numeric($number)) {
                continue; // Skip files with non-numeric content
            }
            
            $number = (float)$number;
            $processedFiles++;
            
            // Try to read corresponding JSON file to get user
            $user = null;
            $fileDetails = [
                'filename' => $file,
                'value' => $number,
                'json_file' => $jsonFilename
            ];
            
            if (file_exists($jsonPath)) {
                $jsonContent = file_get_contents($jsonPath);
                $jsonData = json_decode($jsonContent, true);
                
                // FIX: Check for 'user' field instead of 'usuario'
                if ($jsonData && isset($jsonData['user']) && !empty(trim($jsonData['user']))) {
                    $user = trim($jsonData['user']);
                }
            }
            
            // If no user found, skip this file
            if ($user === null) {
                $filesWithoutUser++;
                continue;
            }
            
            // Use the exact user string as key
            $userKey = $user;
            
            // Initialize user data if not exists
            if (!isset($userData[$userKey])) {
                $userData[$userKey] = [
                    'display_name' => $user,
                    'total' => 0,
                    'file_count' => 0,
                    'files' => []
                ];
            }
            
            // Add to user's total
            $userData[$userKey]['total'] += $number;
            $userData[$userKey]['file_count']++;
            $userData[$userKey]['files'][] = $fileDetails;
        }
        
        // Sort users by total value in descending order
        uasort($userData, function($a, $b) {
            return $b['total'] <=> $a['total'];
        });
        
        if (empty($userData)) {
            echo '<div class="message info">';
            echo 'Nenhum arquivo com campo "user" encontrado nos JSONs.';
            echo '<br>Arquivos processados: ' . $processedFiles;
            echo '<br>Arquivos sem usuário: ' . $filesWithoutUser;
            echo '</div>';
        } else {
            echo '<div class="header">';
            echo '<div class="header-rank">#</div>';
            echo '<div class="header-name">Usuário</div>';
            echo '<div class="header-value">Total</div>';
            echo '</div>';
            
            echo '<ul class="user-list">';
            
            $rank = 1;
            foreach ($userData as $userKey => $data) {
                echo '<li class="user-item">';
                echo '<div class="rank">' . $rank . '</div>';
                echo '<div class="user-info">';
                echo '<div class="user-name">' . htmlspecialchars($data['display_name']) . '</div>';
                echo '<div class="user-files">';
                
                // Show all file links in one line
                $fileLinks = [];
                foreach ($data['files'] as $fileDetail) {
                    $link = $jsonDir . '/' . htmlspecialchars($fileDetail['json_file']);
                    $fileLinks[] = '<a href="' . $link . '" class="file-link" target="_blank" title="Valor: ' . $fileDetail['value'] . '">' . 
                                  htmlspecialchars($fileDetail['filename']) . ' (' . $fileDetail['value'] . ')' . 
                                  '</a>';
                }
                
                echo implode(' • ', $fileLinks);
                echo '</div>';
                echo '</div>';
                echo '<div class="user-value">' . number_format($data['total'], 2) . '</div>';
                echo '</li>';
                
                $rank++;
            }
            
            echo '</ul>';
            
            echo '<footer>';
            echo 'Total de usuários: ' . count($userData) . ' | ';
            echo 'Arquivos processados: ' . $processedFiles . ' | ';
            echo 'Arquivos sem usuário: ' . $filesWithoutUser;
            echo '</footer>';
        }
        ?>
    </div>
</body>
</html>