import java.io.*;
import java.net.ServerSocket;
import java.net.Socket;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.ArrayList;
import java.util.List;

public class Server {

    private static final int PORT = 12345;
    private static final String FILES_DIRECTORY = "files";

    public static void main(String[] args) {
        try (ServerSocket serverSocket = new ServerSocket(PORT)) {
            System.out.println("Server started on port " + PORT);

            createDirectoryIfNotExists(FILES_DIRECTORY);
            populateFilesDirectory(); // For demonstration purposes in server 'files' dir

            while (true) {
                Socket clientSocket = serverSocket.accept();
                System.out.println("Client connected: " + clientSocket.getInetAddress());
                new Thread(new ClientHandler(clientSocket)).start();
            }

        } catch (IOException e) {
            e.printStackTrace();
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
                sendFilesToClient();      // Then send files to client

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
                String fileName = dataInputStream.readUTF();
                long fileSize = dataInputStream.readLong();
                System.out.println("Receiving file: " + fileName + ", size: " + fileSize + " bytes");

                File receivedFile = new File(FILES_DIRECTORY, fileName);
                try (FileOutputStream fileOutputStream = new FileOutputStream(receivedFile)) {
                    byte[] buffer = new byte[4096];
                    int bytesRead;
                    long totalBytesRead = 0;
                    while (totalBytesRead < fileSize) {
                        bytesRead = dataInputStream.read(buffer, 0, (int) Math.min(buffer.length, fileSize - totalBytesRead));
                        if (bytesRead == -1) break;
                        fileOutputStream.write(buffer, 0, bytesRead);
                        totalBytesRead += bytesRead;
                    }
                }
                System.out.println("File received: " + fileName);
            }
            System.out.println("All files received from client.");
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