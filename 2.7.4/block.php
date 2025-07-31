<?php
// Function to validate SHA256 hash
function isValidSha256($hash) {
    return preg_match('/^[a-f0-9]{64}$/i', $hash);
}

// Function to validate Bitcoin address
function isValidBitcoinAddress($address) {
    return preg_match('/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $address) || 
           preg_match('/^bc1[a-z0-9]{39,59}$/i', $address);
}

// Create files_ownership directory if it doesn't exist
if (!file_exists('files_ownership')) {
    if (!mkdir('files_ownership', 0755, true)) {
        die("Error: Could not create files_ownership directory");
    }
}

// Check if we're in save mode (parameters provided)
if (isset($_GET['btc']) && isset($_GET['filehash'])) {
    $btc = trim($_GET['btc']);
    $filehash = trim($_GET['filehash']);
    
    // Validate inputs
    if (!isValidSha256($filehash)) {
        die("Error: Invalid filehash (must be SHA256)");
    }
    
    if (!isValidBitcoinAddress($btc)) {
        die("Error: Invalid Bitcoin address");
    }
    
    // Check if file exists in files_ownership directory
    $filename = 'files_ownership/' . $filehash . '.txt';
    if (file_exists($filename)) {
        die("Error: File already exists");
    }
    
    // Save to file
    if (file_put_contents($filename, $btc) !== false) {
        echo "<div class='success'>Success: Data saved to $filename</div>";
    } else {
        echo "<div class='error'>Error: Could not save file</div>";
    }
    
    exit;
}

// Display form if no parameters provided
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['btc'])) {
?>
<!DOCTYPE html>
<html>
<head>
    <title>BTC and Filehash Processor</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        input[type="text"],
        input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        input[type="submit"] {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 12px 20px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s;
        }
        
        input[type="submit"]:hover {
            background-color: #2980b9;
        }
        
        .hash-options {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .hash-option-btn {
            flex: 1;
            padding: 10px;
            background-color: #ecf0f1;
            border: 1px solid #bdc3c7;
            border-radius: 4px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }
        
        .hash-option-btn.active {
            background-color: #3498db;
            color: white;
            border-color: #2980b9;
        }
        
        .hash-input-container {
            display: none;
        }
        
        .hash-input-container.active {
            display: block;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        #hashProgress {
            margin-top: 10px;
            display: none;
        }
        
        progress {
            width: 100%;
            height: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>BTC and Filehash Processor</h1>
        
        <form method="get" id="mainForm">
            <div class="form-group">
                <label for="btc">Bitcoin Address:</label>
                <input type="text" id="btc" name="btc" required>
            </div>
            
            <div class="form-group">
                <label>Filehash (SHA256):</label>
                <div class="hash-options">
                    <div class="hash-option-btn active" data-target="type-hash">Type Hash</div>
                    <div class="hash-option-btn" data-target="file-hash">Select File</div>
                </div>
                
                <div id="type-hash" class="hash-input-container active">
                    <input type="text" id="filehash" name="filehash" placeholder="Enter SHA256 hash">
                </div>
                
                <div id="file-hash" class="hash-input-container">
                    <input type="file" id="fileInput">
                    <progress id="hashProgress" value="0" max="100"></progress>
                </div>
            </div>
            
            <input type="submit" value="Submit">
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Hash option switching
            const optionButtons = document.querySelectorAll('.hash-option-btn');
            const inputContainers = document.querySelectorAll('.hash-input-container');
            
            optionButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Update active button
                    optionButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show corresponding input container
                    const target = this.getAttribute('data-target');
                    inputContainers.forEach(container => {
                        container.classList.remove('active');
                        if (container.id === target) {
                            container.classList.add('active');
                        }
                    });
                    
                    // Update form requirements
                    document.getElementById('filehash').required = (target === 'type-hash');
                });
            });
            
            // File hash calculation
            const fileInput = document.getElementById('fileInput');
            const hashProgress = document.getElementById('hashProgress');
            const filehashField = document.getElementById('filehash');
            
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;
                
                hashProgress.style.display = 'block';
                hashProgress.value = 0;
                
                // Read the file and calculate hash
                const reader = new FileReader();
                const crypto = window.crypto || window.msCrypto;
                const algorithm = {name: 'SHA-256'};
                
                reader.onload = function(e) {
                    const fileData = new Uint8Array(e.target.result);
                    const chunkSize = 1024 * 1024; // 1MB chunks
                    let offset = 0;
                    let hashBuffer;
                    
                    function processChunk() {
                        const chunk = fileData.subarray(offset, offset + chunkSize);
                        offset += chunkSize;
                        
                        if (!hashBuffer) {
                            // First chunk - initialize hash
                            crypto.subtle.digest(algorithm, chunk)
                                .then(hash => {
                                    hashBuffer = new Uint8Array(hash);
                                    updateProgress();
                                    if (offset < fileData.length) {
                                        processChunk();
                                    } else {
                                        finalizeHash();
                                    }
                                });
                        } else {
                            // Subsequent chunks - combine with existing hash
                            const combined = new Uint8Array(hashBuffer.length + chunk.length);
                            combined.set(hashBuffer);
                            combined.set(chunk, hashBuffer.length);
                            
                            crypto.subtle.digest(algorithm, combined)
                                .then(hash => {
                                    hashBuffer = new Uint8Array(hash);
                                    updateProgress();
                                    if (offset < fileData.length) {
                                        processChunk();
                                    } else {
                                        finalizeHash();
                                    }
                                });
                        }
                    }
                    
                    function updateProgress() {
                        const progress = Math.min(100, Math.round((offset / fileData.length) * 100));
                        hashProgress.value = progress;
                    }
                    
                    function finalizeHash() {
                        // Convert hash to hex string
                        const hashArray = Array.from(hashBuffer);
                        const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
                        filehashField.value = hashHex;
                        hashProgress.value = 100;
                    }
                    
                    processChunk();
                };
                
                reader.onerror = function() {
                    alert('Error reading file');
                    hashProgress.style.display = 'none';
                };
                
                reader.readAsArrayBuffer(file);
            });
            
            // Form validation
            document.getElementById('mainForm').addEventListener('submit', function(e) {
                const activeOption = document.querySelector('.hash-option-btn.active').getAttribute('data-target');
                
                if (activeOption === 'file-hash' && !filehashField.value) {
                    e.preventDefault();
                    alert('Please select a file to calculate its hash');
                }
            });
        });
    </script>
</body>
</html>
<?php
    exit;
}

// Process servers.txt if parameters are provided but not for saving
if (file_exists('servers.txt')) {
    $servers = file('servers.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    if (!empty($servers)) {
        $btc = urlencode($_GET['btc']);
        $filehash = urlencode($_GET['filehash']);
        
        foreach ($servers as $server) {
            $url = trim($server);
            
            // Check if URL has query string
            $separator = (parse_url($url, PHP_URL_QUERY) === null) ? '?' : '&';
            $requestUrl = $url . $separator . "btc=$btc&filehash=$filehash";
            
            // Initialize cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $requestUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            // Execute request
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Output result
            echo "Server: $url<br>";
            echo "Response Code: $httpCode<br>";
            echo "Response: " . htmlspecialchars(substr($response, 0, 200)) . "<br><br>";
            
            curl_close($ch);
        }
    } else {
        echo "<div class='error'>No servers found in servers.txt</div>";
    }
} else {
    echo "<div class='error'>Error: servers.txt file not found</div>";
}
?>