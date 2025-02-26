import javax.swing.*;
import java.awt.*;
import java.io.File;
import java.io.IOException;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import com.sun.net.httpserver.HttpServer;
import java.net.InetSocketAddress;
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.IOException;
import java.net.HttpURLConnection;
import java.net.URL;

public class HTTPServerGUI extends JFrame {
    private JTextField portField;
    private JTextField urlField;
    private JButton submitButton;
    private JLabel statusLabel;
    private HttpServer server;
    private static final String FILES_DIRECTORY = "files";

    public HTTPServerGUI() {
        // Frame settings
        setTitle("HTTP Server GUI");
        setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        setSize(500, 400);
        setLayout(new BorderLayout(10, 10));
        
        // Center the window on screen
        setLocationRelativeTo(null);

        // Create directory if it doesn't exist
        new File(FILES_DIRECTORY).mkdirs();

        // Panel for image
        JPanel imagePanel = createImagePanel();
        add(imagePanel, BorderLayout.NORTH);

        // Main panel for input fields
        JPanel inputPanel = createInputPanel();
        add(inputPanel, BorderLayout.CENTER);

        // Status panel
        statusLabel = new JLabel("Status: Ready", SwingConstants.CENTER);
        statusLabel.setFont(new Font("Arial", Font.PLAIN, 14));
        add(statusLabel, BorderLayout.SOUTH);

        // Submit button functionality
        submitButton.addActionListener(e -> handleSubmit());
    }

    private JPanel createImagePanel() {
        JPanel imagePanel = new JPanel(new BorderLayout());
        ImageIcon icon = new ImageIcon("img/logo.jpg"); // Replace with the actual image path
        JLabel pictureLabel = new JLabel(icon);
        pictureLabel.setHorizontalAlignment(SwingConstants.CENTER);
        imagePanel.add(pictureLabel, BorderLayout.CENTER);
        return imagePanel;
    }

    private JPanel createInputPanel() {
        JPanel inputPanel = new JPanel();
        inputPanel.setLayout(new GridBagLayout());
        GridBagConstraints gbc = new GridBagConstraints();
        gbc.insets = new Insets(10, 10, 10, 10);
        
        // Create larger font for all components
        Font largerFont = new Font("Arial", Font.PLAIN, 16);
        
        // Labels and input fields
        JLabel portLabel = new JLabel("Port:");
        portLabel.setFont(largerFont);
        
        portField = new JTextField(15);
        portField.setFont(largerFont);
        portField.setPreferredSize(new Dimension(200, 35)); // Make text field bigger
        
        JLabel urlLabel = new JLabel("URL:");
        urlLabel.setFont(largerFont);
        
        urlField = new JTextField(15);
        urlField.setFont(largerFont);
        urlField.setPreferredSize(new Dimension(200, 35)); // Make text field bigger
        
        submitButton = new JButton("Start Server");
        submitButton.setFont(largerFont);
        submitButton.setPreferredSize(new Dimension(150, 40)); // Make button bigger
        
        // Add components to the panel with GridBagLayout
        gbc.gridx = 0;
        gbc.gridy = 0;
        gbc.anchor = GridBagConstraints.EAST;
        inputPanel.add(portLabel, gbc);
        
        gbc.gridx = 1;
        gbc.gridy = 0;
        gbc.anchor = GridBagConstraints.WEST;
        inputPanel.add(portField, gbc);
        
        gbc.gridx = 0;
        gbc.gridy = 1;
        gbc.anchor = GridBagConstraints.EAST;
        inputPanel.add(urlLabel, gbc);
        
        gbc.gridx = 1;
        gbc.gridy = 1;
        gbc.anchor = GridBagConstraints.WEST;
        inputPanel.add(urlField, gbc);
        
        gbc.gridx = 1;
        gbc.gridy = 2;
        gbc.gridwidth = 2;
        gbc.anchor = GridBagConstraints.CENTER;
        inputPanel.add(submitButton, gbc);
        
        return inputPanel;
    }

    private String getPublicIPAddress() {
        String publicIP = "";
        try {
            // Create URL object to an IP lookup service
            URL url = new URL("http://checkip.amazonaws.com");
            
            // Open connection
            HttpURLConnection connection = (HttpURLConnection) url.openConnection();
            connection.setRequestMethod("GET");
            connection.setConnectTimeout(5000);  // 5 seconds timeout
            connection.setReadTimeout(5000);
            
            // Read response
            BufferedReader reader = new BufferedReader(
                new InputStreamReader(connection.getInputStream())
            );
            publicIP = reader.readLine().trim();
            reader.close();
            
        } catch (IOException e) {
            System.err.println("Error getting public IP: " + e.getMessage());
            // Optionally, try backup services if the first one fails
            try {
                URL backupUrl = new URL("https://api.ipify.org");
                HttpURLConnection conn = (HttpURLConnection) backupUrl.openConnection();
                conn.setRequestMethod("GET");
                conn.setConnectTimeout(5000);
                conn.setReadTimeout(5000);
                
                BufferedReader reader = new BufferedReader(
                    new InputStreamReader(conn.getInputStream())
                );
                publicIP = reader.readLine().trim();
                reader.close();
                
            } catch (IOException ex) {
                System.err.println("Error getting public IP from backup service: " + ex.getMessage());
                return "Unable to retrieve IP";
            }
        }
        return publicIP;
    }

    private void handleSubmit() {
        try {
            int port = Integer.parseInt(portField.getText());
            String url = urlField.getText();

            // Stop existing server if running
            if (server != null) {
                server.stop(0);
            }

            // Start HTTP server
            startServer(port);

            // Make HTTP request
            makeHttpRequest(url + "?url=" + getPublicIPAddress() + ":" + port);

            statusLabel.setText("Status: Server started on port " + port + " and request sent to " + url);
        } catch (NumberFormatException ex) {
            JOptionPane.showMessageDialog(this, "Please enter a valid port number");
        } catch (Exception ex) {
            JOptionPane.showMessageDialog(this, "Error: " + ex.getMessage());
        }
    }

    private void startServer(int port) throws IOException {
        server = HttpServer.create(new InetSocketAddress(port), 0);

        // Create context to serve files list
        server.createContext("/", exchange -> {
            StringBuilder responseContent = new StringBuilder();
            responseContent.append("<html><body><h1>Files Directory</h1><ul>");

            File directory = new File(FILES_DIRECTORY);
            File[] files = directory.listFiles();

            if (files != null) {
                for (File file : files) {
                    if (file.isFile()) {
                        String fileName = file.getName();
                        responseContent.append(String.format(
                                "<li><a href='/files/%s' target='_blank'>%s</a></li>",
                                fileName, fileName
                        ));
                    }
                }
            }

            responseContent.append("</ul></body></html>");
            exchange.getResponseHeaders().set("Content-Type", "text/html");
            exchange.sendResponseHeaders(200, responseContent.length());

            try (var os = exchange.getResponseBody()) {
                os.write(responseContent.toString().getBytes());
            }
        });

        // Create context to serve file content
        server.createContext("/files/", exchange -> {
            String fileName = exchange.getRequestURI().getPath().substring("/files/".length());
            Path filePath = Paths.get(FILES_DIRECTORY, fileName);

            if (Files.exists(filePath) && Files.isRegularFile(filePath)) {
                byte[] fileContent = Files.readAllBytes(filePath);
                exchange.getResponseHeaders().set("Content-Type", getContentType(fileName));
                exchange.sendResponseHeaders(200, fileContent.length);
                try (var os = exchange.getResponseBody()) {
                    os.write(fileContent);
                }
            } else {
                String response = "File not found";
                exchange.sendResponseHeaders(404, response.length());
                try (var os = exchange.getResponseBody()) {
                    os.write(response.getBytes());
                }
            }
        });

        server.setExecutor(null);  // Default executor
        server.start();
    }

    private String getContentType(String fileName) {
        if (fileName.endsWith(".txt")) return "text/plain";
        if (fileName.endsWith(".html")) return "text/html";
        if (fileName.endsWith(".jpg") || fileName.endsWith(".jpeg")) return "image/jpeg";
        if (fileName.endsWith(".png")) return "image/png";
        if (fileName.endsWith(".pdf")) return "application/pdf";
        return "application/octet-stream";
    }

    private void makeHttpRequest(String url) {
        try {
            HttpClient client = HttpClient.newHttpClient();
            HttpRequest request = HttpRequest.newBuilder()
                    .uri(URI.create(url))
                    .GET()
                    .build();

            HttpResponse<String> response = client.send(request, HttpResponse.BodyHandlers.ofString());
            JOptionPane.showMessageDialog(this, "Request sent successfully. Response code: " + response.statusCode());
        } catch (Exception e) {
            JOptionPane.showMessageDialog(this, "Error making HTTP request: " + e.getMessage());
        }
    }

    public static void main(String[] args) {
        SwingUtilities.invokeLater(() -> {
            new HTTPServerGUI().setVisible(true);
        });
    }
}
