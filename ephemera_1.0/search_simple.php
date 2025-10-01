<?php
$searchQuery = "";

// Get input from GET or POST
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['search'])) {
    $searchQuery = trim($_GET['search']);
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['search'])) {
    $searchQuery = trim($_POST['search']);
}

$results = [];

if ($searchQuery !== "") {
    $jsonFiles = glob("json/*.json");

    foreach ($jsonFiles as $file) {
        $jsonContent = file_get_contents($file);
        $data = json_decode($jsonContent, true);

        if ($data && stripos($jsonContent, $searchQuery) !== false) {
            $filenameBase = pathinfo($file, PATHINFO_FILENAME);
            $fileLink = "";

            // If file exists in "files" folder
            if (!empty($data['type'])) {
                $possibleFile = "files/" . $filenameBase . "." . $data['type'];
                if (file_exists($possibleFile)) {
                    $fileLink = $possibleFile;
                }
            }

            $results[] = [
                "data" => $data,
                "fileLink" => $fileLink,
                "filenameBase" => $filenameBase // Store filename for redirect
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Resultados da Pesquisa</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #fafafa;
            margin: 0;
            padding: 0;
        }
        header {
            background: #2e7d32;
            color: white;
            padding: 1rem;
            text-align: center;
        }
        main {
            padding: 2rem;
        }
        form {
            margin-bottom: 2rem;
            text-align: center;
        }
        input[type="text"] {
            width: 50%;
            padding: .6rem;
            font-size: 1rem;
            border-radius: 6px;
            border: 1px solid #ccc;
        }
        button {
            padding: .6rem 1rem;
            margin-left: .5rem;
            background: #2e7d32;
            border: none;
            border-radius: 6px;
            color: white;
            font-size: 1rem;
            cursor: pointer;
        }
        button:hover {
            background: #256628;
        }
        .result {
            background: white;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .result h3 {
            margin-top: 0;
            background: linear-gradient(90deg, #2e7d32, #66bb6a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .result p {
            margin: .3rem 0;
        }
        a {
            color: #2e7d32;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<header>
    <h2>Resultados da Pesquisa</h2>
</header>

<main>
    <form method="get" action="search.php">
        <input type="text" name="search" placeholder="Digite sua pesquisa..." value="<?php echo htmlspecialchars($searchQuery); ?>">
        <button type="submit">Buscar</button>
    </form>

    <?php if ($searchQuery === ""): ?>
        <p>Digite algo no campo de pesquisa para começar.</p>
    <?php elseif (empty($results)): ?>
        <p>Nenhum resultado encontrado para <strong><?php echo htmlspecialchars($searchQuery); ?></strong>.</p>
    <?php else: ?>
        <h3>Resultados encontrados:</h3>
        <?php foreach ($results as $result): ?>
            <div class="result">
                <h3><?php echo htmlspecialchars($result['data']['title'] ?? "Sem título"); ?></h3>
                <?php foreach ($result['data'] as $key => $value): ?>
                    <?php if ($key === "url"): ?>
                        <p><strong><?php echo htmlspecialchars($key); ?>:</strong>
                            <a href="redirect.php?hash=<?php echo htmlspecialchars($result['filenameBase']); ?>" target="_blank">Abrir Link</a>
                        </p>
                    <?php else: ?>
                        <p><strong><?php echo htmlspecialchars($key); ?>:</strong> <?php echo htmlspecialchars($value); ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if (!empty($result['fileLink'])): ?>
                    <p><strong>Arquivo enviado:</strong>
                        <a href="redirect.php?hash=<?php echo htmlspecialchars($result['filenameBase']); ?>" target="_blank">Abrir Arquivo</a>
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
</body>
</html>