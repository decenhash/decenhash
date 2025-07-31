package main

import (
	"crypto/sha256"
	"fmt"
	"html/template"
	"io/ioutil" // Use ioutil for compatibility with older Go versions
	"log"
	"net/http"
	"os"
	"path/filepath"
)

const (
	PORT               = "8080"
	MAX_FILE_SIZE_MB   = 10
	MAX_FILE_SIZE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024
	UPLOAD_DIR         = "files"
	FORBIDDEN_EXTENSION = ".php"
)

// Data structure to pass messages to the HTML template.
type PageData struct {
	Message string
}

// Main function to set up the server and routes.
func main() {
	// Create the upload directory if it doesn't exist.
	if err := os.MkdirAll(UPLOAD_DIR, os.ModePerm); err != nil {
		log.Fatalf("Error creating upload directory: %v", err)
	}

	// Set up the HTTP handlers.
	// http.HandleFunc handles routing. Go's net/http server handles
	// each request in a separate goroutine, providing concurrency automatically.
	http.HandleFunc("/", rootHandler)
	http.HandleFunc("/upload", uploadHandler)

	fmt.Printf("Server started on port %s\n", PORT)
	fmt.Printf("Open http://localhost:%s in your browser.\n", PORT)

	// Start the HTTP server.
	if err := http.ListenAndServe(":"+PORT, nil); err != nil {
		log.Fatalf("Server error: %v", err)
	}
}

// rootHandler serves the main page with the file upload form.
// It displays a message if one is passed as a query parameter.
func rootHandler(w http.ResponseWriter, r *http.Request) {
	// Basic security check: only allow GET requests for the root page.
	if r.Method != "GET" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	// Get the message from the URL query to display on the page.
	message := r.URL.Query().Get("message")
	data := PageData{Message: message}

	// A simple HTML template for the upload form.
	const htmlTemplate = `
	<!DOCTYPE html>
	<html>
	<head>
		<title>File Upload</title>
	</head>
	<body>
		<h1>Upload a File</h1>
		<form action="/upload" method="post" enctype="multipart/form-data">
			<input type="file" name="fileToUpload" id="fileToUpload">
			<input type="submit" value="Upload File" name="submit">
		</form>
		{{if .Message}}
			<p>{{.Message}}</p>
		{{end}}
	</body>
	</html>`

	// Parse and execute the template.
	t, err := template.New("uploadPage").Parse(htmlTemplate)
	if err != nil {
		http.Error(w, "Internal Server Error", http.StatusInternalServerError)
		log.Printf("Error parsing template: %v", err)
		return
	}

	// Write the HTML to the response.
	err = t.Execute(w, data)
	if err != nil {
		http.Error(w, "Internal Server Error", http.StatusInternalServerError)
		log.Printf("Error executing template: %v", err)
	}
}

// uploadHandler processes the file upload.
func uploadHandler(w http.ResponseWriter, r *http.Request) {
	// Only allow POST requests for uploads.
	if r.Method != "POST" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	// Parse the multipart form, with a limit on the memory used for parts.
	// The file data itself is stored in a temporary file on disk if it's larger
	// than the specified memory limit.
	if err := r.ParseMultipartForm(MAX_FILE_SIZE_BYTES); err != nil {
		message := fmt.Sprintf("Error: File is too large. Maximum size is %d MB.", MAX_FILE_SIZE_MB)
		http.Redirect(w, r, "/?message="+template.URLQueryEscaper(message), http.StatusSeeOther)
		return
	}

	// Retrieve the file from the form data.
	file, handler, err := r.FormFile("fileToUpload")
	if err != nil {
		// Handle case where no file is selected.
		if err == http.ErrMissingFile {
			http.Redirect(w, r, "/?message="+template.URLQueryEscaper("Error: No file selected for upload."), http.StatusSeeOther)
			return
		}
		http.Error(w, "Error retrieving the file", http.StatusInternalServerError)
		log.Printf("Error getting form file: %v", err)
		return
	}
	defer file.Close()

	// Check for forbidden extension.
	originalFilename := handler.Filename
	extension := filepath.Ext(originalFilename)
	if extension == FORBIDDEN_EXTENSION {
		message := fmt.Sprintf("Error: Files with '%s' extension are not allowed.", FORBIDDEN_EXTENSION)
		http.Redirect(w, r, "/?message="+template.URLQueryEscaper(message), http.StatusSeeOther)
		return
	}

	// Read the file content into a byte slice using ioutil.ReadAll for compatibility.
	fileData, err := ioutil.ReadAll(file)
	if err != nil {
		http.Error(w, "Error reading the file", http.StatusInternalServerError)
		log.Printf("Error reading file data: %v", err)
		return
	}

	// Check file size again, as ParseMultipartForm checks the whole request size.
	if len(fileData) > MAX_FILE_SIZE_BYTES {
		message := fmt.Sprintf("Error: File is too large. Maximum size is %d MB.", MAX_FILE_SIZE_MB)
		http.Redirect(w, r, "/?message="+template.URLQueryEscaper(message), http.StatusSeeOther)
		return
	}

	if len(fileData) == 0 {
		http.Redirect(w, r, "/?message="+template.URLQueryEscaper("Error: Cannot upload an empty file."), http.StatusSeeOther)
		return
	}

	// Calculate SHA-256 hash.
	hash := sha256.Sum256(fileData)
	hashStr := fmt.Sprintf("%x", hash)

	// Create the new filename and destination path.
	newFilename := hashStr + extension
	destinationPath := filepath.Join(UPLOAD_DIR, newFilename)

	// Check if the file already exists.
	if _, err := os.Stat(destinationPath); err == nil {
		message := fmt.Sprintf("File already exists on the server (hash: %s). Not saved again.", hashStr)
		http.Redirect(w, r, "/?message="+template.URLQueryEscaper(message), http.StatusSeeOther)
		return
	}

	// Create and write the file to the destination using ioutil.WriteFile for compatibility.
	err = ioutil.WriteFile(destinationPath, fileData, 0644)
	if err != nil {
		http.Error(w, "Error saving the file", http.StatusInternalServerError)
		log.Printf("Error writing file to disk: %v", err)
		return
	}

	// Redirect back to the main page with a success message.
	message := fmt.Sprintf("File uploaded successfully! Saved as %s", newFilename)
	http.Redirect(w, r, "/?message="+template.URLQueryEscaper(message), http.StatusSeeOther)
}
