import javax.swing.*;
import javax.swing.table.DefaultTableModel;
import java.awt.*;
import java.awt.event.MouseAdapter;
import java.awt.event.MouseEvent;
import java.io.BufferedReader;
import java.io.File;
import java.io.FileReader;
import java.io.IOException;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.ExecutionException;

/**
 * It searches across a predefined list of servers for resources based on user input.
 *
 * HOW TO USE:
 * 1. Compile and run this Java file.
 * 2. Create a file named "servers.txt" in the SAME directory where you run the program.
 * 3. Add the base URLs of the servers you want to search to this file, one URL per line.
 * For example:
 * http://your-local-server.net
 *
 * SEARCH LOGIC:
 * - If you enter text WITHOUT a dot (e.g., "cat"), the program calculates its
 * SHA-256 hash and searches for a resource at: {server_url}/data/{hash}/index.html
 *
 * - If you enter text WITH a dot (e.g., "cat.jpg"), the program searches for a
 * file directly at: {server_url}/files/{your_input}
 *
 * Results found will be displayed in the table. Clicking a row will open the URL in your browser.
 */
public class Seacher extends JFrame {

    // --- Modern UI Colors ---
    private static final Color BG_COLOR = new Color(224, 242, 241); // Light Blue-Green
    private static final Color PANEL_COLOR = new Color(178, 223, 219); // Slightly darker shade
    private static final Color BUTTON_COLOR = new Color(0, 150, 136); // Teal
    private static final Color TEXT_COLOR = new Color(0, 77, 64); // Dark Teal

    private final JTextField searchField;
    private final JButton searchButton;
    private final JTable resultsTable;
    private final DefaultTableModel tableModel;
    private final JLabel statusLabel;

    public static void main(String[] args) {
        // Run the GUI creation on the Event Dispatch Thread for thread safety
        SwingUtilities.invokeLater(() -> {
            Seacher finder = new Seacher();
            finder.setVisible(true);
        });
    }

    public Seacher() {
        super("Decenhash Seacher");

        // --- Window Setup ---
        setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        setSize(800, 600);
        setLocationRelativeTo(null); // Center the window
        setLayout(new BorderLayout(10, 10));
        getContentPane().setBackground(BG_COLOR);


        // --- Top Panel for Input ---
        JPanel topPanel = new JPanel(new BorderLayout(10, 10));
        topPanel.setBorder(BorderFactory.createEmptyBorder(15, 15, 15, 15));
        topPanel.setBackground(PANEL_COLOR);

        JLabel searchPrompt = new JLabel("Enter Search Term or Filename:");
        searchPrompt.setFont(new Font("SansSerif", Font.BOLD, 14));
        searchPrompt.setForeground(TEXT_COLOR);

        searchField = new JTextField();
        searchField.setFont(new Font("SansSerif", Font.PLAIN, 18));
        searchField.setToolTipText("Enter a search term or a full filename");
        searchField.setBorder(BorderFactory.createCompoundBorder(
            BorderFactory.createLineBorder(TEXT_COLOR, 1),
            BorderFactory.createEmptyBorder(5, 5, 5, 5)
        ));

        searchButton = new JButton("Search");
        searchButton.setFont(new Font("SansSerif", Font.BOLD, 16));
        searchButton.setCursor(new Cursor(Cursor.HAND_CURSOR));
        searchButton.setToolTipText("Click to begin the search across specified servers");
        searchButton.setBackground(BUTTON_COLOR);
        searchButton.setForeground(Color.WHITE);
        searchButton.setFocusPainted(false);
        searchButton.setBorder(BorderFactory.createEmptyBorder(10, 20, 10, 20));


        topPanel.add(searchPrompt, BorderLayout.NORTH);
        topPanel.add(searchField, BorderLayout.CENTER);
        topPanel.add(searchButton, BorderLayout.EAST);

        // --- Center Panel for Results Table ---
        String[] columnNames = {"Status", "Resource URL"};
        tableModel = new DefaultTableModel(columnNames, 0) {
            @Override
            public boolean isCellEditable(int row, int column) {
                // Make table cells non-editable
                return false;
            }
        };
        resultsTable = new JTable(tableModel);
        resultsTable.setFont(new Font("SansSerif", Font.PLAIN, 14));
        resultsTable.setRowHeight(25);
        resultsTable.getTableHeader().setFont(new Font("SansSerif", Font.BOLD, 14));
        resultsTable.getTableHeader().setBackground(TEXT_COLOR);
        resultsTable.getTableHeader().setForeground(Color.WHITE);

        // Set column widths
        resultsTable.getColumnModel().getColumn(0).setPreferredWidth(100);
        resultsTable.getColumnModel().getColumn(0).setMaxWidth(120);
        resultsTable.getColumnModel().getColumn(1).setPreferredWidth(600);


        JScrollPane scrollPane = new JScrollPane(resultsTable);
        scrollPane.setBorder(BorderFactory.createTitledBorder("Search Results"));
        scrollPane.getViewport().setBackground(Color.WHITE);

        // --- Bottom Panel for Status and Link ---
        JPanel bottomPanel = new JPanel(new BorderLayout(10, 5));
        bottomPanel.setBorder(BorderFactory.createEmptyBorder(5, 15, 5, 15));
        bottomPanel.setOpaque(false); // Make it transparent to show frame background

        statusLabel = new JLabel("Ready. Enter a term and click Search.");
        statusLabel.setFont(new Font("SansSerif", Font.ITALIC, 12));
        statusLabel.setForeground(TEXT_COLOR);

        // Create a clickable link label
        JLabel institutionLink = new JLabel("<html><a href=''>Contact</a></html>");
        institutionLink.setFont(new Font("SansSerif", Font.PLAIN, 12));
        institutionLink.setCursor(new Cursor(Cursor.HAND_CURSOR));
        institutionLink.setHorizontalAlignment(SwingConstants.RIGHT);

        bottomPanel.add(statusLabel, BorderLayout.CENTER);
        bottomPanel.add(institutionLink, BorderLayout.EAST);

        // --- Add Components to Frame ---
        add(topPanel, BorderLayout.NORTH);
        add(scrollPane, BorderLayout.CENTER);
        add(bottomPanel, BorderLayout.SOUTH);

        // --- Action Listeners ---
        searchButton.addActionListener(e -> startSearch());
        searchField.addActionListener(e -> startSearch());

        resultsTable.addMouseListener(new MouseAdapter() {
            @Override
            public void mouseClicked(MouseEvent e) {
                int row = resultsTable.rowAtPoint(e.getPoint());
                if (row >= 0 && e.getClickCount() == 2) {
                    String url = (String) tableModel.getValueAt(row, 1);
                    openUrlInBrowser(url);
                }
            }
        });

        institutionLink.addMouseListener(new MouseAdapter() {
            @Override
            public void mouseClicked(MouseEvent e) {
                // The URL of the institution to open
                openUrlInBrowser("https://www.t.me/decenhash");
            }
        });
    }

    /**
     * Initiates the search process by creating and executing a background worker thread.
     */
    private void startSearch() {
        String query = searchField.getText().trim();
        if (query.isEmpty()) {
            JOptionPane.showMessageDialog(this, "Please enter a search term.", "Input Required", JOptionPane.WARNING_MESSAGE);
            return;
        }

        // Clear previous results and disable UI during search
        tableModel.setRowCount(0);
        searchButton.setEnabled(false);
        searchField.setEnabled(false);
        statusLabel.setText("Initializing search for: " + query);

        // Run the search in a background thread to keep the GUI responsive
        SearchWorker worker = new SearchWorker(query);
        worker.execute();
    }

    /**
     * Converts a string to its SHA-256 hash representation.
     */
    private static String toSha256(String input) {
        try {
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            byte[] hash = digest.digest(input.getBytes(StandardCharsets.UTF_8));
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

    /**
     * Attempts to open the given URL in the user's default web browser.
     */
    private void openUrlInBrowser(String urlString) {
        try {
            Desktop.getDesktop().browse(new URI(urlString));
        } catch (Exception ex) {
            statusLabel.setText("Error opening URL: " + ex.getMessage());
            JOptionPane.showMessageDialog(this,
                "Could not open the URL in the browser.\nURL: " + urlString + "\nError: " + ex.getMessage(),
                "Browser Error",
                JOptionPane.ERROR_MESSAGE);
        }
    }


    /**
     * SwingWorker class to perform network operations in the background,
     * preventing the GUI from freezing.
     */
    class SearchWorker extends SwingWorker<Void, String[]> {
        private final String query;

        SearchWorker(String query) {
            this.query = query;
        }

        @Override
        protected Void doInBackground() throws Exception {
            File serversFile = new File("servers.txt");
            if (!serversFile.exists()) {
                publish(new String[]{"Error", "File 'servers.txt' not found in the application directory."});
                return null;
            }

            List<String> servers = new ArrayList<>();
            try (BufferedReader reader = new BufferedReader(new FileReader(serversFile))) {
                String line;
                while ((line = reader.readLine()) != null) {
                    line = line.trim();
                    if (!line.isEmpty() && (line.startsWith("http://") || line.startsWith("https://"))) {
                        servers.add(line);
                    }
                }
            } catch (IOException e) {
                publish(new String[]{"Error", "Failed to read 'servers.txt': " + e.getMessage()});
                return null;
            }

            if (servers.isEmpty()) {
                publish(new String[]{"Info", "No valid server URLs found in 'servers.txt'."});
                return null;
            }

            boolean isFileInput = query.contains(".");
            String searchTerm = isFileInput ? query : toSha256(query);

            for (String server : servers) {
                String urlToCheck;
                if (isFileInput) {
                    urlToCheck = server + "/files/" + searchTerm;
                } else {
                    urlToCheck = server + "/data/" + searchTerm + "/index.html";
                }

                publish(new String[]{"Checking", urlToCheck}); // Update UI with current check status
                if (checkUrlExists(urlToCheck)) {
                    publish(new String[]{"Found", urlToCheck}); // Publish only found results to the table
                }
            }
            return null;
        }

        /**
         * Checks if a resource exists at the given URL by making an HTTP HEAD request.
         */
        private boolean checkUrlExists(String urlString) {
            try {
                URL url = new URL(urlString);
                HttpURLConnection connection = (HttpURLConnection) url.openConnection();
                connection.setRequestMethod("HEAD");
                connection.setConnectTimeout(5000); // 5 seconds
                connection.setReadTimeout(5000); // 5 seconds
                int responseCode = connection.getResponseCode();
                return (responseCode == HttpURLConnection.HTTP_OK);
            } catch (IOException e) {
                return false;
            }
        }

        @Override
        protected void process(List<String[]> chunks) {
            // This method is called on the EDT to safely update the GUI
            for (String[] result : chunks) {
                String status = result[0];
                String message = result[1];

                switch (status) {
                    case "Found":
                        // Only "Found" status adds a row to the table
                        tableModel.addRow(result);
                        break;
                    case "Checking":
                        statusLabel.setText("Scanning: " + message);
                        break;
                    case "Error":
                    case "Info":
                        // Show errors and info in the status bar, not the table
                        statusLabel.setText(message);
                        break;
                }
            }
        }

        @Override
        protected void done() {
            // This method is called when doInBackground completes
            try {
                get(); // To catch any exceptions from doInBackground
                if (tableModel.getRowCount() == 0) {
                     statusLabel.setText("Search complete. No results found for your query.");
                } else {
                     statusLabel.setText("Search complete. Double-click a result to open.");
                }
            } catch (InterruptedException | ExecutionException e) {
                statusLabel.setText("An error occurred during the search: " + e.getCause().getMessage());
            } finally {
                // Re-enable UI components
                searchButton.setEnabled(true);
                searchField.setEnabled(true);
            }
        }
    }
}

