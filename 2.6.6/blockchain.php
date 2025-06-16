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

// Create directories if they don't exist
$directories = ['files_ownership', 'blocks'];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0755, true)) {
            die("Error: Could not create $dir directory");
        }
    }
}

// Display form if no parameters provided
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['btc'])) {
    // Read servers.txt if it exists
    $servers = [];
    if (file_exists('servers.txt')) {
        $servers = file('servers.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }
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
        
        #serverResponses {
            margin-top: 20px;
        }
        
        .server-response {
            background-color: white;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .server-url {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .response-status {
            color: #666;
        }
        
        .response-content {
            margin-top: 5px;
            font-family: monospace;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        #processingIndicator {
            display: none;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>BTC and Filehash Processor</h1>
        
        <form id="mainForm">
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
        
        <div id="processingIndicator">
            <p>Processing request and sending to servers...</p>
        </div>
        
        <div id="serverResponses"></div>
    </div>

    <script>
        // Store servers from PHP
        const servers = <?php echo json_encode($servers); ?>;
        
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
            
            // Form submission
            document.getElementById('mainForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const activeOption = document.querySelector('.hash-option-btn.active').getAttribute('data-target');
                const btc = document.getElementById('btc').value.trim();
                const filehash = document.getElementById('filehash').value.trim();
                
                // Validate inputs
                if (activeOption === 'file-hash' && !filehash) {
                    alert('Please select a file to calculate its hash');
                    return;
                }
                
                if (!btc) {
                    alert('Please enter a Bitcoin address');
                    return;
                }
                
                // Validate Bitcoin address format
                const btcRegex = /^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$|^bc1[a-z0-9]{39,59}$/i;
                if (!btcRegex.test(btc)) {
                    alert('Error: Invalid Bitcoin address format');
                    return;
                }
                
                // Validate SHA256 format if manually entered
                if (activeOption === 'type-hash') {
                    const sha256Regex = /^[a-f0-9]{64}$/i;
                    if (!sha256Regex.test(filehash)) {
                        alert('Error: Invalid filehash (must be SHA256)');
                        return;
                    }
                }
                
                // Show processing indicator
                document.getElementById('processingIndicator').style.display = 'block';
                document.getElementById('serverResponses').innerHTML = '';
                
                try {
                    // First save locally
                    const localResponse = await fetch(window.location.href + `?btc=${encodeURIComponent(btc)}&filehash=${encodeURIComponent(filehash)}`);
                    const localResult = await localResponse.text();
                    
                    // Check if local save was successful
                    if (!localResponse.ok) {
                        throw new Error('Local save failed');
                    }
                    
                    // Display local result
                    const localResponseDiv = document.createElement('div');
                    localResponseDiv.className = 'server-response';
                    localResponseDiv.innerHTML = `
                        <div class="server-url">Local Server</div>
                        <div class="response-status">Status: ${localResponse.status}</div>
                        <div class="response-content">${localResult}</div>
                    `;
                    document.getElementById('serverResponses').appendChild(localResponseDiv);
                    
                    // If no remote servers, we're done
                    if (servers.length === 0) {
                        document.getElementById('processingIndicator').style.display = 'none';
                        return;
                    }
                    
                    // Send to each server in servers.txt
                    for (const server of servers) {
                        try {
                            const url = new URL(server);
                            const separator = url.search ? '&' : '?';
                            const fullUrl = server + separator + `btc=${encodeURIComponent(btc)}&filehash=${encodeURIComponent(filehash)}`;
                            
                            const response = await fetch(fullUrl, {
                                method: 'GET',
                                mode: 'cors',
                                cache: 'no-cache'
                            });
                            
                            const result = await response.text();
                            
                            const responseDiv = document.createElement('div');
                            responseDiv.className = 'server-response';
                            responseDiv.innerHTML = `
                                <div class="server-url">${server}</div>
                                <div class="response-status">Status: ${response.status}</div>
                                <div class="response-content">${result}</div>
                            `;
                            document.getElementById('serverResponses').appendChild(responseDiv);
                            
                        } catch (error) {
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'server-response error';
                            errorDiv.innerHTML = `
                                <div class="server-url">${server}</div>
                                <div class="response-status">Error</div>
                                <div class="response-content">Failed to connect to server: ${error.message}</div>
                            `;
                            document.getElementById('serverResponses').appendChild(errorDiv);
                        }
                    }
                    
                } catch (error) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'server-response error';
                    errorDiv.innerHTML = `
                        <div class="response-content">Error processing request: ${error.message}</div>
                    `;
                    document.getElementById('serverResponses').appendChild(errorDiv);
                } finally {
                    document.getElementById('processingIndicator').style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
<?php
    exit;
}

// The rest of the PHP code remains the same for handling the GET requests
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
    
    // Save to ownership file
    if (file_put_contents($filename, $btc) === false) {
        die("Error: Could not save ownership file");
    }
    
    // Create blockchain-style record
    $blockData = [
        'version' => '1.0',
        'timestamp' => time(),
        'previous_hash' => getPreviousBlockHash(),
        'filehash' => $filehash,
        'bitcoin_address' => $btc,
        'nonce' => generateNonce()
    ];
    
    // Calculate hash for this block
    $blockHash = hash('sha256', json_encode($blockData));
    $blockData['hash'] = $blockHash;
    
    // Save to blockchain
    $blockFilename = 'blocks/' . $blockHash . '.json';
    if (file_put_contents($blockFilename, json_encode($blockData, JSON_PRETTY_PRINT)) === false) {
        unlink($filename); // Rollback ownership file if block save fails
        die("Error: Could not save block file");
    }
    
    echo "<div class='success'>Success: Data saved to $filename and block $blockHash</div>";
    exit;
}

// Helper function to get the hash of the previous block
function getPreviousBlockHash() {
    $blocks = glob('blocks/*.json');
    if (empty($blocks)) {
        return '0000000000000000000000000000000000000000000000000000000000000000'; // Genesis block
    }
    
    // Get the most recent block
    usort($blocks, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    $lastBlock = json_decode(file_get_contents($blocks[0]), true);
    return $lastBlock['hash'] ?? '0000000000000000000000000000000000000000000000000000000000000000';
}

// Helper function to generate a nonce
function generateNonce() {
    return bin2hex(random_bytes(16));
}
?>