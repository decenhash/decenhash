import java.io.*;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.file.*;
import java.util.*;

public class FileChecker {
    private static final String DATA_DIR = "data";
    private static final String RESULTS_FILE = "results.txt";
    private static final int TIMEOUT_MS = 5000;

    public static void main(String[] args) {
        Scanner scanner = new Scanner(System.in);
        
        // Get server list from user
        System.out.println("Enter server URLs (comma separated):");
        String[] servers = scanner.nextLine().split(",");
        
        // Trim whitespace from server URLs
        for (int i = 0; i < servers.length; i++) {
            servers[i] = servers[i].trim();
        }
        
        try {
            // Get all files in data directory and subdirectories
            List<String> localFiles = getAllFilesInDataDirectory();
            
            if (localFiles.isEmpty()) {
                System.out.println("No files found in " + DATA_DIR + " directory.");
                return;
            }
            
            // Check each server for matching files
            Map<String, List<String>> serverMatches = new HashMap<>();
            for (String server : servers) {
                List<String> matchingFiles = checkServerForFiles(server, localFiles);
                if (!matchingFiles.isEmpty()) {
                    serverMatches.put(server, matchingFiles);
                }
            }
            
            // Save results
            saveResults(serverMatches, localFiles);
            
            System.out.println("Results saved to " + RESULTS_FILE);
        } catch (IOException e) {
            System.err.println("Error: " + e.getMessage());
        } finally {
            scanner.close();
        }
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
    
    private static List<String> checkServerForFiles(String server, List<String> files) {
        List<String> matchingFiles = new ArrayList<>();
        
        System.out.println("Checking server: " + server);
        
        for (String file : files) {
            String fileUrl = server + "/" + file;
            if (checkFileExists(fileUrl)) {
                matchingFiles.add(file);
            }
        }
        
        return matchingFiles;
    }
    
    private static boolean checkFileExists(String fileUrl) {
        try {
            URL url = new URL(fileUrl);
            HttpURLConnection connection = (HttpURLConnection) url.openConnection();
            connection.setRequestMethod("HEAD");
            connection.setConnectTimeout(TIMEOUT_MS);
            connection.setReadTimeout(TIMEOUT_MS);
            
            int responseCode = connection.getResponseCode();
            return responseCode == HttpURLConnection.HTTP_OK;
        } catch (IOException e) {
            return false;
        }
    }
    
    private static void saveResults(Map<String, List<String>> serverMatches, List<String> allLocalFiles) throws IOException {
        try (PrintWriter writer = new PrintWriter(RESULTS_FILE)) {
            writer.println("File Existence Check Results");
            writer.println("============================");
            writer.println();
            
            writer.println("Local files checked (" + allLocalFiles.size() + "):");
            for (String file : allLocalFiles) {
                writer.println("- " + file);
            }
            writer.println();
            
            writer.println("Servers with all matching files (" + serverMatches.size() + "):");
            writer.println();
            
            for (Map.Entry<String, List<String>> entry : serverMatches.entrySet()) {
                String server = entry.getKey();
                List<String> matchingFiles = entry.getValue();
                
                if (matchingFiles.size() == allLocalFiles.size()) {
                    writer.println(server + " - ALL FILES MATCH");
                } else {
                    writer.println(server + " - " + matchingFiles.size() + "/" + allLocalFiles.size() + " files match");
                }
            }
            
            // Detailed report
            writer.println();
            writer.println("Detailed Report:");
            for (String file : allLocalFiles) {
                writer.println();
                writer.println("File: " + file);
                writer.println("Available on:");
                
                boolean found = false;
                for (Map.Entry<String, List<String>> entry : serverMatches.entrySet()) {
                    if (entry.getValue().contains(file)) {
                        writer.println("- " + entry.getKey());
                        found = true;
                    }
                }
                
                if (!found) {
                    writer.println("- No servers have this file");
                }
            }
        }
    }
}