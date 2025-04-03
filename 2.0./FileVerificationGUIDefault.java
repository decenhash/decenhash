import javax.swing.*;
import javax.swing.border.EmptyBorder;
import javax.swing.table.DefaultTableModel;
import java.awt.*;
import java.awt.event.ActionEvent;
import java.awt.event.ActionListener;
import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Paths;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.util.*;
import java.util.List;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.stream.Collectors;

public class FileVerificationGUIDefault extends JFrame {
    // GUI Components
    private JTextField moneyField;
    private JButton startButton;
    private JButton updateMoneyButton;
    private JButton exitButton;
    private JTextArea logArea;
    private JTable statsTable;
    private JProgressBar progressBar;
    private JTabbedPane tabbedPane;
    private DefaultTableModel serverTableModel;
    private DefaultTableModel fileTableModel;
    private DefaultTableModel moneyTableModel;
    
    // Data structures
    private Map<String, Integer> serverVerifiedFilesCount = new HashMap<>();
    private Map<String, Set<String>> fileFoundOnServers = new HashMap<>();
    private Map<String, Boolean> fileVerificationStatus = new HashMap<>();
    private double totalMoney = 0;
    private int totalServers = 0;
    private List<String> filenames = new ArrayList<>();
    private List<String> serversList = new ArrayList<>();
    
    // Thread management
    private ExecutorService executorService;
    private boolean isProcessing = false;
    
    public FileVerificationGUIDefault() {
        setTitle("File Verification System");
        setSize(800, 600);
        setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        setLocationRelativeTo(null);
        
        initComponents();
        layoutComponents();
        
        // Initialize executor service
        executorService = Executors.newSingleThreadExecutor();
    }
    
    private void initComponents() {
        // Initialize components
        moneyField = new JTextField("0.00", 10);
        startButton = new JButton("Start Processing");
        updateMoneyButton = new JButton("Update Money");
        exitButton = new JButton("Exit");
        logArea = new JTextArea();
        logArea.setEditable(false);
        progressBar = new JProgressBar(0, 100);
        
        // Create table models
        serverTableModel = new DefaultTableModel(new String[]{"Rank", "Server", "Verified Files"}, 0);
        fileTableModel = new DefaultTableModel(new String[]{"Rank", "File Hash", "Filename", "Server Count"}, 0);
        moneyTableModel = new DefaultTableModel(new String[]{"File", "Server %", "Money Amount"}, 0);
        
        // Create tables
        statsTable = new JTable();
        tabbedPane = new JTabbedPane();
        
        JTable serverTable = new JTable(serverTableModel);
        JTable fileTable = new JTable(fileTableModel);
        JTable moneyTable = new JTable(moneyTableModel);
        
        // Add tables to tabbed pane
        tabbedPane.addTab("Servers", new JScrollPane(serverTable));
        tabbedPane.addTab("Files", new JScrollPane(fileTable));
        tabbedPane.addTab("Money Distribution", new JScrollPane(moneyTable));
        
        // Add action listeners
        startButton.addActionListener(new ActionListener() {
            @Override
            public void actionPerformed(ActionEvent e) {
                if (!isProcessing) {
                    startProcessing();
                } else {
                    JOptionPane.showMessageDialog(FileVerificationGUIDefault.this, 
                        "Processing is already in progress.", "Warning", JOptionPane.WARNING_MESSAGE);
                }
            }
        });
        
        updateMoneyButton.addActionListener(new ActionListener() {
            @Override
            public void actionPerformed(ActionEvent e) {
                try {
                    totalMoney = Double.parseDouble(moneyField.getText().trim());
                    if (totalMoney <= 0) {
                        log("Invalid money value. Setting to default 0.");
                        totalMoney = 0;
                        moneyField.setText("0.00");
                    } else {
                        log("Money value updated to: " + String.format("%.2f", totalMoney));
                    }
                } catch (NumberFormatException ex) {
                    log("Invalid money value format. Setting to default 0.");
                    totalMoney = 0;
                    moneyField.setText("0.00");
                }
            }
        });
        
        exitButton.addActionListener(new ActionListener() {
            @Override
            public void actionPerformed(ActionEvent e) {
                if (isProcessing) {
                    int result = JOptionPane.showConfirmDialog(FileVerificationGUIDefault.this,
                        "Processing is in progress. Are you sure you want to exit?",
                        "Confirm Exit", JOptionPane.YES_NO_OPTION);
                    
                    if (result == JOptionPane.YES_OPTION) {
                        executorService.shutdownNow();
                        System.exit(0);
                    }
                } else {
                    System.exit(0);
                }
            }
        });
    }
    
    private void layoutComponents() {
        // Top panel for input controls
        JPanel topPanel = new JPanel(new FlowLayout(FlowLayout.LEFT));
        topPanel.setBorder(new EmptyBorder(10, 10, 10, 10));
        
        topPanel.add(new JLabel("Money Value:"));
        topPanel.add(moneyField);
        topPanel.add(updateMoneyButton);
        topPanel.add(startButton);
        topPanel.add(exitButton);
        
        // Center panel for log area
        JPanel centerPanel = new JPanel(new BorderLayout());
        centerPanel.setBorder(new EmptyBorder(0, 10, 10, 10));
        
        // Add log area with scroll pane
        centerPanel.add(new JScrollPane(logArea), BorderLayout.CENTER);
        
        // Progress bar at bottom of log area
        centerPanel.add(progressBar, BorderLayout.SOUTH);
        
        // Statistics tables
        JPanel bottomPanel = new JPanel(new BorderLayout());
        bottomPanel.setBorder(new EmptyBorder(0, 10, 10, 10));
        bottomPanel.add(tabbedPane, BorderLayout.CENTER);
        
        // Main layout
        setLayout(new BorderLayout());
        add(topPanel, BorderLayout.NORTH);
        add(centerPanel, BorderLayout.CENTER);
        add(bottomPanel, BorderLayout.SOUTH);
        
        // Set split pane between log and tables
        JSplitPane splitPane = new JSplitPane(JSplitPane.VERTICAL_SPLIT, centerPanel, bottomPanel);
        splitPane.setResizeWeight(0.5);
        add(splitPane, BorderLayout.CENTER);
    }
    
    private void startProcessing() {
        // Clear logs and statistics
        logArea.setText("");
        serverTableModel.setRowCount(0);
        fileTableModel.setRowCount(0);
        moneyTableModel.setRowCount(0);
        
        // Clear data structures
        serverVerifiedFilesCount.clear();
        fileFoundOnServers.clear();
        fileVerificationStatus.clear();
        
        // Start processing in background thread
        executorService.submit(() -> {
            try {
                isProcessing = true;
                
                // Load server files
                loadServerFiles();
                
                // Ensure data directory exists
                File dataDir = new File("data");
                if (!dataDir.exists()) {
                    dataDir.mkdir();
                    log("Created 'data' directory.");
                }
                
                // Load filenames
                loadFilenames();
                
                // Process files
                processFiles();
                
                // Display statistics
                displayStatistics();
                
                isProcessing = false;
                
            } catch (Exception e) {
                log("Error: " + e.getMessage());
                e.printStackTrace();
                isProcessing = false;
            }
        });
    }
    
    private void loadServerFiles() {
        try {
            serversList = listFilesAndGetContents("servers");
            log("Found " + serversList.size() + " server files in directory.");
            
            // Count total unique servers
            Set<String> uniqueServers = new HashSet<>();
            for (String serverContent : serversList) {
                for (String serverAddress : serverContent.split("\n")) {
                    serverAddress = serverAddress.trim();
                    if (!serverAddress.isEmpty()) {
                        uniqueServers.add(serverAddress);
                    }
                }
            }
            totalServers = uniqueServers.size();
            log("Total unique servers: " + totalServers);
            
        } catch (IOException e) {
            log("Error loading server files: " + e.getMessage());
        }
    }
    
    private void loadFilenames() {
        try {
            filenames = Files.readAllLines(Paths.get("files.txt"));
            log("Found " + filenames.size() + " filenames to process in files.txt.");
        } catch (IOException e) {
            log("Error loading filenames: " + e.getMessage());
            filenames = new ArrayList<>();
        }
    }
    
    private void processFiles() {
        progressBar.setMaximum(filenames.size());
        progressBar.setValue(0);
        
        for (int i = 0; i < filenames.size(); i++) {

            final int currentIndex = i;

            String filename = filenames.get(i).trim();
            if (filename.isEmpty()) continue;
            
            log("\nProcessing file " + (i+1) + "/" + filenames.size() + ": " + filename);
            
            // Split the input by '.'
            String[] inputParts = filename.split("\\.");
            if (inputParts.length < 2) {
                log("Invalid filename format. Skipping: " + filename);
                continue;
            }
            
            String fileHash = inputParts[0];
            boolean fileFound = false;
            
            // Initialize entry in our tracking maps
            fileFoundOnServers.putIfAbsent(filename, new HashSet<>());
            
            // Process each server in the list
            for (String serverContent : serversList) {
                // Each line in the file content is treated as a server address
                for (String serverAddress : serverContent.split("\n")) {
                    serverAddress = serverAddress.trim();
                    if (serverAddress.isEmpty()) continue;
                    
                    // Create the URL structure
                    String urlString = serverAddress + "/data/" + fileHash + "/" + filename;
                    log("Trying to connect to: " + urlString);
                    
                    // Try to connect to the URL
                    if (checkUrlExists(urlString)) {
                        fileFound = true;
                        log("? Connection successful!");
                        
                        // Add server to the list of servers containing this file
                        fileFoundOnServers.get(filename).add(serverAddress);
                        
                        try {
                            // Download the file content
                            byte[] fileBytes = downloadFile(urlString);
                            
                            // Calculate its SHA-256 hash
                            String calculatedHash = calculateSHA256(fileBytes);
                            
                            // Check if the hash equals the first part of the filename
                            boolean verificationSuccessful = calculatedHash.equals(fileHash);
                            fileVerificationStatus.put(filename, verificationSuccessful);
                            
                            if (verificationSuccessful) {
                                log("? Hash verification successful!");
                                log("  SHA-256: " + calculatedHash);
                                
                                // Increment the verified files count for this server
                                serverVerifiedFilesCount.put(serverAddress, 
                                        serverVerifiedFilesCount.getOrDefault(serverAddress, 0) + 1);
                                
                                // Create a subfolder with the hash in the data directory
                                File subDir = new File("data/" + fileHash);
                                if (!subDir.exists()) {
                                    subDir.mkdir();
                                    log("  Created directory: data/" + fileHash);
                                }
                                
                                // Save the file if it doesn't already exist
                                File downloadedFile = new File(subDir, filename);
                                if (!downloadedFile.exists()) {
                                    try (FileOutputStream fos = new FileOutputStream(downloadedFile)) {
                                        fos.write(fileBytes);
                                        log("  Saved file: " + downloadedFile.getPath());
                                    }
                                } else {
                                    log("  File already exists: " + downloadedFile.getPath());
                                }
                                
                                // Calculate SHA-256 hash of the server address
                                String serverAddressHash = calculateSHA256(
                                        serverAddress.getBytes(StandardCharsets.UTF_8));
                                
                                // Save the server address to a file named with its hash
                                File serverFile = new File(subDir, serverAddressHash);
                                if (!serverFile.exists()) {
                                    Files.write(serverFile.toPath(), 
                                            serverAddress.getBytes(StandardCharsets.UTF_8));
                                    log("  Saved server address to: " + serverFile.getPath());
                                } else {
                                    log("  Server address file already exists: " + 
                                            serverFile.getPath());
                                }
                            } else {
                                log("? Hash verification failed!");
                                log("  Expected: " + fileHash);
                                log("  Actual: " + calculatedHash);
                            }
                            
                        } catch (IOException | NoSuchAlgorithmException e) {
                            log("Error processing file: " + e.getMessage());
                        }
                        
                        // Break to avoid downloading the same file from multiple servers
                        break;
                    } else {
                        log("? Connection failed!");
                    }
                }
            }
            
            if (!fileFound) {
                log("File not found on any server: " + filename);
            }
            
            SwingUtilities.invokeLater(() -> {

                progressBar.setValue(currentIndex + 1);

            });
        }
    }
    
    private void displayStatistics() {
        log("\n==== SERVER STATISTICS ====");
        
        // Clear previous data
        serverTableModel.setRowCount(0);
        fileTableModel.setRowCount(0);
        moneyTableModel.setRowCount(0);
        
        // Sort servers by number of verified files (descending)
        List<Map.Entry<String, Integer>> sortedServers = serverVerifiedFilesCount.entrySet()
                .stream()
                .sorted(Map.Entry.<String, Integer>comparingByValue().reversed())
                .collect(Collectors.toList());
        
        // Display servers hosting most verified files
        if (sortedServers.isEmpty()) {
            log("No verified files found on any server.");
        } else {
            log("Servers hosting verified files (in descending order):");
            for (int i = 0; i < sortedServers.size(); i++) {
                Map.Entry<String, Integer> entry = sortedServers.get(i);
                log((i + 1) + ". " + entry.getKey() + " - " + entry.getValue() + " verified files");
                
                // Add to table model
                serverTableModel.addRow(new Object[]{
                    i + 1, entry.getKey(), entry.getValue()
                });
            }
        }
        
        log("\n==== FILE STATISTICS ====");
// Calculate file frequency and filter for verified files
        Map<String, Integer> fileFrequency = new HashMap<>();
        Map<String, String> fileNameMap = new HashMap<>(); // To store full filename by hash
        
        for (Map.Entry<String, Set<String>> entry : fileFoundOnServers.entrySet()) {
            String filename = entry.getKey();
            // Only count verified files
            if (fileVerificationStatus.getOrDefault(filename, false)) {
                String fileHash = filename.split("\\.")[0]; // Get hash part
                fileFrequency.put(fileHash, entry.getValue().size());
                fileNameMap.put(fileHash, filename);
            }
        }
        
        // Sort files by frequency (descending)
        List<Map.Entry<String, Integer>> sortedFiles = fileFrequency.entrySet()
                .stream()
                .sorted(Map.Entry.<String, Integer>comparingByValue().reversed())
                .collect(Collectors.toList());
        
        // Display files most found across servers
        if (sortedFiles.isEmpty()) {
            log("No verified files found.");
        } else {
            log("Verified file hashes by frequency (in descending order):");
            for (int i = 0; i < sortedFiles.size(); i++) {
                Map.Entry<String, Integer> entry = sortedFiles.get(i);
                String fileHash = entry.getKey();
                String fullFilename = fileNameMap.get(fileHash);
                
                log((i + 1) + ". File hash: " + fileHash + " - Found on " + entry.getValue() + " servers");
                
                // Add to table model
                fileTableModel.addRow(new Object[]{
                    i + 1, fileHash, fullFilename, entry.getValue()
                });
            }
        }
        
        // Calculate and display money distribution
        if (totalMoney > 0 && !sortedFiles.isEmpty() && totalServers > 0) {
            log("\n==== MONEY DISTRIBUTION ====");
            log("Total money to distribute: " + String.format("%.2f", totalMoney));
            log("Total unique servers: " + totalServers);
            
            double remainingMoney = totalMoney;
            double distributedMoney = 0;
            
            log("\nDistribution by percentage of servers hosting each file:");
            for (Map.Entry<String, Integer> entry : sortedFiles) {
                String fileHash = entry.getKey();
                int serverCount = entry.getValue();
                String fullFilename = fileNameMap.get(fileHash);
                
                // Calculate distribution percentage based on server count
                double percentage = (double) serverCount / totalServers * 100;
                double moneyAmount = totalMoney * serverCount / totalServers;
                distributedMoney += moneyAmount;
                remainingMoney -= moneyAmount;
                
                log("File: " + fullFilename + " - " + String.format("%.2f", percentage) + 
                    "% of servers (" + String.format("%.2f", moneyAmount) + " of total money)");
                
                // Add to money table model
                moneyTableModel.addRow(new Object[]{
                    fullFilename, 
                    String.format("%.2f%%", percentage),
                    String.format("%.2f", moneyAmount)
                });
            }
            
            // Handle rounding errors
            if (Math.abs(remainingMoney) < 0.01) {
                remainingMoney = 0;
            }
            
            log("\nTotal distributed: " + String.format("%.2f", distributedMoney));
            if (remainingMoney > 0) {
                log("Remaining (undistributed): " + String.format("%.2f", remainingMoney));
            }
            
            // Add summary row to money table
            moneyTableModel.addRow(new Object[]{
                "Total Distributed", "", String.format("%.2f", distributedMoney)
            });
            
            if (remainingMoney > 0) {
                moneyTableModel.addRow(new Object[]{
                    "Remaining (undistributed)", "", String.format("%.2f", remainingMoney)
                });
            }
        }
    }
    
    /**
     * Lists all files in the given directory and returns their contents as a list of strings
     */
    private List<String> listFilesAndGetContents(String directoryPath) throws IOException {
        List<String> contents = new ArrayList<>();
        File directory = new File(directoryPath);
        
        if (!directory.exists() || !directory.isDirectory()) {
            log("Directory '" + directoryPath + "' does not exist or is not a directory");
            return contents;
        }
        
        File[] files = directory.listFiles();
        if (files != null) {
            for (File file : files) {
                if (file.isFile()) {
                    String content = new String(Files.readAllBytes(Paths.get(file.getPath())));
                    contents.add(content);
                    log("Loaded server file: " + file.getName());
                }
            }
        }
        
        return contents;
    }
    
    /**
     * Checks if a URL exists by establishing a connection
     */
    private boolean checkUrlExists(String urlString) {
        try {
            URL url = new URL(urlString);
            HttpURLConnection connection = (HttpURLConnection) url.openConnection();
            connection.setRequestMethod("HEAD");
            connection.setConnectTimeout(5000);
            connection.setReadTimeout(5000);
            int responseCode = connection.getResponseCode();
            return (responseCode == HttpURLConnection.HTTP_OK);
        } catch (IOException e) {
            return false;
        }
    }
    
    /**
     * Downloads a file from a URL and returns it as a byte array
     */
    private byte[] downloadFile(String urlString) throws IOException {
        URL url = new URL(urlString);
        return url.openStream().readAllBytes();
    }
    
    /**
     * Calculates the SHA-256 hash of a byte array
     */
    private String calculateSHA256(byte[] bytes) throws NoSuchAlgorithmException {
        MessageDigest digest = MessageDigest.getInstance("SHA-256");
        byte[] hashBytes = digest.digest(bytes);
        
        // Convert bytes to hex string
        StringBuilder hexString = new StringBuilder();
        for (byte b : hashBytes) {
            String hex = Integer.toHexString(0xff & b);
            if (hex.length() == 1) hexString.append('0');
            hexString.append(hex);
        }
        
        return hexString.toString();
    }
    
    /**
     * Logs a message to the log area
     */
    private void log(String message) {
        SwingUtilities.invokeLater(() -> {
            logArea.append(message + "\n");
            // Auto-scroll to bottom
            logArea.setCaretPosition(logArea.getDocument().getLength());
        });
    }
    
    public static void main(String[] args) {
        // Set Look and Feel to system default
        try {
            UIManager.setLookAndFeel(UIManager.getSystemLookAndFeelClassName());
        } catch (Exception e) {
            e.printStackTrace();
        }
        
        // Start the application on the Event Dispatch Thread
        SwingUtilities.invokeLater(() -> {
            FileVerificationGUIDefault gui = new FileVerificationGUIDefault();
            gui.setVisible(true);
        });
    }
}