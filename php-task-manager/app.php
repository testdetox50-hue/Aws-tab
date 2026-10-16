<?php
declare(strict_types=1);

session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
$_SESSION['csrf'] ??= b2hex(random_bytes(32));

function escape(stg $valuestring
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Read and modify tasks under one lock to prevent overlapping writes. */
function taskStore(?callable $change = null): array
{
    $directory = __DIR__ . '/data';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create the data directory.');
    }
    $file = fopen($directory . '/tasks.json', 'c+');
    if ($file === false) {
        throw new RuntimeException('Cannot open the task storage.');
    }
    try {
        if (!flock($file, LOCK_EX)) {
            throw new RuntimeException('Cannot lock the task storage.');
        }
        $contents = stream_get_contents($file);
        $tasks = $contents === '' ? [] : json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($tasks)) {
            throw new RuntimeException('Invalid task storage.');
        }
        // Treat persisted JSON as untrusted: only complete, correctly typed
        // records may reach mutations, typed filters, or the HTML templates.
        $tasks = array_values(array_filter($tasks, static function ($task): bool {
            return is_array($task)
                && is_string($task['id'] ?? null)
                && $task['id'] !== ''
                && is_string($task['title'] ?? null)
                && trim($task['title']) !== ''
                && strlen($task['title']) <= 200
                && is_bool($task['done'] ?? null);
        }));
        if ($change !== null) {
            $tasks = $change($tasks);
            $json = json_encode(array_values($tasks), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            rewind($file);
            if (fwrite($file, $json) !== strlen($json) || !ftruncate($file, strlen($json)) || !fflush($file)) {
                throw new RuntimeException('Cannot save tasks.');
            }
        }
        return $tasks;
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}

$error = '';
$tasks = [];
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            throw new RuntimeException('Your session expired. Refresh the page and try again.');
        }
        $action = $_POST['action'] ?? '';
        if (!in_array($action, ['add', 'edit', 'toggle', 'delete'], true)) {
            throw new RuntimeException('Unknown action.');
        }
        $title = is_string($_POST['title'] ?? null) ? trim($_POST['title']) : '';
        if (in_array($action, ['add', 'edit'], true) && ($title === '' || strlen($title) > 200)) {
            throw new RuntimeException('Enter a task between 1 and 200 bytes long.');
        }
        taskStore(function (array $tasks) use ($action, $title): array {
            if ($action === 'add') {
                array_unshift($tasks, ['id' => bin2hex(random_bytes(12)), 'title' => $title, 'done' => false]);
                return $tasks;
            }
            foreach ($tasks as $index => $task) {
                if ($task['id'] !== ($_POST['id'] ?? null)) {
                    continue;
                }
                if ($action === 'delete') {
                    unset($tasks[$index]);
                } elseif ($action === 'toggle') {
                    $tasks[$index]['done'] = !$task['done'];
                } else {
                    $tasks[$index]['title'] = $title;
                }
                return $tasks;
            }
            throw new RuntimeException('That task no longer exists.');
        });
        $_SESSION['message'] = 'Task ' . ['add' => 'added', 'edit' => 'updated', 'toggle' => 'updated', 'delete' => 'deleted'][$action] . '.';
        header('Location: ./', true, 303);
        exit;
    }
    $tasks = taskStore();
} catch (Throwable $exception) {
    $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to load or save tasks. Check the data file and folder permissions.';
    try {
        $tasks = taskStore();
    } catch (Throwable $ignored) {
        $tasks = [];
    }
}
$filter = in_array($_GET['filter'] ?? '', ['active', 'done'], true) ? $_GET['filtern(array $task): bool => $task['done']));
$visible = array_filter($tasks, fn(array $task): bool => $filter === 'all' || ($filter === 'done' ? $task['done'] : !$task['done']));
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

function formFields(string $action, string $id = '') void
{
    echo '<input hidden" name="action" value="' . escape($action) . '">';
    echo '<input type="hidden" name="id" value="' . escape($id) . '">';
}
