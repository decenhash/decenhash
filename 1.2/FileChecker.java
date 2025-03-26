import java.io.*;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.file.*;
import java.security.MessageDigest;
import java.util.*;
import java.util.stream.Collectors;

public class FileHashChecker {
    private static final String DATA_DIR = "data";
    private static final String SERVERS_DIR = "servers";
    private static final String RESULTS_FILE = "results.txt";
    private static final int TIMEOUT_MS = 5000;
    private static final int BUFFER_SIZE = 8192;

    public static void main(String[] args) {
        try {
            // Get server URLs from files in servers directory
            List<String> servers = getServerUrlsFromDirectory();
            
            if (servers.isEmpty()) {
                System.out.println("No server URLs found in " + SERVERS_DIR + " directory.");
                return;
            }
            
            System.out.println("Found " + servers.size() + " servers to check:");
            servers.forEach(System.out::println);
            
            // Get all files in data directory and subdirectories
            List<String> localFiles = getAllFilesInDataDirectory();
            
            if (localFiles.isEmpty()) {
                System.out.println("No files found in " + DATA_DIR + " directory.");
                return;
            }
            
            // Check each server for matching files with valid hashes
            Map<String, List<String>> serverMatches = new HashMap<>();
            for (String server : servers) {
                List<String> matchingFiles = checkServerForValidFiles(server, localFiles);
                if (!matchingFiles.isEmpty()) {
                    serverMatches.put(server, matchingFiles);
                }
            }
            
            // Save results
            saveResults(serverMatches, localFiles);
            
            System.out.println("Results saved to " + RESULTS_FILE);
        } catch (Exception e) {
            System.err.println("Error: " + e.getMessage());
            e.printStackTrace();
        }
    }
    
    private static List<String> getServerUrlsFromDirectory() throws IOException {
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
                    servers.addAll(lines.stream()
                        .map(String::trim)
                        .filter(line -> !line.isEmpty() && !line.startsWith("#"))
                        .collect(Collectors.toList()));
                } catch (IOException e) {
                    System.err.println("Warning: Could not read file " + path + ": " + e.getMessage());
                }
            });
        
        return servers;
    }
    
    private static List<String> getAllFilesInDataDirectory() throws IOException {
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
    
    private static List<String> checkServerForValidFiles(String server, List<String> files) {
        List<String> validFiles = new ArrayList<>();
        
        System.out.println("Checking server: " + server);
        
        for (String file : files) {
            String fileUrl = server + (server.endsWith("/") ? "" : "/") + file;
            try {
                // Get the expected hash from filename (assuming filename is the hash)
                String expectedHash = getHashFromFilename(file);
                
                if (expectedHash == null) {
                    System.err.println("Skipping file " + file + " - filename doesn't appear to be a SHA-256 hash");
                    continue;
                }
                
                // Download file and calculate hash
                String actualHash = calculateRemoteFileHash(fileUrl);
                
                if (actualHash != null && actualHash.equalsIgnoreCase(expectedHash)) {
                    validFiles.add(file);
                    System.out.println("Valid file found: " + file);
                } else {
                    System.out.println("Invalid hash for file: " + file + 
                                     " (Expected: " + expectedHash + ", Actual: " + actualHash + ")");
                }
            } catch (Exception e) {
                System.err.println("Error checking file " + fileUrl + ": " + e.getMessage());
            }
        }
        
        return validFiles;
    }
    
    private static String getHashFromFilename(String filePath) {
        // Extract filename without extension
        String filename = Paths.get(filePath).getFileName().toString();
        int dotIndex = filename.lastIndexOf('.');
        if (dotIndex > 0) {
            filename = filename.substring(0, dotIndex);
        }
        
        // Check if filename looks like a SHA-256 hash (64 hex characters)
        if (filename.matches("[a-fA-F0-9]{64}")) {
            return filename.toLowerCase();
        }
        return null;
    }
    
    private static String calculateRemoteFileHash(String fileUrl) throws IOException {
        InputStream inputStream = null;
        try {
            URL url = new URL(fileUrl);
            HttpURLConnection connection = (HttpURLConnection) url.openConnection();
            connection.setRequestMethod("GET");
            connection.setConnectTimeout(TIMEOUT_MS);
            connection.setReadTimeout(TIMEOUT_MS * 5); // Longer timeout for downloads
            
            int responseCode = connection.getResponseCode();
            if (responseCode != HttpURLConnection.HTTP_OK) {
                throw new IOException("HTTP error code: " + responseCode);
            }
            
            inputStream = connection.getInputStream();
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            byte[] buffer = new byte[BUFFER_SIZE];
            int bytesRead;
            
            while ((bytesRead = inputStream.read(buffer)) != -1) {
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
                    System.err.println("Warning: Error closing stream: " + e.getMessage());
                }
            }
        }
    }
    
    private static String bytesToHex(byte[] bytes) {
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
    
    private static void saveResults(Map<String, List<String>> serverMatches, List<String> allLocalFiles) throws IOException {
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
            
            // Detailed report
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
}