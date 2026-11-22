<?php

declare(strict_types=1);

require dirname(__DIR__) . '/repository.php';

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

header('Content-Type: text/html; charset=UTF-8');

$masters = [];
$works = [];
$masterId = null;
$error = null;

try {
    $db = openDatabase();
    $masters = fetchMasters($db);
    $masterId = selectedMasterId($_GET['master_id'] ?? '', $masters);
    $works = fetchWorks($db, $masterId);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    $error = $exception->getMessage();
} catch (PDOException $exception) {
    http_response_code(500);
    $error = 'Не удалось прочитать базу данных.';
} catch (RuntimeException $exception) {
    http_response_code(500);
    $error = $exception->getMessage();
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Оказанные услуги СТО</title>
    <style>
        body {
            margin: 0;
            background: #f3f6f8;
            color: #233243;
            font: 16px/1.5 system-ui, sans-serif;
        }
        main {
            max-width: 980px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        h1 { margin-bottom: 1rem; }
        form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .6rem;
            margin: 0 0 1.25rem;
        }
        select, button {
            box-sizing: border-box;
            max-width: 100%;
            padding: .45rem .7rem;
            font: inherit;
        }
        button {
            border: 1px solid #234e72;
            border-radius: 4px;
            background: #234e72;
            color: #fff;
            cursor: pointer;
        }
        .table-wrap { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        th, td {
            padding: .65rem .75rem;
            border: 1px solid #d6dfe7;
            text-align: left;
        }
        th { background: #e4ecf3; }
        td:nth-child(3), td:last-child { white-space: nowrap; }
        td:last-child { text-align: right; }
        .error { color: #a22222; }
    </style>
</head>
<body>
<main>
    <h1>Оказанные услуги СТО</h1>

    <form method="get" action="index.php">
        <label for="master_id">Мастер</label>
        <select name="master_id" id="master_id">
            <option value=""<?= $masterId === null ? ' selected' : '' ?>>Все мастера</option>
            <?php foreach ($masters as $id => $name): ?>
                <option value="<?= $id ?>"<?= $masterId === $id ? ' selected' : '' ?>>
                    <?= $id ?>. <?= escapeHtml($name) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Показать</button>
    </form>

    <?php if ($error !== null): ?>
        <p class="error" role="alert"><?= escapeHtml($error) ?></p>
    <?php elseif ($works === []): ?>
        <p>Оказанных услуг нет.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">№ мастера</th>
                        <th scope="col">ФИО</th>
                        <th scope="col">Дата работы</th>
                        <th scope="col">Услуга</th>
                        <th scope="col">Стоимость, руб.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($works as $work): ?>
                        <tr>
                            <td><?= $work['master_id'] ?></td>
                            <td><?= escapeHtml($work['master_name']) ?></td>
                            <td><?= escapeHtml($work['work_date']) ?></td>
                            <td><?= escapeHtml($work['service_name']) ?></td>
                            <td><?= escapeHtml($work['price']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
