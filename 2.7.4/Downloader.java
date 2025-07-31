import java.io.*;
import java.net.*;
import java.nio.file.*;
import java.security.*;
import java.util.*;
import java.util.regex.*;
import javax.net.ssl.HttpsURLConnection;
import javax.net.ssl.SSLContext;
import javax.net.ssl.TrustManager;
import javax.net.ssl.X509TrustManager;

public class Downloader {

    private static final String USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36";
    private static final int CONNECT_TIMEOUT = 10000; // 10 seconds
    private static final int READ_TIMEOUT = 30000;    // 30 seconds

    public static void main(String[] args) {
        // Main application loop to continuously ask for user input
        try (Scanner scanner = new Scanner(System.in)) {
            while (true) {
                System.out.print("\nEnter Text or SHA256 Hash (or type 'exit' to quit): ");
                String userText = scanner.nextLine().trim();

                // Check for exit condition
                if ("exit".equalsIgnoreCase(userText)) {
                    System.out.println("Exiting application.");
                    break;
                }

                if (userText.isEmpty()) {
                    continue;
                }
                
                // Process the user's request
                processRequest(userText);
            }
        }
    }

    /**
     * Processes a single download request based on user input (text or hash).
     * @param userText The text or SHA256 hash provided by the user.
     */
    private static void processRequest(String userText) {
        String hash;
        if (isValidSha256(userText)) {
            hash = userText.toLowerCase();
            System.out.println("Using provided Hash: " + hash);
        } else {
            hash = generateSha256(userText);
            System.out.println("Generated Hash: " + hash);
            System.out.println("Original Text: " + userText);
        }

        String dataServersDir = "data";
        String hashDir = dataServersDir + File.separator + hash;
        String indexFile = hashDir + File.separator + "index.html";
        String dataDir = "data";

        try {
            // Create base directories if they don't exist
            Files.createDirectories(Paths.get(dataServersDir));
            Files.createDirectories(Paths.get(dataDir));

            // Always attempt to find and process content from all available servers.
            processAllServers(hash, hashDir, indexFile, dataDir);

            if (Files.exists(Paths.get(indexFile))) {
                System.out.println("\nLocal HTML file available at: " + Paths.get(indexFile).toAbsolutePath());
                System.out.println("Note: This file reflects the content from the last successful server query.");
            }

        } catch (IOException e) {
            System.err.println("An I/O error occurred: " + e.getMessage());
        } catch (Exception e) {
            System.err.println("An unexpected error occurred: " + e.getMessage());
            e.printStackTrace();
        }
    }

    /**
     * Iterates through all servers in servers.txt, downloads index files, and processes all linked assets.
     */
    private static void processAllServers(String hash, String hashDir, String indexFile, String dataDir) throws IOException {
        String serversFile = "servers.txt";
        List<String> servers;

        try {
            servers = Files.readAllLines(Paths.get(serversFile));
        } catch (NoSuchFileException e) {
            System.err.println("Error: 'servers.txt' file not found. Please create it and add server URLs.");
            return;
        }
        
        List<String> successfulServers = new ArrayList<>();
        // Aggregated results from all servers
        List<Map<String, Object>> allDownloadedFiles = new ArrayList<>();
        List<Map<String, Object>> allFailedDownloads = new ArrayList<>();
        Set<String> processedUrls = new HashSet<>(); // Avoids re-processing the same asset URL in one run

        System.out.println("\nChecking all servers for content...");

        for (String server : servers) {
            server = server.trim();
            if (server.isEmpty()) continue;

            String urlToCheck = buildProperUrl(server, hash, "index.html");
            StringBuilder errorMessage = new StringBuilder();
            
            System.out.println("---------------------------------");
            System.out.println("Querying Server: " + server);
            
            String pageContent = getContent(urlToCheck, errorMessage);

            if (pageContent != null) {
                System.out.println("SUCCESS: Found index.html on " + server);
                successfulServers.add(server);

                // Create the hash-specific directory for the index file if it doesn't exist
                Files.createDirectories(Paths.get(hashDir));
                
                // Save or overwrite the local index.html with the content from the current server
                Files.write(Paths.get(indexFile), pageContent.getBytes());
                
                // Process linked files described in this index.html
                System.out.println("Processing linked files from " + server + "...");
                Map<String, List<Map<String, Object>>> results = downloadLinkedFiles(pageContent, server, hash, dataDir, processedUrls);
                
                // Aggregate results
                allDownloadedFiles.addAll(results.get("downloaded"));
                allFailedDownloads.addAll(results.get("failed"));

            } else {
                System.out.println("FAILURE: Could not retrieve index.html. Reason: " + errorMessage);
            }
        }
        
        // Print the final aggregated results from all server queries
        Map<String, List<Map<String, Object>>> finalResults = new HashMap<>();
        finalResults.put("downloaded", allDownloadedFiles);
        finalResults.put("failed", allFailedDownloads);

        System.out.println("\n--- Overall Download Report ---");
        if (successfulServers.isEmpty()) {
            System.out.println("Content not found on any of the specified servers.");
        } else {
            System.out.println("Found index.html on " + successfulServers.size() + " server(s): " + String.join(", ", successfulServers));
            printDownloadResults(finalResults);
        }
    }

    /**
     * Downloads files linked within HTML, skipping files that already exist in the `data` directory.
     * @param processedUrls A set of URLs that have already been queued for download in this session to prevent duplicates.
     */
    private static Map<String, List<Map<String, Object>>> downloadLinkedFiles(
            String htmlContent, String baseServer, String hash, String dataDir, Set<String> processedUrls) throws IOException {
        
        List<Map<String, Object>> downloadedFiles = new ArrayList<>();
        List<Map<String, Object>> failedDownloads = new ArrayList<>();

        Pattern pattern = Pattern.compile("(href|src)=[\"']([^\"']+)[\"']", Pattern.CASE_INSENSITIVE);
        Matcher matcher = pattern.matcher(htmlContent);

        while (matcher.find()) {
            String attribute = matcher.group(1);
            String originalUrl = matcher.group(2);

            if (originalUrl.matches("^(data:|javascript:|mailto:|#|android-app:|ios-app:).*") ||
               (attribute.equalsIgnoreCase("href") && originalUrl.matches(".*\\.html?$"))) {
                continue;
            }

            String processedUrl;
            if (originalUrl.matches("^https?://.*")) {
                processedUrl = originalUrl;
            } else {
                baseServer = baseServer.replaceAll("/+$", "");
                processedUrl = originalUrl.startsWith("/") ? baseServer + originalUrl : buildProperUrl(baseServer, hash, originalUrl);
            }
            
            // Skip if this URL has already been processed in this run
            if (processedUrls.contains(processedUrl)) {
                continue;
            }
            processedUrls.add(processedUrl); // Mark as processed for this session

            String filename = sanitizeFilename(processedUrl.replaceAll(".*/", ""));
            if (filename.isEmpty()) filename = "index.html";
            
            String filenameWithoutExt = getFilenameWithoutExtension(processedUrl);
            String fileDir = dataDir + File.separator + sanitizeFilename(filenameWithoutExt);
            String filePath = fileDir + File.separator + filename;

            if (Files.exists(Paths.get(filePath))) {
                Map<String, Object> fileInfo = new HashMap<>();
                fileInfo.put("url", processedUrl);
                fileInfo.put("local_path", filePath);
                fileInfo.put("size", Files.size(Paths.get(filePath)));
                fileInfo.put("status", "already_exists");
                downloadedFiles.add(fileInfo);
                continue;
            }
            
            StringBuilder errorMessage = new StringBuilder();
            byte[] fileContent = getContentBytes(processedUrl, errorMessage);

            if (fileContent != null) {
                try {
                    Files.createDirectories(Paths.get(fileDir));
                    Files.write(Paths.get(filePath), fileContent);
                    Map<String, Object> fileInfo = new HashMap<>();
                    fileInfo.put("url", processedUrl);
                    fileInfo.put("local_path", filePath);
                    fileInfo.put("size", (long) fileContent.length);
                    fileInfo.put("status", "downloaded");
                    downloadedFiles.add(fileInfo);
                } catch (IOException e) {
                    Map<String, Object> failedInfo = new HashMap<>();
                    failedInfo.put("url", processedUrl);
                    failedInfo.put("error", "Failed to write file to disk: " + e.getMessage());
                    failedDownloads.add(failedInfo);
                }
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
    
    // --- UTILITY AND HELPER METHODS ---

    private static String getContent(String url, StringBuilder errorMessage) {
        byte[] contentBytes = getContentBytes(url, errorMessage);
        return contentBytes != null ? new String(contentBytes) : null;
    }

    private static byte[] getContentBytes(String url, StringBuilder errorMessage) {
        HttpURLConnection connection = null;
        try {
            URL urlObj = new URL(url);
            connection = (HttpURLConnection) urlObj.openConnection();
            connection.setRequestMethod("GET");
            connection.setRequestProperty("User-Agent", USER_AGENT);
            connection.setConnectTimeout(CONNECT_TIMEOUT);
            connection.setReadTimeout(READ_TIMEOUT);
            connection.setInstanceFollowRedirects(true);

            if (connection instanceof HttpsURLConnection) {
                trustAllCertificates();
            }
            
            int responseCode = connection.getResponseCode();
            if (responseCode >= 400) {
                errorMessage.append("HTTP status code: ").append(responseCode);
                return null;
            }

            try (InputStream in = connection.getInputStream();
                 ByteArrayOutputStream out = new ByteArrayOutputStream()) {
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

    private static void trustAllCertificates() {
        try {
            TrustManager[] trustAllCerts = new TrustManager[]{
                new X509TrustManager() {
                    public java.security.cert.X509Certificate[] getAcceptedIssuers() { return null; }
                    public void checkClientTrusted(java.security.cert.X509Certificate[] certs, String authType) {}
                    public void checkServerTrusted(java.security.cert.X509Certificate[] certs, String authType) {}
                }
            };
            SSLContext sc = SSLContext.getInstance("SSL");
            sc.init(null, trustAllCerts, new java.security.SecureRandom());
            HttpsURLConnection.setDefaultSSLSocketFactory(sc.getSocketFactory());
            HttpsURLConnection.setDefaultHostnameVerifier((hostname, session) -> true);
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private static String buildProperUrl(String server, String hash, String filename) {
        server = server.replaceAll("/+$", "");
        return server + "/data/" + hash + "/" + filename;
    }
    
    private static String sanitizeFilename(String filename) {
        return filename.replaceAll("[^a-zA-Z0-9._-]", "_");
    }

    private static String getFilenameWithoutExtension(String url) {
        String path = url.replaceAll("[?#].*$", "").replaceAll(".*/", "");
        int lastDot = path.lastIndexOf('.');
        return (lastDot > 0) ? path.substring(0, lastDot) : (path.isEmpty() ? "file" : path);
    }
    
    private static boolean isValidSha256(String hash) {
        return hash != null && hash.matches("^[a-f0-9]{64}$");
    }

    private static String generateSha256(String input) {
        try {
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            byte[] hashBytes = digest.digest(input.getBytes("UTF-8"));
            StringBuilder hexString = new StringBuilder(2 * hashBytes.length);
            for (byte b : hashBytes) {
                String hex = Integer.toHexString(0xff & b);
                if (hex.length() == 1) {
                    hexString.append('0');
                }
                hexString.append(hex);
            }
            return hexString.toString();
        } catch (Exception e) {
            throw new RuntimeException("Error generating SHA-256 hash", e);
        }
    }

    private static void printDownloadResults(Map<String, List<Map<String, Object>>> results) {
        List<Map<String, Object>> downloaded = results.get("downloaded");
        List<Map<String, Object>> failed = results.get("failed");

        long downloadedCount = downloaded.stream().filter(f -> "downloaded".equals(f.get("status"))).count();
        long existingCount = downloaded.stream().filter(f -> "already_exists".equals(f.get("status"))).count();
        long failedCount = failed.size();
        long totalSize = downloaded.stream().mapToLong(f -> (Long) f.get("size")).sum();

        System.out.println("\n--- Asset Download Statistics ---");
        System.out.println("New files downloaded: " + downloadedCount);
        System.out.println("Files already existed: " + existingCount);
        System.out.println("Failed to download: " + failedCount);
        System.out.println("Total size of local assets: " + (totalSize / 1024) + " KB");

        if (!failed.isEmpty()) {
            System.out.println("\n--- Failed Download Details ---");
            for (Map<String, Object> file : failed) {
                System.out.println("URL: " + file.get("url") + "\n  Error: " + file.get("error"));
            }
        }
        System.out.println("---------------------------------");
    }
}
