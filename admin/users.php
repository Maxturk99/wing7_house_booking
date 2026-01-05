<?php
/**
 * Users Management Page
 * หน้าจัดการผู้ใช้
 */

session_start();

// ตรวจสอบสิทธิ์ - เฉพาะ admin เท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php?error=กรุณาล็อกอินเพื่อเข้าถึงระบบ');
    exit();
}

$pageTitle = "จัดการผู้ใช้";

// เชื่อมต่อฐานข้อมูล
require_once '../config/database.php';
$db = getDatabaseConnection();

// ดึงข้อมูลผู้ใช้ทั้งหมด
$stmt = $db->query("SELECT * FROM users ORDER BY role, full_name");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ฟังก์ชันแปลงบทบาทเป็นภาษาไทย
function getRoleThai($role) {
    $roles = [
        'admin' => 'ผู้ดูแลระบบ',
        'staff' => 'เจ้าหน้าที่',
        'approver' => 'ผู้อนุมัติ',
        'commander' => 'ผู้บังคับบัญชา',
        'user' => 'ผู้ใช้งาน'
    ];
    return $roles[$role] ?? $role;
}

// ฟังก์ชันแปลงสถานะเป็นภาษาไทย
function getStatusThai($status) {
    $statuses = [
        'active' => 'ใช้งาน',
        'inactive' => 'ระงับการใช้งาน'
    ];
    return $statuses[$status] ?? $status;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - ระบบจองบ้านพักรับรอง กองบิน7</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .sidebar {
            background: white;
            min-height: calc(100vh - 56px);
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: absolute;
            width: 250px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .user-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .user-card:hover {
            transform: translateY(-5px);
        }
        .role-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-admin {
            background-color: #dc3545;
            color: white;
        }
        .badge-staff {
            background-color: #fd7e14;
            color: white;
        }
        .badge-approver {
            background-color: #20c997;
            color: white;
        }
        .badge-commander {
            background-color: #6f42c1;
            color: white;
        }
        .badge-user {
            background-color: #17a2b8;
            color: white;
        }
        .avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
        }
        @media (max-width: 768px) {
            .sidebar {
                position: static;
                width: 100%;
                min-height: auto;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-home me-2"></i>ระบบจองบ้านพักรับรอง กองบิน7
            </a>
            <div class="d-flex align-items-center text-white">
                <span class="me-3"><?php echo $_SESSION['full_name']; ?></span>
                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit me-2"></i>โปรไฟล์</a></li>
                        <li><a class="dropdown-item" href="change_password.php"><i class="fas fa-key me-2"></i>เปลี่ยนรหัสผ่าน</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>แดชบอร์ด
                    </a>
                    <a href="bookings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar-check me-2"></i>จัดการการจอง
                    </a>
                    <a href="buildings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-building me-2"></i>จัดการอาคาร
                    </a>
                    <a href="users.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-users me-2"></i>จัดการผู้ใช้
                    </a>
                    <a href="reports.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-bar me-2"></i>รายงานและสถิติ
                    </a>
                    <a href="settings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-cog me-2"></i>ตั้งค่าระบบ
                    </a>
                    <div class="mt-4 pt-3 border-top">
                        <a href="../general/booking_public.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-external-link-alt me-2"></i>จองสำหรับบุคคลทั่วไป
                        </a>
                        <a href="../logout.php" class="list-group-item list-group-item-action text-danger">
                            <i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-users me-2"></i><?php echo $pageTitle; ?></h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus me-2"></i>เพิ่มผู้ใช้ใหม่
                    </button>
                </div>

                <!-- User Statistics -->
                <div class="row mb-4">
                    <div class="col-md-2 col-6 mb-3">
                        <div class="card user-card">
                            <div class="card-body text-center">
                                <h6 class="text-muted mb-1">ทั้งหมด</h6>
                                <h4 class="mb-0"><?php echo count($users); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="card user-card">
                            <div class="card-body text-center">
                                <h6 class="text-muted mb-1">Admin</h6>
                                <h4 class="mb-0"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'admin')); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="card user-card">
                            <div class="card-body text-center">
                                <h6 class="text-muted mb-1">Staff</h6>
                                <h4 class="mb-0"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'staff')); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="card user-card">
                            <div class="card-body text-center">
                                <h6 class="text-muted mb-1">Approver</h6>
                                <h4 class="mb-0"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'approver')); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="card user-card">
                            <div class="card-body text-center">
                                <h6 class="text-muted mb-1">Commander</h6>
                                <h4 class="mb-0"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'commander')); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="card user-card">
                            <div class="card-body text-center">
                                <h6 class="text-muted mb-1">User</h6>
                                <h4 class="mb-0"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'user')); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="usersTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>ชื่อผู้ใช้</th>
                                        <th>ชื่อ-นามสกุล</th>
                                        <th>บทบาท</th>
                                        <th>สังกัด</th>
                                        <th>สถานะ</th>
                                        <th>วันที่สร้าง</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $index => $user): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar me-3">
                                                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo $user['username']; ?></strong><br>
                                                    <small class="text-muted"><?php echo $user['email']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo $user['full_name']; ?></td>
                                        <td>
                                            <span class="role-badge badge-<?php echo $user['role']; ?>">
                                                <?php echo getRoleThai($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $user['department']; ?><br>
                                            <small class="text-muted"><?php echo $user['rank']; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo getStatusThai($user['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($user['created_at'])); ?><br>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($user['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info view-user" 
                                                    data-id="<?php echo $user['id']; ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewUserModal">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning edit-user" 
                                                    data-id="<?php echo $user['id']; ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editUserModal">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($user['role'] !== 'admin' && $user['id'] !== $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-danger delete-user" 
                                                    data-id="<?php echo $user['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel"><i class="fas fa-user-plus me-2"></i>เพิ่มผู้ใช้ใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addUserForm" action="process_user.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_user">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="username" class="form-label">ชื่อผู้ใช้ *</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                                <div class="form-text">สำหรับใช้ล็อกอินเข้าสู่ระบบ</div>
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label">รหัสผ่าน *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="form-text">ต้องมีความยาวอย่างน้อย 6 ตัวอักษร</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="full_name" class="form-label">ชื่อ-นามสกุล *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">อีเมล</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                            <div class="col-md-6">
                                <label for="department" class="form-label">สังกัด/หน่วยงาน</label>
                                <input type="text" class="form-control" id="department" name="department">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="rank" class="form-label">ยศ/ตำแหน่ง</label>
                                <input type="text" class="form-control" id="rank" name="rank">
                            </div>
                            <div class="col-md-6">
                                <label for="role" class="form-label">บทบาท *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="user">ผู้ใช้งาน</option>
                                    <option value="staff">เจ้าหน้าที่</option>
                                    <option value="approver">ผู้อนุมัติ</option>
                                    <option value="commander">ผู้บังคับบัญชา</option>
                                    <option value="admin">ผู้ดูแลระบบ</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="line_token" class="form-label">Line Notify Token</label>
                                <input type="text" class="form-control" id="line_token" name="line_token">
                                <div class="form-text">สำหรับรับการแจ้งเตือนผ่าน Line</div>
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label">สถานะ</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active">ใช้งาน</option>
                                    <option value="inactive">ระงับการใช้งาน</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewUserModalLabel">รายละเอียดผู้ใช้</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewUserContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">แก้ไขข้อมูลผู้ใช้</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editUserForm" action="process_user.php" method="POST">
                    <div class="modal-body" id="editUserContent">
                        <!-- Content will be loaded via AJAX -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-primary">บันทึกการเปลี่ยนแปลง</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePasswordModalLabel">เปลี่ยนรหัสผ่าน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="changePasswordForm" action="process_user.php" method="POST">
                    <div class="modal-body" id="changePasswordContent">
                        <!-- Content will be loaded via AJAX -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-primary">เปลี่ยนรหัสผ่าน</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#usersTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json'
                },
                pageLength: 10,
                order: [[0, 'asc']]
            });
        });

        // Handle add user form
        $('#addUserForm').on('submit', function(e) {
            e.preventDefault();
            
            const password = $('#password').val();
            if (password.length < 6) {
                Swal.fire({
                    icon: 'warning',
                    title: 'รหัสผ่านสั้นเกินไป',
                    text: 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร'
                });
                return;
            }
            
            const formData = new FormData(this);
            
            $.ajax({
                url: 'process_user.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ',
                            text: result.message,
                            confirmButtonText: 'ตกลง'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'ผิดพลาด',
                            text: result.message,
                            confirmButtonText: 'ตกลง'
                        });
                    }
                }
            });
        });

        // Handle view user
        $('.view-user').on('click', function() {
            const userId = $(this).data('id');
            
            $.ajax({
                url: 'get_user.php',
                type: 'GET',
                data: { id: userId, action: 'view' },
                success: function(response) {
                    $('#viewUserContent').html(response);
                }
            });
        });

        // Handle edit user
        $('.edit-user').on('click', function() {
            const userId = $(this).data('id');
            
            $.ajax({
                url: 'get_user.php',
                type: 'GET',
                data: { id: userId, action: 'edit' },
                success: function(response) {
                    $('#editUserContent').html(response);
                }
            });
        });

        // Handle change password
        $(document).on('click', '.change-password-btn', function() {
            const userId = $(this).data('id');
            
            $.ajax({
                url: 'get_user.php',
                type: 'GET',
                data: { id: userId, action: 'change_password' },
                success: function(response) {
                    $('#changePasswordContent').html(response);
                    $('#changePasswordModal').modal('show');
                }
            });
        });

        // Handle delete user
        $('.delete-user').on('click', function() {
            const userId = $(this).data('id');
            
            Swal.fire({
                title: 'ยืนยันการลบ',
                text: 'คุณต้องการลบผู้ใช้นี้ใช่หรือไม่?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ลบ',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'process_user.php',
                        type: 'POST',
                        data: {
                            action: 'delete_user',
                            id: userId
                        },
                        success: function(response) {
                            const result = JSON.parse(response);
                            if (result.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'สำเร็จ',
                                    text: result.message
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ผิดพลาด',
                                    text: result.message
                                });
                            }
                        }
                    });
                }
            });
        });

        // Handle edit user form submission
        $(document).on('submit', '#editUserForm', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            $.ajax({
                url: 'process_user.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ',
                            text: result.message,
                            confirmButtonText: 'ตกลง'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'ผิดพลาด',
                            text: result.message,
                            confirmButtonText: 'ตกลง'
                        });
                    }
                }
            });
        });

        // Handle change password form submission
        $(document).on('submit', '#changePasswordForm', function(e) {
            e.preventDefault();
            
            const newPassword = $('#new_password').val();
            const confirmPassword = $('#confirm_password').val();
            
            if (newPassword !== confirmPassword) {
                Swal.fire({
                    icon: 'error',
                    title: 'รหัสผ่านไม่ตรงกัน',
                    text: 'กรุณากรอกรหัสผ่านใหม่ให้ตรงกันทั้งสองช่อง'
                });
                return;
            }
            
            if (newPassword.length < 6) {
                Swal.fire({
                    icon: 'warning',
                    title: 'รหัสผ่านสั้นเกินไป',
                    text: 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร'
                });
                return;
            }
            
            const formData = new FormData(this);
            
            $.ajax({
                url: 'process_user.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ',
                            text: result.message,
                            confirmButtonText: 'ตกลง'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'ผิดพลาด',
                            text: result.message,
                            confirmButtonText: 'ตกลง'
                        });
                    }
                }
            });
        });
    </script>
</body>
</html>