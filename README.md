# Decenhash - File Sharing Project

Decenhash is a file sharing project that provides standards and conventions to easily identify which servers host the same files and enable efficient sharing. The system uses SHA-256 hashes for file identification and supports categorization for better organization.

## Features

- All files are saved using their SHA-256 hash as the filename (plus extension)
- Files are stored in the "data" directory in hash-based subdirectories (e.g., `data/123/123.jpg`)
- Search for files by hash or explore by categories
- Distributed server architecture for redundancy
- Blockchain-inspired structure for content verification

## How It Works

### Components

1. **Index/FileUploadServer**
   - Uploaded files are renamed using their SHA-256 hash
   - Files can be assigned to categories for organization
   - Files are accessible via their hash or category

2. **Data Builder**
   - Processes files in the "categories" directory
   - Copies files to the "data" directory with hash-based naming
   - Supports hierarchical categorization (e.g., music/images/videos)

3. **Servers**
   - Verifies file availability across servers listed in `servers.txt`
   - Creates records of which servers host which files
   - Outputs to "servers" directory with hash-server mappings

4. **Download**
   - Similar to Servers component but also downloads missing files
   - Ensures local availability of requested files

5. **Downloader**
   - Searches servers and downloads files by hash or category
   - Provides bulk download capabilities

6. **Blockchain**
   - Implements basic block structure for content
   - Distributes user-submitted content to servers
   - *(Note: Currently doesn't implement full consensus mechanism)*

## Getting Started

### Prerequisites
- Web server with PHP support
- Basic file system permissions
- Some scripts with Java and Golang version (optional)

### Installation
1. Clone this repository to your web server
2. Ensure the "data" and "categories" directories are writable
3. Configure your server to point to the project directory

## Usage

Access the following pages in your web browser:
- `index.php` - File upload interface
- `add_server.php` - Add new servers to the network
- `downloader.php` - Download files by hash or category
- `blockchain.php` - Blockchain content submission interface

## Community

Join our community:
- [WhatsApp Group](https://chat.whatsapp.com/GbT2LBn2AGG1mw3vTreIWr)
- [Telegram Channel](https://t.me/decenhash)
- Email: decenhash@gmail.com

## License

MIT

## Acknowledgments

- Inspired by distributed file sharing systems
- Uses SHA-256 for content-addressable storage
