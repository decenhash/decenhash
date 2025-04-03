import javax.swing.*;
import javax.swing.border.*;
import javax.swing.table.*;
import java.awt.*;
import java.awt.event.*;
import java.io.*;
import java.net.*;
import java.nio.file.*;
import java.security.*;
import java.text.SimpleDateFormat;
import java.util.*;
import java.util.List;
import java.util.concurrent.*;

public class FileHashCheckerGUI extends JFrame {
    // Constants
    private static final String DATA_DIR = "data";
    private static final String SERVERS_DIR = "servers";
    private static final String RESULTS_FILE = "results.csv";
    private static final int TIMEOUT_MS = 5000;
    private static final int BUFFER_SIZE = 8192;
    
    // UI Colors
    private static final Color BLUE_BACKGROUND = new Color(230, 240, 255);
    private static final Color BLUE_HEADER = new Color(70, 130, 180);
    private static final Color BLUE_BUTTON = new Color(100, 150, 220);
    
    // UI Components
    private JTable resultsTable;
    private DefaultTableModel tableModel;
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
        setSize(1000, 700);
        setLocationRelativeTo(null);
        
        initComponents();
        layoutComponents();
        applyStyles();
    }
    
    private void initComponents() {
        // Table setup
        String[] columnNames = {"Timestamp", "Server", "File", "Status", "Expected Hash", "Actual Hash"};
        tableModel = new DefaultTableModel(columnNames, 0) {
            @Override
            public boolean isCellEditable(int row, int column) {
                return false;
            }
        };
        resultsTable = new JTable(tableModel);
        resultsTable.setAutoCreateRowSorter(true);
        resultsTable.setRowHeight(25);
        
        // Log area
        logArea = new JTextArea();
        logArea.setEditable(false);
        logArea.setFont(new Font("Monospaced", Font.PLAIN, 12));
        
        // Buttons
        startButton = new JButton("Start Verification");
        startButton.addActionListener(e -> startChecking());
        
        stopButton = new JButton("Stop");
        stopButton.setEnabled(false);
        stopButton.addActionListener(e -> stopChecking());
        
        // Progress
        progressBar = new JProgressBar();
        progressBar.setStringPainted(true);
        
        statusLabel = new JLabel("Ready");
        statusLabel.setBorder(BorderFactory.createEmptyBorder(5, 5, 5, 5));
        
        saveResultsCheckbox = new JCheckBox("Save results to CSV", true);
    }
    
    private void layoutComponents() {
        // Main panel with blue background
        JPanel mainPanel = new JPanel(new BorderLayout(10, 10));
        mainPanel.setBorder(new EmptyBorder(10, 10, 10, 10));
        mainPanel.setBackground(BLUE_BACKGROUND);
        
        // Button panel
        JPanel buttonPanel = new JPanel(new FlowLayout(FlowLayout.CENTER, 10, 10));
        buttonPanel.setOpaque(false);
        buttonPanel.add(startButton);
        buttonPanel.add(stopButton);
        buttonPanel.add(saveResultsCheckbox);
        
        // Table panel
        JScrollPane tableScroll = new JScrollPane(resultsTable);
        tableScroll.setBorder(new TitledBorder("Verification Results"));
        
        // Log panel
        JScrollPane logScroll = new JScrollPane(logArea);
        logScroll.setBorder(new TitledBorder("Activity Log"));
        logScroll.setPreferredSize(new Dimension(0, 150));
        
        // Status panel
        JPanel statusPanel = new JPanel(new BorderLayout(10, 10));
        statusPanel.setOpaque(false);
        statusPanel.add(statusLabel, BorderLayout.WEST);
        statusPanel.add(progressBar, BorderLayout.CENTER);
        
        // Main layout
        mainPanel.add(buttonPanel, BorderLayout.NORTH);
        mainPanel.add(tableScroll, BorderLayout.CENTER);
        mainPanel.add(logScroll, BorderLayout.SOUTH);
        mainPanel.add(statusPanel, BorderLayout.PAGE_END);
        
        setContentPane(mainPanel);
    }
    
    private void applyStyles() {
        // Table styling
        resultsTable.setBackground(Color.WHITE);
        resultsTable.setGridColor(new Color(200, 200, 200));
        resultsTable.setSelectionBackground(new Color(220, 230, 255));
        
        JTableHeader header = resultsTable.getTableHeader();
        header.setBackground(BLUE_HEADER);
        header.setForeground(Color.WHITE);
        header.setFont(header.getFont().deriveFont(Font.BOLD));
        
        // Button styling
        styleButton(startButton);
        styleButton(stopButton);
        
        // Progress bar
        progressBar.setBackground(Color.WHITE);
        progressBar.setForeground(BLUE_HEADER);
        
        // Status label
        statusLabel.setFont(statusLabel.getFont().deriveFont(Font.BOLD));
    }
    
    private void styleButton(JButton button) {
        button.setBackground(BLUE_BUTTON);
        button.setForeground(Color.WHITE);
        button.setFocusPainted(false);
        button.setBorder(new CompoundBorder(
            new LineBorder(BLUE_HEADER.darker(), 1),
            new EmptyBorder(5, 15, 5, 15)
        ));
        button.setCursor(Cursor.getPredefinedCursor(Cursor.HAND_CURSOR));
    }
    
    private void startChecking() {
        if (running) return;
        
        running = true;
        startButton.setEnabled(false);
        stopButton.setEnabled(true);
        tableModel.setRowCount(0);
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
    
    private void logMessage(String message) {
        SwingUtilities.invokeLater(() -> {
            logArea.append(message + "\n");
            logArea.setCaretPosition(logArea.getDocument().getLength());
        });
    }
    
    private void addTableRow(String timestamp, String server, String file, String status, String expectedHash, String actualHash) {
        SwingUtilities.invokeLater(() -> {
            tableModel.addRow(new Object[]{timestamp, server, file, status, expectedHash, actualHash});
            
            // Scroll to the new row
            int row = tableModel.getRowCount() - 1;
            resultsTable.scrollRectToVisible(resultsTable.getCellRect(row, 0, true));
        });
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
    
    private void runFileCheck() throws Exception {
        logMessage("Starting file hash verification...");
        
        // Get server URLs from files in servers directory
        List<String> servers = getServerUrlsFromDirectory();
        if (servers.isEmpty()) {
            logMessage("No server URLs found in " + SERVERS_DIR + " directory.");
            return;
        }
        
        logMessage("Found " + servers.size() + " servers to check:");
        servers.forEach(s -> logMessage("- " + s));
        
        // Get all files in data directory and subdirectories
        List<String> localFiles = getAllFilesInDataDirectory();
        if (localFiles.isEmpty()) {
            logMessage("No files found in " + DATA_DIR + " directory.");
            return;
        }
        
        logMessage("Found " + localFiles.size() + " files to verify:");
        localFiles.forEach(f -> logMessage("- " + f));
        
        // Prepare CSV data structure
        List<Map<String, String>> csvData = new ArrayList<>();
        Map<String, Boolean> serverStatus = new LinkedHashMap<>();
        
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
                
                for (String file : localFiles) {
                    if (!running) break;
                    
                    String timestamp = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss").format(new Date());
                    try {
                        String fileUrl = server + (server.endsWith("/") ? "" : "/") + file;
                        String expectedHash = getHashFromFilename(file);
                        
                        if (expectedHash == null) {
                            logMessage("Skipping " + file + " - invalid hash format in filename");
                            continue;
                        }
                        
                        String actualHash = calculateRemoteFileHash(fileUrl);
                        boolean hashValid = actualHash != null && actualHash.equalsIgnoreCase(expectedHash);
                        String status = hashValid ? "VALID" : "INVALID";
                        
                        // Add to table
                        addTableRow(timestamp, server, file, status, expectedHash, actualHash != null ? actualHash : "N/A");
                        
                        // Record result for CSV
                        synchronized (csvData) {
                            Map<String, String> record = new LinkedHashMap<>();
                            record.put("Timestamp", timestamp);
                            record.put("Server", server);
                            record.put("File", file);
                            record.put("ExpectedHash", expectedHash);
                            record.put("ActualHash", actualHash != null ? actualHash : "N/A");
                            record.put("Status", status);
                            record.put("ResponseTime", "N/A");
                            csvData.add(record);
                            
                            serverStatus.merge(server, hashValid, (oldVal, newVal) -> oldVal || newVal);
                        }
                        
                        if (hashValid) {
                            logMessage("? Valid: " + file + " on " + server);
                        } else {
                            logMessage("? Invalid: " + file + " on " + server + 
                                      " (Expected: " + expectedHash + ", Actual: " + (actualHash != null ? actualHash : "N/A") + ")");
                        }
                    } catch (Exception e) {
                        logMessage("? Error checking " + file + " on " + server + ": " + e.getMessage());
                        addTableRow(timestamp, server, file, "ERROR", "N/A", "N/A");
                    }
                    
                    // Update progress
                    SwingUtilities.invokeLater(() -> {
                        progressBar.setValue(++checksCompleted[0]);
                        statusLabel.setText("Verifying... " + checksCompleted[0] + "/" + totalChecks);
                    });
                }
            }));
        }
        
        // Wait for all checks to complete
        for (Future<?> future : futures) {
            try {
                future.get();
            } catch (InterruptedException | ExecutionException e) {
                if (running) {
                    logMessage("Error during verification: " + e.getMessage());
                }
            }
        }
        
        checkExecutor.shutdown();
        
        if (!running) {
            logMessage("Verification stopped by user");
            return;
        }
        
        // Save results if requested
        if (saveResultsCheckbox.isSelected()) {
            saveResultsAsCSV(csvData, serverStatus);
            logMessage("Results saved to " + RESULTS_FILE);
        }
        
        logMessage("File hash verification completed");
    }
    
    private void saveResultsAsCSV(List<Map<String, String>> csvData, Map<String, Boolean> serverStatus) throws IOException {
        try (PrintWriter writer = new PrintWriter(RESULTS_FILE)) {
            // Write summary header
            writer.println("File Hash Validation Report");
            writer.println("Generated:," + new SimpleDateFormat("yyyy-MM-dd HH:mm:ss").format(new Date()));
            writer.println("Total Files Checked:," + csvData.stream().map(r -> r.get("File")).distinct().count());
            writer.println("Total Servers Checked:," + serverStatus.size());
            writer.println("Servers With Valid Files:," + serverStatus.values().stream().filter(b -> b).count());
            writer.println();
            
            // Write server summary
            writer.println("Server Summary");
            writer.println("Server,Has Valid Files");
            for (Map.Entry<String, Boolean> entry : serverStatus.entrySet()) {
                writer.println(entry.getKey() + "," + (entry.getValue() ? "YES" : "NO"));
            }
            writer.println();
            
            // Write detailed results
            writer.println("Detailed Results");
            if (!csvData.isEmpty()) {
                // Write header
                writer.println(String.join(",", csvData.get(0).keySet()));
                
                // Write data
                for (Map<String, String> record : csvData) {
                    writer.println(String.join(",", record.values()));
                }
            }
        }
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