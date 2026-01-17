<?php
session_start();
session_destroy(); // Oturumu bitir
header("Location: index.php"); // Ana sayfaya at
?>