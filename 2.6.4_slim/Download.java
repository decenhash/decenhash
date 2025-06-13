import java.io.*;
import java.net.*;
import java.nio.file.*;
import java.security.*;
import java.util.*;

public class Download {
    public static void main(String[] args) {
        // Create data directory if it doesn't exist
        Path dataDir = Paths.get("data");
        try {
            if (!Files.exists(dataDir)) {
                Files.createDirectory(dataDir);
            }
        } catch (IOException e) {
            System.err.println("Error creating data directory: " + e.getMessage());
            return;
        }

        // Create servers directory if it doesn't exist
        Path serversDir = Paths.get("servers");
        try {
            if (!Files.exists(serversDir)) {
                Files.createDirectory(serversDir);
            }
        } catch (IOException e) {
            System.err.println("Error creating servers directory: " + e.getMessage());
            return;
        }

        // Read servers from servers.txt
        List<String> servers = readLinesFromFile("servers.txt");
        if (servers == null) {
            System.err.println("Error reading servers.txt");
            return;
        }

        // Read files from files.txt
        List<String> files = readLinesFromFile("files.txt");
        if (files == null) {
            System.err.println("Error reading files.txt");
            return;
        }

        for (String filename : files) {
            // Get file info
            String filenameNoExt = getFilenameWithoutExtension(filename);
            String expectedHash = filenameNoExt;
            String extension = getFileExtension(filename);
            
            // Check if file already exists in data folder
            Path targetDir = dataDir.resolve(expectedHash);
            Path targetPath = targetDir.resolve(expectedHash + extension);
            
            if (Files.exists(targetPath)) {
                continue; // File already exists, skip to next file
            }

            for (String server : servers) {
                // Normalize server URL
                String normalizedServer = server.replaceAll("/+$", "") + "/";
                String url = normalizedServer + "data/" + filenameNoExt + "/" + filename;

                try {
                    // Download file
                    byte[] fileContent = downloadFile(url);
                    if (fileContent == null) {
                        continue; // File not found on this server
                    }

                    // Calculate hash
                    String actualHash = calculateSHA256(fileContent);

                    // Check if hash matches
                    if (actualHash.equals(expectedHash)) {
                        // Create directory for this hash if it doesn't exist
                        if (!Files.exists(targetDir)) {
                            Files.createDirectory(targetDir);
                        }

                        // Save the file
                        Files.write(targetPath, fileContent);

                        // Create hash file in servers directory
                        Path hashFile = serversDir.resolve(expectedHash + ".txt");

                        // Read existing servers if file exists
                        Set<String> existingServers = new HashSet<>();
                        if (Files.exists(hashFile)) {
                            existingServers.addAll(Files.readAllLines(hashFile));
                        }

                        // Add server if not already present
                        if (!existingServers.contains(server)) {
                            Files.write(hashFile, (server + System.lineSeparator()).getBytes(),
                                    StandardOpenOption.CREATE, StandardOpenOption.APPEND);
                        }

                        break; // Found valid file, move to next file
                    }
                } catch (IOException e) {
                    // Silently skip errors
                    continue;
                }
            }
        }

        System.out.println("Processing complete.");
    }

    private static List<String> readLinesFromFile(String filename) {
        try {
            return Files.readAllLines(Paths.get(filename));
        } catch (IOException e) {
            return null;
        }
    }

    private static String getFilenameWithoutExtension(String filename) {
        int dotIndex = filename.lastIndexOf('.');
        return (dotIndex == -1) ? filename : filename.substring(0, dotIndex);
    }

    private static String getFileExtension(String filename) {
        int dotIndex = filename.lastIndexOf('.');
        return (dotIndex == -1) ? "" : filename.substring(dotIndex);
    }

    private static byte[] downloadFile(String urlString) {
        try {
            URL url = new URL(urlString);
            HttpURLConnection connection = (HttpURLConnection) url.openConnection();
            connection.setRequestMethod("GET");
            connection.setConnectTimeout(5000);
            connection.setReadTimeout(5000);

            int responseCode = connection.getResponseCode();
            if (responseCode != HttpURLConnection.HTTP_OK) {
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
            return null;
        }
    }

    private static String calculateSHA256(byte[] data) {
        try {
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            byte[] hash = digest.digest(data);
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
            throw new RuntimeException("SHA-256 algorithm not found", e);
        }
    }
}