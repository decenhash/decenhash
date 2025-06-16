package main

import (
	"crypto/sha256" // For SHA256 hashing
	"fmt"          // For formatted I/O (printing to console)
	"io/ioutil"    // For reading/writing files
	"net/http"     // For making HTTP requests
	"net/url"      // For URL parsing
	"os"           // For file system operations (creating directories, checking existence)
	"path/filepath" // For manipulating file paths in a platform-independent way
	"regexp"       // For regular expressions (hash validation, filename sanitization, HTML parsing)
	"strings"      // For string manipulation (trimming, replacing, splitting)
	"time"         // For time durations, used in HTTP client timeout
)

// FileStatus represents the status and details of a downloaded file.
type FileStatus struct {
	URL       string // The original URL of the file
	LocalPath string // The local path where the file is saved
	Size      int64  // The size of the file in bytes
	Status    string // "downloaded", "already_exists", or "failed"
	Error     string // Error message if the status is "failed"
}

// getContent fetches content from a given URL.
// It sets a User-Agent header and includes basic error handling for HTTP requests
// and status codes (400 and above are considered errors).
func getContent(urlStr string) ([]byte, error) {
	// Create an HTTP client with a timeout for robustness.
	client := &http.Client{
		Timeout: 30 * time.Second, // Overall request timeout
	}

	// Create a new GET request for the URL.
	req, err := http.NewRequest("GET", urlStr, nil)
	if err != nil {
		return nil, fmt.Errorf("failed to create HTTP request for %s: %w", urlStr, err)
	}

	// Set a User-Agent header to mimic a web browser, which can prevent some servers from blocking the request.
	req.Header.Set("User-Agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36")

	// Execute the HTTP request.
	resp, err := client.Do(req)
	if err != nil {
		return nil, fmt.Errorf("failed to perform HTTP request to %s: %w", urlStr, err)
	}
	defer resp.Body.Close() // Ensure the response body is closed after reading or an error occurs.

	// Check the HTTP status code. Status codes 400 or higher indicate an error.
	if resp.StatusCode >= 400 {
		return nil, fmt.Errorf("HTTP status code %d received from %s", resp.StatusCode, urlStr)
	}

	// Read the entire response body.
	body, err := ioutil.ReadAll(resp.Body)
	if err != nil {
		return nil, fmt.Errorf("failed to read response body from %s: %w", urlStr, err)
	}

	return body, nil // Return the content as a byte slice and a nil error.
}

// replaceDotDot replaces instances of '/../' with '/data/' in a URL string.
// This function is likely intended to normalize specific URL patterns.
func replaceDotDot(urlStr string) string {
	return strings.ReplaceAll(urlStr, "/../", "/data/")
}

// buildProperURL constructs a complete URL using a base server, a hash, and a filename.
// It ensures the server URL is trimmed of trailing slashes and formats the path.
func buildProperURL(server, hash, filename string) string {
	server = strings.TrimRight(server, "/") // Remove any trailing slash from the server URL.
	return fmt.Sprintf("%s/data/%s/%s", server, hash, filename)
}

// sanitizeFilename removes path traversal components (like '..') and invalid characters
// from a filename to ensure it's safe for local file system operations.
func sanitizeFilename(filename string) string {
	// Use filepath.Base to remove any directory components, preventing path traversal.
	filename = filepath.Base(filename)
	// Define a regular expression to match any character that is NOT an alphanumeric,
	// dot (.), underscore (_), or hyphen (-).
	reg := regexp.MustCompile(`[^a-zA-Z0-9._-]`)
	// Replace all matched invalid characters with an underscore.
	return reg.ReplaceAllString(filename, "_")
}

// getFilenameWithoutExtension extracts the base filename from a URL path,
// removing its extension. It returns "file" as a default if parsing fails.
func getFilenameWithoutExtension(urlStr string) string {
	parsedURL, err := url.Parse(urlStr)
	if err != nil {
		return "file" // Default fallback if the URL cannot be parsed.
	}
	filename := filepath.Base(parsedURL.Path) // Get the base filename from the URL path.
	ext := filepath.Ext(filename)             // Get the file extension.
	if ext != "" {
		return strings.TrimSuffix(filename, ext) // Remove the extension if it exists.
	}
	return filename // Return the filename as is if no extension.
}

// isValidSHA256 checks if a given string is a valid SHA256 hash (64 hexadecimal characters).
func isValidSHA256(hash string) bool {
	// Regex to match exactly 64 hexadecimal characters (a-f, 0-9), case-insensitive.
	reg := regexp.MustCompile(`^[a-f0-9]{64}$`)
	return reg.MatchString(hash)
}

// downloadLinkedFiles parses HTML content, identifies linked resources (src/href),
// and attempts to download them locally. It manages directories for downloaded files.
func downloadLinkedFiles(htmlContent []byte, baseServer, hash, dataDir string) ([]FileStatus, []FileStatus) {
	downloadedFiles := []FileStatus{} // List of successfully downloaded or existing files.
	failedDownloads := []FileStatus{}  // List of files that failed to download.

	// Ensure the base data directory exists.
	if err := os.MkdirAll(dataDir, 0755); err != nil {
		failedDownloads = append(failedDownloads, FileStatus{Error: fmt.Sprintf("failed to create base data directory '%s': %v", dataDir, err)})
		return downloadedFiles, failedDownloads // Return early if directory creation fails.
	}

	// Regular expression to find 'href' or 'src' attributes and their values.
	// Removed negative lookahead, will filter in the loop.
	re := regexp.MustCompile(`(?i)(href|src)=["']([^"']+)["']`)
	// Find all occurrences of the pattern in the HTML content.
	matches := re.FindAllStringSubmatch(string(htmlContent), -1)

	// Keep track of URLs that have already been processed to avoid redundant attempts.
	processedURLs := make(map[string]bool)

	for _, match := range matches {
		// match[0] is the full matched string (e.g., 'href="style.css"').
		// match[1] is the attribute name (e.g., 'href', 'src').
		// match[2] is the URL value (e.g., 'style.css').
		attribute := strings.ToLower(match[1]) // Convert attribute name to lowercase for consistent checking.
		rawURL := match[2]                     // The raw URL from the HTML.

		// Skip URLs that are likely not files to download (e.g., data URIs, JavaScript, mailto links, internal anchors).
		// This filtering is now done explicitly here, as the regex no longer handles it.
		if strings.HasPrefix(rawURL, "data:") || strings.HasPrefix(rawURL, "javascript:") || strings.HasPrefix(rawURL, "mailto:") || strings.HasPrefix(rawURL, "#") {
			continue
		}

		// Skip HTML files for 'href' attributes, as these are typically navigation links, not assets.
		if attribute == "href" && (strings.HasSuffix(strings.ToLower(rawURL), ".html") || strings.HasSuffix(strings.ToLower(rawURL), ".htm")) {
			continue
		}

		var targetURL string
		// Determine if the URL is absolute or relative and construct the full target URL.
		if strings.HasPrefix(rawURL, "http://") || strings.HasPrefix(rawURL, "https://") || strings.HasPrefix(rawURL, "//") {
			targetURL = rawURL // Already an absolute URL.
		} else {
			// Handle relative URLs.
			baseURL := strings.TrimRight(baseServer, "/") // Ensure base server has no trailing slash.
			if strings.HasPrefix(rawURL, "/") {
				targetURL = baseURL + rawURL // Root-relative URL.
			} else {
				// Path-relative URL within the /data/<hash>/ context.
				targetURL = fmt.Sprintf("%s/data/%s/%s", baseURL, hash, rawURL)
			}
		}

		// Check if this URL has already been processed in this batch to avoid duplicates.
		if processedURLs[targetURL] {
			continue
		}
		processedURLs[targetURL] = true // Mark as processed.

		// Parse the target URL to extract filename components for local storage.
		parsedTargetURL, err := url.Parse(targetURL)
		if err != nil {
			failedDownloads = append(failedDownloads, FileStatus{URL: targetURL, Error: fmt.Sprintf("failed to parse URL: %v", err)})
			continue
		}

		// Sanitize the filename for the local file system.
		filename := sanitizeFilename(filepath.Base(parsedTargetURL.Path))
		if filename == "" || filename == "." {
			// Fallback for empty or invalid filenames; assume index.html if path ends with '/', otherwise a generic name.
			if strings.HasSuffix(strings.ToLower(parsedTargetURL.Path), "/") {
				filename = "index.html"
			} else {
				filename = "file.txt"
			}
		}

		// Get the filename without extension to create a distinct subdirectory for each asset group.
		filenameWithoutExt := getFilenameWithoutExtension(targetURL)
		fileDir := filepath.Join(dataDir, sanitizeFilename(filenameWithoutExt))

		// Create the specific subdirectory for this file.
		if err := os.MkdirAll(fileDir, 0755); err != nil {
			failedDownloads = append(failedDownloads, FileStatus{URL: targetURL, Error: fmt.Sprintf("failed to create subdirectory '%s': %v", fileDir, err)})
			continue
		}

		// Construct the full local path for the file.
		filePath := filepath.Join(fileDir, filename)

		// Check if the file already exists locally.
		if info, err := os.Stat(filePath); err == nil {
			// File exists, record it as "already_exists".
			downloadedFiles = append(downloadedFiles, FileStatus{
				URL:       targetURL,
				LocalPath: filePath,
				Size:      info.Size(),
				Status:    "already_exists",
			})
			continue // Skip download, move to the next linked file.
		} else if !os.IsNotExist(err) {
			// An error other than "does not exist" occurred during os.Stat.
			failedDownloads = append(failedDownloads, FileStatus{URL: targetURL, Error: fmt.Sprintf("failed to check local file existence: %v", err)})
			continue
		}

		// Download the file content.
		fileContent, err := getContent(targetURL)
		if err != nil {
			failedDownloads = append(failedDownloads, FileStatus{
				URL:   targetURL,
				Error: err.Error(), // Store the error message.
			})
			continue
		}

		// Save the downloaded content to the local file.
		if err := ioutil.WriteFile(filePath, fileContent, 0644); err != nil {
			failedDownloads = append(failedDownloads, FileStatus{
				URL:   targetURL,
				Error: fmt.Sprintf("failed to save file to '%s': %v", filePath, err),
			})
		} else {
			// Successfully downloaded and saved.
			downloadedFiles = append(downloadedFiles, FileStatus{
				URL:       targetURL,
				LocalPath: filePath,
				Size:      int64(len(fileContent)), // Size of the downloaded content.
				Status:    "downloaded",
			})
		}
	}

	return downloadedFiles, failedDownloads // Return the categorized lists of files.
}

func main() {
	// Check if a command-line argument is provided.
	if len(os.Args) < 2 {
		fmt.Println("Usage: go run main.go <text_or_sha256_hash>")
		fmt.Println("Example: go run main.go \"hello world\"")
		fmt.Println("Example: go run main.go \"2cf24dba5fb0a30e26e83b2ac5b9e29e1b161e5c1fa7425e73043362938b9824\"")
		return // Exit if no argument is provided.
	}

	userText := os.Args[1] // The command-line argument provided by the user.

	var hash string
	// Determine if the user input is an SHA256 hash or plain text to be hashed.
	if isValidSHA256(userText) {
		hash = strings.ToLower(userText) // Use the provided hash directly.
		fmt.Printf("Using provided Hash: %s\n", hash)
	} else {
		// Generate SHA256 hash from the user's text.
		hasher := sha256.New()
		hasher.Write([]byte(userText)) // Write the input text to the hasher.
		hash = fmt.Sprintf("%x", hasher.Sum(nil)) // Get the hexadecimal representation of the hash.
		fmt.Printf("Generated Hash: %s\n", hash)
		fmt.Printf("Original Text: \"%s\"\n", userText)
	}

	// Define directory paths for caching and data.
	dataServersDir := "data_servers"            // Directory to store cached index.html files.
	hashDir := filepath.Join(dataServersDir, hash) // Specific directory for the current hash's index.html.
	indexFile := filepath.Join(hashDir, "index.html") // Path to the cached index.html.
	dataDir := "data"                           // Directory to store linked assets.

	// Create the base directory for cached index files if it doesn't exist.
	if err := os.MkdirAll(dataServersDir, 0755); err != nil {
		fmt.Printf("Error: Failed to create directory '%s': %v\n", dataServersDir, err)
		return // Exit if directory creation fails.
	}

	var htmlContent []byte                  // Will store the content of the index.html.
	var successfulServer string             // The server from which index.html was successfully downloaded.
	var downloadedFiles, failedDownloads []FileStatus // Lists for download results.

	// Check if a cached version of the index.html already exists.
	if _, err := os.Stat(indexFile); err == nil {
		fmt.Println("Found cached version.")
		htmlContent, err = ioutil.ReadFile(indexFile) // Read the cached content.
		if err != nil {
			fmt.Printf("Error: Failed to read cached index file '%s': %v\n", indexFile, err)
			return
		}
		// Even if cached, attempt to download linked files to ensure all assets are local.
		fmt.Println("Downloading linked files (from cached HTML, assuming relative paths might be original or need further resolution)...")
		downloadedFiles, failedDownloads = downloadLinkedFiles(htmlContent, "", hash, dataDir)
	} else {
		// No cached version found, try to fetch from servers.
		serversFile := "servers.txt" // File containing list of servers.
		serversBytes, err := ioutil.ReadFile(serversFile)
		if err != nil {
			fmt.Printf("Error: '%s' file not found. Please create it with one server URL per line.\n", serversFile)
			return // Exit if servers.txt is missing.
		}
		// Split the file content into lines to get individual server URLs.
		servers := strings.Split(string(serversBytes), "\n")

		contentSaved := false // Flag to indicate if index.html has been found and saved.
		var originalContent []byte // Store the content as received from the server before modifications.

		foundServers := []struct {
			Original  string
			Processed string
			FullURL   string
		}{} // List of servers where the file was found.

		// Iterate through each server in the list.
		for _, server := range servers {
			server = strings.TrimSpace(server) // Remove leading/trailing whitespace.
			if server == "" {
				continue // Skip empty lines.
			}
			originalServer := server // Store the original server URL.
			urlToCheck := buildProperURL(server, hash, "index.html") // Construct the full URL for index.html.

			fmt.Printf("Checking server: %s\n", urlToCheck)
			pageContent, err := getContent(urlToCheck) // Attempt to fetch content from the server.

			if err == nil {
				// Content successfully retrieved from this server.
				foundServers = append(foundServers, struct {
					Original  string
					Processed string
					FullURL   string
				}{Original: originalServer, Processed: server, FullURL: urlToCheck})

				if !contentSaved {
					// If this is the first successful download of index.html.
					if err := os.MkdirAll(hashDir, 0755); err != nil {
						fmt.Printf("Error: Failed to create hash directory '%s': %v\n", hashDir, err)
						continue // Continue to the next server if directory creation fails.
					}

					originalContent = pageContent // Save the original content before any URL modifications.
					successfulServer = server     // Record the server that provided the content.

					// PHP's logic here modifies relative URLs in the fetched HTML to absolute URLs pointing back
					// to the *original* server. This ensures the cached `index.html` still links to remote assets,
					// even if downloaded locally. We replicate this behavior.
					modifiedPageContent := string(pageContent)
					// Updated regex: no negative lookahead. Now matches all href/src attributes.
					reAttr := regexp.MustCompile(`(?i)(href|src)=["']([^"']+)["']`)
					modifiedPageContent = reAttr.ReplaceAllStringFunc(modifiedPageContent, func(s string) string {
						// Extract the attribute name and the URL value from the match.
						parts := reAttr.FindStringSubmatch(s)
						if len(parts) < 3 {
							return s // Should not happen with a successful regex match.
						}
						attrName := parts[1] // e.g., "href" or "src"
						urlVal := parts[2]   // e.g., "style.css" or "/images/logo.png"

						// Manual check for absolute URLs or data URIs
						if strings.HasPrefix(urlVal, "http://") ||
							strings.HasPrefix(urlVal, "https://") ||
							strings.HasPrefix(urlVal, "//") ||
							strings.HasPrefix(urlVal, "data:") {
							return s // If it's already an absolute or data URI, return the original string.
						}

						baseURL := strings.TrimRight(server, "/") // Base URL for constructing absolute paths.
						var absoluteURL string
						if strings.HasPrefix(urlVal, "/") {
							absoluteURL = baseURL + urlVal // If root-relative, append to base server.
						} else {
							// If path-relative, assume it's relative to the /data/<hash>/ directory on the server.
							absoluteURL = fmt.Sprintf("%s/data/%s/%s", baseURL, hash, urlVal)
						}
						// Return the modified attribute string with the new absolute URL.
						return fmt.Sprintf(`%s="%s"`, attrName, absoluteURL)
					})

					// Save the (potentially modified) index.html content to the local cache.
					if err := ioutil.WriteFile(indexFile, []byte(modifiedPageContent), 0644); err != nil {
						fmt.Printf("Error: Failed to save index file to '%s': %v\n", indexFile, err)
					} else {
						htmlContent = originalContent // Use original content for subsequent linked file download.
						contentSaved = true           // Mark content as saved.
						fmt.Println("File found and saved locally.")
						fmt.Println("Downloading linked files...")
						// Now, download all assets linked in the original (unmodified) HTML content,
						// using the successful server as the base.
						downloadedFiles, failedDownloads = downloadLinkedFiles(htmlContent, successfulServer, hash, dataDir)
					}
				}
			} else {
				fmt.Printf("  Failed to retrieve from %s: %v\n", urlToCheck, err)
			}
			if contentSaved {
				break // Stop checking other servers if the main content is found and saved.
			}
		}

		if len(foundServers) == 0 {
			fmt.Println("File not found on any server.")
		} else {
			fmt.Println("\n--- Servers Where File Was Found ---")
			for _, s := range foundServers {
				fmt.Printf("  Original Server: %s\n", s.Original)
				fmt.Printf("  Full URL Checked: %s\n", s.FullURL)
				fmt.Println("--------------------")
			}
		}
	}

	// Print a summary of download results if any downloads were attempted.
	if len(downloadedFiles) > 0 || len(failedDownloads) > 0 {
		fmt.Println("\n--- Download Summary ---")
		downloadedCount := 0
		alreadyExistsCount := 0
		totalDownloadedSize := int64(0)

		for _, fs := range downloadedFiles {
			if fs.Status == "downloaded" {
				downloadedCount++
			} else if fs.Status == "already_exists" {
				alreadyExistsCount++
			}
			totalDownloadedSize += fs.Size
		}

		fmt.Printf("Downloaded: %d\n", downloadedCount)
		fmt.Printf("Already Existed: %d\n", alreadyExistsCount)
		fmt.Printf("Failed: %d\n", len(failedDownloads))
		fmt.Printf("Total Downloaded Size: %.1f KB\n", float64(totalDownloadedSize)/1024.0)

		if len(downloadedFiles) > 0 {
			fmt.Println("\n--- File Status Details ---")
			for _, fs := range downloadedFiles {
				statusText := fs.Status
				if fs.Status == "already_exists" {
					statusText = "Already Exists"
				} else if fs.Status == "downloaded" {
					statusText = "Downloaded"
				}
				fmt.Printf("  URL: %s\n    Status: %s\n    Size: %.1f KB\n", fs.URL, statusText, float64(fs.Size)/1024.0)
				if fs.LocalPath != "" {
					fmt.Printf("    Local Path: %s\n", fs.LocalPath)
				}
				fmt.Println("----")
			}
		}

		if len(failedDownloads) > 0 {
			fmt.Println("\n--- Failed Downloads ---")
			for _, fs := range failedDownloads {
				fmt.Printf("  URL: %s\n    Error: %s\n", fs.URL, fs.Error)
				fmt.Println("----")
			}
		}
	}

	// Provide instructions to the user on where to find the local HTML file.
	if _, err := os.Stat(indexFile); err == nil {
		fmt.Printf("\n--- Local HTML File ---")
		fmt.Printf("\nYou can open the main HTML file locally at: %s\n", indexFile)
		fmt.Println("Note: This file might still link to assets on the original server, even if the assets are also downloaded locally.")
		fmt.Printf("Downloaded assets (like images, CSS, JS) are stored in the '%s' directory.\n", dataDir)
		fmt.Println("-----------------------")
	}
}

