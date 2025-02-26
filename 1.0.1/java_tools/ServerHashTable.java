import java.io.*;
import java.net.ServerSocket;
import java.net.Socket;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.util.HashSet;
import java.util.List;
import java.util.Set;
import java.util.ArrayList;

public class ServerHashTable {

    private static final int PORT = 12345;
    private static final String FILES_DIRECTORY = "filess";
    private static final String HASHTABLE_FILE = "hashtable.txt";
    private static Set<String> validHashes = new HashSet<>();

    public static void main(String[] args) {
        try (ServerSocket serverSocket = new ServerSocket(PORT)) {
            System.out.println("Server started on port " + PORT);

            createDirectoryIfNotExists(FILES_DIRECTORY);
            populateFilesDirectory(); // For demonstration purposes in server 'files' dir
            loadValidHashes(); // Load valid hashes from hashtable.txt

            while (true) {
                Socket clientSocket = serverSocket.accept();
                System.out.println("Client connected: " + clientSocket.getInetAddress());
                new Thread(new ClientHandler(clientSocket)).start();
            }

        } catch (IOException e) {
            e.printStackTrace();
        }
    }

    private static void loadValidHashes() {
        File hashtableFile = new File(HASHTABLE_FILE);
        if (!hashtableFile.exists()) {
            System.out.println("Hashtable file not found. Creating empty hashtable.");
            try {
                hashtableFile.createNewFile();
            } catch (IOException e) {
                System.err.println("Error creating hashtable file: " + e.getMessage());
            }
            return;
        }

        try (BufferedReader reader = new BufferedReader(new FileReader(hashtableFile))) {
            String line;
            while ((line = reader.readLine()) != null) {
                validHashes.add(line.trim()); // Add each hash to the set
            }
            System.out.println("Loaded " + validHashes.size() + " valid hashes from hashtable.");
        } catch (IOException e) {
            System.err.println("Error loading hashes from hashtable: " + e.getMessage());
        }
    }


    private static void createDirectoryIfNotExists(String dirName) {
        File directory = new File(dirName);
        if (!directory.exists()) {
            directory.mkdirs();
        }
    }

    // Create some dummy files for testing if the directory is empty
    private static void populateFilesDirectory() throws IOException {
        File filesDir = new File(FILES_DIRECTORY);
        if (filesDir.list().length == 0) {
            for (int i = 1; i <= 3; i++) {
                File dummyFile = new File(filesDir, "server_file" + i + ".txt"); // Differentiate server files
                if (!dummyFile.exists()) {
                    try (FileWriter writer = new FileWriter(dummyFile)) {
                        writer.write("This is server file number " + i + ".");
                    }
                }
            }
        }
    }

    private static class ClientHandler implements Runnable {
        private final Socket clientSocket;
        private DataInputStream dataInputStream;
        private DataOutputStream dataOutputStream;

        public ClientHandler(Socket socket) {
            this.clientSocket = socket;
        }

        @Override
        public void run() {
            try {
                dataInputStream = new DataInputStream(clientSocket.getInputStream());
                dataOutputStream = new DataOutputStream(clientSocket.getOutputStream());

                receiveFilesFromClient(); // Receive files from client first
                sendFilesToClient();     // Then send files to client

            } catch (IOException e) {
                System.err.println("Client handler exception: " + e.getMessage());
            } finally {
                try {
                    if (dataInputStream != null) dataInputStream.close();
                    if (dataOutputStream != null) dataOutputStream.close();
                    if (clientSocket != null) clientSocket.close();
                    System.out.println("Client disconnected: " + clientSocket.getInetAddress());
                } catch (IOException e) {
                    e.printStackTrace();
                }
            }
        }

        private void receiveFilesFromClient() throws IOException {
            int filesToReceive = dataInputStream.readInt();
            System.out.println("Receiving " + filesToReceive + " files from client.");
            for (int i = 0; i < filesToReceive; i++) {
                String originalFileName = dataInputStream.readUTF();
                long fileSize = dataInputStream.readLong();
                System.out.println("Receiving file: " + originalFileName + ", size: " + fileSize + " bytes");

                byte[] fileContent = new byte[(int) fileSize]; // Read all file content at once for hash calculation
                dataInputStream.readFully(fileContent); // Ensure all bytes are read

                String sha256Hash = calculateSHA256Hash(fileContent);
                if (validHashes.contains(sha256Hash)) {
                    String fileExtension = "";
                    int dotIndex = originalFileName.lastIndexOf('.');
                    if (dotIndex > 0 && dotIndex < originalFileName.length() - 1) {
                        fileExtension = originalFileName.substring(dotIndex);
                    }
                    String newFileName = sha256Hash + fileExtension;
                    File receivedFile = new File(FILES_DIRECTORY, newFileName);

                    try (FileOutputStream fileOutputStream = new FileOutputStream(receivedFile)) {
                        fileOutputStream.write(fileContent);
                    }
                    System.out.println("File received and saved as: " + newFileName);
                } else {
                    System.out.println("File with hash " + sha256Hash + " is not in the valid hashtable. File not saved.");
                }
            }
            System.out.println("All files received from client.");
        }

        private String calculateSHA256Hash(byte[] fileContent) {
            try {
                MessageDigest digest = MessageDigest.getInstance("SHA-256");
                byte[] hashBytes = digest.digest(fileContent);
                StringBuilder hexString = new StringBuilder();
                for (byte hashByte : hashBytes) {
                    String hex = Integer.toHexString(0xff & hashByte);
                    if (hex.length() == 1) hexString.append('0');
                    hexString.append(hex);
                }
                return hexString.toString();
            } catch (NoSuchAlgorithmException e) {
                System.err.println("SHA-256 algorithm not found: " + e.getMessage());
                return null;
            }
        }


        private void sendFilesToClient() throws IOException {
            // Send list of files to client
            List<String> fileList = getFileList(FILES_DIRECTORY);
            dataOutputStream.writeInt(fileList.size()); // Send number of files
            for (String fileName : fileList) {
                dataOutputStream.writeUTF(fileName); // Send each file name
            }
            dataOutputStream.flush();

            // Send files
            for (String fileName : fileList) {
                File fileToSend = new File(FILES_DIRECTORY, fileName);
                if (fileToSend.isFile()) {
                    dataOutputStream.writeUTF(fileName); // Send file name
                    dataOutputStream.writeLong(fileToSend.length()); // Send file size

                    try (FileInputStream fileInputStream = new FileInputStream(fileToSend)) {
                        byte[] buffer = new byte[4096];
                        int bytesRead;
                        while ((bytesRead = fileInputStream.read(buffer)) != -1) {
                            dataOutputStream.write(buffer, 0, bytesRead);
                        }
                    }
                    dataOutputStream.flush();
                    System.out.println("File sent: " + fileName);
                }
            }
            dataOutputStream.writeUTF("DOWNLOAD_COMPLETE"); // Signal end of download
            dataOutputStream.flush();
        }


        private List<String> getFileList(String directoryPath) {
            List<String> fileNames = new ArrayList<>();
            File directory = new File(directoryPath);
            File[] files = directory.listFiles();
            if (files != null) {
                for (File file : files) {
                    if (file.isFile()) {
                        fileNames.add(file.getName());
                    }
                }
            }
            return fileNames;
        }
    }
}