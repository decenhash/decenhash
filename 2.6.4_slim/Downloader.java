import java.io.*;
import java.net.*;
import java.nio.file.*;
import java.security.*;
import java.util.*;
import java.util.regex.*;

public class Downloader {
    private static final String USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36";
    private static final int CONNECT_TIMEOUT = 10000; // 10 seconds
    private static final int READ_TIMEOUT = 30000;    // 30 seconds
    
    public static void main(String[] args) {
        Scanner scanner = new Scanner(System.in);
        System.out.print("Enter Text or SHA256 Hash: ");
        String userText = scanner.nextLine().trim();
        
        String hash;
        if (isValidSha256(userText)) {
            hash = userText.toLowerCase();
            System.out.println("Using provided Hash: " + hash);
        } else {
            hash = generateSha256(userText);
            System.out.println("Generated Hash: " + hash);
            System.out.println("Original Text: " + userText);
        }
        
        String dataServersDir = "data_servers";
        String hashDir = dataServersDir + File.separator + hash;
        String indexFile = hashDir + File.separator + "index.html";
        String dataDir = "data";
        
        try {
            // Create directories if they don't exist
            Files.createDirectories(Paths.get(dataServersDir));
            Files.createDirectories(Paths.get(dataDir));
            
            String htmlContent = null;
            if (Files.exists(Paths.get(indexFile))) {
                System.out.println("Found cached version.");
                htmlContent = new String(Files.readAllBytes(Paths.get(indexFile)));
                
                // Download linked files
                System.out.println("Downloading linked files...");
                Map<String, List<Map<String, Object>>> downloadResults = downloadLinkedFiles(htmlContent, "", hash, dataDir);
                printDownloadResults(downloadResults);
            } else {
                String serversFile = "servers.txt";
                if (!Files.exists(Paths.get(serversFile))) {
                    System.err.println("Error: 'servers.txt' file not found.");
                    return;
                }
                
                List<String> servers = Files.readAllLines(Paths.get(serversFile));
                List<Map<String, String>> foundServers = new ArrayList<>();
                boolean contentSaved = false;
                String successfulServer = "";
                
                for (String server : servers) {
                    server = server.trim();
                    if (server.isEmpty()) continue;
                    
                    String originalServer = server;
                    String urlToCheck = buildProperUrl(server, hash, "index.html");
                    
                    StringBuilder errorMessage = new StringBuilder();
                    String pageContent = getContent(urlToCheck, errorMessage);
                    
                    if (pageContent != null) {
                        Map<String, String> serverInfo = new HashMap<>();
                        serverInfo.put("original", originalServer);
                        serverInfo.put("processed", server);
                        serverInfo.put("full_url", urlToCheck);
                        foundServers.add(serverInfo);
                        
                        if (!contentSaved) {
                            Files.createDirectories(Paths.get(hashDir));
                            successfulServer = server;
                            
                            // Process the content for local viewing
                            String processedContent = processHtmlLinks(pageContent, server, hash);
                            Files.write(Paths.get(indexFile), processedContent.getBytes());
                            contentSaved = true;
                            
                            System.out.println("File found and saved locally.");
                            System.out.println("Downloading linked files...");
                            Map<String, List<Map<String, Object>>> downloadResults = downloadLinkedFiles(pageContent, server, hash, dataDir);
                            printDownloadResults(downloadResults);
                        }
                    }
                }
                
                if (foundServers.isEmpty()) {
                    System.out.println("File not found on any server.");
                } else {
                    System.out.println("\nFile Found on These Servers:");
                    for (Map<String, String> server : foundServers) {
                        System.out.println("Server: " + server.get("original"));
                        System.out.println("Full URL: " + server.get("full_url") + "\n");
                    }
                }
            }
            
            if (Files.exists(Paths.get(indexFile))) {
                System.out.println("\nLocal HTML file: " + Paths.get(indexFile).toAbsolutePath());
            }
            
        } catch (Exception e) {
            System.err.println("Error: " + e.getMessage());
            e.printStackTrace();
        }
    }
    
    private static String getContent(String url, StringBuilder errorMessage) {
        HttpURLConnection connection = null;
        try {
            URL urlObj = new URL(url);
            connection = (HttpURLConnection) urlObj.openConnection();
            connection.setRequestMethod("GET");
            connection.setRequestProperty("User-Agent", USER_AGENT);
            connection.setConnectTimeout(CONNECT_TIMEOUT);
            connection.setReadTimeout(READ_TIMEOUT);
            
            // Skip SSL verification (not recommended for production)
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
            errorMessage.append(e.getMessage());
            return null;
        } finally {
            if (connection != null) {
                connection.disconnect();
            }
        }
    }
    
    private static void trustAllCertificates() {
        // This is insecure and should not be used in production
        javax.net.ssl.HttpsURLConnection.setDefaultHostnameVerifier(
            (hostname, session) -> true);
        javax.net.ssl.TrustManager[] trustAllCerts = new javax.net.ssl.TrustManager[]{
            new javax.net.ssl.X509TrustManager() {
                public java.security.cert.X509Certificate[] getAcceptedIssuers() { return null; }
                public void checkClientTrusted(java.security.cert.X509Certificate[] certs, String authType) {}
                public void checkServerTrusted(java.security.cert.X509Certificate[] certs, String authType) {}
            }
        };
        try {
            javax.net.ssl.SSLContext sc = javax.net.ssl.SSLContext.getInstance("SSL");
            sc.init(null, trustAllCerts, new java.security.SecureRandom());
            javax.net.ssl.HttpsURLConnection.setDefaultSSLSocketFactory(sc.getSocketFactory());
        } catch (Exception e) {
            e.printStackTrace();
        }
    }
    
    private static String buildProperUrl(String server, String hash, String filename) {
        server = server.replaceAll("/+$", "");
        return server + "/data/" + hash + "/" + filename;
    }
    
    private static String sanitizeFilename(String filename) {
        // Remove path traversal and invalid characters
        filename = filename.replaceAll(".*/", "");
        filename = filename.replaceAll("[^a-zA-Z0-9._-]", "_");
        return filename;
    }
    
    private static String getFileExtension(String url) {
        String path = url.replaceAll("[?#].*$", "");
        int lastDot = path.lastIndexOf('.');
        if (lastDot > 0) {
            return path.substring(lastDot + 1);
        }
        return "";
    }
    
    private static String getFilenameWithoutExtension(String url) {
        String path = url.replaceAll("[?#].*$", "");
        path = path.replaceAll(".*/", "");
        int lastDot = path.lastIndexOf('.');
        if (lastDot > 0) {
            return path.substring(0, lastDot);
        }
        return path.isEmpty() ? "file" : path;
    }
    
    private static boolean isValidSha256(String hash) {
        return hash.matches("^[a-f0-9]{64}$");
    }
    
    private static String generateSha256(String input) {
        try {
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            byte[] hashBytes = digest.digest(input.getBytes("UTF-8"));
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
    
    private static Map<String, List<Map<String, Object>>> downloadLinkedFiles(
            String htmlContent, String baseServer, String hash, String dataDir) throws IOException {
        List<Map<String, Object>> downloadedFiles = new ArrayList<>();
        List<Map<String, Object>> failedDownloads = new ArrayList<>();
        
        // Create data directory if it doesn't exist
        Files.createDirectories(Paths.get(dataDir));
        
        // Extract all src and href attributes
        Pattern pattern = Pattern.compile("(href|src)=[\"']([^\"']+)[\"']", Pattern.CASE_INSENSITIVE);
        Matcher matcher = pattern.matcher(htmlContent);
        
        while (matcher.find()) {
            String attribute = matcher.group(1);
            final String originalUrl = matcher.group(2);
            
            // Skip data URLs, javascript, mailto, etc.
            if (originalUrl.matches("^(data:|javascript:|mailto:|#).*")) {
                continue;
            }
            
            // Skip HTML files for href attributes (likely navigation links)
            if (attribute.equalsIgnoreCase("href") && originalUrl.matches(".*\\.html?$")) {
                continue;
            }
            
            // Convert relative URLs to absolute
            String processedUrl = originalUrl;
            if (!originalUrl.matches("^https?://")) {
                baseServer = baseServer.replaceAll("/+$", "");
                if (originalUrl.startsWith("/")) {
                    processedUrl = baseServer + originalUrl;
                } else {
                    processedUrl = baseServer + "/data/" + hash + "/" + originalUrl;
                }
            }
            
            // Get filename and create subdirectory
            String filename = sanitizeFilename(processedUrl.replaceAll(".*/", ""));
            if (filename.isEmpty() || filename.equals(".")) {
                filename = "index.html";
            }
            
            String filenameWithoutExt = getFilenameWithoutExtension(processedUrl);
            String fileDir = dataDir + File.separator + sanitizeFilename(filenameWithoutExt);
            
            Files.createDirectories(Paths.get(fileDir));
            
            String filePath = fileDir + File.separator + filename;
            
            // Skip if already downloaded
            final String finalUrl = processedUrl;
            boolean alreadyProcessed = downloadedFiles.stream()
                .anyMatch(f -> f.get("url").equals(finalUrl));
            if (alreadyProcessed) {
                continue;
            }
            
            // Check if file already exists
            if (Files.exists(Paths.get(filePath))) {
                Map<String, Object> fileInfo = new HashMap<>();
                fileInfo.put("url", processedUrl);
                fileInfo.put("local_path", filePath);
                fileInfo.put("size", Files.size(Paths.get(filePath)));
                fileInfo.put("status", "already_exists");
                downloadedFiles.add(fileInfo);
                continue;
            }
            
            // Download the file
            StringBuilder errorMessage = new StringBuilder();
            byte[] fileContent = getContentBytes(processedUrl, errorMessage);
            
            if (fileContent != null) {
                Files.write(Paths.get(filePath), fileContent);
                
                Map<String, Object> fileInfo = new HashMap<>();
                fileInfo.put("url", processedUrl);
                fileInfo.put("local_path", filePath);
                fileInfo.put("size", fileContent.length);
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
    
    private static byte[] getContentBytes(String url, StringBuilder errorMessage) {
        HttpURLConnection connection = null;
        try {
            URL urlObj = new URL(url);
            connection = (HttpURLConnection) urlObj.openConnection();
            connection.setRequestMethod("GET");
            connection.setRequestProperty("User-Agent", USER_AGENT);
            connection.setConnectTimeout(CONNECT_TIMEOUT);
            connection.setReadTimeout(READ_TIMEOUT);
            
            // Skip SSL verification (not recommended for production)
            if (url.startsWith("https")) {
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
            errorMessage.append(e.getMessage());
            return null;
        } finally {
            if (connection != null) {
                connection.disconnect();
            }
        }
    }
    
    private static String processHtmlLinks(String content, String server, String hash) {
        Pattern pattern = Pattern.compile("(href|src)=[\"'](?!https?://|//|data:)([^\"']+)[\"']", Pattern.CASE_INSENSITIVE);
        Matcher matcher = pattern.matcher(content);
        StringBuffer sb = new StringBuffer();
        
        while (matcher.find()) {
            String url = matcher.group(2);
            server = server.replaceAll("/+$", "");
            
            String absoluteUrl;
            if (url.startsWith("/")) {
                absoluteUrl = server + url;
            } else {
                absoluteUrl = server + "/data/" + hash + "/" + url;
            }
            
            matcher.appendReplacement(sb, matcher.group(1) + "=\"" + absoluteUrl + "\"");
        }
        matcher.appendTail(sb);
        return sb.toString();
    }
    
    private static void printDownloadResults(Map<String, List<Map<String, Object>>> results) {
        List<Map<String, Object>> downloaded = results.get("downloaded");
        List<Map<String, Object>> failed = results.get("failed");
        
        long downloadedCount = downloaded.stream()
            .filter(f -> f.get("status").equals("downloaded"))
            .count();
        long existingCount = downloaded.stream()
            .filter(f -> f.get("status").equals("already_exists"))
            .count();
        long failedCount = failed.size();
        
        long totalSize = downloaded.stream()
            .mapToLong(f -> (Long) f.get("size"))
            .sum();
        
        System.out.println("\nDownload Statistics:");
        System.out.println("Downloaded: " + downloadedCount);
        System.out.println("Already Existed: " + existingCount);
        System.out.println("Failed: " + failedCount);
        System.out.println("Total Size: " + (totalSize / 1024) + " KB");
        
        if (!downloaded.isEmpty()) {
            System.out.println("\nDownloaded Files:");
            for (Map<String, Object> file : downloaded) {
                String status = (String) file.get("status");
                String statusMsg = status.equals("downloaded") ? "[Downloaded]" : "[Already Existed]";
                System.out.println(statusMsg + " " + file.get("url"));
                System.out.println("  -> " + file.get("local_path") + " (" + (Long) file.get("size") + " bytes)");
            }
        }
        
        if (!failed.isEmpty()) {
            System.out.println("\nFailed Downloads:");
            for (Map<String, Object> file : failed) {
                System.out.println(file.get("url") + " - Error: " + file.get("error"));
            }
        }
    }
}