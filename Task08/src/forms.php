<?php

declare(strict_types=1);

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function inputText(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function positiveId(mixed $value): ?int
{
    if (!is_string($value)) {
        return null;
    }
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? null : $id;
}

function validDate(string $date): bool
{
    if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) !== 1) {
        return false;
    }
    [$year, $month, $day] = array_map('intval', explode('-', $date));
    return checkdate($month, $day, $year);
}

function validTime(string $time): bool
{
    return preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $time) === 1;
}

function validText(string $value, int $limit): bool
{
    return preg_match('/\A[^\p{Cc}]{1,' . $limit . '}\z/u', $value) === 1;
}

function startFormSession(): void
{
    session_start(['use_strict_mode' => 1, 'cookie_httponly' => 1, 'cookie_samesite' => 'Strict']);
    $_SESSION['form_token'] ??= bin2hex(random_bytes(32));
}

function verifyFormToken(): void
{
    if (!hash_equals($_SESSION['form_token'], inputText($_POST, 'form_token'))) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Откройте форму заново и повторите отправку.');
    }
}

function takeNotice(): string
{
    $notice = $_SESSION['notice'] ?? '';
    unset($_SESSION['notice']);
    return $notice;
}

function redirectWithNotice(string $page, string $notice): void
{
    $_SESSION['notice'] = $notice;
    header('Location: ' . $page, true, 303);
    exit;
}
