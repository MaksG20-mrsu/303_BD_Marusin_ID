<?php

declare(strict_types=1);

require __DIR__ . '/../src/forms.php';
require __DIR__ . '/../src/database.php';
require __DIR__ . '/../src/booking.php';

startFormSession();
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost) {
    verifyFormToken();
}

$db = openDatabase();
$today = substr(stationNow($db), 0, 10);
$input = $isPost ? $_POST : $_GET;
$serviceId = positiveId($input['service_id'] ?? null);
$masterId = positiveId($input['master_id'] ?? null);
$date = inputText($input, 'work_date');
$start = inputText($_POST, 'start_time');
$details = [];
foreach (['client_name', 'phone', 'plate', 'make', 'model'] as $key) {
    $details[$key] = inputText($_POST, $key);
}

$services = fetchServices($db);
$service = $serviceId === null ? null : ($services[$serviceId] ?? null);
$masters = $service === null ? [] : fetchMasters($db, $today, $serviceId);
$master = $masterId === null ? null : ($masters[$masterId] ?? null);
$shifts = $master === null ? [] : fetchBookingShifts($db, $masterId, $today);
$shift = $shifts[$date] ?? null;

if ($isPost) {
    try {
        if ($service === null || $master === null || $shift === null || !validTime($start)) {
            throw new InvalidArgumentException('Выберите услугу, мастера, дату и время из списков.');
        }
        if (!validText($details['client_name'], 80) || !validText($details['plate'], 20)
            || !validText($details['make'], 40) || !validText($details['model'], 40)) {
            throw new InvalidArgumentException('Укажите имя клиента, госномер, марку и модель автомобиля.');
        }
        $phoneDigits = preg_replace('/\D/', '', $details['phone']);
        if (preg_match('/\A\+?[0-9() -]{7,25}\z/', $details['phone']) !== 1
            || strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) {
            throw new InvalidArgumentException('Укажите телефон клиента с 7–15 цифрами.');
        }

        bookAppointment($db, $serviceId, $masterId, $date, $start, $details);
        $selection = http_build_query(['service_id' => $serviceId, 'master_id' => $masterId, 'work_date' => $date]);
        redirectWithNotice('appointment_form.php?' . $selection, "Запись на $date в $start сохранена.");
    } catch (InvalidArgumentException $exception) {
        http_response_code(422);
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        http_response_code(500);
        $error = 'Не удалось сохранить запись. Попробуйте ещё раз.';
    }
}

$slots = $shift === null ? [] : availableSlots($db, $masterId, $date, $shift, (int) $service['duration_minutes']);
$title = 'Предварительная запись';
$notice = takeNotice();
require __DIR__ . '/../templates/header.php';
?>
        <form method="get" action="appointment_form.php">
            <div class="fields">
                <label for="service_id">Услуга
                    <select id="service_id" name="service_id" onchange="this.form.submit()">
                        <option value="">Выберите услугу</option>
                        <?php foreach ($services as $id => $item): ?>
                            <option value="<?= $id ?>"<?= $id === $serviceId ? ' selected' : '' ?>>
                                <?= escapeHtml($item['name']) ?> (<?= (int) $item['duration_minutes'] ?> мин.)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label for="master_id">Мастер
                    <select id="master_id" name="master_id" onchange="this.form.submit()"<?= $masters === [] ? ' disabled' : '' ?>>
                        <option value="">Выберите мастера</option>
                        <?php foreach ($masters as $id => $item): ?>
                            <option value="<?= $id ?>"<?= $id === $masterId ? ' selected' : '' ?>><?= escapeHtml(fullName($item)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label for="work_date">Дата
                    <select id="work_date" name="work_date" onchange="this.form.submit()"<?= $shifts === [] ? ' disabled' : '' ?>>
                        <option value="">Выберите дату</option>
                        <?php foreach (array_keys($shifts) as $shiftDate): ?>
                            <option value="<?= escapeHtml($shiftDate) ?>"<?= $shiftDate === $date ? ' selected' : '' ?>><?= escapeHtml($shiftDate) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <button type="submit">Показать время</button>
        </form>

        <?php if ($service !== null && $masters === []): ?>
            <p>Эту услугу пока некому выполнить.</p>
        <?php elseif ($master !== null && $shifts === []): ?>
            <p>На ближайшие даты смен нет. <a href="schedule_form.php">Добавить смену</a></p>
        <?php elseif ($shift !== null && $slots === []): ?>
            <p>На выбранную дату свободного времени нет.</p>
        <?php endif; ?>

        <?php if ($slots !== []): ?>
            <form method="post" action="appointment_form.php">
                <input type="hidden" name="form_token" value="<?= escapeHtml($_SESSION['form_token']) ?>">
                <input type="hidden" name="service_id" value="<?= $serviceId ?>">
                <input type="hidden" name="master_id" value="<?= $masterId ?>">
                <input type="hidden" name="work_date" value="<?= escapeHtml($date) ?>">
                <div class="fields">
                    <label for="start_time">Свободное время
                        <select id="start_time" name="start_time" required>
                            <option value="">Выберите время</option>
                            <?php foreach ($slots as $slotStart => $slotEnd): ?>
                                <option value="<?= escapeHtml($slotStart) ?>"<?= $slotStart === $start ? ' selected' : '' ?>>
                                    <?= escapeHtml($slotStart . ' – ' . $slotEnd) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <fieldset>
                    <legend>Клиент и автомобиль</legend>
                    <div class="fields">
                        <label for="client_name">ФИО клиента
                            <input id="client_name" name="client_name" maxlength="80" required value="<?= escapeHtml($details['client_name']) ?>">
                        </label>
                        <label for="phone">Телефон
                            <input id="phone" name="phone" type="tel" maxlength="26" required value="<?= escapeHtml($details['phone']) ?>">
                        </label>
                        <label for="plate">Госномер
                            <input id="plate" name="plate" maxlength="20" required value="<?= escapeHtml($details['plate']) ?>">
                        </label>
                        <label for="make">Марка
                            <input id="make" name="make" maxlength="40" required value="<?= escapeHtml($details['make']) ?>">
                        </label>
                        <label for="model">Модель
                            <input id="model" name="model" maxlength="40" required value="<?= escapeHtml($details['model']) ?>">
                        </label>
                    </div>
                </fieldset>
                <button type="submit">Сохранить запись</button>
            </form>
        <?php endif; ?>
<?php require __DIR__ . '/../templates/footer.php'; ?>
