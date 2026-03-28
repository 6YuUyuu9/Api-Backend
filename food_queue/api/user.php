<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';
require_once '../model/user.php';

$db = new DB();
$conn = $db->getConnection();
$user = new User($conn);

$method = $_SERVER['REQUEST_METHOD'];
$url = $_SERVER['REQUEST_URI'];

$data = json_decode(file_get_contents("php://input"), true) ?? [];

// 1. REGISTER
if ($method === 'POST' && preg_match('#user.php/register#', $url)) {
    $result = $user->register($data['username'], $data['password']);
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'User registered successfully' : 'Username already exists'
    ]);
    exit();
}

// 2. LOGIN
if ($method === 'POST' && preg_match('#user.php/login#', $url)) {
    $result = $user->login($data['username'], $data['password']);
    if($result) unset($result['password']); // ลบรหัสผ่านก่อนส่งกลับ
    echo json_encode([
        'success' => $result !== false,
        'message' => $result ? 'Login successful' : 'Invalid username or password',
        'user' => $result
    ]);
    exit();
}

// 3. CHANGE PASSWORD
if ($method === 'POST' && preg_match('#user.php/change-password#', $url)) {
    // เปลี่ยนจาก 'id' เป็น 'user_id' ให้ตรงตาม SQL
    $result = $user->changePassword($data['user_id'], $data['old_password'], $data['new_password']);
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Password changed successfully' : 'Old password is incorrect'
    ]);
    exit();
}

// 4. GET ALL USERS
if ($method === 'GET' && preg_match('#user.php/users#', $url)) {
    $users = $user->getUsers();
    echo json_encode($users);
    exit();
}

// 4. GET USER BY ID
if ($method === 'GET' && preg_match('#user.php/user/(\d+)#', $url, $matches)) {
    $user_id = $matches[1];
    $result = $user->getUserById($user_id);
    if($result) unset($result['password']); // ลบรหัสผ่านก่อนส่งกลับ
    echo json_encode([
        'success' => $result !== false,
        'message' => $result ? 'User found' : 'User not found',
        'user' => $result
    ]);
    exit();
}

// 5. UPDATE
if ($method === 'POST' && preg_match('#user.php/update#', $url)) {
    $result = $user->update($data['user_id'], $data['username'], $data['role']);
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'User updated successfully' : 'Failed to update user'
    ]);
    exit();
}

// 6. DELETE
if ($method === 'DELETE' && preg_match('#user.php/delete#', $url)) {
    $result = $user->delete($data['user_id']);
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'User deleted successfully' : 'Failed to delete user'
    ]);
    exit();
}

// กรณีหา Endpoint ไม่เจอ
http_response_code(404);
echo json_encode([
    'success' => false,
    'message' => 'Endpoint not found',
    'debug_url' => $url 
]);
?>