<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Check authentication
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit();
}

$pageTitle = "แดชบอร์ดผู้ดูแลระบบ";
require_once '../templates/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">แดชบอร์ดผู้ดูแลระบบ</h1>
        <a href="reports.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-download fa-sm text-white-50"></i> ส่งออกรายงาน
        </a>
    </div>
    
    <!-- Statistics Row -->
    <div class="row">
        <!-- Total Bookings Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                การจองทั้งหมด</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_bookings']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pending Approvals Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                รออนุมัติ</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending_bookings']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Revenue Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                รายได้เดือนนี้</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">฿<?php echo number_format($stats['monthly_revenue'], 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Users Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                ผู้ใช้ระบบ</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $userCount; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Content Row -->
    <div class="row">
        <!-- Recent Bookings -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">การจองล่าสุด</h6>
                    <a href="bookings.php" class="btn btn-sm btn-primary">ดูทั้งหมด</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>รหัสการจอง</th>
                                    <th>ผู้พัก</th>
                                    <th>วันที่</th>
                                    <th>สถานะ</th>
                                    <th>ดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBookings as $booking): ?>
                                <tr>
                                    <td><?php echo $booking['booking_code']; ?></td>
                                    <td><?php echo $booking['guest_name']; ?></td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($booking['check_in_date'])); ?> - 
                                        <?php echo date('d/m/Y', strtotime($booking['check_out_date'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $this->getStatusBadge($booking['status']); ?>">
                                            <?php echo $this->getStatusText($booking['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="booking_detail.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">เมนูดำเนินการด่วน</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <a href="bookings.php?action=create" class="btn btn-success btn-block">
                                <i class="fas fa-plus"></i> สร้างการจอง
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="users.php" class="btn btn-info btn-block">
                                <i class="fas fa-users"></i> จัดการผู้ใช้
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="buildings.php" class="btn btn-warning btn-block">
                                <i class="fas fa-building"></i> จัดการอาคาร
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="settings.php" class="btn btn-secondary btn-block">
                                <i class="fas fa-cog"></i> ตั้งค่าระบบ
                            </a>
                        </div>
                    </div>
                    
                    <!-- Building Status -->
                    <div class="mt-4">
                        <h6 class="font-weight-bold text-primary">สถานะอาคาร</h6>
                        <?php foreach ($buildingStatus as $building): ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span><?php echo $building['building_name']; ?></span>
                                <span class="badge badge-<?php echo $this->getBuildingStatusBadge($building['status']); ?>">
                                    <?php echo $building['available_rooms']; ?>/<?php echo $building['total_rooms']; ?> ห้อง
                                </span>
                            </div>
                            <div class="progress" style="height: 5px;">
                                <div class="progress-bar bg-<?php echo $this->getBuildingStatusColor($building['status']); ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo ($building['available_rooms'] / $building['total_rooms']) * 100; ?>%">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>