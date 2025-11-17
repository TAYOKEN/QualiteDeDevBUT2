<?php
	$pdo = new PDO("mysql:host=localhost;dbname=nom_base;charset=utf8", "root", ""); //mettre le nom de la base de données à la place de nom_base
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>
