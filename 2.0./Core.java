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
import java.util.stream.Collectors;

public class Core {
    
    public static void main(String[] args) {
        Scanner scanner = new Scanner(System.in);
        
        // Maps to track statistics
        Map<String, Integer> serverVerifiedFilesCount = new HashMap<>();
        Map<String, Set<String>> fileFoundOnServers = new HashMap<>();
        Map<String, Boolean> fileVerificationStatus = new HashMap<>();
        
        try {
            // Get the list of files from the servers directory
            List<String> serversList = listFilesAndGetContents("servers");
            System.out.println("Found " + serversList.size() + " server files in directory.");
            
            // Ensure data directory exists
            File dataDir = new File("data");
            if (!dataDir.exists()) {
                dataDir.mkdir();
                System.out.println("Created 'data' directory.");
            }
            
            // Read the filenames from files.txt
            List<String> filenames = Files.readAllLines(Paths.get("files.txt"));
            System.out.println("Found " + filenames.size() + " filenames to process in files.txt.");
            
            boolean continueProcessing = true;
            
            while (continueProcessing) {
                // Reset statistics for each run
                serverVerifiedFilesCount.clear();
                fileFoundOnServers.clear();
                fileVerificationStatus.clear();
                
                // Process each filename from the files.txt
                for (int i = 0; i < filenames.size(); i++) {
                    String filename = filenames.get(i).trim();
                    if (filename.isEmpty()) continue;
                    
                    System.out.println("\nProcessing file " + (i+1) + "/" + filenames.size() + ": " + filename);
                    
                    // Split the input by '.'
                    String[] inputParts = filename.split("\\.");
                    if (inputParts.length < 2) {
                        System.out.println("Invalid filename format. Skipping: " + filename);
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
                            System.out.println("Trying to connect to: " + urlString);
                            
                            // Try to connect to the URL
                            if (checkUrlExists(urlString)) {
                                fileFound = true;
                                System.out.println("? Connection successful!");
                                
                                // Add server to the list of servers containing this file
                                fileFoundOnServers.get(filename).add(serverAddress);
                                
                                // Download the file content
                                byte[] fileBytes = downloadFile(urlString);
                                
                                // Calculate its SHA-256 hash
                                String calculatedHash = calculateSHA256(fileBytes);
                                
                                // Check if the hash equals the first part of the filename
                                boolean verificationSuccessful = calculatedHash.equals(fileHash);
                                fileVerificationStatus.put(filename, verificationSuccessful);
                                
                                if (verificationSuccessful) {
                                    System.out.println("? Hash verification successful!");
                                    System.out.println("  SHA-256: " + calculatedHash);
                                    
                                    // Increment the verified files count for this server
                                    serverVerifiedFilesCount.put(serverAddress, 
                                            serverVerifiedFilesCount.getOrDefault(serverAddress, 0) + 1);
                                    
                                    // Create a subfolder with the hash in the data directory
                                    File subDir = new File("data/" + fileHash);
                                    if (!subDir.exists()) {
                                        subDir.mkdir();
                                        System.out.println("  Created directory: data/" + fileHash);
                                    }
                                    
                                    // Save the file if it doesn't already exist
                                    File downloadedFile = new File(subDir, filename);
                                    if (!downloadedFile.exists()) {
                                        try (FileOutputStream fos = new FileOutputStream(downloadedFile)) {
                                            fos.write(fileBytes);
                                            System.out.println("  Saved file: " + downloadedFile.getPath());
                                        }
                                    } else {
                                        System.out.println("  File already exists: " + downloadedFile.getPath());
                                    }
                                    
                                    // Calculate SHA-256 hash of the server address
                                    String serverAddressHash = calculateSHA256(
                                            serverAddress.getBytes(StandardCharsets.UTF_8));
                                    
                                    // Save the server address to a file named with its hash
                                    File serverFile = new File(subDir, serverAddressHash);
                                    if (!serverFile.exists()) {
                                        Files.write(serverFile.toPath(), 
                                                serverAddress.getBytes(StandardCharsets.UTF_8));
                                        System.out.println("  Saved server address to: " + serverFile.getPath());
                                    } else {
                                        System.out.println("  Server address file already exists: " + 
                                                serverFile.getPath());
                                    }
                                } else {
                                    System.out.println("? Hash verification failed!");
                                    System.out.println("  Expected: " + fileHash);
                                    System.out.println("  Actual: " + calculatedHash);
                                }
                                
                                // We break here to avoid downloading the same file from multiple servers
                                // This is different from the original logic which tried all servers
                                break;
                            } else {
                                System.out.println("? Connection failed!");
                            }
                        }
                    }
                    
                    if (!fileFound) {
                        System.out.println("File not found on any server: " + filename);
                    }
                }
                
                // Display statistics after processing all files
                displayStatistics(serverVerifiedFilesCount, fileFoundOnServers, fileVerificationStatus);
                
                // Ask if user wants to continue
                System.out.print("\nAll files processed. Press Enter to run again or type 'exit' to quit: ");
                String userInput = scanner.nextLine();
                
                if (userInput.trim().equalsIgnoreCase("exit")) {
                    continueProcessing = false;
                    System.out.println("Exiting program. Goodbye!");
                } else {
                    System.out.println("Reprocessing files...");
                    // Refresh the file list in case it was modified
                    filenames = Files.readAllLines(Paths.get("files.txt"));
                    System.out.println("Found " + filenames.size() + " filenames to process in files.txt.");
                }
            }
            
        } catch (IOException | NoSuchAlgorithmException e) {
            System.err.println("Error: " + e.getMessage());
            e.printStackTrace();
        } finally {
            scanner.close();
        }
    }
    
    /**
     * Displays statistics about verified files and servers
     */
    private static void displayStatistics(
            Map<String, Integer> serverVerifiedFilesCount,
            Map<String, Set<String>> fileFoundOnServers,
            Map<String, Boolean> fileVerificationStatus) {
        
        System.out.println("\n==== SERVER STATISTICS ====");
        
        // Sort servers by number of verified files (descending)
        List<Map.Entry<String, Integer>> sortedServers = serverVerifiedFilesCount.entrySet()
                .stream()
                .sorted(Map.Entry.<String, Integer>comparingByValue().reversed())
                .collect(Collectors.toList());
        
        // Display servers hosting most verified files
        if (sortedServers.isEmpty()) {
            System.out.println("No verified files found on any server.");
        } else {
            System.out.println("Servers hosting verified files (in descending order):");
            for (int i = 0; i < sortedServers.size(); i++) {
                Map.Entry<String, Integer> entry = sortedServers.get(i);
                System.out.printf("%d. %s - %d verified files\n", 
                        i + 1, entry.getKey(), entry.getValue());
            }
        }
        
        System.out.println("\n==== FILE STATISTICS ====");
        
        // Calculate file frequency and filter for verified files
        Map<String, Integer> fileFrequency = new HashMap<>();
        for (Map.Entry<String, Set<String>> entry : fileFoundOnServers.entrySet()) {
            String filename = entry.getKey();
            // Only count verified files
            if (fileVerificationStatus.getOrDefault(filename, false)) {
                String fileHash = filename.split("\\.")[0]; // Get hash part
                fileFrequency.put(fileHash, entry.getValue().size());
            }
        }
        
        // Sort files by frequency (descending)
        List<Map.Entry<String, Integer>> sortedFiles = fileFrequency.entrySet()
                .stream()
                .sorted(Map.Entry.<String, Integer>comparingByValue().reversed())
                .collect(Collectors.toList());
        
        // Display files most found across servers
        if (sortedFiles.isEmpty()) {
            System.out.println("No verified files found.");
        } else {
            System.out.println("Verified file hashes by frequency (in descending order):");
            for (int i = 0; i < sortedFiles.size(); i++) {
                Map.Entry<String, Integer> entry = sortedFiles.get(i);
                System.out.printf("%d. File hash: %s - Found on %d servers\n", 
                        i + 1, entry.getKey(), entry.getValue());
            }
        }
    }
    
    /**
     * Lists all files in the given directory and returns their contents as a list of strings
     */
    private static List<String> listFilesAndGetContents(String directoryPath) throws IOException {
        List<String> contents = new ArrayList<>();
        File directory = new File(directoryPath);
        
        if (!directory.exists() || !directory.isDirectory()) {
            System.err.println("Directory '" + directoryPath + "' does not exist or is not a directory");
            return contents;
        }
        
        File[] files = directory.listFiles();
        if (files != null) {
            for (File file : files) {
                if (file.isFile()) {
                    String content = new String(Files.readAllBytes(Paths.get(file.getPath())));
                    contents.add(content);
                    System.out.println("Loaded server file: " + file.getName());
                }
            }
        }
        
        return contents;
    }
    
    /**
     * Checks if a URL exists by establishing a connection
     */
    private static boolean checkUrlExists(String urlString) {
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
    private static byte[] downloadFile(String urlString) throws IOException {
        URL url = new URL(urlString);
        return url.openStream().readAllBytes();
    }
    
    /**
     * Calculates the SHA-256 hash of a byte array
     */
    private static String calculateSHA256(byte[] bytes) throws NoSuchAlgorithmException {
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
}