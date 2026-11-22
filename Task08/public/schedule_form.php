<?php

declare(strict_types=1);

require __DIR__ . '/../src/forms.php';
require __DIR__ . '/../src/database.php';

startFormSession();
$db = openDatabase();
$today = substr(stationNow($db), 0, 10);
$masters = fetchMasters($db, $today);
$masterId = null;
$workDate = $today;
$startTime = '09:00';
$endTime = '18:00';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyFormToken();
    $masterId = positiveId($_POST['master_id'] ?? null);
    $workDate = inputText($_POST, 'work_date');
    $startTime = inputText($_POST, 'start_time');
    $endTime = inputText($_POST, 'end_time');

    try {
        if ($masterId === null || !isset($masters[$masterId])) {
            throw new InvalidArgumentException('Выберите мастера из списка.');
        }
        $master = $masters[$masterId];
        if (!validDate($workDate) || $workDate < $master['hired_on']
            || ($master['dismissed_on'] !== null && $workDate > $master['dismissed_on'])) {
            throw new InvalidArgumentException('Дата смены должна входить в период работы мастера.');
        }
        if (!validTime($startTime) || !validTime($endTime) || $startTime >= $endTime) {
            throw new InvalidArgumentException('Начало смены должно быть раньше окончания.');
        }

        addShift($db, $masterId, $workDate, $startTime, $endTime);
        redirectWithNotice('schedule_form.php', 'Смена сохранена.');
    } catch (InvalidArgumentException $exception) {
        http_response_code(422);
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        http_response_code(500);
        $error = 'Не удалось сохранить смену. Попробуйте ещё раз.';
    }
}

$title = 'Добавить смену';
$notice = takeNotice();
require __DIR__ . '/../templates/header.php';
?>
        <form method="post" action="schedule_form.php">
            <input type="hidden" name="form_token" value="<?= escapeHtml($_SESSION['form_token']) ?>">
            <div class="fields">
                <label for="master_id">Мастер
                    <select id="master_id" name="master_id" required>
                        <option value="">Выберите мастера</option>
                        <?php foreach ($masters as $id => $master): ?>
                            <option value="<?= $id ?>"<?= $id === $masterId ? ' selected' : '' ?>><?= escapeHtml(fullName($master)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label for="work_date">Дата смены
                    <input type="date" id="work_date" name="work_date" required value="<?= escapeHtml($workDate) ?>">
                </label>
                <label for="start_time">Начало смены
                    <input type="time" id="start_time" name="start_time" required value="<?= escapeHtml($startTime) ?>">
                </label>
                <label for="end_time">Конец смены
                    <input type="time" id="end_time" name="end_time" required value="<?= escapeHtml($endTime) ?>">
                </label>
            </div>
            <button type="submit">Сохранить смену</button>
        </form>
<?php require __DIR__ . '/../templates/footer.php'; ?>
