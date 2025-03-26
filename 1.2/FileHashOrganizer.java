import java.io.File;
import java.io.FileInputStream;
import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.nio.file.StandardCopyOption;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;

public class FileHashOrganizer {
    
    public static void main(String[] args) {
        String sourceDir = "hashed_files";
        String dataDir = "data";
        processFiles(sourceDir, dataDir);
    }
    
    private static void processFiles(String dirPath, String dataDir) {
        File directory = new File(dirPath);
        
        if (!directory.exists() || !directory.isDirectory()) {
            System.err.println("Error: Directory '" + dirPath + "' does not exist or is not a directory");
            return;
        }
        
        // Create data directory if it doesn't exist
        File dataDirFile = new File(dataDir);
        if (!dataDirFile.exists() && !dataDirFile.mkdirs()) {
            System.err.println("Error: Failed to create data directory: " + dataDir);
            return;
        }
        
        File[] files = directory.listFiles();
        if (files == null || files.length == 0) {
            System.out.println("No files found in directory: " + dirPath);
            return;
        }
        
        for (File file : files) {
            if (file.isFile()) {
                try {
                    // Get the file hash
                    String fileHash = calculateSHA256(file);
                    System.out.println("File: " + file.getName() + " | Hash: " + fileHash);
                    
                    // Create destination directory based on hash inside the data directory
                    File destDir = new File(dataDir + File.separator + fileHash);
                    if (!destDir.exists() && !destDir.mkdirs()) {
                        System.err.println("Failed to create directory: " + destDir.getPath());
                        continue;
                    }
                    
                    // Get the file extension
                    String fileName = file.getName();
                    String extension = "";
                    int dotIndex = fileName.lastIndexOf('.');
                    if (dotIndex > 0) {
                        extension = fileName.substring(dotIndex);
                    }
                    
                    // Move and rename file
                    Path source = file.toPath();
                    Path destination = Paths.get(destDir.getPath(), fileHash + extension);
                    Files.move(source, destination, StandardCopyOption.REPLACE_EXISTING);
                    
                    System.out.println("Moved to: " + destination);
                } catch (Exception e) {
                    System.err.println("Error processing file " + file.getName() + ": " + e.getMessage());
                }
            }
        }
    }
    
    private static String calculateSHA256(File file) throws NoSuchAlgorithmException, IOException {
        MessageDigest digest = MessageDigest.getInstance("SHA-256");
        FileInputStream fis = new FileInputStream(file);
        
        byte[] byteArray = new byte[1024];
        int bytesRead;
        
        while ((bytesRead = fis.read(byteArray)) != -1) {
            digest.update(byteArray, 0, bytesRead);
        }
        
        fis.close();
        
        byte[] bytes = digest.digest();
        
        // Convert to hex string
        StringBuilder sb = new StringBuilder();
        for (byte b : bytes) {
            sb.append(String.format("%02x", b));
        }
        
        return sb.toString();
    }
}