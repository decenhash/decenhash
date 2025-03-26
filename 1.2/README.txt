# Overview

The goal of the project is to implement a system that can verify which servers host the same file and, based on that, 
develop mechanisms to reward users the more their files are distributed. There are at least two approaches that align with this idea.

## Centralized Approach
The user's file is associated with a SHA-256 hash and stored on the server. The criterion for defining whether a file is popular is the number of comments 
or responses it receives (associations with other files). The user can spend a credit point to comment, upload a file, or do so for free with temporary storage.

## Decentralized Approach
A list of servers is checked to see if the filename (which is a hash) matches the actual hash of the file. Then, in a distributed database or something 
similar, there will be file information, ownership details, and metadata.

## Key Features
An interesting aspect of this implementation is that both centralized and decentralized servers store files in the same way—that is, by saving them inside 
the data directory in a subdirectory whose name is the hash of the file. For example:

data/e3dd699f7432e52a74e328e04f8220a171a38bff70ab68d3b0c86ec6d6ba1c04/e3dd699f7432e52a74e328e04f8220a171a38bff70ab68d3b0c86ec6d6ba1c04.jpg

The protocol for this system is HTTP (https). The idea is that a node or server should be able to run the service even if it is just a simple web server 
hosting files. In other words, if a server that hosts files becomes very popular, it could gain access to rewards.

# DecenHash - Distributed File Sharing and Verification System

A distributed file sharing and verification system that allows users to upload, categorize, and verify files across multiple servers. The system consists of two main components:

1. **PHP File Sharing Component (index.php)** - A web-based interface for uploading and categorizing files
2. **Java File Verification Tool (FileVerificationGUI.java)** - A GUI application for verifying file integrity across multiple servers and distributing rewards

## System Overview

This system enables a decentralized approach to file sharing where:

- Files are uniquely identified and verified using SHA-256 hashing
- Content is organized through a categorization system with bidirectional links
- File integrity is verified across multiple servers
- Rewards can be distributed based on file availability across the network

## PHP File Sharing Component

### Features

- Upload files or text content through a web interface
- Categorize content using a tag/category system
- Content organization based on SHA256 hashing
- Bidirectional linking between content and categories
- Reply functionality for nested discussions
- Automatic index generation for easy navigation

### Directory Structure

```
data/
+-- [content_hash1]/
¦   +-- [content_hash1].[extension]
¦   +-- index.html
+-- [content_hash2]/
¦   +-- [content_hash2].[extension]
¦   +-- index.html
+-- [category_hash1]/
¦   +-- index.html
+-- [category_hash2]/
    +-- index.html
```

### How It Works

1. When a file or text is uploaded, a SHA256 hash of the content is generated
2. Each piece of content is stored in a directory named after its hash
3. Categories are either provided as SHA256 hashes or converted to hashes
4. The system creates bidirectional links between content and categories

## Java File Verification Tool

### Features

- Verify file integrity across multiple servers using SHA-256 hashing
- Track which servers host verified files
- Calculate distribution statistics for files across the network
- Distribute rewards based on file availability percentage
- Download and locally store verified files
- Process individual files or batch lists

### How It Works

1. The tool reads lists of servers from the `servers` directory
2. It processes files either from a `files.txt` list or a selected individual file
3. For each file, it:
   - Checks if the file exists on each server
   - Downloads the file and verifies its SHA-256 hash matches the filename
   - Records which servers have the verified file
   - Stores the verified file locally
4. The tool calculates statistics and can distribute rewards based on server participation

### User Interface

The Java application provides a GUI with:
- File selection options (individual or list-based)
- Money value input for reward distribution
- Progress tracking
- Detailed logs of the verification process
- Tabbed views for server statistics, file statistics, and money distribution

## Installation

### PHP Component

1. Upload the PHP files to a web server with PHP support
2. Ensure the web server has write permissions to create directories
3. Access the application through your web browser

```bash
# Create the data directory with proper permissions
mkdir data
chmod 755 data/
```

### Java Component

1. Compile the Java code or use the provided JAR file
2. Create a `servers` directory in the same location as the Java application
3. Add server list files to the `servers` directory (each file should contain server URLs, one per line)
4. Optionally create a `files.txt` file with a list of filenames to verify

```bash
# Create required directories
mkdir servers
mkdir data
```

## Usage

### PHP File Sharing

1. Access the main page in your web browser
2. Upload a file or enter text content
3. Provide a category for the content
4. Submit the form to process the upload

### Java File Verification

1. Launch the Java application
2. Choose between using a file list or selecting an individual file
3. If distributing rewards, enter the monetary value in the designated field
4. Click "Start Processing" to begin verification
5. Review the results in the log area and statistics tabs

## Integration Between Components

The system is designed to work together:

1. Files uploaded through the PHP component are stored with their SHA-256 hash as the directory name
2. The Java verification tool can check these files across multiple servers running the PHP component
3. Both components use the same directory structure for storing files and verification information

## Security Considerations

- The PHP component blocks PHP file uploads to prevent potential security exploits
- Content integrity is verified using SHA-256 hashing
- The Java component verifies files before storing them locally
- Consider implementing additional security measures for production use:
  - User authentication and authorization
  - Rate limiting
  - Content validation
  - Secure server communication

## File Formats

- Files are stored using their SHA-256 hash as the directory name
- Each file's name includes its hash to ensure integrity
- Server addresses are stored as files named with the SHA-256 hash of the address

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

MIT



We are not responsible for the use that third parties make of our software, nor for any damages, failures, or errors. 
By using our code or software, you agree to the terms of use.