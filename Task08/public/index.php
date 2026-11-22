<?php

declare(strict_types=1);

require __DIR__ . '/../src/forms.php';

$title = 'Формы СТО';
require __DIR__ . '/../templates/header.php';
?>
        <ul class="page-links">
            <li><a href="master_form.php">Добавить мастера</a></li>
            <li><a href="schedule_form.php">Добавить смену</a></li>
            <li><a href="appointment_form.php">Записать на обслуживание</a></li>
        </ul>
<?php require __DIR__ . '/../templates/footer.php'; ?>
