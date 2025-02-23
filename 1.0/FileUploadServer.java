import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpHandler;
import com.sun.net.httpserver.HttpServer;

import java.io.*;
import java.net.InetSocketAddress;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.util.Arrays;
import java.util.List;
import java.util.Map;
import java.util.HashMap;
import java.net.URLDecoder;

public class FileUploadServer {
    private static final String UPLOAD_DIR_BASE = "data";

    public static void main(String[] args) throws IOException {
        int port = 8080;
        HttpServer server = HttpServer.create(new InetSocketAddress(port), 0);
        server.createContext("/", new UploadHandler());
        server.setExecutor(null);
        server.start();
        System.out.println("Server started on port " + port);
    }

    static class UploadHandler implements HttpHandler {
        @Override
        public void handle(HttpExchange exchange) throws IOException {
            if ("GET".equals(exchange.getRequestMethod())) {
                handleGetRequest(exchange);
            } else if ("POST".equals(exchange.getRequestMethod())) {
                handlePostRequest(exchange);
            } else {
                sendResponse(exchange, 405, "Method Not Allowed");
            }
        }

        private void handleGetRequest(HttpExchange exchange) throws IOException {
            String response = getUploadForm();
            sendResponse(exchange, 200, response);
        }

        private void handlePostRequest(HttpExchange exchange) throws IOException {
            Map<String, byte[]> fileData = new HashMap<>();
            Map<String, String> formFields = parseMultipartFormData(exchange, fileData);

            String categoryText = formFields.get("category");
            byte[] fileContentBytes = null;
            String originalFileName = null;
            String fileExtension = "txt";

            // Check for file upload first
            if (fileData.containsKey("uploaded_file") && fileData.get("uploaded_file").length > 0) {
                fileContentBytes = fileData.get("uploaded_file");
                originalFileName = formFields.get("uploaded_file_name");
                if (originalFileName != null && !originalFileName.isEmpty()) {
                    fileExtension = getFileExtension(originalFileName);
                }
            } 
            // If no file uploaded, check for text content
            else if (formFields.containsKey("text_content") && !formFields.get("text_content").trim().isEmpty()) {
                String textContent = formFields.get("text_content").trim();
                fileContentBytes = textContent.getBytes();
                // Generate a timestamp-based filename for text content
                originalFileName = "text_" + System.currentTimeMillis() + ".txt";
                fileExtension = "txt";
            }

            if (categoryText != null && fileContentBytes != null) {
                // Calculate hash from the actual content bytes
                String fileHash = calculateSHA256HashFromBytes(fileContentBytes);
                String categoryHash = calculateSHA256Hash(categoryText);
                String fileNameWithExtension = fileHash + "." + fileExtension;

                Path uploadDirBasePath = Paths.get(UPLOAD_DIR_BASE);
                Path fileUploadDirPath = uploadDirBasePath.resolve(fileHash);
                Path categoryDirPath = uploadDirBasePath.resolve(categoryHash);

                // Create necessary directories
                Files.createDirectories(uploadDirBasePath);
                Files.createDirectories(fileUploadDirPath);
                Files.createDirectories(categoryDirPath);

                Path destinationFilePath = fileUploadDirPath.resolve(fileNameWithExtension);

                boolean fileSaved = false;
                try {
                    Files.write(destinationFilePath, fileContentBytes);
                    fileSaved = true;
                } catch (IOException e) {
                    e.printStackTrace();
                    fileSaved = false;
                }

                if (fileSaved) {
                    Path categoryFilePath = categoryDirPath.resolve(fileNameWithExtension);
                    boolean emptyFileCreated = false;
                    try {
                        Files.createFile(categoryFilePath);
                        emptyFileCreated = true;
                    } catch (IOException e) {
                        emptyFileCreated = false;
                    }

                    if (emptyFileCreated) {
                        updateIndexFiles(fileUploadDirPath, categoryDirPath, fileHash, fileNameWithExtension, originalFileName);
                        String successResponse = "<p class='success'>Content processed successfully!</p>" +
                                "<p>Content saved in: <pre>" + destinationFilePath.toString() + "</pre></p>" +
                                "<p>Category folder created: <pre>" + categoryDirPath.toString() + "</pre></p>" +
                                "<p>Empty file created in category folder: <pre>" + categoryFilePath.toString() + "</pre></p>";
                        sendResponse(exchange, 200, getUploadForm() + successResponse);
                    } else {
                        sendResponse(exchange, 200, getUploadForm() + "<p class='error'>Error creating empty file in category folder.</p>");
                    }
                } else {
                    sendResponse(exchange, 200, getUploadForm() + "<p class='error'>Error saving content.</p>");
                }
            } else {
                sendResponse(exchange, 200, getUploadForm() + "<p class='error'>Please select a file or enter text content and provide a category.</p>");
            }
        }

        // [Previous parseMultipartFormData method remains the same]
        private Map<String, String> parseMultipartFormData(HttpExchange exchange, Map<String, byte[]> fileData) throws IOException {
            // [Same implementation as before]
            // [Keep the existing implementation from the previous version]
            Map<String, String> formFields = new HashMap<>();
            String contentType = exchange.getRequestHeaders().getFirst("Content-Type");
            
            if (contentType != null && contentType.startsWith("multipart/form-data")) {
                String boundary = contentType.substring(contentType.indexOf("boundary=") + 9);
                byte[] boundaryBytes = ("--" + boundary).getBytes("UTF-8");
                byte[] finalBoundaryBytes = ("--" + boundary + "--").getBytes("UTF-8");
                
                InputStream input = exchange.getRequestBody();
                ByteArrayOutputStream data = new ByteArrayOutputStream();
                byte[] buffer = new byte[4096];
                int bytesRead;
                while ((bytesRead = input.read(buffer)) != -1) {
                    data.write(buffer, 0, bytesRead);
                }
                byte[] completeData = data.toByteArray();
                
                int pos = 0;
                while (pos < completeData.length) {
                    int boundaryPos = findSequence(completeData, pos, boundaryBytes);
                    if (boundaryPos == -1) break;
                    
                    pos = boundaryPos + boundaryBytes.length;
                    
                    if (pos + 2 < completeData.length && completeData[pos] == '-' && completeData[pos + 1] == '-') {
                        break;
                    }
                    
                    pos += 2;
                    
                    String headers = "";
                    while (pos < completeData.length - 1) {
                        if (completeData[pos] == '\r' && completeData[pos + 1] == '\n') {
                            if (pos + 3 < completeData.length && completeData[pos + 2] == '\r' && completeData[pos + 3] == '\n') {
                                pos += 4;
                                break;
                            }
                        }
                        headers += (char) completeData[pos++];
                    }
                    
                    String name = null;
                    String filename = null;
                    for (String header : headers.split("\r\n")) {
                        if (header.startsWith("Content-Disposition:")) {
                            for (String part : header.split(";")) {
                                part = part.trim();
                                if (part.startsWith("name=")) {
                                    name = part.substring(6, part.length() - 1);
                                } else if (part.startsWith("filename=")) {
                                    filename = part.substring(10, part.length() - 1);
                                }
                            }
                        }
                    }
                    
                    if (name == null) continue;
                    
                    int nextBoundary = findSequence(completeData, pos, boundaryBytes);
                    if (nextBoundary == -1) {
                        nextBoundary = findSequence(completeData, pos, finalBoundaryBytes);
                        if (nextBoundary == -1) break;
                    }
                    
                    byte[] content = Arrays.copyOfRange(completeData, pos, nextBoundary - 2);
                    
                    if (filename != null) {
                        fileData.put(name, content);
                        formFields.put("uploaded_file_name", filename);
                    } else {
                        formFields.put(name, new String(content, "UTF-8"));
                    }
                    
                    pos = nextBoundary;
                }
            }
            return formFields;
        }

        private int findSequence(byte[] data, int start, byte[] sequence) {
            for (int i = start; i <= data.length - sequence.length; i++) {
                boolean found = true;
                for (int j = 0; j < sequence.length; j++) {
                    if (data[i + j] != sequence[j]) {
                        found = false;
                        break;
                    }
                }
                if (found) return i;
            }
            return -1;
        }

        private String getUploadForm() {
            return "<!DOCTYPE html>" +
                    "<html>" +
                    "<head>" +
                    "<title>File/Text Upload with Category (Java)</title>" +
                    "<style>" +
                    ".success { color: green; font-weight: bold; }" +
                    ".error { color: red; font-weight: bold; }" +
                    "</style>" +
                    "</head>" +
                    "<body>" +
                    "<h2>Upload File or Enter Text Content and Category</h2>" +
                    "<form action='/' method='post' enctype='multipart/form-data'>" +
                    "<label for='uploaded_file'>Select File:</label>" +
                    "<input type='file' name='uploaded_file' id='uploaded_file'><br><br>" +
                    "<label for='text_content'>Or Enter Text Content (will be saved as a text file):</label><br>" +
                    "<textarea name='text_content' id='text_content' rows='5' cols='40'></textarea><br><br>" +
                    "<label for='category'>Enter Category (required):</label>" +
                    "<input type='text' name='category' id='category' required><br><br>" +
                    "<input type='submit' value='Upload/Save Content'>" +
                    "</form>" +
                    "</body>" +
                    "</html>";
        }

        private void updateIndexFiles(Path fileUploadDirPath, Path categoryDirPath, String fileHash, 
                                   String fileNameWithExtension, String originalFileName) throws IOException {
            // Update file folder index
            Path indexPathFileFolder = fileUploadDirPath.resolve("index.html");
            if (!Files.exists(indexPathFileFolder)) {
                Files.createFile(indexPathFileFolder);
            }
            String linkToFileFolderIndex = "<a href='" + fileNameWithExtension + "'>" + originalFileName + "</a><br>";
            String indexContentFileFolder = new String(Files.readAllBytes(indexPathFileFolder));
            if (!indexContentFileFolder.contains(linkToFileFolderIndex)) {
                Files.write(indexPathFileFolder, (indexContentFileFolder + linkToFileFolderIndex).getBytes());
            }

            // Update category folder index
            Path indexPathCategoryFolder = categoryDirPath.resolve("index.html");
            if (!Files.exists(indexPathCategoryFolder)) {
                Files.createFile(indexPathCategoryFolder);
            }
            String relativePathToFile = "../" + fileHash + "/" + fileNameWithExtension;
            String linkToCategoryFolderIndex = "<a href='" + relativePathToFile + "'>" + originalFileName + "</a><br>";
            String indexContentCategoryFolder = new String(Files.readAllBytes(indexPathCategoryFolder));
            if (!indexContentCategoryFolder.contains(linkToCategoryFolderIndex)) {
                Files.write(indexPathCategoryFolder, (indexContentCategoryFolder + linkToCategoryFolderIndex).getBytes());
            }
        }

        private void sendResponse(HttpExchange exchange, int statusCode, String response) throws IOException {
            exchange.getResponseHeaders().put("Content-Type", Arrays.asList("text/html"));
            exchange.sendResponseHeaders(statusCode, response.getBytes().length);
            OutputStream os = exchange.getResponseBody();
            os.write(response.getBytes());
            os.close();
        }

        private String getFileExtension(String filename) {
            if (filename == null || filename.lastIndexOf('.') == -1) {
                return "";
            }
            return filename.substring(filename.lastIndexOf('.') + 1);
        }

        private String calculateSHA256Hash(String content) {
            try {
                MessageDigest digest = MessageDigest.getInstance("SHA-256");
                byte[] hashBytes = digest.digest(content.getBytes());
                return bytesToHex(hashBytes);
            } catch (NoSuchAlgorithmException e) {
                throw new RuntimeException("SHA-256 algorithm not available!", e);
            }
        }

        private String calculateSHA256HashFromBytes(byte[] content) {
            try {
                MessageDigest digest = MessageDigest.getInstance("SHA-256");
                byte[] hashBytes = digest.digest(content);
                return bytesToHex(hashBytes);
            } catch (NoSuchAlgorithmException e) {
                throw new RuntimeException("SHA-256 algorithm not available!", e);
            }
        }

        private String bytesToHex(byte[] bytes) {
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
    }
}