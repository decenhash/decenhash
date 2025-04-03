import javax.swing.*;
import java.awt.*;
import java.awt.event.*;
import java.io.*;
import java.net.*;
import java.nio.file.*;
import java.security.*;
import java.util.*;
import java.util.List;
import java.util.concurrent.*;

public class FileHashCheckerGUI extends JFrame {
    // Constants
    private static final String DATA_DIR = "data";
    private static final String SERVERS_DIR = "servers";
    private static final String RESULTS_FILE = "results.txt";
    private static final int TIMEOUT_MS = 5000;
    private static final int BUFFER_SIZE = 8192;
    
    // UI Components
    private JTextArea logArea;
    private JButton startButton;
    private JButton stopButton;
    private JProgressBar progressBar;
    private JLabel statusLabel;
    private JCheckBox saveResultsCheckbox;
    
    // Execution control
    private volatile boolean running = false;
    private ExecutorService executorService;
    
    public FileHashCheckerGUI() {
        setTitle("File Hash Checker");
        setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        setSize(800, 600);
        setLocationRelativeTo(null);
        
        initComponents();
        layoutComponents();
    }
    
    private void initComponents() {
        logArea = new JTextArea();
        logArea.setEditable(false);
        logArea.setFont(new Font("Monospaced", Font.PLAIN, 12));
        
        startButton = new JButton("Start Check");
        startButton.addActionListener(e -> startChecking());
        
        stopButton = new JButton("Stop");
        stopButton.setEnabled(false);
        stopButton.addActionListener(e -> stopChecking());
        
        progressBar = new JProgressBar();
        progressBar.setStringPainted(true);
        
        statusLabel = new JLabel("Ready");
        statusLabel.setBorder(BorderFactory.createEmptyBorder(5, 5, 5, 5));
        
        saveResultsCheckbox = new JCheckBox("Save results to " + RESULTS_FILE, true);
    }
    
    private void layoutComponents() {
        JPanel buttonPanel = new JPanel(new FlowLayout(FlowLayout.CENTER));
        buttonPanel.add(startButton);
        buttonPanel.add(stopButton);
        buttonPanel.add(saveResultsCheckbox);
        
        JPanel statusPanel = new JPanel(new BorderLayout());
        statusPanel.add(statusLabel, BorderLayout.WEST);
        statusPanel.add(progressBar, BorderLayout.CENTER);
        
        JScrollPane scrollPane = new JScrollPane(logArea);
        
        setLayout(new BorderLayout());
        add(buttonPanel, BorderLayout.NORTH);
        add(scrollPane, BorderLayout.CENTER);
        add(statusPanel, BorderLayout.SOUTH);
    }
    
    private void startChecking() {
        if (running) return;
        
        running = true;
        startButton.setEnabled(false);
        stopButton.setEnabled(true);
        logArea.setText("");
        
        executorService = Executors.newSingleThreadExecutor();
        executorService.execute(() -> {
            try {
                runFileCheck();
            } catch (Exception e) {
                logMessage("Error: " + e.getMessage());
                e.printStackTrace();
            } finally {
                SwingUtilities.invokeLater(() -> {
                    running = false;
                    startButton.setEnabled(true);
                    stopButton.setEnabled(false);
                    statusLabel.setText("Completed");
                });
            }
        });
    }
    
    private void stopChecking() {
        if (!running) return;
        
        running = false;
        executorService.shutdownNow();
        statusLabel.setText("Stopped");
    }
    
    private void runFileCheck() throws Exception {
        logMessage("Starting file hash check...");
        
        // Get server URLs from files in servers directory
        List<String> servers = getServerUrlsFromDirectory();
        if (servers.isEmpty()) {
            logMessage("No server URLs found in " + SERVERS_DIR + " directory.");
            return;
        }
        
        logMessage("Found " + servers.size() + " servers to check:");
        servers.forEach(this::logMessage);
        
        // Get all files in data directory and subdirectories
        List<String> localFiles = getAllFilesInDataDirectory();
        if (localFiles.isEmpty()) {
            logMessage("No files found in " + DATA_DIR + " directory.");
            return;
        }
        
        logMessage("Found " + localFiles.size() + " files to check:");
        localFiles.forEach(this::logMessage);
        
        // Check each server for matching files with valid hashes
        Map<String, List<String>> serverMatches = new ConcurrentHashMap<>();
        
        int totalChecks = servers.size() * localFiles.size();
        final int[] checksCompleted = {0};
        
        SwingUtilities.invokeLater(() -> {
            progressBar.setMaximum(totalChecks);
            progressBar.setValue(0);
        });
        
        // Use thread pool for concurrent checking
        ExecutorService checkExecutor = Executors.newFixedThreadPool(10);
        List<Future<?>> futures = new ArrayList<>();
        
        for (String server : servers) {
            futures.add(checkExecutor.submit(() -> {
                if (!running) return;
                
                List<String> matchingFiles = new ArrayList<>();
                for (String file : localFiles) {
                    if (!running) break;
                    
                    try {
                        String fileUrl = server + (server.endsWith("/") ? "" : "/") + file;
                        String expectedHash = getHashFromFilename(file);
                        
                        if (expectedHash == null) {
                            logMessage("Skipping " + file + " - invalid hash format in filename");
                            continue;
                        }
                        
                        String actualHash = calculateRemoteFileHash(fileUrl);
                        
                        if (actualHash != null && actualHash.equalsIgnoreCase(expectedHash)) {
                            matchingFiles.add(file);
                            logMessage("Valid: " + file + " on " + server);
                        } else {
                            logMessage("Invalid hash for " + file + " on " + server + 
                                      " (Expected: " + expectedHash + ", Actual: " + actualHash + ")");
                        }
                    } catch (Exception e) {
                        logMessage("Error checking " + file + " on " + server + ": " + e.getMessage());
                    }
                    
                    // Update progress
                    SwingUtilities.invokeLater(() -> {
                        progressBar.setValue(++checksCompleted[0]);
                        statusLabel.setText("Checking... " + checksCompleted[0] + "/" + totalChecks);
                    });
                }
                
                if (!matchingFiles.isEmpty()) {
                    serverMatches.put(server, matchingFiles);
                }
            }));
        }
        
        // Wait for all checks to complete
        for (Future<?> future : futures) {
            try {
                future.get();
            } catch (InterruptedException | ExecutionException e) {
                if (running) {
                    logMessage("Error during checking: " + e.getMessage());
                }
            }
        }
        
        checkExecutor.shutdown();
        
        if (!running) {
            logMessage("Check stopped by user");
            return;
        }
        
        // Save results if requested
        if (saveResultsCheckbox.isSelected()) {
            saveResults(serverMatches, localFiles);
            logMessage("Results saved to " + RESULTS_FILE);
        }
        
        logMessage("File hash check completed");
    }
    
    private List<String> getServerUrlsFromDirectory() throws IOException {
        List<String> servers = new ArrayList<>();
        Path serversPath = Paths.get(SERVERS_DIR);
        
        if (!Files.exists(serversPath)) {
            throw new IOException(SERVERS_DIR + " directory does not exist");
        }
        
        Files.walk(serversPath, 1)
            .filter(Files::isRegularFile)
            .forEach(path -> {
                try {
                    List<String> lines = Files.readAllLines(path);
                    lines.stream()
                        .map(String::trim)
                        .filter(line -> !line.isEmpty() && !line.startsWith("#"))
                        .forEach(servers::add);
                } catch (IOException e) {
                    logMessage("Warning: Could not read file " + path + ": " + e.getMessage());
                }
            });
        
        return servers;
    }
    
    private List<String> getAllFilesInDataDirectory() throws IOException {
        List<String> files = new ArrayList<>();
        Path dataPath = Paths.get(DATA_DIR);
        
        if (!Files.exists(dataPath)) {
            throw new IOException(DATA_DIR + " directory does not exist");
        }
        
        Files.walk(dataPath)
            .filter(Files::isRegularFile)
            .forEach(path -> {
                String relativePath = dataPath.relativize(path).toString();
                files.add(relativePath);
            });
        
        return files;
    }
    
    private String getHashFromFilename(String filePath) {
        String filename = Paths.get(filePath).getFileName().toString();
        int dotIndex = filename.lastIndexOf('.');
        if (dotIndex > 0) {
            filename = filename.substring(0, dotIndex);
        }
        
        if (filename.matches("[a-fA-F0-9]{64}")) {
            return filename.toLowerCase();
        }
        return null;
    }
    
    private String calculateRemoteFileHash(String fileUrl) throws IOException {
        InputStream inputStream = null;
        try {
            URL url = new URL(fileUrl);
            HttpURLConnection connection = (HttpURLConnection) url.openConnection();
            connection.setRequestMethod("GET");
            connection.setConnectTimeout(TIMEOUT_MS);
            connection.setReadTimeout(TIMEOUT_MS * 5);
            
            int responseCode = connection.getResponseCode();
            if (responseCode != HttpURLConnection.HTTP_OK) {
                throw new IOException("HTTP error code: " + responseCode);
            }
            
            inputStream = connection.getInputStream();
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            byte[] buffer = new byte[BUFFER_SIZE];
            int bytesRead;
            
            while ((bytesRead = inputStream.read(buffer)) != -1) {
                if (!running) {
                    throw new IOException("Operation cancelled");
                }
                digest.update(buffer, 0, bytesRead);
            }
            
            byte[] hashBytes = digest.digest();
            return bytesToHex(hashBytes);
        } catch (Exception e) {
            throw new IOException("Failed to calculate hash: " + e.getMessage(), e);
        } finally {
            if (inputStream != null) {
                try {
                    inputStream.close();
                } catch (IOException e) {
                    logMessage("Warning: Error closing stream: " + e.getMessage());
                }
            }
        }
    }
    
    private String bytesToHex(byte[] bytes) {
        StringBuilder hexString = new StringBuilder();
        for (byte b : bytes) {
            String hex = Integer.toHexString(0xff & b);
            if (hex.length() == 1) {
                hexString.append('0');
            }
            hexString.append(hex);
        }
        return hexString.toString();
    }
    
    private void saveResults(Map<String, List<String>> serverMatches, List<String> allLocalFiles) throws IOException {
        try (PrintWriter writer = new PrintWriter(RESULTS_FILE)) {
            writer.println("File Hash Validation Results");
            writer.println("===========================");
            writer.println("Generated: " + new Date());
            writer.println();
            
            writer.println("Local files checked (" + allLocalFiles.size() + "):");
            for (String file : allLocalFiles) {
                writer.println("- " + file);
            }
            writer.println();
            
            writer.println("Servers with valid matching files (" + serverMatches.size() + "):");
            writer.println();
            
            for (Map.Entry<String, List<String>> entry : serverMatches.entrySet()) {
                String server = entry.getKey();
                List<String> validFiles = entry.getValue();
                
                if (validFiles.size() == allLocalFiles.size()) {
                    writer.println(server + " - ALL FILES VALID (hash matches filename)");
                } else {
                    writer.println(server + " - " + validFiles.size() + "/" + allLocalFiles.size() + 
                                 " files valid (hash matches filename)");
                }
            }
            
            writer.println();
            writer.println("Detailed Report:");
            for (String file : allLocalFiles) {
                writer.println();
                writer.println("File: " + file);
                writer.println("Valid on:");
                
                boolean found = false;
                for (Map.Entry<String, List<String>> entry : serverMatches.entrySet()) {
                    if (entry.getValue().contains(file)) {
                        writer.println("- " + entry.getKey());
                        found = true;
                    }
                }
                
                if (!found) {
                    writer.println("- No servers have this file with valid hash");
                }
            }
        }
    }
    
    private void logMessage(String message) {
        SwingUtilities.invokeLater(() -> {
            logArea.append(message + "\n");
            logArea.setCaretPosition(logArea.getDocument().getLength());
        });
    }
    
    public static void main(String[] args) {
        SwingUtilities.invokeLater(() -> {
            try {
                UIManager.setLookAndFeel(UIManager.getSystemLookAndFeelClassName());
            } catch (Exception e) {
                e.printStackTrace();
            }
            
            FileHashCheckerGUI gui = new FileHashCheckerGUI();
            gui.setVisible(true);
        });
    }
}