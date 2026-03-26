<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

const USERS_FILE = __DIR__ . '/users.json';
const WATER_FILE = __DIR__ . '/water.json';

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function readJsonFile(string $file): array
{
    if (!file_exists($file)) {
        file_put_contents($file, '[]', LOCK_EX);
    }

    $content = file_get_contents($file);
    $data = json_decode($content ?: '[]', true);

    return is_array($data) ? $data : [];
}

function writeJsonFile(string $file, array $data): void
{
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function requireUserId(): string
{
    $userId = $_SESSION['user_id'] ?? null;

    if (!is_string($userId) || $userId === '') {
        jsonResponse(['success' => false, 'message' => 'Требуется авторизация'], 401);
    }

    return $userId;
}

function findUser(array $users, string $userId): ?array
{
    foreach ($users as $user) {
        if (($user['id'] ?? '') === $userId) {
            return $user;
        }
    }

    return null;
}

function requireAdmin(array $user): void
{
    if (($user['role'] ?? 'user') !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'Доступ разрешён только администратору'], 403);
    }
}

function buildAdminData(array $users, array $entries, string $currentUserId): array
{
    $adminUsers = array_map(static function (array $user) use ($entries, $currentUserId): array {
        $userId = (string) ($user['id'] ?? '');
        $entriesCount = count(array_filter($entries, static fn(array $entry): bool => ($entry['user_id'] ?? '') === $userId));

        return [
            'id' => $userId,
            'username' => htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'role' => (string) ($user['role'] ?? 'user'),
            'goal' => max(100, (int) ($user['goal'] ?? 2000)),
            'entries_count' => $entriesCount,
            'is_current' => $userId === $currentUserId,
        ];
    }, $users);

    $adminEntries = array_map(static function (array $entry) use ($users): array {
        $ownerName = 'Неизвестно';

        foreach ($users as $user) {
            if (($user['id'] ?? '') === ($entry['user_id'] ?? '')) {
                $ownerName = (string) ($user['username'] ?? 'Неизвестно');
                break;
            }
        }

        return [
            'id' => (string) ($entry['id'] ?? ''),
            'username' => htmlspecialchars($ownerName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'date' => htmlspecialchars((string) ($entry['date'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'time' => htmlspecialchars((string) ($entry['time'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'amount' => (int) ($entry['amount'] ?? 0),
        ];
    }, $entries);

    usort($adminEntries, static fn(array $a, array $b): int => strcmp($b['date'] . $b['time'], $a['date'] . $a['time']));

    return [
        'users_count' => count($adminUsers),
        'entries_count' => count($adminEntries),
        'users' => $adminUsers,
        'entries' => $adminEntries,
    ];
}

function calculateStreakDays(array $entries, string $userId): int
{
    $dates = [];

    foreach ($entries as $entry) {
        if (($entry['user_id'] ?? '') !== $userId) {
            continue;
        }

        $date = (string) ($entry['date'] ?? '');
        if ($date !== '') {
            $dates[$date] = true;
        }
    }

    $today = new DateTimeImmutable('today');
    $streak = 0;

    while (isset($dates[$today->format('Y-m-d')])) {
        $streak++;
        $today = $today->modify('-1 day');
    }

    return $streak;
}

function buildPayload(string $userId): array
{
    $users = readJsonFile(USERS_FILE);
    $entries = readJsonFile(WATER_FILE);
    $today = date('Y-m-d');
    $user = findUser($users, $userId);

    if ($user === null) {
        jsonResponse(['success' => false, 'message' => 'Пользователь не найден'], 404);
    }

    $todayEntries = array_values(array_filter($entries, static function (array $entry) use ($userId, $today): bool {
        return ($entry['user_id'] ?? '') === $userId && ($entry['date'] ?? '') === $today;
    }));
    $userEntries = array_values(array_filter($entries, static function (array $entry) use ($userId): bool {
        return ($entry['user_id'] ?? '') === $userId;
    }));

    usort($todayEntries, static fn(array $a, array $b): int => strcmp($b['time'], $a['time']));
    usort($userEntries, static fn(array $a, array $b): int => strcmp(($b['date'] ?? '') . ($b['time'] ?? ''), ($a['date'] ?? '') . ($a['time'] ?? '')));

    $goal = max(100, (int) ($user['goal'] ?? 2000));
    $streakDays = calculateStreakDays($entries, $userId);
    $consumed = array_sum(array_map(static fn(array $entry): int => (int) ($entry['amount'] ?? 0), $todayEntries));
    $remaining = max(0, $goal - $consumed);
    $percent = $goal > 0 ? (int) round(($consumed / $goal) * 100) : 0;
    $isAdmin = (($user['role'] ?? 'user') === 'admin');

    return [
        'success' => true,
        'username' => htmlspecialchars((string) $user['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'role' => (string) ($user['role'] ?? 'user'),
        'is_admin' => $isAdmin,
        'goal' => $goal,
        'streak_days' => $streakDays,
        'consumed' => $consumed,
        'remaining' => $remaining,
        'percent' => $percent,
        'admin' => $isAdmin ? buildAdminData($users, $entries, $userId) : null,
        'entries' => array_map(static function (array $entry): array {
            return [
                'id' => (string) ($entry['id'] ?? ''),
                'date' => htmlspecialchars((string) ($entry['date'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'time' => htmlspecialchars((string) ($entry['time'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'amount' => (int) ($entry['amount'] ?? 0),
            ];
        }, $userEntries),
    ];
}

$userId = requireUserId();
$users = readJsonFile(USERS_FILE);
$currentUser = findUser($users, $userId);

if ($currentUser === null) {
    jsonResponse(['success' => false, 'message' => 'Пользователь не найден'], 404);
}

$action = $_SERVER['REQUEST_METHOD'] === 'GET'
    ? trim((string) ($_GET['action'] ?? 'get'))
    : trim((string) ($_POST['action'] ?? ''));

if ($action === 'get') {
    jsonResponse(buildPayload($userId));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Метод не поддерживается'], 405);
}

if ($action === 'set_goal') {
    $goal = filter_input(INPUT_POST, 'goal', FILTER_VALIDATE_INT);

    if ($goal === false || $goal < 100 || $goal > 10000) {
        jsonResponse(['success' => false, 'message' => 'Цель должна быть от 100 до 10000 мл'], 422);
    }

    foreach ($users as &$user) {
        if (($user['id'] ?? '') === $userId) {
            $user['goal'] = $goal;
            break;
        }
    }
    unset($user);

    writeJsonFile(USERS_FILE, $users);
    jsonResponse(buildPayload($userId));
}

if ($action === 'add') {
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_INT);
    $datetime = trim((string) ($_POST['datetime'] ?? ''));

    if ($amount === false || $amount < 1 || $amount > 5000) {
        jsonResponse(['success' => false, 'message' => 'Объём должен быть от 1 до 5000 мл'], 422);
    }

    $dateObject = DateTime::createFromFormat('Y-m-d\TH:i', $datetime);
    if (!$dateObject || $dateObject->format('Y-m-d\TH:i') !== $datetime) {
        jsonResponse(['success' => false, 'message' => 'Некорректная дата или время'], 422);
    }

    $entries = readJsonFile(WATER_FILE);
    $entries[] = [
        'id' => uniqid('entry_', true),
        'user_id' => $userId,
        'date' => $dateObject->format('Y-m-d'),
        'time' => $dateObject->format('H:i'),
        'amount' => $amount,
    ];

    writeJsonFile(WATER_FILE, $entries);
    jsonResponse(buildPayload($userId), 201);
}

if ($action === 'delete') {
    $entryId = trim((string) ($_POST['id'] ?? ''));

    if ($entryId === '') {
        jsonResponse(['success' => false, 'message' => 'Не передан идентификатор записи'], 422);
    }

    $entries = readJsonFile(WATER_FILE);
    $filteredEntries = array_values(array_filter($entries, static function (array $entry) use ($entryId, $userId): bool {
        if (($entry['id'] ?? '') !== $entryId) {
            return true;
        }

        return ($entry['user_id'] ?? '') !== $userId;
    }));

    if (count($entries) === count($filteredEntries)) {
        jsonResponse(['success' => false, 'message' => 'Запись не найдена'], 404);
    }

    writeJsonFile(WATER_FILE, $filteredEntries);
    jsonResponse(buildPayload($userId));
}

if ($action === 'admin_delete_entry') {
    requireAdmin($currentUser);

    $entryId = trim((string) ($_POST['id'] ?? ''));
    if ($entryId === '') {
        jsonResponse(['success' => false, 'message' => 'Не передан идентификатор записи'], 422);
    }

    $entries = readJsonFile(WATER_FILE);
    $filteredEntries = array_values(array_filter($entries, static fn(array $entry): bool => ($entry['id'] ?? '') !== $entryId));

    if (count($entries) === count($filteredEntries)) {
        jsonResponse(['success' => false, 'message' => 'Запись не найдена'], 404);
    }

    writeJsonFile(WATER_FILE, $filteredEntries);
    jsonResponse(buildPayload($userId));
}

if ($action === 'admin_delete_user') {
    requireAdmin($currentUser);

    $targetUserId = trim((string) ($_POST['user_id'] ?? ''));
    if ($targetUserId === '' || $targetUserId === $userId) {
        jsonResponse(['success' => false, 'message' => 'Нельзя удалить этого пользователя'], 422);
    }

    $filteredUsers = array_values(array_filter($users, static fn(array $user): bool => ($user['id'] ?? '') !== $targetUserId));
    if (count($users) === count($filteredUsers)) {
        jsonResponse(['success' => false, 'message' => 'Пользователь не найден'], 404);
    }

    $entries = readJsonFile(WATER_FILE);
    $filteredEntries = array_values(array_filter($entries, static fn(array $entry): bool => ($entry['user_id'] ?? '') !== $targetUserId));

    writeJsonFile(USERS_FILE, $filteredUsers);
    writeJsonFile(WATER_FILE, $filteredEntries);
    jsonResponse(buildPayload($userId));
}

jsonResponse(['success' => false, 'message' => 'Неизвестное действие'], 400);
