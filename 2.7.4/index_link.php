<?php

session_start();

// Max size of each HTML page
$sizeLimit = 100 * 1024; // 100KB in bytes

// Allow finding pages based in the file extension and words in the filename
$morePages = 1; 

    function sha256($message) {
        return hash('sha256', $message);
    }

    // Function to handle search form submission
    function performSearch() {
        if (isset($_GET['search'])) {
            $searchInput = trim($_GET['search']);

            if (empty($searchInput)) return;

            // Check if input is already a valid SHA-256 hash (64 hex characters)
            $isValidHash = preg_match('/^[a-fA-F0-9]{64}$/', $searchInput);

            if ($isValidHash) {
                // If input is already a valid hash, use it directly
                $hash = $searchInput;
            } else {
                // Otherwise generate SHA-256 hash of the input
                $hash = sha256($searchInput);
            }

            if (file_exists("data/$hash/index.html")) {
                // Redirect to the page instead of including it
                header("Location: data/$hash/index.html");
                exit(); // Important to prevent further script execution
            } else {
                echo "File don't exists!"; die;
            }
        }
    }

    // Call the function when form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
        performSearch();
    }

    function check_sha256(string $input): string {
        $sha256_regex = '/^[a-f0-9]{64}$/'; // Regex for a 64-character hexadecimal string

        if (preg_match($sha256_regex, $input)) {
            return $input; // Input is a valid SHA256 hash
        } else {
            return hash('sha256', $input); // Input is not a valid SHA256 hash, return its hash
        }
    }

    if (isset($_GET['reply'])) {
        $reply = $_GET['reply'];
    } else {
        $reply = "";
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Configuration - Customize these paths and settings as needed
        $uploadDirBase = 'data'; // Base directory for all uploads
        $ownersDir = 'owners'; // Directory for BTC information
        $metadataDir = 'metadata'; // Directory for metadata JSON files

        // Create directories if they don't exist
        if (!is_dir($uploadDirBase)) {
            mkdir($uploadDirBase, 0777, true);
        }
        if (!is_dir($ownersDir)) {
            mkdir($ownersDir, 0777, true);
        }
        if (!is_dir($metadataDir)) {
            mkdir($metadataDir, 0777, true);
        }

        // Check if category was provided
        if (isset($_POST['category']) && !empty($_POST['category'])) {
            $categoryText = strtolower($_POST['category']);
            $fileContent = null;
            $originalFileName = null;
            $fileExtension = 'txt'; // Default extension for text content
            $isTextContent = false; // Flag to track if content is from text area

            // Check if a file was uploaded
            if (isset($_FILES['uploaded_file']) && $_FILES['uploaded_file']['error'] === UPLOAD_ERR_OK) {
                $uploadedFile = $_FILES['uploaded_file'];
                $fileContent = file_get_contents($uploadedFile['tmp_name']);
                $originalFileName = strtolower($uploadedFile['name']);
                $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
                $isTextContent = false;
            } elseif (isset($_POST['text_content']) && !empty($_POST['text_content'])) {
                // If no file uploaded, check for text content
                $fileContent = $_POST['text_content'];
                $date = date("Y.m.d H:i:s"); // Just for naming purposes in index.html

                if (stripos($fileNameWithExtension, 'http') !== 0) {
                    $relativePathToFile = 'https://' . $fileNameWithExtension;
                }
                
                $originalFileName = hash('sha256', $fileContent);
                $fileContentLen = strlen($fileContent);

                if ($fileContentLen > 50) {
                    $originalFileName = htmlspecialchars(substr($fileContent, 0, 50)) . " ($date)"; 
                } else {
                    $originalFileName = htmlspecialchars(substr($fileContent, 0, 50)) . " ($date)";
                }

                $isTextContent = true;
            }

            if ($fileContent !== null) { // Proceed if we have either file content or text content
                if (strtolower($fileExtension) === 'php') {
                    die('Error: PHP files are not allowed!');
                }

                if ($_POST['category'] == $_POST['text_content']) {
                    die ("Error: Category can't be the same of text contents.");
                }

                // Calculate SHA256 hashes
                $fileHash = hash('sha256', $fileContent);
                $categoryHash = check_sha256($categoryText);

                // Determine file extension (already done above, default is 'txt' for text content)
                $fileNameWithExtension = $fileHash . '.' . $fileExtension; // Hash + extension as filename

                // Construct directory paths
                $fileUploadDir = $uploadDirBase . '/' . $fileHash; // Folder name is file hash
                $categoryDir = $uploadDirBase . '/' . $categoryHash; // Folder name is category hash

                // Create directories if they don't exist
                if (!is_dir($fileUploadDir)) {
                    mkdir($fileUploadDir, 0777, true); // Create file hash folder
                }
                if (!is_dir($categoryDir)) {
                    mkdir($categoryDir, 0777, true); // Create category hash folder
                }

                // Save the content (either uploaded file or text content)
                $destinationFilePath = $fileUploadDir . '/' . $fileNameWithExtension;

                if (file_exists($destinationFilePath)) {
                    die('Error: File already exists!');
                }

                if ($isTextContent) {

                    $file = fopen($destinationFilePath, 'w');
                    fwrite($file, $fileContent);
                    fclose($file);                                          

                    if ($saveResult !== false) {
                        $fileSaved = true;
                    } else {
                        $fileSaved = false;
                    }
                } else {
                    $fileSaved = move_uploaded_file($uploadedFile['tmp_name'], $destinationFilePath); // Save uploaded file
                }

                if ($fileSaved) {
                    // Content saved successfully

                    // Save BTC information if provided
                    if (isset($_POST['btc']) && !empty($_POST['btc'])) {
                        $btcFilePath = $ownersDir . '/' . $fileHash;
                        if (!file_exists($btcFilePath)) {                            
                            $file = fopen($btcFilePath, 'w');
                            fwrite($file, $_POST['btc']);
                            fclose($file);                          
                        }
                    }

                    // Save metadata if all required fields are provided
                    if (isset($_POST['user']) && !empty($_POST['user']) &&
                        isset($_POST['title']) && !empty($_POST['title']) &&
                        isset($_POST['description']) && !empty($_POST['description']) &&
                        isset($_POST['url']) && !empty($_POST['url'])) {
                        
                        $metadata = [
                            'user' => $_POST['user'],
                            'title' => $_POST['title'],
                            'description' => $_POST['description'],
                            'url' => $_POST['url']
                        ];
                        
                        $metadataFilePath = $metadataDir . '/' . $fileHash . '.json';
                        if (!file_exists($metadataFilePath)) {                            
                            $file = fopen($metadataFilePath, 'w');
                            fwrite($file, json_encode($metadata, JSON_PRETTY_PRINT));
                            fclose($file);
                        }
                    }

                    // Create empty file in category folder with hash + extension name
                    $categoryFilePath = $categoryDir . '/' . $fileNameWithExtension; // Empty file name is file hash + extension inside category folder
                    if (touch($categoryFilePath)) {
                        // Empty file created successfully
                        
                        $contentHead = "<link rel='stylesheet' href='../../default.css'><script src='../../default.js'></script><script src='../../ads.js'></script><div id='ads' name='ads' class='ads'></div><div id='default' name='default' class='default'></div>";
 
                        // Handle index.html inside file hash folder (for content links)
                        $indexPathFileFolder = $fileUploadDir . '/index.html';

                        if (file_exists($indexPathFileFolder)){

                            $indexPathFileFolderSize = filesize($indexPathFileFolder);
                       
                            if ($indexPathFileFolderSize > $sizeLimit) {
                                 $currentDate = date('Ymd');
                                 $indexPathFileFolder = $fileUploadDir . '/index_' . $currentDate .  '.html';
                            } 
                        }

                        if (!file_exists($indexPathFileFolder)) {
                            $file = fopen($indexPathFileFolder, 'a');
                            fwrite($file, $contentHead);
                            fclose($file);
                        }

                        $fileImage = "";
                        $fileImageCategory = ""; 

                        if (isset($_POST['url']) && !empty($_POST['url'])) {
  
                            $fileImage = '<a href="' . htmlspecialchars($_POST['url']) . '"><img src="' . htmlspecialchars($_POST['url']) . '" width="100%"></a><br>';
                            $fileImageCategory = '<a href="' . htmlspecialchars($_POST['url']) . '"><img src="' . htmlspecialchars($_POST['url']) . '" width="100%"></a><br>';
                        }

                        if (strtolower($fileExtension) === 'jpg' || strtolower($fileExtension) === 'png') {
                            $fileImage = '<a href="' . htmlspecialchars($fileNameWithExtension) . '"><img src="' . htmlspecialchars($fileNameWithExtension) . '" width="100%"></a><br>';
                            $fileImageCategory = '<a href="../' . $fileHash . '/' . htmlspecialchars($fileNameWithExtension) . '"><img src="../' . $fileHash . '/' . htmlspecialchars($fileNameWithExtension) . '" width="100%"></a><br>';
                        }   

                        if ($isTextContent){
                            $fileNameWithExtension = $_POST['text_content'];

                            if (stripos($fileNameWithExtension, 'http') !== 0) {
                                $relativePathToFile = 'https://' . $fileNameWithExtension;
                            }
                        }                        

                        $linkLike = $fileImage . '<a href="../../like.php?reply=' . htmlspecialchars($fileHash) . '">' . "<img src='../../icons/thumb_up.png' alt='[ Like ]'>" . '</a> ';
                        $linkReply = $linkLike . '<a href="../../index.php?reply=' . htmlspecialchars($fileHash) . '">' . "<img src='../../icons/arrow_undo.png' alt='[ Reply ]'>" . '</a> ';
                        $linkToHash = $linkReply . '<a href="../' . htmlspecialchars($fileHash) . '/index.html">' . "<img src='../../icons/text_align_justity.png' alt='[ Open ]'>" . '</a> ';
                        $linkToFileFolderIndex = $linkToHash . '<a href="' . htmlspecialchars($fileNameWithExtension) . '">' . htmlspecialchars($originalFileName) . '</a><br>' ; //Use original file name or 'text_content.txt' for link text
                        
                        $indexContentFileFolder = file_get_contents($indexPathFileFolder);
                        if (strpos($indexContentFileFolder, $linkToFileFolderIndex) === false) {
                            $file = fopen($indexPathFileFolder, 'w');
                            fwrite($file, $indexContentFileFolder . $linkToFileFolderIndex);
                            fclose($file);  
                        }

                        // Handle index.html inside category folder (for link to original content)
                        $indexPathCategoryFolder = $categoryDir . '/index.html';

                        if (file_exists($indexPathCategoryFolder)){

                            $indexPathCategoryFolderSize = filesize($indexPathCategoryFolder);

                            if ($indexPathCategoryFolderSize > $sizeLimit) {
                                 $currentDate = date('Ymd');
                                 $indexPathCategoryFolder = $categoryDir . '/index_' . $currentDate .  '.html';
                            }
                        }

                        if (!file_exists($indexPathCategoryFolder)) {
                            $file = fopen($indexPathCategoryFolder, 'a');
                            fwrite($file, $contentHead);
                            fclose($file);                 
                        }

                        // Construct relative path to the content in the content hash folder
                        $relativePathToFile = '../' . $fileHash . '/' . $fileNameWithExtension;
                        
                        if ($isTextContent){
                            $relativePathToFile = $_POST['text_content'];

                            if (stripos($relativePathToFile, 'http') !== 0) {
                                $relativePathToFile = 'https://' . $relativePathToFile;
                            }

                        }  
   
                        $categoryLike = $fileImageCategory . '<a href="../../like.php?reply=' . htmlspecialchars($fileHash) . '">' . "<img src='../../icons/thumb_up.png' alt='[ Like ]'>" . '</a> ';
                        $categoryReply = $categoryLike . '<a href="../../index.php?reply=' . htmlspecialchars($fileHash) . '">' . "<img src='../../icons/arrow_undo.png' alt='[ Reply ]'>" . '</a> ';
                        $linkToHashCategory = $categoryReply . '<a href="../' . htmlspecialchars($fileHash) . '/index.html">' . "<img src='../../icons/text_align_justity.png' alt='[ Open ]'>" . '</a> ';
                        $linkToCategoryFolderIndex = $linkToHashCategory . '<a href="' . htmlspecialchars($relativePathToFile) . '">' . htmlspecialchars($originalFileName) . '</a><br>'; //Use original file name or 'text_content.txt' for link text
                        $indexContentCategoryFolder = file_get_contents($indexPathCategoryFolder);
                        if (strpos($indexContentCategoryFolder, $linkToCategoryFolderIndex) === false) {
                            $file = fopen($indexPathCategoryFolder, 'w');
                            fwrite($file, $indexContentCategoryFolder . $linkToCategoryFolderIndex);
                            fclose($file);  
                        }

                        if (isset($_SESSION['user'])){

                            $usernameHash = hash('sha256', $_SESSION['user']);
 
                            $fileUserFolder = 'files';

                            // Create directories if they don't exist
                            if (!is_dir($fileUserFolder)) {
                                mkdir($fileUserFolder, 0777, true);
                            }

                        $usernameFilePath = $fileUserFolder . '/'. $fileHash . '.txt';

                        $file = fopen($usernameFilePath, 'w');
                        fwrite($file, $usernameHash);
                        fclose($file);  
                        }                                      

                        if ($morePages == 1){
                            $originalFileName = strtolower($originalFileName);                            

                            $originalFileName = str_replace(" ", "_", $originalFileName);
                            $originalFileName = str_replace(".", "_", $originalFileName);

                            $pages = explode("_", $originalFileName);
                            $pages = array_map('trim', $pages);
                 
                            $folderFile = "";
  
                            // Upload file to each URL
                            foreach ($pages as $page) {

                                if ($page == $categoryText){continue;}
 
                                $pageHash = hash('sha256', $page);

                                $folder = "data/" . $pageHash;

                                $folderFile = $folder . '/index.html';

                                echo $page . ' ' . $pageHash . ' ' . '<br>';
 
                                if (!is_dir($folder)) {
                                    mkdir($folder, 0777, true);
                                }                             
 
                                if (file_exists($folderFile)){

                                    $indexPathCategoryFolderSize = filesize($folderFile);

                                    if ($indexPathCategoryFolderSize > $sizeLimit) {
                                        $currentDate = date('Ymd');
                                        $folderFile = $folder . '/index_' . $currentDate .  '.html';
                                    }

                                $file = fopen($folderFile, 'a');
                                fwrite($file, $linkToCategoryFolderIndex);
                                fclose($file);  

                                } else {

                                $file = fopen($folderFile, 'a');
                                fwrite($file, $contentHead . $linkToCategoryFolderIndex);
                                fclose($file);  

                                }
    
                           
                               
                            }
                        }
                    }

                    echo "<p class='success'>Content processed successfully!</p>";
                    echo "<p>Content saved in: <pre><a href='" . htmlspecialchars($indexPathCategoryFolder) . "'>$indexPathCategoryFolder</a></pre></p>";
                } else {
                    echo "<p class='error'>Error creating empty file in category folder.</p>";
                }
            } else {
                echo "<p class='error'>Error saving content.</p>";
            }
        } else {
            echo "<p class='error'>Please select a file or enter text content and provide a category.</p>";
        }
    }
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganhe dinheiro compartilhando arquivos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
            color: #333;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 50px;
        }
        .language-switcher {
            padding: 8px 16px;
            border: 1px solid #ccc;
            border-radius: 20px;
            background: white;
            cursor: pointer;
            font-size: 14px;
        }
        .contact-button {
            padding: 8px 16px;
            background-color: #FF7F00; /* Laranja */
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            color: white;
        }
        .main-content {
            max-width: 800px;
            margin: 0 auto;
        }
        .main-heading {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .sub-heading {
            font-size: 24px;
            margin-bottom: 40px;
            color: #555;
        }
        .search-form, .upload-form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
        }
        input[type="text"], input[type="file"], input[type="url"] {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            background: #f9f9f9;
        }
        button[type="submit"], input[type="submit"] {
            background-color: #777;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
            font-weight: normal;
        }
        .more-options-link {
            color: #555;
            text-decoration: none;
            cursor: pointer;
            display: inline-block;
            margin: 10px 0;
            font-size: 14px;
        }
        .optional-fields {
            display: none;
            margin-top: 15px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        .footer-links {
            text-align: center;
            margin: 30px 0;
        }
        .footer-links a {
            margin: 0 10px;
            text-decoration: none;
            color: #555;
            font-size: 14px;
        }
        .copyright {
            text-align: center;
            color: #999;
            font-size: 13px;
        }
        label {
            display: block;
            margin-top: 15px;
            color: #555;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">

        <button class="contact-button" onclick="window.open('https://example.com/contact', '_blank')">Contato</button>
        <button class="language-switcher" onclick="toggleLanguage()">Português</button>
    </div>

    <div class="main-content">
        <h1 class="main-heading">Ganhe dinheiro compartilhando arquivos</h1>
        <p class="sub-heading">Quanto mais likes e comentários você receber maiores são as suas recompensas. Crie a sua conta por apenas 25,00 R$</p>
        
        <form method="GET" action="" id="search-form" class="search-form">
            <input type="text" id="search" name="search" placeholder="Digite o hash do arquivo ou categoria" required>
            <button type="submit">Buscar</button>
        </form>

        <h2>Enviar Arquivo</h2>
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); if(isset($_GET['reply'])){echo "?reply=" . $_GET['reply'];} ?>" method="post" enctype="multipart/form-data" class="upload-form">
            <label for="uploaded_file">Selecione o arquivo:</label>
            <input type="file" name="uploaded_file" id="uploaded_file">

            <label for="text_content">Ou digite o conteúdo:</label>
            <input type="text" name="text_content" id="text_content">

            <label for="category">Categoria:</label>
            <input type="text" name="category" id="category" value="<?php if(isset($_GET['reply'])){echo $_GET['reply'];} ?>" required <?php if(isset($_GET['reply'])){echo "readonly";} ?>>

            <a id="more-options-link" class="more-options-link">Mais opções</a>
            
            <div id="optional-fields" class="optional-fields">
                <label for="btc">BTC/PIX (opcional):</label>
                <input type="text" name="btc" id="btc" placeholder="Endereço BTC">

                <label for="user">Usuário (opcional):</label>
                <input type="text" name="user" id="user" placeholder="Nome de usuário">

                <label for="title">Título (opcional):</label>
                <input type="text" name="title" id="title" placeholder="Título do conteúdo">

                <label for="description">Descrição (opcional):</label>
                <input type="text" name="description" id="description" placeholder="Descrição do conteúdo">

                <label for="url">URL da imagem (opcional):</label>
                <input type="url" name="url" id="url" placeholder="URL da miniatura">
            </div>
            <input type="submit" value="Enviar">
        </form>

        <div class="footer-links">
            <a href="about.html">Sobre</a>
            <a href="add_server.php">Adicionar servidor</a>
            <a href="downloader.php">Downloader</a>
            <a href="blockchain.php">Blockchain</a>  
            <a href="rank.php">Ranking</a>   
        </div>
        <div class="copyright">Todos os direitos reservados</div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const moreOptionsLink = document.getElementById('more-options-link');
            const optionalFields = document.getElementById('optional-fields');

            moreOptionsLink.addEventListener('click', () => {
                const isHidden = optionalFields.style.display === 'none' || optionalFields.style.display === '';
                optionalFields.style.display = isHidden ? 'block' : 'none';
                moreOptionsLink.textContent = isHidden ? 'Menos opções' : 'Mais opções';
            });

            let currentLanguage = 'pt';
            
            function toggleLanguage() {
                const languageButton = document.querySelector('.language-switcher');
                
                if (currentLanguage === 'pt') {
                    // Mudar para inglês
                    document.querySelector('.main-heading').textContent = 'Make money by sharing files';
                    document.querySelector('.sub-heading').textContent = 'The more likes and comments you receive, the higher your rewards. Create your account for only R$25.00';
                    document.querySelector('.contact-button').textContent = 'Contact';
                    languageButton.textContent = 'English';
                    
                    // Atualizar formulário
                    document.querySelector('#search-form input').placeholder = 'Enter file hash or category';
                    document.querySelector('#search-form button').textContent = 'Search';
                    document.querySelector('h2').textContent = 'Upload File';
                    document.querySelector('label[for="uploaded_file"]').textContent = 'Select File:';
                    document.querySelector('label[for="text_content"]').textContent = 'Or enter text content:';
                    document.querySelector('label[for="category"]').textContent = 'Category:';
                    document.querySelector('#more-options-link').textContent = optionalFields.style.display === 'block' ? 'Less options' : 'More options';
                    document.querySelector('input[type="submit"]').value = 'Upload';
                    
                    currentLanguage = 'en';
                } else {
                    // Mudar para português
                    document.querySelector('.main-heading').textContent = 'Ganhe dinheiro compartilhando arquivos';
                    document.querySelector('.sub-heading').textContent = 'Quanto mais likes e comentários você receber maiores são as suas recompensas. Crie a sua conta por apenas 25,00 R$';
                    document.querySelector('.contact-button').textContent = 'Contato';
                    languageButton.textContent = 'Português';
                    
                    // Atualizar formulário
                    document.querySelector('#search-form input').placeholder = 'Digite o hash do arquivo ou categoria';
                    document.querySelector('#search-form button').textContent = 'Buscar';
                    document.querySelector('h2').textContent = 'Enviar Arquivo';
                    document.querySelector('label[for="uploaded_file"]').textContent = 'Selecione o arquivo:';
                    document.querySelector('label[for="text_content"]').textContent = 'Ou digite o conteúdo:';
                    document.querySelector('label[for="category"]').textContent = 'Categoria:';
                    document.querySelector('#more-options-link').textContent = optionalFields.style.display === 'block' ? 'Menos opções' : 'Mais opções';
                    document.querySelector('input[type="submit"]').value = 'Enviar';
                    
                    currentLanguage = 'pt';
                }
            }
            
            // Expor a função para o botão
            window.toggleLanguage = toggleLanguage;
        });
    </script>
</body>
</html>