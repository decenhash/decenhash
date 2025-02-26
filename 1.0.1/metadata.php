<?php
class Block {
    private int $index;
    private string $previousHash;
    private string $timestamp;
    private array $data;
    private string $hash;

    public function __construct(int $index, string $previousHash, array $data) {
        $this->index = $index;
        $this->previousHash = $previousHash;
        $this->timestamp = date('Y-m-d H:i:s');
        $this->data = $data;
        $this->hash = $this->calculateHash();
    }

    private function calculateHash(): string {
        return hash('sha256', $this->index . $this->previousHash . $this->timestamp . json_encode($this->data));
    }

    public function getBlockData(): array {
        return [
            'index' => $this->index,
            'previousHash' => $this->previousHash,
            'timestamp' => $this->timestamp,
            'data' => $this->data,
            'hash' => $this->hash
        ];
    }

    public function getHash(): string {
        return $this->hash;
    }
}

class Blockchain {
    private array $chain;
    private string $baseStoragePath;

    public function __construct(string $baseStoragePath = 'blocks/') {
        $this->chain = [];
        $this->baseStoragePath = rtrim($baseStoragePath, '/') . '/';
        if (!is_dir($this->baseStoragePath)) {
            mkdir($this->baseStoragePath, 0777, true);
        }
    }

    public function getChainLength(): int {
        return count($this->chain);
    }

    private function getStoragePath(string $category): string {
        $path = $this->baseStoragePath . trim($category, '/') . '/';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        return $path;
    }

    private function loadChain(string $category): void {
        $path = $this->getStoragePath($category);
        $files = glob($path . '*.txt');
        usort($files, function ($a, $b) {
            return (int)basename($a, '.txt') - (int)basename($b, '.txt');
        });
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            $block = new Block($data['index'], $data['previousHash'], $data['data']);
            $this->chain[] = $block;
        }
    }

    public function createBlock(array $data, string $category): Block {
        $this->loadChain($category);
        $index = count($this->chain);
        $previousHash = $index > 0 ? $this->chain[$index - 1]->getHash() : '0';
        $block = new Block($index, $previousHash, $data);
        $this->chain[] = $block;
        $this->saveBlock($block, $category);
        return $block;
    }

    private function saveBlock(Block $block, string $category): void {
        $path = $this->getStoragePath($category);
        $filename = $path . $block->getBlockData()['index'] . '.txt';
        file_put_contents($filename, json_encode($block->getBlockData(), JSON_PRETTY_PRINT));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = '';
    $success = '';
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        
        if ($file['size'] > 10 * 1024 * 1024) {
            $error = 'File size exceeds 10MB limit';
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension === 'php') {
            $error = 'PHP files are not allowed';
        }
        
        if (empty($error)) {
            $filehash = hash_file('sha256', $file['tmp_name']);
            
            unlink($file['tmp_name']);
            $folder = 'unique';

            if (!is_dir($folder)) {
                if (!mkdir($folder, 0755, true)) {
                    die("Failed to create folder '$folder'.\n");
                }
            }

            $filepath = $folder . DIRECTORY_SEPARATOR . $filehash;

            if (file_exists($filepath)) {
                die("File with the hash '$filehash' already exists.\n");
            }

            $handle = fopen($filepath, 'w');
            if (!$handle) {
                die("Failed to create the file '$filepath'.\n");
            }
            fclose($handle);
        }
    }
    
    if (empty($error)) {
        $blockData = [
            'user' => $_POST['user'] ?? '',
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'filehash' => $filehash ?? '',
            'category' => $_POST['category'] ?? 'default'
        ];

        $defaultCategory = "default";
        
        $blockchain = new Blockchain();
        $block = $blockchain->createBlock($blockData, $defaultCategory);
        $blockPath = 'blocks/' . $defaultCategory  . '/' . ($blockchain->getChainLength() - 1) . '.txt';
        $success = "<a href='" . htmlspecialchars($blockPath) . "' target='_blank'>Block created successfully! </a>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Block Form</title>
    <style>
        body {
            margin: 0;
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #0e2f44, #10a3a3);
            color: #ffffff;
            min-height: 100vh;
            padding: 20px;
            box-sizing: border-box;
            padding-bottom: 80px;
        }

        form {
            max-width: 600px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 30px;
        }

        .error, .success {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .error {
            background: rgba(255, 0, 0, 0.2);
            border: 1px solid rgba(255, 0, 0, 0.3);
        }

        .success {
            background: rgba(0, 255, 163, 0.2);
            border: 1px solid rgba(0, 255, 163, 0.3);
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 1.1em;
            color: #00ffa3;
        }

        input[type="text"],
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
            font-size: 1em;
            box-sizing: border-box;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        input[type="file"] {
            background: transparent;
            padding: 10px 0;
            color: #ffffff;
        }

        button {
            background: #00ffa3;
            color: #0e2f44;
            font-size: 1.2em;
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.3s ease;
            display: block;
            width: 100%;
            margin-top: 30px;
        }

        button:hover {
            background: #10a3a3;
            transform: scale(1.02);
        }

        footer {
            text-align: center;
            padding: 20px;
            font-size: 0.9em;
            background: rgba(0, 0, 0, 0.5);
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
        }

        h1 {
            font-size: 3em;
            margin-bottom: 20px;
        }

        a {
            color: #FFFFFF;
        }
    </style>
</head>
<body>
<div align="center"><h1>Metadata</h1></div>
    <form method="POST" enctype="multipart/form-data">        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="form-group">
            <label for="user">User:</label>
            <input type="text" name="user" id="user" required>
        </div>
        
        <div class="form-group">
            <label for="title">Title:</label>
            <input type="text" name="title" id="title" required>
        </div>
        
        <div class="form-group">
            <label for="description">Description:</label>
            <textarea name="description" id="description" required></textarea>
        </div>
        
        <div class="form-group">
            <label for="category">Category:</label>
            <input type="text" name="category" id="category" required>
        </div>
        
        <div class="form-group">
            <label for="file">Select File (Max 10MB, no PHP files):</label>
            <input type="file" name="file" id="file" required>
        </div>
        
        <button type="submit">Create Block</button>
    </form>
</body>
</html>