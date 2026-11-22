<?php

declare(strict_types=1);

function openDatabase(): PDO
{
    $path = dirname(__DIR__) . '/data/sto.db';
    if (!is_file($path)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Создайте базу sto.db по инструкции в README.');
    }

    try {
        $db = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('PRAGMA busy_timeout = 5000');
        return $db;
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Не удалось открыть базу данных.');
    }
}

function stationNow(PDO $db): string
{
    return $db->query("SELECT strftime('%Y-%m-%d %H:%M', 'now', 'localtime')")->fetchColumn();
}

function fetchServices(PDO $db): array
{
    return $db->query('SELECT id, name, duration_minutes FROM services ORDER BY name')
        ->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
}

function fetchMasters(PDO $db, string $today, ?int $serviceId = null): array
{
    $statement = $db->prepare(
        'SELECT m.id, m.last_name, m.first_name, m.middle_name, m.hired_on, m.dismissed_on
         FROM masters AS m
         WHERE (m.dismissed_on IS NULL OR m.dismissed_on >= :today)
           AND (:service_id IS NULL OR EXISTS (
               SELECT 1 FROM master_services AS ms
               WHERE ms.master_id = m.id AND ms.service_id = :service_id
           ))
         ORDER BY m.last_name, m.first_name, m.id'
    );
    $statement->bindValue(':today', $today);
    $statement->bindValue(':service_id', $serviceId, $serviceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
}

function fullName(array $master): string
{
    return trim($master['last_name'] . ' ' . $master['first_name'] . ' ' . ($master['middle_name'] ?? ''));
}

function writeTransaction(PDO $db, callable $save): void
{
    $db->exec('BEGIN IMMEDIATE');
    try {
        $save();
        $db->exec('COMMIT');
    } catch (Throwable $exception) {
        $db->exec('ROLLBACK');
        throw $exception;
    }
}

function addMaster(PDO $db, array $master, array $serviceIds): void
{
    writeTransaction($db, function () use ($db, $master, $serviceIds): void {
        $statement = $db->prepare(
            'INSERT INTO masters (last_name, first_name, middle_name, hired_on, salary_percent)
             VALUES (:last_name, :first_name, :middle_name, :hired_on, :salary_percent)'
        );
        $statement->execute($master);
        $masterId = (int) $db->lastInsertId();
        $statement = $db->prepare('INSERT INTO master_services (master_id, service_id) VALUES (?, ?)');
        foreach ($serviceIds as $serviceId) {
            $statement->execute([$masterId, $serviceId]);
        }
    });
}

function addShift(PDO $db, int $masterId, string $date, string $start, string $end): void
{
    $statement = $db->prepare(
        'INSERT INTO work_shifts (master_id, work_date, start_time, end_time)
         VALUES (?, ?, ?, ?) ON CONFLICT (master_id, work_date) DO NOTHING'
    );
    $statement->execute([$masterId, $date, $start, $end]);
    if ($statement->rowCount() === 0) {
        throw new InvalidArgumentException('У этого мастера уже есть смена на выбранную дату.');
    }
}
