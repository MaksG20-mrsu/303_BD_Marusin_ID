<?php

declare(strict_types=1);

require __DIR__ . '/repository.php';

function cellWidth(string $value): int
{
    return preg_match_all('/\X/u', $value) ?: 0;
}

function printWorks(array $works): void
{
    $headings = ['№ мастера', 'ФИО', 'Дата работы', 'Услуга', 'Стоимость, руб.'];
    $rows = [];
    foreach ($works as $work) {
        $rows[] = [
            (string) $work['master_id'],
            str_replace(["\r", "\n", "\t"], ' ', $work['master_name']),
            $work['work_date'],
            str_replace(["\r", "\n", "\t"], ' ', $work['service_name']),
            $work['price'],
        ];
    }

    $widths = array_map('cellWidth', $headings);
    foreach ($rows as $row) {
        foreach ($row as $index => $value) {
            $widths[$index] = max($widths[$index], cellWidth($value));
        }
    }

    $segments = array_map(static fn (int $width): string => str_repeat('─', $width + 2), $widths);
    $formatRow = static function (array $row) use ($widths): string {
        $cells = [];
        foreach ($row as $index => $value) {
            $cells[] = ' ' . $value . str_repeat(' ', $widths[$index] - cellWidth($value)) . ' ';
        }
        return '│' . implode('│', $cells) . '│';
    };

    echo '┌' . implode('┬', $segments) . '┐' . PHP_EOL;
    echo $formatRow($headings) . PHP_EOL;
    echo '├' . implode('┼', $segments) . '┤' . PHP_EOL;
    foreach ($rows as $row) {
        echo $formatRow($row) . PHP_EOL;
    }
    echo '└' . implode('┴', $segments) . '┘' . PHP_EOL;
}

try {
    $db = openDatabase();
    $masters = fetchMasters($db);

    echo 'Мастера СТО' . PHP_EOL;
    foreach ($masters as $id => $name) {
        echo $id . '. ' . $name . PHP_EOL;
    }

    while (true) {
        echo 'Введите номер мастера или нажмите Enter для вывода всех работ.' . PHP_EOL;
        $line = fgets(STDIN);
        if ($line === false) {
            fwrite(STDERR, 'Ввод завершён.' . PHP_EOL);
            exit(1);
        }
        try {
            $masterId = selectedMasterId($line, $masters);
            break;
        } catch (InvalidArgumentException $exception) {
            fwrite(STDERR, $exception->getMessage() . PHP_EOL);
        }
    }

    $works = fetchWorks($db, $masterId);
    if ($works === []) {
        echo 'Оказанных услуг нет.' . PHP_EOL;
    } else {
        printWorks($works);
    }
} catch (PDOException $exception) {
    fwrite(STDERR, 'Не удалось прочитать базу данных.' . PHP_EOL);
    exit(1);
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
