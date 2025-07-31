import java.io.*;
import java.nio.file.*;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.util.regex.Pattern;

public class DataBuilder {
    
    // Configuration
    private static final String UPLOAD_DIR_BASE = "data";
    private static final String SOURCE_DIR = "categories";
    private static final Pattern SHA256_REGEX = Pattern.compile("^[a-f0-9]{64}$");
    
    public static void main(String[] args) {
        DataBuilder processor = new DataBuilder();
        processor.processFiles();
    }
    
    /**
     * Check if input is a valid SHA256 hash, if not return its SHA256 hash
     */
    private String checkSha256(String input) {
        if (SHA256_REGEX.matcher(input).matches()) {
            return input; // Input is a valid SHA256 hash
        } else {
            return calculateSha256(input); // Input is not a valid SHA256 hash, return its hash
        }
    }
    
    /**
     * Calculate SHA256 hash of a string
     */
    private String calculateSha256(String input) {
        try {
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            byte[] hash = digest.digest(input.getBytes());
            StringBuilder hexString = new StringBuilder();
            
            for (byte b : hash) {
                String hex = Integer.toHexString(0xff & b);
                if (hex.length() == 1) {
                    hexString.append('0');
                }
                hexString.append(hex);
            }
            
            return hexString.toString();
        } catch (NoSuchAlgorithmException e) {
            throw new RuntimeException("SHA-256 algorithm not available", e);
        }
    }
    
    /**
     * Calculate SHA256 hash of file content
     */
    private String calculateFileSha256(byte[] content) {
        try {
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            byte[] hash = digest.digest(content);
            StringBuilder hexString = new StringBuilder();
            
            for (byte b : hash) {
                String hex = Integer.toHexString(0xff & b);
                if (hex.length() == 1) {
                    hexString.append('0');
                }
                hexString.append(hex);
            }
            
            return hexString.toString();
        } catch (NoSuchAlgorithmException e) {
            throw new RuntimeException("SHA-256 algorithm not available", e);
        }
    }
    
    /**
     * Get file extension from filename
     */
    private String getFileExtension(String filename) {
        int lastDot = filename.lastIndexOf('.');
        if (lastDot == -1 || lastDot == filename.length() - 1) {
            return "";
        }
        return filename.substring(lastDot + 1);
    }
    
    /**
     * HTML escape a string
     */
    private String htmlEscape(String input) {
        return input.replace("&", "&amp;")
                   .replace("<", "&lt;")
                   .replace(">", "&gt;")
                   .replace("\"", "&quot;")
                   .replace("'", "&#x27;");
    }
    
    /**
     * Create directory if it doesn't exist
     */
    private void createDirectoryIfNotExists(Path dir) throws IOException {
        if (!Files.exists(dir)) {
            Files.createDirectories(dir);
        }
    }
    
    /**
     * Main processing method
     */
    public void processFiles() {
        try {
            Path sourceDirectory = Paths.get(SOURCE_DIR);
            
            if (!Files.exists(sourceDirectory) || !Files.isDirectory(sourceDirectory)) {
                System.err.println("<p class='error'>Source directory '" + SOURCE_DIR + "' not found.</p>");
                return;
            }
            
            // Get all subdirectories
            File[] subdirectories = sourceDirectory.toFile().listFiles(File::isDirectory);
            
            if (subdirectories == null || subdirectories.length == 0) {
                System.err.println("<p class='error'>No subdirectories found in the '" + SOURCE_DIR + "' directory.</p>");
                return;
            }
            
            // Process each subdirectory
            for (File subdirectory : subdirectories) {
                String categoryText = subdirectory.getName().toLowerCase();
                
                // Get all files in the subdirectory (excluding directories)
                File[] files = subdirectory.listFiles(File::isFile);
                
                if (files == null || files.length == 0) {
                    System.out.println("<p class='notice'>No files found in subdirectory: " + htmlEscape(categoryText) + "</p>");
                    continue;
                }
                
                // Process each file in the subdirectory
                for (File file : files) {
                    processFile(file, categoryText);
                }
            }
            
            System.out.println("<p class='success'>All files processed successfully!</p>");
            
        } catch (Exception e) {
            System.err.println("<p class='error'>Error processing files: " + e.getMessage() + "</p>");
            e.printStackTrace();
        }
    }
    
    /**
     * Process a single file
     */
    private void processFile(File file, String categoryText) {
        try {
            String originalFileName = file.getName();
            String fileExtension = getFileExtension(originalFileName);
            
            if ("php".equalsIgnoreCase(fileExtension)) {
                System.out.println("<p class='error'>Skipping PHP file: " + htmlEscape(originalFileName) + "</p>");
                return;
            }
            
            // Read file content
            byte[] fileContent = Files.readAllBytes(file.toPath());
            
            // Calculate SHA256 hashes
            String fileHash = calculateFileSha256(fileContent);
            String categoryHash = checkSha256(categoryText);
            String fileNameWithExtension = fileHash + "." + fileExtension;
            
            // Construct directory paths
            Path fileUploadDir = Paths.get(UPLOAD_DIR_BASE, fileHash);
            Path categoryDir = Paths.get(UPLOAD_DIR_BASE, categoryHash);
            Path uploadDirBasePath = Paths.get(UPLOAD_DIR_BASE);
            
            // Create directories if they don't exist
            createDirectoryIfNotExists(uploadDirBasePath);
            createDirectoryIfNotExists(fileUploadDir);
            createDirectoryIfNotExists(categoryDir);
            
            // Save the content
            Path destinationFilePath = fileUploadDir.resolve(fileNameWithExtension);
            
            if (Files.exists(destinationFilePath)) {
                System.out.println("<p class='notice'>File already exists, skipping: " + htmlEscape(originalFileName) + "</p>");
                return;
            }
            
            Files.copy(file.toPath(), destinationFilePath);
            
            // Create empty file in category folder
            Path categoryFilePath = categoryDir.resolve(fileNameWithExtension);
            Files.createFile(categoryFilePath);
            
            String contentHead = "<link rel='stylesheet' href='../../default.css'><script src='../../default.js'></script><script src='../../ads.js'></script><div id='ads' name='ads' class='ads'></div><div id='default' name='default' class='default'></div>";
            
            // Handle index.html inside file hash folder
            Path indexPathFileFolder = fileUploadDir.resolve("index.html");
            if (!Files.exists(indexPathFileFolder)) {
                Files.write(indexPathFileFolder, contentHead.getBytes());
            }
            
            String linkReply = "<a href=\"../../index.php?reply=" + htmlEscape(fileHash) + "\">[ Reply ]</a> ";
            String linkToHash = linkReply + "<a href=\"../" + htmlEscape(fileHash) + "/index.html\">[ Open ]</a> ";
            String linkToFileFolderIndex = linkToHash + "<a href=\"" + htmlEscape(fileNameWithExtension) + "\">" + htmlEscape(originalFileName) + "</a><br>";
            
            String indexContentFileFolder = new String(Files.readAllBytes(indexPathFileFolder));
            if (!indexContentFileFolder.contains(linkToFileFolderIndex)) {
                Files.write(indexPathFileFolder, (indexContentFileFolder + linkToFileFolderIndex).getBytes());
            }
            
            // Handle index.html inside category folder
            Path indexPathCategoryFolder = categoryDir.resolve("index.html");
            if (!Files.exists(indexPathCategoryFolder)) {
                Files.write(indexPathCategoryFolder, contentHead.getBytes());
            }
            
            String relativePathToFile = "../" + fileHash + "/" + fileNameWithExtension;
            String categoryReply = "<a href=\"../../index.php?reply=" + htmlEscape(fileHash) + "\">[ Reply ]</a> ";
            String linkToHashCategory = categoryReply + "<a href=\"../" + htmlEscape(fileHash) + "/index.html\">[ Open ]</a> ";
            String linkToCategoryFolderIndex = linkToHashCategory + "<a href=\"" + htmlEscape(relativePathToFile) + "\">" + htmlEscape(originalFileName) + "</a><br>";
            
            String indexContentCategoryFolder = new String(Files.readAllBytes(indexPathCategoryFolder));
            if (!indexContentCategoryFolder.contains(linkToCategoryFolderIndex)) {
                Files.write(indexPathCategoryFolder, (indexContentCategoryFolder + linkToCategoryFolderIndex).getBytes());
            }
            
            System.out.println("<p class='success'>Processed file: " + htmlEscape(originalFileName) + " in category: " + htmlEscape(categoryText) + "</p>");
            
        } catch (IOException e) {
            System.err.println("<p class='error'>Error processing file " + htmlEscape(file.getName()) + ": " + e.getMessage() + "</p>");
        }
    }
}