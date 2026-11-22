<?php

declare(strict_types=1);

require __DIR__ . '/../src/forms.php';
require __DIR__ . '/../src/database.php';

startFormSession();
$db = openDatabase();
$services = fetchServices($db);
$values = [
    'last_name' => '',
    'first_name' => '',
    'middle_name' => '',
    'hired_on' => substr(stationNow($db), 0, 10),
    'salary_percent' => '30',
];
$serviceIds = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyFormToken();
    foreach (array_keys($values) as $key) {
        $values[$key] = inputText($_POST, $key);
    }

    try {
        $selectedServices = $_POST['service_ids'] ?? [];
        if (!is_array($selectedServices) || $selectedServices === []) {
            throw new InvalidArgumentException('Выберите хотя бы одну услугу мастера.');
        }
        foreach ($selectedServices as $selectedService) {
            $serviceId = positiveId($selectedService);
            if ($serviceId === null || !isset($services[$serviceId])) {
                throw new InvalidArgumentException('Выберите услуги из списка.');
            }
            $serviceIds[$serviceId] = $serviceId;
        }

        if (!validText($values['last_name'], 80) || !validText($values['first_name'], 80)
            || ($values['middle_name'] !== '' && !validText($values['middle_name'], 80))) {
            throw new InvalidArgumentException('Укажите фамилию и имя. В каждом поле допустимо до 80 символов.');
        }
        if (!validDate($values['hired_on'])) {
            throw new InvalidArgumentException('Укажите корректную дату найма.');
        }
        $salary = str_replace(',', '.', $values['salary_percent']);
        if (!is_numeric($salary) || (float) $salary < 0 || (float) $salary > 100) {
            throw new InvalidArgumentException('Процент от стоимости работ должен быть от 0 до 100.');
        }

        $master = $values;
        $master['middle_name'] = $values['middle_name'] === '' ? null : $values['middle_name'];
        $master['salary_percent'] = $salary;
        addMaster($db, $master, $serviceIds);
        redirectWithNotice('master_form.php', 'Мастер добавлен.');
    } catch (InvalidArgumentException $exception) {
        http_response_code(422);
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        http_response_code(500);
        $error = 'Не удалось сохранить мастера. Попробуйте ещё раз.';
    }
}

$title = 'Добавить мастера';
$notice = takeNotice();
require __DIR__ . '/../templates/header.php';
?>
        <form method="post" action="master_form.php">
            <input type="hidden" name="form_token" value="<?= escapeHtml($_SESSION['form_token']) ?>">
            <div class="fields">
                <label for="last_name">Фамилия
                    <input id="last_name" name="last_name" maxlength="80" required value="<?= escapeHtml($values['last_name']) ?>">
                </label>
                <label for="first_name">Имя
                    <input id="first_name" name="first_name" maxlength="80" required value="<?= escapeHtml($values['first_name']) ?>">
                </label>
                <label for="middle_name">Отчество
                    <input id="middle_name" name="middle_name" maxlength="80" value="<?= escapeHtml($values['middle_name']) ?>">
                </label>
                <label for="hired_on">Дата найма
                    <input id="hired_on" name="hired_on" type="date" required value="<?= escapeHtml($values['hired_on']) ?>">
                </label>
                <label for="salary_percent">Процент от стоимости работ
                    <input id="salary_percent" name="salary_percent" type="number" min="0" max="100" step="any" required
                           value="<?= escapeHtml(str_replace(',', '.', $values['salary_percent'])) ?>">
                </label>
            </div>
            <fieldset>
                <legend>Услуги мастера</legend>
                <?php foreach ($services as $id => $service): ?>
                    <label class="checkbox">
                        <input type="checkbox" name="service_ids[]" value="<?= $id ?>"<?= isset($serviceIds[$id]) ? ' checked' : '' ?>>
                        <?= escapeHtml($service['name']) ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <button type="submit">Сохранить мастера</button>
        </form>
<?php require __DIR__ . '/../templates/footer.php'; ?>
