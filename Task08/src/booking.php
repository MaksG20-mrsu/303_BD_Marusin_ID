<?php

declare(strict_types=1);

const SLOT_STEP_MINUTES = 15;

function fetchBookingShifts(PDO $db, int $masterId, string $today): array
{
    $statement = $db->prepare(
        'SELECT s.work_date, s.start_time, s.end_time
         FROM work_shifts AS s JOIN masters AS m ON m.id = s.master_id
         WHERE s.master_id = :master_id AND s.work_date >= :today
           AND s.work_date >= m.hired_on
           AND (m.dismissed_on IS NULL OR s.work_date <= m.dismissed_on)
         ORDER BY s.work_date'
    );
    $statement->execute(['master_id' => $masterId, 'today' => $today]);
    return $statement->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
}

function timeToMinutes(string $time): int
{
    return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
}

function minutesToTime(int $minutes): string
{
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

function availableSlots(PDO $db, int $masterId, string $date, array $shift, int $duration): array
{
    $statement = $db->prepare(
        "SELECT start_time, end_time FROM appointments
         WHERE master_id = ? AND work_date = ? AND status = 'booked'
         ORDER BY start_time"
    );
    $statement->execute([$masterId, $date]);
    $busy = $statement->fetchAll();
    $shiftStart = timeToMinutes($shift['start_time']);
    $shiftEnd = timeToMinutes($shift['end_time']);
    $now = stationNow($db);

    $slots = [];
    $start = $shiftStart;
    $busyIndex = 0;
    while ($start + $duration <= $shiftEnd) {
        $end = $start + $duration;
        $startTime = minutesToTime($start);
        $endTime = minutesToTime($end);

        while (isset($busy[$busyIndex]) && $busy[$busyIndex]['end_time'] <= $startTime) {
            $busyIndex++;
        }
        if (isset($busy[$busyIndex]) && $busy[$busyIndex]['start_time'] < $endTime) {
            $start = timeToMinutes($busy[$busyIndex]['end_time']);
            $busyIndex++;
            continue;
        }
        if ($date . ' ' . $startTime > $now) {
            $slots[$startTime] = $endTime;
        }
        $start += SLOT_STEP_MINUTES;
    }
    return $slots;
}

function findOrAddCar(PDO $db, array $details): int
{
    $statement = $db->prepare('SELECT id, full_name FROM clients WHERE phone = ?');
    $statement->execute([$details['phone']]);
    $client = $statement->fetch();
    if ($client === false) {
        $statement = $db->prepare('INSERT INTO clients (full_name, phone) VALUES (?, ?)');
        $statement->execute([$details['client_name'], $details['phone']]);
        $clientId = (int) $db->lastInsertId();
    } else {
        if ($client['full_name'] !== $details['client_name']) {
            throw new InvalidArgumentException('У этого телефона в базе указано другое имя клиента.');
        }
        $clientId = (int) $client['id'];
    }

    $statement = $db->prepare('SELECT id, client_id, make, model FROM cars WHERE plate = ?');
    $statement->execute([$details['plate']]);
    $car = $statement->fetch();
    if ($car !== false) {
        if ((int) $car['client_id'] !== $clientId) {
            throw new InvalidArgumentException('Этот автомобиль уже привязан к другому клиенту.');
        }
        if ($car['make'] !== $details['make'] || $car['model'] !== $details['model']) {
            throw new InvalidArgumentException('Марка или модель отличаются от данных автомобиля в базе.');
        }
        return (int) $car['id'];
    }

    $statement = $db->prepare('INSERT INTO cars (client_id, plate, make, model) VALUES (?, ?, ?, ?)');
    $statement->execute([$clientId, $details['plate'], $details['make'], $details['model']]);
    return (int) $db->lastInsertId();
}

function bookAppointment(
    PDO $db,
    int $serviceId,
    int $masterId,
    string $date,
    string $start,
    array $details
): void {
    writeTransaction($db, function () use ($db, $serviceId, $masterId, $date, $start, $details): void {
        $today = substr(stationNow($db), 0, 10);
        $masters = fetchMasters($db, $today, $serviceId);
        $service = fetchServices($db)[$serviceId] ?? null;
        if ($service === null || !isset($masters[$masterId])) {
            throw new InvalidArgumentException('Выберите мастера, который выполняет эту услугу.');
        }

        $shift = fetchBookingShifts($db, $masterId, $today)[$date] ?? null;
        $duration = (int) $service['duration_minutes'];
        $slots = $shift === null ? [] : availableSlots($db, $masterId, $date, $shift, $duration);
        if (!isset($slots[$start])) {
            throw new InvalidArgumentException('Это время недоступно. Выберите другое время.');
        }

        $carId = findOrAddCar($db, $details);
        $end = $slots[$start];
        $statement = $db->prepare(
            "SELECT 1 FROM appointments
             WHERE car_id = ? AND work_date = ? AND status = 'booked'
               AND start_time < ? AND end_time > ? LIMIT 1"
        );
        $statement->execute([$carId, $date, $end, $start]);
        if ($statement->fetchColumn() !== false) {
            throw new InvalidArgumentException('Этот автомобиль уже записан на выбранное время.');
        }

        $statement = $db->prepare(
            'INSERT INTO appointments (car_id, master_id, work_date, start_time, end_time) VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([$carId, $masterId, $date, $start, $end]);
        $appointmentId = (int) $db->lastInsertId();

        $statement = $db->prepare(
            'INSERT INTO appointment_services (appointment_id, service_id, planned_minutes) VALUES (?, ?, ?)'
        );
        $statement->execute([$appointmentId, $serviceId, $duration]);

        $statement = $db->prepare("UPDATE appointments SET status = 'booked' WHERE id = ?");
        $statement->execute([$appointmentId]);
    });
}
