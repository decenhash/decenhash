package main

import (
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"io"
	"io/ioutil"
	"os"
	"path/filepath"
	"regexp"
	"strings"
)

func checkSHA256(input string) string {
	sha256Regex := regexp.MustCompile(`^[a-f0-9]{64}$`)
	if sha256Regex.MatchString(input) {
		return input
	}
	hash := sha256.Sum256([]byte(input))
	return hex.EncodeToString(hash[:])
}

func main() {
	// Configuration
	uploadDirBase := "data"     // Base directory for all uploads
	sourceDir := "categories" // Directory containing subdirectories with files to process

	// Get all subdirectories in the source directory
	subdirectories, err := getSubdirectories(sourceDir)
	if err != nil {
		fmt.Printf("<p class='error'>Error reading source directory: %v</p>\n", err)
		os.Exit(1)
	}

	if len(subdirectories) == 0 {
		fmt.Println("<p class='error'>No subdirectories found in the 'categories' directory.</p>")
		os.Exit(1)
	}

	// Process each subdirectory
	for _, subdirectory := range subdirectories {
		categoryText := strings.ToLower(filepath.Base(subdirectory))

		// Get all files in the subdirectory
		files, err := getFilesInDirectory(subdirectory)
		if err != nil {
			fmt.Printf("<p class='error'>Error reading files in subdirectory %s: %v</p>\n", categoryText, err)
			continue
		}

		if len(files) == 0 {
			fmt.Printf("<p class='notice'>No files found in subdirectory: %s</p>\n", categoryText)
			continue
		}

		// Process each file in the subdirectory
		for _, filePath := range files {
			originalFileName := filepath.Base(filePath)
			fileExtension := strings.ToLower(filepath.Ext(originalFileName))
			if len(fileExtension) > 0 {
				fileExtension = fileExtension[1:] // Remove the dot
			}

			if fileExtension == "php" {
				fmt.Printf("<p class='error'>Skipping PHP file: %s</p>\n", originalFileName)
				continue
			}

			// Read file content
			fileContent, err := ioutil.ReadFile(filePath)
			if err != nil {
				fmt.Printf("<p class='error'>Error reading file: %s: %v</p>\n", originalFileName, err)
				continue
			}

			// Calculate SHA256 hashes
			hash := sha256.Sum256(fileContent)
			fileHash := hex.EncodeToString(hash[:])
			categoryHash := checkSHA256(categoryText)
			fileNameWithExtension := fileHash + "." + fileExtension

			// Construct directory paths
			fileUploadDir := filepath.Join(uploadDirBase, fileHash)
			categoryDir := filepath.Join(uploadDirBase, categoryHash)

			// Create directories if they don't exist
			if err := createDirIfNotExist(uploadDirBase); err != nil {
				fmt.Printf("<p class='error'>Error creating base directory: %v</p>\n", err)
				continue
			}
			if err := createDirIfNotExist(fileUploadDir); err != nil {
				fmt.Printf("<p class='error'>Error creating file directory: %v</p>\n", err)
				continue
			}
			if err := createDirIfNotExist(categoryDir); err != nil {
				fmt.Printf("<p class='error'>Error creating category directory: %v</p>\n", err)
				continue
			}

			// Save the content
			destinationFilePath := filepath.Join(fileUploadDir, fileNameWithExtension)
			if _, err := os.Stat(destinationFilePath); err == nil {
				fmt.Printf("<p class='notice'>File already exists, skipping: %s</p>\n", originalFileName)
				continue
			}

			if err := copyFile(filePath, destinationFilePath); err != nil {
				fmt.Printf("<p class='error'>Error saving file: %s: %v</p>\n", originalFileName, err)
				continue
			}

			// Create empty file in category folder
			categoryFilePath := filepath.Join(categoryDir, fileNameWithExtension)
			if _, err := os.Stat(categoryFilePath); os.IsNotExist(err) {
				if err := ioutil.WriteFile(categoryFilePath, []byte{}, 0644); err != nil {
					fmt.Printf("<p class='error'>Error creating empty file in category folder for: %s: %v</p>\n", originalFileName, err)
					continue
				}
			}

			contentHead := `<link rel='stylesheet' href='../../default.css'><script src='../../default.js'></script><script src='../../ads.js'></script><div id='ads' name='ads' class='ads'></div><div id='default' name='default' class='default'></div>`

			// Handle index.html inside file hash folder
			indexPathFileFolder := filepath.Join(fileUploadDir, "index.html")
			if _, err := os.Stat(indexPathFileFolder); os.IsNotExist(err) {
				if err := ioutil.WriteFile(indexPathFileFolder, []byte(contentHead), 0644); err != nil {
					fmt.Printf("<p class='error'>Error creating index.html in file folder: %v</p>\n", err)
					continue
				}
			}

			linkReply := fmt.Sprintf("<a href='../../index.php?reply=%s'>[ Reply ]</a> ", fileHash)
			linkToHash := fmt.Sprintf("%s<a href='../%s/index.html'>[ Open ]</a> ", linkReply, fileHash)
			linkToFileFolderIndex := fmt.Sprintf("%s<a href='%s'>%s</a><br>", linkToHash, fileNameWithExtension, originalFileName)

			indexContentFileFolder, err := ioutil.ReadFile(indexPathFileFolder)
			if err != nil {
				fmt.Printf("<p class='error'>Error reading index.html in file folder: %v</p>\n", err)
				continue
			}

			if !strings.Contains(string(indexContentFileFolder), linkToFileFolderIndex) {
				if err := ioutil.WriteFile(indexPathFileFolder, append(indexContentFileFolder, []byte(linkToFileFolderIndex)...), 0644); err != nil {
					fmt.Printf("<p class='error'>Error updating index.html in file folder: %v</p>\n", err)
					continue
				}
			}

			// Handle index.html inside category folder
			indexPathCategoryFolder := filepath.Join(categoryDir, "index.html")
			if _, err := os.Stat(indexPathCategoryFolder); os.IsNotExist(err) {
				if err := ioutil.WriteFile(indexPathCategoryFolder, []byte(contentHead), 0644); err != nil {
					fmt.Printf("<p class='error'>Error creating index.html in category folder: %v</p>\n", err)
					continue
				}
			}

			relativePathToFile := fmt.Sprintf("../%s/%s", fileHash, fileNameWithExtension)
			categoryReply := fmt.Sprintf("<a href='../../index.php?reply=%s'>[ Reply ]</a> ", fileHash)
			linkToHashCategory := fmt.Sprintf("%s<a href='../%s/index.html'>[ Open ]</a> ", categoryReply, fileHash)
			linkToCategoryFolderIndex := fmt.Sprintf("%s<a href='%s'>%s</a><br>", linkToHashCategory, relativePathToFile, originalFileName)

			indexContentCategoryFolder, err := ioutil.ReadFile(indexPathCategoryFolder)
			if err != nil {
				fmt.Printf("<p class='error'>Error reading index.html in category folder: %v</p>\n", err)
				continue
			}

			if !strings.Contains(string(indexContentCategoryFolder), linkToCategoryFolderIndex) {
				if err := ioutil.WriteFile(indexPathCategoryFolder, append(indexContentCategoryFolder, []byte(linkToCategoryFolderIndex)...), 0644); err != nil {
					fmt.Printf("<p class='error'>Error updating index.html in category folder: %v</p>\n", err)
					continue
				}
			}

			fmt.Printf("<p class='success'>Processed file: %s in category: %s</p>\n", originalFileName, categoryText)
		}
	}

	fmt.Println("<p class='success'>All files processed successfully!</p>")
}

// Helper functions
func getSubdirectories(dir string) ([]string, error) {
	var subdirs []string
	entries, err := ioutil.ReadDir(dir)
	if err != nil {
		return nil, err
	}

	for _, entry := range entries {
		if entry.IsDir() {
			subdirs = append(subdirs, filepath.Join(dir, entry.Name()))
		}
	}

	return subdirs, nil
}

func getFilesInDirectory(dir string) ([]string, error) {
	var files []string
	entries, err := ioutil.ReadDir(dir)
	if err != nil {
		return nil, err
	}

	for _, entry := range entries {
		if !entry.IsDir() {
			files = append(files, filepath.Join(dir, entry.Name()))
		}
	}

	return files, nil
}

func createDirIfNotExist(dir string) error {
	if _, err := os.Stat(dir); os.IsNotExist(err) {
		return os.MkdirAll(dir, 0755)
	}
	return nil
}

func copyFile(src, dst string) error {
	source, err := os.Open(src)
	if err != nil {
		return err
	}
	defer source.Close()

	destination, err := os.Create(dst)
	if err != nil {
		return err
	}
	defer destination.Close()

	_, err = io.Copy(destination, source)
	return err
}