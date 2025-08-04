<?php session_start(); ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Upload</title>
 
    <style>
        body {
            font-family: 'Inter', sans-serif;
            padding: 5px 0px 5px 50px;
        }

    </style>
</head>
<body>

<h1>Decenhash</h1>
<hr>
<br>
<div>
<a href="upload.php"">Simple upload</a><br>
<a href="blockchain.php"">Blockchain</a><br>
<a href="main_tools/server.php">Add server information</a><br>
<br>
<div style="font-size: 10px"; align="">
Indexer and tools : <a href="main_tools/db.php">Graph,</a>
<a href="search_block.php">Search,</a>
<a href="main_tools/index_search.html">JSON,</a>
<a href="main_tools/index_simple.php">HTML,</a>
<a href="php_others/upload/index.php">MySQL</a><br>
</div>
</div>
<br>
<i>Notice: Create the database (DB) "decenhash" to use the MySQL scripts.</i>
<br><br>
<ul>
   <i><h2>Welcome to Decenhash</h2></i>  
  <li><strong>Objective</strong> : Create a cryptocurrency or blockchain based in file sharing.</li><br>
  <li><strong>Blocks work as indexing</strong> : The blocks are saved in JSON files with metadata information.<br> This provides ease and convenience, as each block serves to index file information and check whether it already exists. (deduplication)</li><br>
  <li><strong>Boost or invest in files</strong> : When registering or adding a file, users can deposit a value. This will increase the file's rating or priority in search and other operations.</li><br>
  <li><strong>Proof of Work or Storage</strong> : To prove that computational work was performed before adding a block, the user must provide a valid URL containing a correctly formatted file. (The SHA-256 hash of the file must match the file name)</li><br>
  <li><strong>Flexibility</strong> : A node can run even on a very simple server. (hosting only files)</li>
</ul>
<br>
<i>All righst reserved</i>

</body>
</html>
