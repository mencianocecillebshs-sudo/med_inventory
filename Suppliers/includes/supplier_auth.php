<?php
function findSupplierForUser(mysqli $conn, int $userId, string $username = '', string $name = ''): ?array
{
    $stmt = $conn->prepare("SELECT id, name, company FROM suppliers WHERE user_id = ? ORDER BY id ASC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $supplier = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        if ($supplier) {
            $supplier['id'] = (int)$supplier['id'];
            return $supplier;
        }
    }

    $candidates = array_values(array_unique(array_filter([$username, $name])));
    foreach ($candidates as $candidate) {
        $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', $candidate));
        if ($normalized === '') {
            continue;
        }

        $stmt = $conn->prepare("
            SELECT id, name, company
            FROM suppliers
            WHERE user_id IS NULL
              AND (
                LOWER(REPLACE(REPLACE(REPLACE(company, ' ', ''), '.', ''), ',', '')) = ?
                OR LOWER(REPLACE(REPLACE(REPLACE(name, ' ', ''), '.', ''), ',', '')) = ?
                OR contact = ?
              )
            ORDER BY id ASC
            LIMIT 1
        ");
        if (!$stmt) {
            continue;
        }

        $stmt->bind_param('sss', $normalized, $normalized, $candidate);
        $stmt->execute();
        $result = $stmt->get_result();
        $supplier = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($supplier) {
            $supplier['id'] = (int)$supplier['id'];
            $update = $conn->prepare("UPDATE suppliers SET user_id = ? WHERE id = ? AND user_id IS NULL");
            if ($update) {
                $update->bind_param('ii', $userId, $supplier['id']);
                $update->execute();
                $update->close();
            }
            return $supplier;
        }
    }

    return createSupplierProfileForUser($conn, $userId, $username, $name);
}

function createSupplierProfileForUser(mysqli $conn, int $userId, string $username = '', string $name = ''): ?array
{
    $profileName = trim($name) !== '' ? trim($name) : trim($username);
    if ($profileName === '') {
        $profileName = 'Supplier';
    }

    $company = $profileName;
    $address = 'Not provided';
    $contact = trim($username) !== '' ? trim($username) : 'supplier-' . $userId;

    $stmt = $conn->prepare("
        INSERT INTO suppliers
            (user_id, name, company, address, contact, total_buy, total_paid, total_due, representative, lead_time, created_at, order_status)
        VALUES
            (?, ?, ?, ?, ?, 0, 0, 0, ?, 0, NOW(), 'pending')
    ");
    if (!$stmt) {
        error_log('Unable to prepare supplier profile insert: ' . $conn->error);
        return null;
    }

    $stmt->bind_param('isssss', $userId, $profileName, $company, $address, $contact, $profileName);
    if (!$stmt->execute()) {
        error_log('Unable to create supplier profile: ' . $stmt->error);
        $stmt->close();
        return null;
    }

    $supplierId = (int)$conn->insert_id;
    $stmt->close();

    return [
        'id' => $supplierId,
        'name' => $profileName,
        'company' => $company,
    ];
}

function requireSupplierPage(mysqli $conn): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['user_id']) || strtolower((string)($_SESSION['role'] ?? '')) !== 'supplier') {
        header('Location: ../index.php');
        exit();
    }

    $supplier = findSupplierForUser(
        $conn,
        (int)$_SESSION['user_id'],
        (string)($_SESSION['username'] ?? ''),
        (string)($_SESSION['name'] ?? '')
    );

    if (!$supplier) {
        session_destroy();
        header('Location: ../index.php?error=supplier_profile_not_found');
        exit();
    }

    $supplier['id'] = (int)$supplier['id'];
    $_SESSION['supplier_id'] = $supplier['id'];

    return $supplier;
}

function sendSupplierJsonError(string $message, int $statusCode): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

function requireSupplierSession(mysqli $conn): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['user_id']) || strtolower((string)($_SESSION['role'] ?? '')) !== 'supplier') {
        sendSupplierJsonError('Unauthorized supplier access', 401);
    }

    $supplier = findSupplierForUser(
        $conn,
        (int)$_SESSION['user_id'],
        (string)($_SESSION['username'] ?? ''),
        (string)($_SESSION['name'] ?? '')
    );

    if (!$supplier) {
        sendSupplierJsonError('Supplier profile not found', 403);
    }

    $supplier['id'] = (int)$supplier['id'];
    $_SESSION['supplier_id'] = $supplier['id'];

    return $supplier;
}
?>
