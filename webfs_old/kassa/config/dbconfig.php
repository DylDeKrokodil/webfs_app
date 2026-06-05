<?php
    define("DB_SERVERNAME", getenv("DB_HOST") ?: "localhost");
    define("DB_DBNAME", getenv("DB_DATABASE") ?: "gouden_draak");
    define("DB_USERNAME", getenv("DB_USERNAME") ?: "root");
    define("DB_PASSWORD", getenv("DB_PASSWORD") ?: "");
?>
