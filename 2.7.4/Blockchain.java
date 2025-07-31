import javax.swing.*;
import javax.swing.border.EmptyBorder;
import javax.swing.filechooser.FileNameExtensionFilter;
import java.awt.*;
import java.awt.event.ActionEvent;
import java.beans.PropertyChangeEvent;
import java.beans.PropertyChangeListener;
import java.io.*;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.security.SecureRandom;
import java.time.Duration;
import java.util.List;
import java.util.Comparator;
import java.util.Optional;
import java.util.concurrent.CompletableFuture;
import java.util.regex.Pattern;
import java.util.stream.Stream;

public class Blockchain extends JFrame {

    // --- GUI Components ---
    private final JTextField btcAddressField;
    private final JTextField fileHashField;
    private final JRadioButton typeHashRadioButton;
    private final JRadioButton selectFileRadioButton;
    private final JButton selectFileButton;
    private final JButton submitButton;
    private final JProgressBar progressBar;
    private final JTextArea responseArea;
    private final JPanel processingIndicator;

    // --- Constants ---
    private static final String FILES_OWNERSHIP_DIR = "files_ownership";
    private static final String BLOCKS_DIR = "blocks";
    private static final String SERVERS_FILE = "servers.txt";
    private static final String GENESIS_PREV_HASH = "0000000000000000000000000000000000000000000000000000000000000000";

    // --- Patterns for Validation ---
    private static final Pattern SHA256_PATTERN = Pattern.compile("^[a-fA-F0-9]{64}$");
    private static final Pattern BTC_ADDRESS_PATTERN = Pattern.compile(
            "^(bc1|[13])[a-zA-HJ-NP-Z0-9]{25,59}$", Pattern.CASE_INSENSITIVE
    );

    /**
     * Constructor: Sets up the main application window and its components.
     */
    public Blockchain() {
        // --- Frame Setup ---
        setTitle("Blockchain File Notary");
        setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        setSize(new Dimension(700, 600));
        setLocationRelativeTo(null); // Center the window

        // --- Main Panel ---
        JPanel mainPanel = new JPanel();
        mainPanel.setLayout(new BorderLayout(10, 10));
        mainPanel.setBorder(new EmptyBorder(15, 15, 15, 15));
        
        // --- Form Panel ---
        JPanel formPanel = new JPanel();
        formPanel.setLayout(new GridBagLayout());
        GridBagConstraints gbc = new GridBagConstraints();
        gbc.insets = new Insets(5, 5, 5, 5);
        gbc.fill = GridBagConstraints.HORIZONTAL;
        gbc.anchor = GridBagConstraints.WEST;

        // --- Form Components ---
        JLabel btcLabel = new JLabel("Bitcoin Address:");
        btcAddressField = new JTextField(40);

        JLabel fileHashLabel = new JLabel("File Hash (SHA-256):");
        fileHashField = new JTextField(40);

        typeHashRadioButton = new JRadioButton("Type Hash", true);
        selectFileRadioButton = new JRadioButton("Select File to Hash");
        ButtonGroup hashMethodGroup = new ButtonGroup();
        hashMethodGroup.add(typeHashRadioButton);
        hashMethodGroup.add(selectFileRadioButton);

        selectFileButton = new JButton("Choose File...");
        selectFileButton.setEnabled(false);
        progressBar = new JProgressBar(0, 100);
        progressBar.setStringPainted(true);
        progressBar.setVisible(false);

        submitButton = new JButton("Submit to Blockchain");

        // --- Layout Form ---
        gbc.gridx = 0; gbc.gridy = 0; gbc.gridwidth = 1;
        formPanel.add(btcLabel, gbc);
        gbc.gridx = 1; gbc.gridy = 0; gbc.gridwidth = 2;
        formPanel.add(btcAddressField, gbc);

        gbc.gridx = 0; gbc.gridy = 1; gbc.gridwidth = 1;
        formPanel.add(fileHashLabel, gbc);
        gbc.gridx = 1; gbc.gridy = 1; gbc.gridwidth = 1;
        formPanel.add(typeHashRadioButton, gbc);
        gbc.gridx = 2; gbc.gridy = 1; gbc.gridwidth = 1;
        formPanel.add(selectFileRadioButton, gbc);
        
        gbc.gridx = 1; gbc.gridy = 2; gbc.gridwidth = 2;
        formPanel.add(fileHashField, gbc);

        gbc.gridx = 1; gbc.gridy = 3; gbc.gridwidth = 1;
        formPanel.add(selectFileButton, gbc);
        gbc.gridx = 2; gbc.gridy = 3; gbc.gridwidth = 1;
        formPanel.add(progressBar, gbc);

        gbc.gridx = 0; gbc.gridy = 4; gbc.gridwidth = 3; gbc.fill = GridBagConstraints.NONE; gbc.anchor = GridBagConstraints.CENTER;
        gbc.insets = new Insets(15, 5, 5, 5);
        formPanel.add(submitButton, gbc);

        // --- Response Panel ---
        responseArea = new JTextArea();
        responseArea.setEditable(false);
        responseArea.setFont(new Font("Monospaced", Font.PLAIN, 12));
        JScrollPane scrollPane = new JScrollPane(responseArea);
        scrollPane.setBorder(BorderFactory.createTitledBorder("Logs & Server Responses"));

        // --- Indicator Panel ---
        processingIndicator = new JPanel(new FlowLayout(FlowLayout.CENTER));
        processingIndicator.add(new JLabel("Processing..."));
        processingIndicator.setVisible(false);
        
        // --- Add Panels to Frame ---
        mainPanel.add(formPanel, BorderLayout.NORTH);
        mainPanel.add(scrollPane, BorderLayout.CENTER);
        mainPanel.add(processingIndicator, BorderLayout.SOUTH);
        
        add(mainPanel);

        // --- Action Listeners ---
        setupActionListeners();
    }

    /**
     * Configures all the event handlers for the GUI components.
     */
    private void setupActionListeners() {
        // Toggle between typing hash and selecting a file
        typeHashRadioButton.addActionListener(e -> toggleHashMethod(true));
        selectFileRadioButton.addActionListener(e -> toggleHashMethod(false));

        // Handle file selection
        selectFileButton.addActionListener(this::onSelectFile);

        // Handle form submission
        submitButton.addActionListener(this::onSubmit);
    }
    
    /**
     * Toggles the UI state between entering a hash manually and selecting a file.
     * @param isTypeHash True if manual hash entry is selected, false otherwise.
     */
    private void toggleHashMethod(boolean isTypeHash) {
        fileHashField.setEditable(isTypeHash);
        selectFileButton.setEnabled(!isTypeHash);
        if (isTypeHash) {
            fileHashField.setBackground(Color.WHITE);
            fileHashField.setText("");
            progressBar.setVisible(false);
        } else {
             fileHashField.setBackground(Color.LIGHT_GRAY);
        }
    }

    /**
     * Handles the "Choose File" button click. Opens a file chooser and
     * starts a background task to hash the selected file.
     */
    private void onSelectFile(ActionEvent e) {
        JFileChooser fileChooser = new JFileChooser();
        fileChooser.setDialogTitle("Select a File to Hash (SHA-256)");
        fileChooser.setFileSelectionMode(JFileChooser.FILES_ONLY);
        if (fileChooser.showOpenDialog(this) == JFileChooser.APPROVE_OPTION) {
            File selectedFile = fileChooser.getSelectedFile();
            hashFileInBackground(selectedFile);
        }
    }

    /**
     * Handles the "Submit" button click. Validates input and starts a background
     * task to process the data.
     */
    private void onSubmit(ActionEvent e) {
        String btcAddress = btcAddressField.getText().trim();
        String fileHash = fileHashField.getText().trim();

        // --- Input Validation ---
        if (!isValidBitcoinAddress(btcAddress)) {
            JOptionPane.showMessageDialog(this, "Error: Invalid Bitcoin address format.", "Validation Error", JOptionPane.ERROR_MESSAGE);
            return;
        }
        if (!isValidSha256(fileHash)) {
            JOptionPane.showMessageDialog(this, "Error: Invalid SHA-256 hash format.", "Validation Error", JOptionPane.ERROR_MESSAGE);
            return;
        }

        // --- Process in Background ---
        processDataInBackground(btcAddress, fileHash);
    }
    
    /**
     * Hashes a file using a SwingWorker to prevent the GUI from freezing.
     * Updates the progress bar as the file is read.
     * @param file The file to be hashed.
     */
    private void hashFileInBackground(File file) {
        progressBar.setValue(0);
        progressBar.setVisible(true);
        submitButton.setEnabled(false);
        fileHashField.setText("Hashing in progress...");

        FileHasherTask task = new FileHasherTask(file);
        
        task.addPropertyChangeListener(evt -> {
            if ("progress".equals(evt.getPropertyName())) {
                progressBar.setValue((Integer) evt.getNewValue());
            }
        });

        task.execute();
    }
    
    /**
     * Processes the data submission using a SwingWorker. This includes saving the data
     * locally and propagating it to other servers.
     * @param btcAddress The validated Bitcoin address.
     * @param fileHash   The validated SHA-256 file hash.
     */
    private void processDataInBackground(String btcAddress, String fileHash) {
        responseArea.setText("");
        setProcessingState(true);

        DataProcessorTask task = new DataProcessorTask(btcAddress, fileHash);
        task.execute();
    }
    
    /**
     * Enables or disables the UI processing state.
     * @param isProcessing True to show "Processing...", false to hide it.
     */
    private void setProcessingState(boolean isProcessing) {
        submitButton.setEnabled(!isProcessing);
        processingIndicator.setVisible(isProcessing);
    }
    
    // --- SwingWorker for File Hashing ---
    private class FileHasherTask extends SwingWorker<String, Integer> {
        private final File file;

        public FileHasherTask(File file) {
            this.file = file;
        }
        
        @Override
        protected String doInBackground() throws Exception {
            MessageDigest sha256 = MessageDigest.getInstance("SHA-256");
            try (InputStream is = new BufferedInputStream(new FileInputStream(file))) {
                byte[] buffer = new byte[8192];
                int bytesRead;
                long totalBytesRead = 0;
                long fileSize = file.length();
                while ((bytesRead = is.read(buffer)) != -1) {
                    sha256.update(buffer, 0, bytesRead);
                    totalBytesRead += bytesRead;
                    if (fileSize > 0) {
                        int progress = (int) ((totalBytesRead * 100) / fileSize);
                        setProgress(progress);
                    }
                }
            }
            return bytesToHex(sha256.digest());
        }

        @Override
        protected void done() {
            try {
                String hash = get();
                fileHashField.setText(hash);
                progressBar.setValue(100);
            } catch (Exception e) {
                fileHashField.setText("Error during hashing.");
                JOptionPane.showMessageDialog(Blockchain.this,
                    "Could not hash the file: " + e.getMessage(), "Hashing Error", JOptionPane.ERROR_MESSAGE);
                progressBar.setVisible(false);
            } finally {
                submitButton.setEnabled(true);
            }
        }
    }

    // --- SwingWorker for Backend Logic and Networking ---
    private class DataProcessorTask extends SwingWorker<Void, String> {
        private final String btcAddress;
        private final String fileHash;
        
        public DataProcessorTask(String btcAddress, String fileHash) {
            this.btcAddress = btcAddress;
            this.fileHash = fileHash;
        }

        @Override
        protected Void doInBackground() throws Exception {
            // 1. Process and save locally
            publish("--- [Local Server Processing] ---");
            try {
                String localResult = processAndSaveRecord(btcAddress, fileHash);
                publish("STATUS: Success");
                publish("RESPONSE: " + localResult);
            } catch (IOException | NoSuchAlgorithmException e) {
                publish("STATUS: Failure");
                publish("RESPONSE: " + e.getMessage());
                // If local save fails, no point in proceeding
                return null;
            }
            
            // 2. Read servers file and propagate
            File servers = new File(SERVERS_FILE);
            if (!servers.exists()) {
                 publish("\nINFO: servers.txt not found. Skipping propagation.");
                 return null;
            }
            
            publish("\n--- [Propagating to Remote Servers] ---");
            List<String> serverUrls = Files.readAllLines(servers.toPath(), StandardCharsets.UTF_8);
            HttpClient client = HttpClient.newBuilder().connectTimeout(Duration.ofSeconds(10)).build();

            for (String url : serverUrls) {
                if (url.trim().isEmpty()) continue;
                
                String fullUrl = url.trim() + (url.contains("?") ? "&" : "?") +
                                 "btc=" + btcAddress + "&filehash=" + fileHash;
                
                publish("\nSending to: " + url);
                try {
                    HttpRequest request = HttpRequest.newBuilder()
                        .uri(URI.create(fullUrl))
                        .GET()
                        .build();

                    HttpResponse<String> response = client.send(request, HttpResponse.BodyHandlers.ofString());
                    publish("STATUS: " + response.statusCode());
                    publish("RESPONSE: " + response.body().trim());
                } catch (Exception e) {
                     publish("STATUS: Connection Failed");
                     publish("RESPONSE: " + e.getMessage());
                }
            }
            return null;
        }
        
        @Override
        protected void process(List<String> chunks) {
            for (String line : chunks) {
                responseArea.append(line + "\n");
            }
        }

        @Override
        protected void done() {
            setProcessingState(false);
            publish("\n--- [Processing Complete] ---");
        }
    }

    // =================================================================
    // --- Business Logic (Ported from PHP backend) ---
    // =================================================================

    /**
     * Main logic to create and save ownership and block files.
     */
    private String processAndSaveRecord(String btc, String filehash) throws IOException, NoSuchAlgorithmException {
        // Check if ownership record already exists
        Path ownershipFile = Paths.get(FILES_OWNERSHIP_DIR, filehash + ".txt");
        if (Files.exists(ownershipFile)) {
            throw new IOException("Error: File hash already exists in the blockchain.");
        }

        // Save ownership file
        Files.writeString(ownershipFile, btc, StandardCharsets.UTF_8);

        // Create the block content
        String previousHash = getPreviousBlockHash();
        long timestamp = System.currentTimeMillis() / 1000L;
        String nonce = generateNonce();

        // Manually create the JSON structure
        String blockData = String.format(
            "{\n" +
            "  \"version\": \"1.0\",\n" +
            "  \"timestamp\": %d,\n" +
            "  \"previous_hash\": \"%s\",\n" +
            "  \"filehash\": \"%s\",\n" +
            "  \"bitcoin_address\": \"%s\",\n" +
            "  \"nonce\": \"%s\"\n" +
            "}",
            timestamp, previousHash, filehash, btc, nonce
        );
        
        // Calculate hash of this block
        MessageDigest digest = MessageDigest.getInstance("SHA-256");
        String blockHash = bytesToHex(digest.digest(blockData.getBytes(StandardCharsets.UTF_8)));

        // Final JSON with the block's own hash included
         String finalBlockJson = String.format(
            "{\n" +
            "  \"hash\": \"%s\",\n" +
            "  \"version\": \"1.0\",\n" +
            "  \"timestamp\": %d,\n" +
            "  \"previous_hash\": \"%s\",\n" +
            "  \"filehash\": \"%s\",\n" +
            "  \"bitcoin_address\": \"%s\",\n" +
            "  \"nonce\": \"%s\"\n" +
            "}",
            blockHash, timestamp, previousHash, filehash, btc, nonce
        );

        // Save block file
        Path blockFile = Paths.get(BLOCKS_DIR, blockHash + ".json");
        try {
            Files.writeString(blockFile, finalBlockJson, StandardCharsets.UTF_8);
        } catch (IOException e) {
            // Rollback: delete ownership file if block save fails
            Files.deleteIfExists(ownershipFile);
            throw new IOException("Error: Could not save block file. Ownership record was rolled back.", e);
        }

        return "Data saved. Block created: " + blockHash;
    }

    /**
     * Finds the hash of the most recent block.
     */
    private String getPreviousBlockHash() throws IOException {
        Path blocksPath = Paths.get(BLOCKS_DIR);
        if (!Files.exists(blocksPath) || !Files.isDirectory(blocksPath)) {
            return GENESIS_PREV_HASH;
        }

        try (Stream<Path> stream = Files.list(blocksPath)) {
            Optional<Path> lastFile = stream
                .filter(p -> p.toString().endsWith(".json"))
                .max(Comparator.comparingLong(p -> p.toFile().lastModified()));

            if (lastFile.isPresent()) {
                String fileName = lastFile.get().getFileName().toString();
                return fileName.substring(0, fileName.lastIndexOf('.'));
            } else {
                return GENESIS_PREV_HASH;
            }
        }
    }

    // --- Validation and Helper Methods ---
    private static boolean isValidSha256(String hash) {
        return hash != null && SHA256_PATTERN.matcher(hash).matches();
    }

    private static boolean isValidBitcoinAddress(String address) {
        return address != null && BTC_ADDRESS_PATTERN.matcher(address).matches();
    }

    private static String generateNonce() {
        SecureRandom random = new SecureRandom();
        byte[] bytes = new byte[16];
        random.nextBytes(bytes);
        return bytesToHex(bytes);
    }
    
    private static String bytesToHex(byte[] bytes) {
        StringBuilder hexString = new StringBuilder(2 * bytes.length);
        for (byte b : bytes) {
            String hex = Integer.toHexString(0xff & b);
            if (hex.length() == 1) {
                hexString.append('0');
            }
            hexString.append(hex);
        }
        return hexString.toString();
    }

    private static void createDirectories() {
        try {
            Files.createDirectories(Paths.get(FILES_OWNERSHIP_DIR));
            Files.createDirectories(Paths.get(BLOCKS_DIR));
        } catch (IOException e) {
            System.err.println("Could not create necessary directories: " + e.getMessage());
            // In a real app, you might want to show a dialog and exit.
        }
    }

    /**
     * Main entry point for the application.
     */
    public static void main(String[] args) {
        // Ensure required directories exist before starting the GUI.
        createDirectories();

        // Run the GUI on the Event Dispatch Thread (EDT).
        SwingUtilities.invokeLater(() -> {
            Blockchain frame = new Blockchain();
            frame.setVisible(true);
        });
    }
}