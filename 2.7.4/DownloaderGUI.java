import javax.net.ssl.HttpsURLConnection;
import javax.net.ssl.SSLContext;
import javax.net.ssl.TrustManager;
import javax.net.ssl.X509TrustManager;
import javax.swing.*;
import java.awt.*;
import java.io.*;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.security.MessageDigest;
import java.security.cert.X509Certificate;
import java.util.List;
import java.util.*;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class DownloaderGUI extends JFrame {

    // --- UI Components ---
    private final JTextField inputField;
    private final JButton downloadButton;
    private final JTextArea logArea;
    private final JProgressBar progressBar;

    // --- Constants from original code ---
    private static final String USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36";
    private static final int CONNECT_TIMEOUT = 10000; // 10 seconds
    private static final int READ_TIMEOUT = 30000;    // 30 seconds
    private static final String DATA_SERVERS_DIR = "data_servers";
    private static final String DATA_DIR = "data";
    private static final String SERVERS_FILE = "servers.txt";

    /**
     * Constructor to set up the GUI.
     */
    public DownloaderGUI() {
        // --- Frame Setup ---
        super("File Downloader");
        setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        setSize(800, 600);
        setLocationRelativeTo(null); // Center the window

        // --- Main Panel ---
        JPanel mainPanel = new JPanel(new BorderLayout(10, 10));
        mainPanel.setBorder(BorderFactory.createEmptyBorder(10, 10, 10, 10));

        // --- Input Panel ---
        JPanel inputPanel = new JPanel(new BorderLayout(5, 5));
        inputPanel.add(new JLabel("Enter Text or SHA256 Hash:"), BorderLayout.WEST);
        inputField = new JTextField();
        inputPanel.add(inputField, BorderLayout.CENTER);
        downloadButton = new JButton("Download");
        inputPanel.add(downloadButton, BorderLayout.EAST);

        // --- Log Area with Scroll Pane ---
        logArea = new JTextArea();
        logArea.setEditable(false);
        logArea.setFont(new Font("Monospaced", Font.PLAIN, 12));
        JScrollPane scrollPane = new JScrollPane(logArea);

        // --- Progress Bar ---
        progressBar = new JProgressBar();
        progressBar.setStringPainted(true);
        progressBar.setVisible(false); // Initially hidden

        // --- Add components to main panel ---
        mainPanel.add(inputPanel, BorderLayout.NORTH);
        mainPanel.add(scrollPane, BorderLayout.CENTER);
        mainPanel.add(progressBar, BorderLayout.SOUTH);

        add(mainPanel);

        // --- Action Listener for the Download Button ---
        downloadButton.addActionListener(e -> startDownloadProcess());
    }

    /**
     * Initiates the download process when the button is clicked.
     * It performs initial checks and then starts a background worker thread.
     */
    private void startDownloadProcess() {
        String userText = inputField.getText().trim();
        if (userText.isEmpty()) {
            JOptionPane.showMessageDialog(this, "Input cannot be empty.", "Error", JOptionPane.ERROR_MESSAGE);
            return;
        }

        // Disable UI elements during processing
        downloadButton.setEnabled(false);
        inputField.setEditable(false);
        logArea.setText(""); // Clear previous logs
        progressBar.setValue(0);
        progressBar.setVisible(true);
        progressBar.setForeground(new JProgressBar().getForeground()); // Reset color on new run

        // Create and execute a SwingWorker for background processing
        DownloadWorker worker = new DownloadWorker(userText);
        worker.execute();
    }

    /**
     * A SwingWorker to handle the download process in a background thread,
     * preventing the GUI from freezing and allowing for real-time log updates.
     */
    private class DownloadWorker extends SwingWorker<Void, String> {
        private final String userText;

        public DownloadWorker(String userText) {
            this.userText = userText;
        }

        @Override
        protected Void doInBackground() throws Exception {
            publish("Process started...\n");

            // --- Hashing Logic ---
            String hash;
            if (isValidSha256(userText)) {
                hash = userText.toLowerCase();
                publish("Using provided Hash: " + hash);
            } else {
                hash = generateSha256(userText);
                publish("Generated Hash: " + hash);
                publish("Original Text: " + userText);
            }
            publish("---");

            Path dataServersPath = Paths.get(DATA_SERVERS_DIR);
            Path dataPath = Paths.get(DATA_DIR);
            Path hashDirPath = dataServersPath.resolve(hash);
            Path indexFilePath = hashDirPath.resolve("index.html");

            try {
                // --- Create Directories ---
                Files.createDirectories(dataServersPath);
                Files.createDirectories(dataPath);
                publish("Required directories are present.");

                // --- Check for Cached Version ---
                if (Files.exists(indexFilePath)) {
                    handleCachedVersion(indexFilePath, hash);
                } else {
                    handleRemoteDownload(hash, hashDirPath, indexFilePath);
                }

                if (Files.exists(indexFilePath)) {
                    publish("\n---");
                    publish("Local HTML file ready at: " + indexFilePath.toAbsolutePath());
                }

            } catch (IOException e) {
                throw new Exception("A file system error occurred: " + e.getMessage(), e);
            } catch (Exception e) {
                // To get the root cause message if it's wrapped
                String message = e.getMessage();
                if (e.getCause() != null && e.getCause().getMessage() != null) {
                    message = e.getCause().getMessage();
                }
                throw new Exception("An unexpected error occurred: " + message, e);
            }
            return null;
        }
        
        /**
         * Processes a cached version of the index file and downloads its linked files.
         */
        private void handleCachedVersion(Path indexFilePath, String hash) throws Exception {
            publish("\nFound cached version of index.html.");
            String htmlContent = new String(Files.readAllBytes(indexFilePath), StandardCharsets.UTF_8);
            
            publish("Downloading linked files from cached index...");
            Map<String, List<Map<String, Object>>> downloadResults = downloadLinkedFiles(htmlContent, "", hash, DATA_DIR);
            printDownloadResults(downloadResults);
        }

        /**
         * Handles downloading the index file from remote servers if not cached.
         */
        private void handleRemoteDownload(String hash, Path hashDirPath, Path indexFilePath) throws Exception {
            publish("\nIndex file not found in cache. Searching remote servers...");
            Path serversPath = Paths.get(SERVERS_FILE);
            if (!Files.exists(serversPath)) {
                throw new FileNotFoundException("'" + SERVERS_FILE + "' file not found. Please create it in the application directory.");
            }

            List<String> servers = Files.readAllLines(serversPath);
            List<Map<String, String>> foundServers = new ArrayList<>();
            boolean contentSaved = false;

            for (int i = 0; i < servers.size(); i++) {
                String server = servers.get(i).trim();
                if (server.isEmpty()) continue;
                
                publish("\nChecking server: " + server);
                String urlToCheck = buildProperUrl(server, hash, "index.html");
                
                StringBuilder errorMessage = new StringBuilder();
                String pageContent = getContent(urlToCheck, errorMessage);

                if (pageContent != null) {
                    publish("  -> File found on this server!");
                    Map<String, String> serverInfo = new HashMap<>();
                    serverInfo.put("original", server);
                    serverInfo.put("full_url", urlToCheck);
                    foundServers.add(serverInfo);
                    
                    if (!contentSaved) {
                        Files.createDirectories(hashDirPath);
                        String processedContent = processHtmlLinks(pageContent, server, hash);
                        Files.write(indexFilePath, processedContent.getBytes(StandardCharsets.UTF_8));
                        contentSaved = true;
                        publish("  -> Index file saved locally.");
                        
                        publish("  -> Downloading linked files...");
                        Map<String, List<Map<String, Object>>> downloadResults = downloadLinkedFiles(pageContent, server, hash, DATA_DIR);
                        printDownloadResults(downloadResults);
                    }
                } else {
                    publish("  -> File not found. Reason: " + errorMessage);
                }
                // Update progress bar after checking each server
                int progress = (int) (((i + 1.0) / servers.size()) * 100);
                progressBar.setValue(progress);
            }

            if (foundServers.isEmpty()) {
                publish("\n---");
                publish("FILE NOT FOUND ON ANY SERVER.");
            } else {
                publish("\n---");
                publish("File was found on the following servers:");
                for (Map<String, String> server : foundServers) {
                    publish("  - " + server.get("original") + " (" + server.get("full_url") + ")");
                }
            }
        }
        
        /**
         * This method is called on the Event Dispatch Thread (EDT) to update the GUI
         * with messages published from the background thread.
         */
        @Override
        protected void process(List<String> chunks) {
            for (String message : chunks) {
                logArea.append(message + "\n");
            }
        }
        
        /**
         * This method is called on the EDT after the background task is finished.
         * It handles cleanup, final messages, and re-enabling the UI.
         */
        @Override
        protected void done() {
            try {
                get(); // Check for exceptions from doInBackground
                publish("\nProcess finished successfully.");
                progressBar.setValue(100);
                progressBar.setString("Done!");
            } catch (Exception e) {
                // Get the most specific error message to display to the user.
                String errorMessage = e.getMessage();
                if(e.getCause() != null && e.getCause().getMessage() != null){
                    errorMessage = e.getCause().getMessage();
                }
                logArea.append("\nERROR: " + errorMessage + "\n");
                JOptionPane.showMessageDialog(DownloaderGUI.this, errorMessage, "Error", JOptionPane.ERROR_MESSAGE);
                progressBar.setValue(100); // Fill the bar to indicate completion, but in red.
                progressBar.setString("Error!");
                progressBar.setForeground(Color.RED);
            } finally {
                // Re-enable UI elements
                downloadButton.setEnabled(true);
                inputField.setEditable(true);
            }
        }

        /**
         * Prints the summary of downloaded and failed files to the log area.
         */
        private void printDownloadResults(Map<String, List<Map<String, Object>>> results) {
            List<Map<String, Object>> downloaded = results.get("downloaded");
            List<Map<String, Object>> failed = results.get("failed");
            
            long downloadedCount = downloaded.stream().filter(f -> f.get("status").equals("downloaded")).count();
            long existingCount = downloaded.stream().filter(f -> f.get("status").equals("already_exists")).count();
            long totalSize = downloaded.stream().mapToLong(f -> (Long) f.get("size")).sum();

            publish("\n--- Download Statistics ---");
            publish("  New files downloaded: " + downloadedCount);
            publish("  Files already existed: " + existingCount);
            publish("  Failed to download: " + failed.size());
            publish("  Total size of all files: " + (totalSize / 1024) + " KB");

            if (!downloaded.isEmpty()) {
                publish("\n--- Downloaded Files ---");
                for (Map<String, Object> file : downloaded) {
                    String status = (String) file.get("status");
                    String statusMsg = status.equals("downloaded") ? "[Downloaded]" : "[Already Existed]";
                    publish(String.format("%s %s", statusMsg, file.get("url")));
                    publish(String.format("  -> Saved to: %s (%d bytes)", file.get("local_path"), (Long) file.get("size")));
                }
            }
            if (!failed.isEmpty()) {
                publish("\n--- Failed Downloads ---");
                for (Map<String, Object> file : failed) {
                    publish(String.format("%s - Error: %s", file.get("url"), file.get("error")));
                }
            }
        }
    }


    // ===================================================================================
    //  STATIC HELPER METHODS (Mostly unchanged from the original command-line version)
    // ===================================================================================

    /**
     * Downloads content from a URL as a string.
     */
    private static String getContent(String url, StringBuilder errorMessage) {
        HttpURLConnection connection = null;
        try {
            URL urlObj = new URL(url);
            connection = (HttpURLConnection) urlObj.openConnection();
            connection.setRequestMethod("GET");
            connection.setRequestProperty("User-Agent", USER_AGENT);
            connection.setConnectTimeout(CONNECT_TIMEOUT);
            connection.setReadTimeout(READ_TIMEOUT);

            if (url.startsWith("https")) {
                trustAllCertificates();
            }

            int responseCode = connection.getResponseCode();
            if (responseCode >= 400) {
                errorMessage.append("HTTP status code: ").append(responseCode);
                return null;
            }

            try (BufferedReader in = new BufferedReader(new InputStreamReader(connection.getInputStream()))) {
                StringBuilder response = new StringBuilder();
                String inputLine;
                while ((inputLine = in.readLine()) != null) {
                    response.append(inputLine).append("\n");
                }
                return response.toString();
            }
        } catch (Exception e) {
            errorMessage.append(e.getClass().getSimpleName()).append(": ").append(e.getMessage());
            return null;
        } finally {
            if (connection != null) {
                connection.disconnect();
            }
        }
    }

    /**
     * Downloads content from a URL as a byte array.
     */
    private static byte[] getContentBytes(String url, StringBuilder errorMessage) {
        HttpURLConnection connection = null;
        try {
            URL urlObj = new URL(url);
            connection = (HttpURLConnection) urlObj.openConnection();
            connection.setRequestMethod("GET");
            connection.setRequestProperty("User-Agent", USER_AGENT);
            connection.setConnectTimeout(CONNECT_TIMEOUT);
            connection.setReadTimeout(READ_TIMEOUT);

            if (url.startsWith("https")) {
                trustAllCertificates();
            }

            int responseCode = connection.getResponseCode();
            if (responseCode >= 400) {
                errorMessage.append("HTTP status code: ").append(responseCode);
                return null;
            }

            try (InputStream in = connection.getInputStream(); ByteArrayOutputStream out = new ByteArrayOutputStream()) {
                byte[] buffer = new byte[4096];
                int bytesRead;
                while ((bytesRead = in.read(buffer)) != -1) {
                    out.write(buffer, 0, bytesRead);
                }
                return out.toByteArray();
            }
        } catch (Exception e) {
            errorMessage.append(e.getClass().getSimpleName()).append(": ").append(e.getMessage());
            return null;
        } finally {
            if (connection != null) {
                connection.disconnect();
            }
        }
    }

    /**
     * Generates a SHA-256 hash from a string.
     */
    private static String generateSha256(String input) {
        try {
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            byte[] hashBytes = digest.digest(input.getBytes(StandardCharsets.UTF_8));
            StringBuilder hexString = new StringBuilder();
            for (byte b : hashBytes) {
                String hex = Integer.toHexString(0xff & b);
                if (hex.length() == 1) hexString.append('0');
                hexString.append(hex);
            }
            return hexString.toString();
        } catch (Exception e) {
            throw new RuntimeException("Error generating SHA-256 hash", e);
        }
    }

    /**
     * Validates if a string is a well-formed SHA-256 hash.
     */
    private static boolean isValidSha256(String hash) {
        return hash.matches("^[a-f0-9]{64}$");
    }

    /**
      * Downloads files linked within the HTML content (e.g., CSS, JS, images).
      * This method has been corrected to fix the "effectively final" error.
      */
    private Map<String, List<Map<String, Object>>> downloadLinkedFiles(
        String htmlContent, String baseServer, String hash, String dataDir) throws IOException {
        
        List<Map<String, Object>> downloadedFiles = new ArrayList<>();
        List<Map<String, Object>> failedDownloads = new ArrayList<>();
        
        Files.createDirectories(Paths.get(dataDir));
        
        Pattern pattern = Pattern.compile("(href|src)=[\"']([^\"']+)[\"']", Pattern.CASE_INSENSITIVE);
        Matcher matcher = pattern.matcher(htmlContent);
        
        while (matcher.find()) {
            String attribute = matcher.group(1);
            String originalUrl = matcher.group(2);

            if (originalUrl.matches("^(data:|javascript:|mailto:|#).*")) continue;
            if (attribute.equalsIgnoreCase("href") && originalUrl.matches(".*\\.html?$")) continue;

            // Use a temporary variable to construct the full URL, then assign to a final variable.
            String tempProcessedUrl = originalUrl;
            if (!originalUrl.matches("^https?://.*")) {
                // Use a local variable for the trimmed server; do not modify the method parameter.
                String currentBaseServer = baseServer.replaceAll("/+$", "");
                tempProcessedUrl = originalUrl.startsWith("/") ? currentBaseServer + originalUrl : buildProperUrl(currentBaseServer, hash, originalUrl);
            }
            
            // This final variable can be used in the lambda expression below.
            final String processedUrl = tempProcessedUrl;

            String filename = sanitizeFilename(processedUrl.replaceAll(".*/", ""));
            if (filename.isEmpty()) filename = "index.html";
            
            String filenameWithoutExt = getFilenameWithoutExtension(processedUrl);
            Path fileDirPath = Paths.get(dataDir, sanitizeFilename(filenameWithoutExt));
            Files.createDirectories(fileDirPath);
            Path filePath = fileDirPath.resolve(filename);

            // The lambda now correctly references an effectively final variable.
            boolean alreadyProcessed = downloadedFiles.stream().anyMatch(f -> f.get("url").equals(processedUrl));
            if (alreadyProcessed) continue;

            if (Files.exists(filePath)) {
                Map<String, Object> fileInfo = new HashMap<>();
                fileInfo.put("url", processedUrl);
                fileInfo.put("local_path", filePath.toString());
                fileInfo.put("size", Files.size(filePath));
                fileInfo.put("status", "already_exists");
                downloadedFiles.add(fileInfo);
                continue;
            }

            StringBuilder errorMessage = new StringBuilder();
            byte[] fileContent = getContentBytes(processedUrl, errorMessage);
            
            if (fileContent != null) {
                Files.write(filePath, fileContent);
                Map<String, Object> fileInfo = new HashMap<>();
                fileInfo.put("url", processedUrl);
                fileInfo.put("local_path", filePath.toString());
                fileInfo.put("size", (long) fileContent.length);
                fileInfo.put("status", "downloaded");
                downloadedFiles.add(fileInfo);
            } else {
                Map<String, Object> failedInfo = new HashMap<>();
                failedInfo.put("url", processedUrl);
                failedInfo.put("error", errorMessage.toString());
                failedDownloads.add(failedInfo);
            }
        }
        
        Map<String, List<Map<String, Object>>> results = new HashMap<>();
        results.put("downloaded", downloadedFiles);
        results.put("failed", failedDownloads);
        return results;
    }

    /**
     * Rewrites relative links in HTML content to be absolute.
     */
    private static String processHtmlLinks(String content, String server, String hash) {
        Pattern pattern = Pattern.compile("(href|src)=[\"'](?!https?://|//|data:)([^\"']+)[\"']", Pattern.CASE_INSENSITIVE);
        Matcher matcher = pattern.matcher(content);
        StringBuffer sb = new StringBuffer();
        
        while (matcher.find()) {
            String url = matcher.group(2);
            String absoluteUrl = url.startsWith("/") ? server.replaceAll("/+$", "") + url : buildProperUrl(server, hash, url);
            matcher.appendReplacement(sb, matcher.group(1) + "=\"" + Matcher.quoteReplacement(absoluteUrl) + "\"");
        }
        matcher.appendTail(sb);
        return sb.toString();
    }
    
    private static String buildProperUrl(String server, String hash, String filename) {
        server = server.replaceAll("/+$", "");
        return String.format("%s/data/%s/%s", server, hash, filename);
    }
    
    private static String sanitizeFilename(String filename) {
        return filename.replaceAll("[^a-zA-Z0-9._-]", "_");
    }
    
    private static String getFilenameWithoutExtension(String url) {
        String path = url.replaceAll("[?#].*$", "").replaceAll(".*/", "");
        int lastDot = path.lastIndexOf('.');
        return (lastDot > 0) ? path.substring(0, lastDot) : (path.isEmpty() ? "file" : path);
    }

    /**
     * Trusts all SSL certificates. Insecure, for development/testing only.
     */
    private static void trustAllCertificates() {
        try {
            HttpsURLConnection.setDefaultHostnameVerifier((hostname, session) -> true);
            TrustManager[] trustAllCerts = new TrustManager[]{new X509TrustManager() {
                public X509Certificate[] getAcceptedIssuers() { return null; }
                public void checkClientTrusted(X509Certificate[] certs, String authType) {}
                public void checkServerTrusted(X509Certificate[] certs, String authType) {}
            }};
            SSLContext sc = SSLContext.getInstance("SSL");
            sc.init(null, trustAllCerts, new java.security.SecureRandom());
            HttpsURLConnection.setDefaultSSLSocketFactory(sc.getSocketFactory());
        } catch (Exception e) {
            // In a GUI app, we prefer not to print stack traces to console
            System.err.println("Warning: Could not install trust manager. HTTPS requests might fail. " + e.getMessage());
        }
    }

    /**
     * Main method to launch the GUI.
     */
    public static void main(String[] args) {
        // Ensure the GUI is created and updated on the Event Dispatch Thread (EDT)
        SwingUtilities.invokeLater(() -> {
            DownloaderGUI gui = new DownloaderGUI();
            gui.setVisible(true);
        });
    }
}
