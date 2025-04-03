import javax.swing.*;
import java.awt.*;
import java.awt.event.*;
import java.io.*;
import java.net.*;
import java.nio.charset.StandardCharsets;
import java.nio.file.*;
import java.security.*;
import java.util.*;
import java.util.List;

public class DecenHash extends JFrame {
    private static final Color BACKGROUND_COLOR = new Color(25, 118, 210); // Blue background
    private static final Color TEXT_COLOR = Color.WHITE;
    private static final String TELEGRAM_URL = "https://t.me/decenhash";
    private static final String WHATSAPP_URL = "https://chat.whatsapp.com/GbT2LBn2AGG1mw3vTreIWr";
    
    private JTextArea logArea;
    private JButton startButton;
    private JButton helpButton;
    private JButton telegramButton;
    private JButton whatsappButton;
    private JLabel statusLabel;
    private boolean isRunning = false;
    
    public DecenHash() {
        // Set up the frame
        setTitle("DecenHash");
        setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        setSize(600, 300);
        setLocationRelativeTo(null);
        
        // Create main panel with blue background
        JPanel mainPanel = new JPanel(new BorderLayout());
        mainPanel.setBackground(BACKGROUND_COLOR);
        
        // Add title at the top
        JLabel titleLabel = new JLabel("DecenHash", JLabel.CENTER);
        titleLabel.setFont(new Font("Arial", Font.BOLD, 48));
        titleLabel.setForeground(TEXT_COLOR);
        titleLabel.setBorder(BorderFactory.createEmptyBorder(30, 0, 30, 0));
        mainPanel.add(titleLabel, BorderLayout.NORTH);
        
        // Create button panel
        JPanel buttonPanel = new JPanel();
        buttonPanel.setBackground(Color.decode("#FFFFFF"));
        
        // Create and configure buttons
        startButton = createButton("Start Processing");
        helpButton = createButton("How It Works");
        telegramButton = createButton("Telegram");
        whatsappButton = createButton("WhatsApp");
        
        // Add buttons to panel
        buttonPanel.add(startButton);
        buttonPanel.add(helpButton);
        buttonPanel.add(telegramButton);
        buttonPanel.add(whatsappButton);
        
        // Add button panel to main panel
        mainPanel.add(buttonPanel, BorderLayout.CENTER);
        
        // Add log area
        logArea = new JTextArea();
        logArea.setEditable(false);
        logArea.setFont(new Font("Monospaced", Font.PLAIN, 12));
        logArea.setBackground(new Color(12, 60, 120)); // Darker blue
        logArea.setForeground(TEXT_COLOR);
        JScrollPane scrollPane = new JScrollPane(logArea);
        scrollPane.setPreferredSize(new Dimension(780, 300));
        scrollPane.setBorder(BorderFactory.createEmptyBorder(10, 10, 10, 10));
        mainPanel.add(scrollPane, BorderLayout.SOUTH);
        
        // Add status label at the bottom
        JPanel footerPanel = new JPanel(new BorderLayout());
        footerPanel.setBackground(BACKGROUND_COLOR);
        
        statusLabel = new JLabel("Ready", JLabel.LEFT);
        statusLabel.setForeground(TEXT_COLOR);
        footerPanel.add(statusLabel, BorderLayout.WEST);
        
        JLabel rightsLabel = new JLabel("All Rights Reserved", JLabel.RIGHT);
        rightsLabel.setForeground(TEXT_COLOR);
        footerPanel.add(rightsLabel, BorderLayout.EAST);
        
        footerPanel.setBorder(BorderFactory.createEmptyBorder(5, 10, 5, 10));
        mainPanel.add(footerPanel, BorderLayout.PAGE_END);
        
        // Add action listeners
        startButton.addActionListener(e -> startProcessing());
        helpButton.addActionListener(e -> showHelpDialog());
        telegramButton.addActionListener(e -> openWebpage(TELEGRAM_URL));
        whatsappButton.addActionListener(e -> openWebpage(WHATSAPP_URL));
        
        // Add main panel to frame
        setContentPane(mainPanel);
        setVisible(true);
        
        // Initialize directories
        initializeDirectories();
    }
    
    private JButton createButton(String text) {
        JButton button = new JButton(text);
        button.setBackground(new Color(41, 182, 246)); // Lighter blue
        button.setForeground(Color.decode("#444444"));
        button.setFocusPainted(false);
        button.setFont(new Font("Arial", Font.BOLD, 14));
        button.setBorder(BorderFactory.createEmptyBorder(10, 15, 10, 15));
        return button;
    }
    
    private void initializeDirectories() {
        try {
            // Ensure servers directory exists
            File serversDir = new File("servers");
            if (!serversDir.exists()) {
                serversDir.mkdir();
                log("Created 'servers' directory");
            }
            
            // Ensure data directory exists
            File dataDir = new File("data");
            if (!dataDir.exists()) {
                dataDir.mkdir();
                log("Created 'data' directory");
            }
            
            // Create files.txt if it doesn't exist
            File filesListFile = new File("files.txt");
            if (!filesListFile.exists()) {
                filesListFile.createNewFile();
                log("Created empty 'files.txt'");
            }
            
            // Check files in servers directory
            File[] serverFiles = serversDir.listFiles();
            if (serverFiles != null) {
                log("Found " + serverFiles.length + " server files");
            } else {
                log("No server files found. Please add server addresses in 'servers' directory");
            }
            
            // Check files.txt
            List<String> filenames = Files.readAllLines(Paths.get("files.txt"));
            log("Found " + filenames.size() + " filenames in files.txt");
            
        } catch (IOException e) {
            log("Error initializing directories: " + e.getMessage());
        }
    }
    
    private void log(String message) {
        SwingUtilities.invokeLater(() -> {
            logArea.append(message + "\n");
            logArea.setCaretPosition(logArea.getDocument().getLength());
        });
    }
    
    private void updateStatus(String status) {
        SwingUtilities.invokeLater(() -> statusLabel.setText(status));
    }
    
    private void startProcessing() {
        if (isRunning) {
            log("Already processing files...");
            return;
        }
        
        new Thread(() -> {
            isRunning = true;
            startButton.setEnabled(false);
            updateStatus("Processing...");
            
            try {
                processFiles();
            } catch (Exception e) {
                log("Error: " + e.getMessage());
                e.printStackTrace();
            } finally {
                isRunning = false;
                SwingUtilities.invokeLater(() -> {
                    startButton.setEnabled(true);
                    updateStatus("Ready");
                });
            }
        }).start();
    }
    
    private void processFiles() {
        try {
            // Get the list of files from the servers directory
            List<String> serversList = listFilesAndGetContents("servers");
            log("Processing " + serversList.size() + " server files");
            
            // Read the filenames from files.txt
            List<String> filenames = Files.readAllLines(Paths.get("files.txt"));
            log("Processing " + filenames.size() + " filenames from files.txt");
            
            // Process each filename from the files.txt
            for (int i = 0; i < filenames.size(); i++) {
                String filename = filenames.get(i).trim();
                if (filename.isEmpty()) continue;
                
                log("\nProcessing file " + (i+1) + "/" + filenames.size() + ": " + filename);
                updateStatus("Processing file " + (i+1) + "/" + filenames.size());
                
                // Split the input by '.'
                String[] inputParts = filename.split("\\.");
                if (inputParts.length < 2) {
                    log("Invalid filename format. Skipping: " + filename);
                    continue;
                }
                
                String firstPart = inputParts[0];
                boolean fileFound = false;
                
                // Process each server in the list
                for (String serverContent : serversList) {
                    // Each line in the file content is treated as a server address
                    for (String serverAddress : serverContent.split("\n")) {
                        serverAddress = serverAddress.trim();
                        if (serverAddress.isEmpty()) continue;
                        
                        // Create the URL structure
                        String urlString = serverAddress + "/data/" + firstPart + "/" + filename;
                        log("Trying to connect to: " + urlString);
                        
                        // Try to connect to the URL
                        if (checkUrlExists(urlString)) {
                            fileFound = true;
                            log("? Connection successful!");
                            
                            // Download the file content
                            byte[] fileBytes = downloadFile(urlString);
                            
                            // Calculate its SHA-256 hash
                            String fileHash = calculateSHA256(fileBytes);
                            
                            // Check if the hash equals the first part of the filename
                            if (fileHash.equals(firstPart)) {
                                log("? Hash verification successful!");
                                log("  SHA-256: " + fileHash);
                                
                                // Create a subfolder with the hash (firstPart) in the data directory
                                File subDir = new File("data/" + firstPart);
                                if (!subDir.exists()) {
                                    subDir.mkdir();
                                    log("  Created directory: data/" + firstPart);
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
                                String serverAddressHash = calculateSHA256(serverAddress.getBytes(StandardCharsets.UTF_8));
                                
                                // Save the server address to a file named with its hash
                                File serverFile = new File(subDir, serverAddressHash);
                                if (!serverFile.exists()) {
                                    Files.write(serverFile.toPath(), serverAddress.getBytes(StandardCharsets.UTF_8));
                                    log("  Saved server address to: " + serverFile.getPath());
                                } else {
                                    log("  Server address file already exists: " + serverFile.getPath());
                                }
                            } else {
                                log("? Hash verification failed!");
                                log("  Expected: " + firstPart);
                                log("  Actual: " + fileHash);
                            }
                        } else {
                            log("? Connection failed!");
                        }
                    }
                }
                
                if (!fileFound) {
                    log("File not found on any server: " + filename);
                }
            }
            
            log("\nAll files processed.");
            
        } catch (IOException | NoSuchAlgorithmException e) {
            log("Error: " + e.getMessage());
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
     * Opens a webpage in the default browser
     */
    private void openWebpage(String urlString) {
        try {
            Desktop.getDesktop().browse(new URI(urlString));
        } catch (IOException | URISyntaxException e) {
            log("Error opening webpage: " + e.getMessage());
        }
    }
    
    /**
     * Shows the help dialog explaining how the program works
     */
    private void showHelpDialog() {
        String helpText = 
            "DecenHash - Decentralized File Verification System\n\n" +
            "How It Works:\n\n" +
            "1. The program reads server addresses from files in the 'servers' directory.\n" +
            "2. It processes filenames listed in 'files.txt'.\n" +
            "3. For each filename, it tries to connect to each server using the URL format:\n" +
            "   serverAddress/data/firstPartOfFilename/fullFilename\n" +
            "4. When a file is found, it verifies if the SHA-256 hash of the file\n" +
            "   matches the first part of the filename (before the dot).\n" +
            "5. If verification is successful, it saves:\n" +
            "   - The file itself in a subdirectory of 'data' named after the hash\n" +
            "   - The server address in a file named after the SHA-256 hash of the address\n\n" +
            "Setup Instructions:\n\n" +
            "1. Create text files in the 'servers' directory with server addresses (one per line)\n" +
            "2. Add filenames to check in 'files.txt' (one per line)\n" +
            "3. Click 'Start Processing' to begin the verification process\n\n" +
            "Note: Files that already exist will not be overwritten.";
        
        JTextArea textArea = new JTextArea(helpText);
        textArea.setEditable(false);
        textArea.setLineWrap(true);
        textArea.setWrapStyleWord(true);
        textArea.setBackground(new Color(12, 60, 120));
        textArea.setForeground(Color.WHITE);
        textArea.setFont(new Font("Dialog", Font.PLAIN, 14));
        textArea.setBorder(BorderFactory.createEmptyBorder(10, 10, 10, 10));
        
        JScrollPane scrollPane = new JScrollPane(textArea);
        scrollPane.setPreferredSize(new Dimension(600, 400));
        
        JOptionPane.showMessageDialog(
            this, 
            scrollPane, 
            "How DecenHash Works", 
            JOptionPane.INFORMATION_MESSAGE
        );
    }
    
    public static void main(String[] args) {
        try {
            // Set look and feel to system default
            UIManager.setLookAndFeel(UIManager.getSystemLookAndFeelClassName());
        } catch (Exception e) {
            e.printStackTrace();
        }
        
        // Create GUI on the Event Dispatch Thread
        SwingUtilities.invokeLater(() -> new DecenHash());
    }
}