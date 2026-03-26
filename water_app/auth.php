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

function currentUserPayload(): array
{
    $users = readJsonFile(USERS_FILE);
    $entries = readJsonFile(WATER_FILE);
    $userId = $_SESSION['user_id'] ?? null;
    $today = date('Y-m-d');
    $currentUser = null;

    foreach ($users as $user) {
        if (($user['id'] ?? '') === $userId) {
            $currentUser = $user;
            break;
        }
    }

    if ($currentUser === null) {
        jsonResponse(['success' => false, 'message' => 'Пользователь не найден'], 401);
    }

    $todayEntries = array_values(array_filter($entries, static function (array $entry) use ($userId, $today): bool {
        return ($entry['user_id'] ?? '') === $userId && ($entry['date'] ?? '') === $today;
    }));
    $userEntries = array_values(array_filter($entries, static function (array $entry) use ($userId): bool {
        return ($entry['user_id'] ?? '') === $userId;
    }));

    usort($todayEntries, static fn(array $a, array $b): int => strcmp($b['time'], $a['time']));
    usort($userEntries, static fn(array $a, array $b): int => strcmp(($b['date'] ?? '') . ($b['time'] ?? ''), ($a['date'] ?? '') . ($a['time'] ?? '')));

    $consumed = array_sum(array_map(static fn(array $entry): int => (int) ($entry['amount'] ?? 0), $todayEntries));
    $goal = max(100, (int) ($currentUser['goal'] ?? 2000));
    $remaining = max(0, $goal - $consumed);
    $percent = $goal > 0 ? (int) round(($consumed / $goal) * 100) : 0;
    $isAdmin = (($currentUser['role'] ?? 'user') === 'admin');

    return [
        'success' => true,
        'username' => htmlspecialchars((string) $currentUser['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'role' => (string) ($currentUser['role'] ?? 'user'),
        'is_admin' => $isAdmin,
        'goal' => $goal,
        'consumed' => $consumed,
        'remaining' => $remaining,
        'percent' => $percent,
        'admin' => $isAdmin ? buildAdminData($users, $entries, (string) $userId) : null,
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Метод не поддерживается'], 405);
}

$action = trim((string) ($_POST['action'] ?? 'login'));

if ($action === 'logout') {
    session_unset();
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'Выход выполнен']);
}

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if (!preg_match('/^[A-Za-zА-Яа-я0-9_]{3,30}$/u', $username)) {
    jsonResponse(['success' => false, 'message' => 'Логин должен содержать 3-30 символов'], 422);
}

if (mb_strlen($password) < 4 || mb_strlen($password) > 50) {
    jsonResponse(['success' => false, 'message' => 'Пароль должен содержать от 4 до 50 символов'], 422);
}

$users = readJsonFile(USERS_FILE);

foreach ($users as $user) {
    if (($user['username'] ?? '') === $username && password_verify($password, (string) ($user['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        jsonResponse(currentUserPayload());
    }
}

jsonResponse(['success' => false, 'message' => 'Неверный логин или пароль'], 401);
