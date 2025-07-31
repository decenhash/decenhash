<?php

/*/

    This script is basically a simple ranking system without MySQL.

/*/

// Counts the number of files in each 'data' subdirectory
include ("files_count.php");

// Counts the number of files of each user folderS
include ("users_count.php");

// Organizes and displays the result in descending order
include ("final_count.php");

?>
