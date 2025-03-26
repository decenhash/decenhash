import java.io.File;
import java.io.IOException;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.file.Files;
import java.nio.file.Paths;
import java.util.ArrayList;
import java.util.List;
import java.util.Scanner;

public class ServerMonitor {
    private static final int TIMEOUT_MILLIS = 5000; // 5 seconds timeout
    private static final int SUCCESS_CODE = 200;
    
    public static void main(String[] args) {
        // Path to the servers directory
        String dirPath = "servers";
        
        // Create File object for the directory
        File serversDir = new File(dirPath);
        
        // Check if directory exists
        if (!serversDir.exists() || !serversDir.isDirectory()) {
            System.out.println("Error: '" + dirPath + "' directory not found or is not a directory");
            waitForEnterKey();
            return;
        }
        
        // Get all files in the directory
        File[] files = serversDir.listFiles();
        
        if (files == null || files.length == 0) {
            System.out.println("No files found in '" + dirPath + "' directory");
            waitForEnterKey();
            return;
        }
        
        List<String> deletedFiles = new ArrayList<>();
        
        // Process each file
        for (File file : files) {
            if (file.isFile()) {
                System.out.println("Processing file: " + file.getName());
                
                try {
                    // Read the content of the file (URL)
                    String urlStr = new String(Files.readAllBytes(Paths.get(file.getPath()))).trim();
                    
                    // Check if the URL is online
                    if (!isUrlOnline(urlStr)) {
                        // Delete the file if URL is offline
                        if (file.delete()) {
                            deletedFiles.add(file.getName());
                            System.out.println("Deleted file: " + file.getName() + " (URL was offline: " + urlStr + ")");
                        } else {
                            System.out.println("Failed to delete file: " + file.getName());
                        }
                    } else {
                        System.out.println("URL is online: " + urlStr);
                    }
                } catch (IOException e) {
                    System.out.println("Error reading file: " + file.getName() + " - " + e.getMessage());
                } catch (Exception e) {
                    System.out.println("Error processing URL in file: " + file.getName() + " - " + e.getMessage());
                }
            }
        }
        
        // Summary of operations
        System.out.println("\n--- Operation Summary ---");
        System.out.println("Total files processed: " + files.length);
        System.out.println("Files deleted (offline URLs): " + deletedFiles.size());
        if (!deletedFiles.isEmpty()) {
            System.out.println("Deleted files: " + String.join(", ", deletedFiles));
        }
        
        // Wait for Enter key before exiting
        waitForEnterKey();
    }
    
    /**
     * Waits for the user to press the Enter key before continuing
     */
    private static void waitForEnterKey() {
        System.out.println("\nPress Enter key to exit...");
        Scanner scanner = new Scanner(System.in);
        scanner.nextLine();
        scanner.close();
    }
    
    /**
     * Checks if a URL is online by attempting an HTTP connection
     * 
     * @param urlStr URL to check
     * @return true if the URL is online, false otherwise
     */
    private static boolean isUrlOnline(String urlStr) {
        try {
            URL url = new URL(urlStr);
            HttpURLConnection connection = (HttpURLConnection) url.openConnection();
            connection.setConnectTimeout(TIMEOUT_MILLIS);
            connection.setReadTimeout(TIMEOUT_MILLIS);
            connection.setRequestMethod("HEAD"); // Use HEAD method for efficiency
            
            int responseCode = connection.getResponseCode();
            return responseCode == SUCCESS_CODE;
        } catch (Exception e) {
            System.out.println("Connection error for URL: " + urlStr + " - " + e.getMessage());
            return false;
        }
    }
}