<?php header('Content-Type: text/html; charset=UTF-8'); ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escapeHtml($title) ?> | СТО</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="site-header">
        <nav aria-label="Разделы сайта">
            <a href="index.php">СТО</a>
            <a href="master_form.php">Мастера</a>
            <a href="schedule_form.php">График</a>
            <a href="appointment_form.php">Запись</a>
        </nav>
    </header>
    <main>
        <h1><?= escapeHtml($title) ?></h1>
        <?php if (($notice ?? '') !== ''): ?>
            <p class="notice" role="status"><?= escapeHtml($notice) ?></p>
        <?php endif; ?>
        <?php if (($error ?? '') !== ''): ?>
            <p class="error" role="alert"><?= escapeHtml($error) ?></p>
        <?php endif; ?>
