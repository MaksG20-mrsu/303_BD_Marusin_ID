<?php

declare(strict_types=1);

function openDatabase(): PDO
{
    $path = __DIR__ . '/sto.db';
    if (!is_file($path)) {
        throw new RuntimeException('Создайте базу sto.db по инструкции в README.');
    }

    $db = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA query_only = ON');

    return $db;
}

function fullName(array $row): string
{
    return trim($row['last_name'] . ' ' . $row['first_name'] . ' ' . ($row['middle_name'] ?? ''));
}

function fetchMasters(PDO $db): array
{
    $statement = $db->prepare(
        'SELECT id, last_name, first_name, middle_name
         FROM masters
         ORDER BY last_name, first_name, id'
    );
    $statement->execute();

    $masters = [];
    foreach ($statement->fetchAll() as $row) {
        $masters[(int) $row['id']] = fullName($row);
    }
    return $masters;
}

function selectedMasterId(mixed $input, array $masters): ?int
{
    if (!is_string($input)) {
        throw new InvalidArgumentException('Укажите номер мастера из списка.');
    }
    $input = trim($input);
    if ($input === '') {
        return null;
    }

    $id = filter_var($input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false || !isset($masters[$id])) {
        throw new InvalidArgumentException('Укажите номер мастера из списка.');
    }
    return $id;
}

function fetchWorks(PDO $db, ?int $masterId): array
{
    $sql = 'SELECT master_id, last_name, first_name, middle_name,
                   substr(finished_at, 1, 10) AS work_date, service_name, charged_kopecks
            FROM work_details';
    if ($masterId !== null) {
        $sql .= ' WHERE master_id = :master_id';
    }
    $sql .= ' ORDER BY last_name, finished_at, master_id, appointment_id, service_id';

    $statement = $db->prepare($sql);
    if ($masterId !== null) {
        $statement->bindValue(':master_id', $masterId, PDO::PARAM_INT);
    }
    $statement->execute();

    $works = [];
    foreach ($statement->fetchAll() as $row) {
        $price = (int) $row['charged_kopecks'];
        $works[] = [
            'master_id' => (int) $row['master_id'],
            'master_name' => fullName($row),
            'work_date' => $row['work_date'],
            'service_name' => $row['service_name'],
            'price' => sprintf('%d,%02d', intdiv($price, 100), $price % 100),
        ];
    }
    return $works;
}
