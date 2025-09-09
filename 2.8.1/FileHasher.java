import java.io.File;
import java.io.FileInputStream;
import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.util.Formatter;
import java.util.Optional;

public class FileHasher {

    private static final String DIRECTORY_NAME = "files";

    public static void main(String[] args) {
        // 1. Get a reference to the directory
        File directory = new File(DIRECTORY_NAME);

        // 2. Check if the directory exists, if not, create it.
        if (!directory.exists()) {
            System.out.println("Directory '" + DIRECTORY_NAME + "' not found. Creating it.");
            if (directory.mkdirs()) {
                System.out.println("Directory created. Please add files to it and run the program again.");
            } else {
                System.err.println("Error: Could not create directory '" + DIRECTORY_NAME + "'.");
            }
            return;
        }

        if (!directory.isDirectory()) {
            System.err.println("Error: '" + DIRECTORY_NAME + "' is not a directory.");
            return;
        }

        // 3. Get the list of files to process.
        File[] files = directory.listFiles();
        if (files == null || files.length == 0) {
            System.out.println("The directory '" + DIRECTORY_NAME + "' is empty. Nothing to do.");
            return;
        }

        System.out.println("Processing " + files.length + " files in '" + DIRECTORY_NAME + "' directory...");

        // 4. Iterate over each file
        for (File currentFile : files) {
            // Skip subdirectories
            if (currentFile.isDirectory()) {
                continue;
            }

            try {
                // 5. Calculate the SHA-256 hash of the file
                String hash = calculateSHA256(currentFile);
                if (hash == null) {
                    System.err.println("Could not generate hash for: " + currentFile.getName());
                    continue;
                }

                // 6. Get the file's extension
                Optional<String> extensionOpt = getFileExtension(currentFile.getName());
                String extension = extensionOpt.orElse(""); // Use empty string if no extension
                String newFileName = hash + extension;

                Path sourcePath = currentFile.toPath();
                Path targetPath = Paths.get(DIRECTORY_NAME, newFileName);

                // 7. Check if a file with the new name already exists
                if (Files.exists(targetPath)) {
                    // If the current file is not already the target file (i.e., it's a true duplicate)
                    if (!Files.isSameFile(sourcePath, targetPath)) {
                        System.out.println("Duplicate found. Deleting: " + currentFile.getName() + " (Hash: " + hash + ")");
                        Files.delete(sourcePath);
                    } else {
                        System.out.println("File is already correctly named: " + currentFile.getName());
                    }
                } else {
                    // 8. If no file with the hash exists, rename the current file
                    System.out.println("Renaming: " + currentFile.getName() + " -> " + newFileName);
                    Files.move(sourcePath, targetPath);
                }

            } catch (IOException e) {
                System.err.println("An I/O error occurred while processing " + currentFile.getName() + ": " + e.getMessage());
            } catch (Exception e) {
                System.err.println("An unexpected error occurred for " + currentFile.getName() + ": " + e.getMessage());
            }
        }
        System.out.println("\nProcessing complete.");
    }

    /**
     * Calculates the SHA-256 hash of a given file.
     * @param file The file to hash.
     * @return A string representing the hex value of the hash, or null on error.
     */
    private static String calculateSHA256(File file) {
        try (FileInputStream fis = new FileInputStream(file)) {
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            byte[] buffer = new byte[8192]; // Read file in 8KB chunks
            int bytesRead;
            while ((bytesRead = fis.read(buffer)) != -1) {
                digest.update(buffer, 0, bytesRead);
            }
            byte[] hashBytes = digest.digest();
            return bytesToHex(hashBytes);
        } catch (NoSuchAlgorithmException | IOException e) {
            System.err.println("Error calculating hash for " + file.getName() + ": " + e.getMessage());
            return null;
        }
    }

    /**
     * Converts a byte array to a hexadecimal string.
     * @param bytes The byte array to convert.
     * @return The resulting hex string.
     */
    private static String bytesToHex(byte[] bytes) {
        try (Formatter formatter = new Formatter()) {
            for (byte b : bytes) {
                formatter.format("%02x", b);
            }
            return formatter.toString();
        }
    }

    /**
     * Extracts the extension (including the dot) from a filename.
     * @param filename The name of the file.
     * @return An Optional containing the extension, or an empty Optional if none exists.
     */
    private static Optional<String> getFileExtension(String filename) {
        int lastIndexOfDot = filename.lastIndexOf('.');
        if (lastIndexOfDot > 0 && lastIndexOfDot < filename.length() - 1) {
            return Optional.of(filename.substring(lastIndexOfDot));
        }
        return Optional.empty();
    }
}
