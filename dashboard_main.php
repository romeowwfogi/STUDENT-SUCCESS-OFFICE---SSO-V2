<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
require_once 'connection/db_connect.php';

// Get filter parameters
$selected_cycle_id = isset($_GET['cycle_id']) ? (int)$_GET['cycle_id'] : null;
// Time aggregation granularity for charts: day, week, month, year
$selected_granularity = isset($_GET['granularity']) ? strtolower(trim($_GET['granularity'])) : 'month';
$allowed_granularities = ['day', 'week', 'month', 'year'];
if (!in_array($selected_granularity, $allowed_granularities, true)) {
    $selected_granularity = 'month';
}

// Fetch all admission cycles for dropdown
// Note: 'admission_cycles' table does not have a 'cycle_name' column; derive display name.
$cycles_sql = "SELECT id, admission_date_time_start, admission_date_time_end, academic_year_start, academic_year_end FROM admission_cycles WHERE is_archived = 0 ORDER BY academic_year_end DESC, admission_date_time_end DESC";
$cycles_result = $conn->query($cycles_sql);
$cycles = [];
while ($cycle = $cycles_result->fetch_assoc()) {
    $ayStart = $cycle['academic_year_start'] ?? null;
    $ayEnd = $cycle['academic_year_end'] ?? null;
    $startDt = $cycle['admission_date_time_start'] ?? null;
    $endDt = $cycle['admission_date_time_end'] ?? null;

    if ($ayStart && $ayEnd) {
        $cycle['cycle_name'] = "Academic Year {$ayStart}-{$ayEnd}";
    } elseif ($startDt && $endDt) {
        $cycle['cycle_name'] = date('M d, Y H:i', strtotime($startDt)) . ' – ' . date('M d, Y H:i', strtotime($endDt));
    } else {
        $cycle['cycle_name'] = 'Cycle ' . $cycle['id'];
    }

    $cycles[] = $cycle;
}

// Fetch all statuses for dropdown
$statuses_sql = "SELECT name FROM statuses ORDER BY name";
$statuses_result = $conn->query($statuses_sql);
$statuses = [];
while ($status = $statuses_result->fetch_assoc()) {
    $statuses[] = $status['name'];
}

// Build the main query for application statistics
$stats_sql = "SELECT 
    s.status,
    COUNT(*) as count
FROM submissions s
LEFT JOIN applicant_types at ON s.applicant_type_id = at.id
LEFT JOIN admission_cycles ac ON at.admission_cycle_id = ac.id
WHERE ac.is_archived = 0";

$params = [];
$types = "";

// Add cycle filter if selected
if ($selected_cycle_id) {
    $stats_sql .= " AND ac.id = ?";
    $params[] = $selected_cycle_id;
    $types .= "i";
}



$stats_sql .= " GROUP BY s.status ORDER BY s.status";

// Execute the query
$stats_stmt = $conn->prepare($stats_sql);
if (!empty($params)) {
    $stats_stmt->bind_param($types, ...$params);
}
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();

// Process results
$application_stats = [];
$total_applications = 0;
while ($row = $stats_result->fetch_assoc()) {
    $application_stats[$row['status']] = $row['count'];
    $total_applications += $row['count'];
}

// Ensure all statuses are represented (even with 0 count)
foreach ($statuses as $status) {
    if (!isset($application_stats[$status])) {
        $application_stats[$status] = 0;
    }
}

// Get selected cycle name for display
$selected_cycle_name = 'All Academic Year';
if ($selected_cycle_id) {
    foreach ($cycles as $cycle) {
        if ($cycle['id'] == $selected_cycle_id) {
            $selected_cycle_name = $cycle['cycle_name'];
            break;
        }
    }
}

// Fetch application data for chart based on selected granularity
switch ($selected_granularity) {
    case 'day':
        $bucket_expr = "DATE(s.submitted_at)"; // e.g., 2025-11-10
        $bucket_alias = 'bucket';
        $window_clause = "s.submitted_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $x_axis_label = 'Day';
        $period_desc = 'Past 30 days';
        $bucket_to_readable = function ($bucket) {
            return date('M d, Y', strtotime($bucket));
        };
        break;
    case 'week':
        // ISO year-week label like 2025-W45
        $bucket_expr = "CONCAT(DATE_FORMAT(s.submitted_at, '%x'), '-W', DATE_FORMAT(s.submitted_at, '%v'))";
        $bucket_alias = 'bucket';
        $window_clause = "s.submitted_at >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)";
        $x_axis_label = 'Week';
        $period_desc = 'Past 12 weeks';
        $bucket_to_readable = function ($bucket) {
            return $bucket;
        };
        break;
    case 'year':
        $bucket_expr = "YEAR(s.submitted_at)"; // e.g., 2025
        $bucket_alias = 'bucket';
        $window_clause = "s.submitted_at >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
        $x_axis_label = 'Year';
        $period_desc = 'Past 5 years';
        $bucket_to_readable = function ($bucket) {
            return (string)$bucket;
        };
        break;
    case 'month':
    default:
        $bucket_expr = "DATE_FORMAT(s.submitted_at, '%Y-%m')"; // e.g., 2025-11
        $bucket_alias = 'bucket';
        $window_clause = "s.submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";
        $x_axis_label = 'Month';
        $period_desc = 'Past 12 months';
        $bucket_to_readable = function ($bucket) {
            return date('M Y', strtotime($bucket . '-01'));
        };
        break;
}

$chart_sql = "SELECT 
    $bucket_expr AS $bucket_alias,
    s.status,
    COUNT(*) as count
FROM submissions s
LEFT JOIN applicant_types at ON s.applicant_type_id = at.id
LEFT JOIN admission_cycles ac ON at.admission_cycle_id = ac.id
WHERE ac.is_archived = 0 
    AND $window_clause";

$chart_params = [];
$chart_types = "";

// Add cycle filter for chart if selected
if ($selected_cycle_id) {
    $chart_sql .= " AND ac.id = ?";
    $chart_params[] = $selected_cycle_id;
    $chart_types .= "i";
}

$chart_sql .= " GROUP BY $bucket_expr, s.status 
                ORDER BY $bucket_alias ASC, s.status";

// Execute chart query
$chart_stmt = $conn->prepare($chart_sql);
if (!empty($chart_params)) {
    $chart_stmt->bind_param($chart_types, ...$chart_params);
}
$chart_stmt->execute();
$chart_result = $chart_stmt->get_result();

// Process chart data
$chart_data = [];
$months = [];
while ($row = $chart_result->fetch_assoc()) {
    $month = $row['bucket'];
    $status = $row['status'];
    $count = $row['count'];

    if (!in_array($month, $months)) {
        $months[] = $month;
    }

    if (!isset($chart_data[$status])) {
        $chart_data[$status] = [];
    }

    $chart_data[$status][$month] = $count;
}

// Fill missing months with 0 for each status
foreach ($chart_data as $status => $data) {
    foreach ($months as $month) {
        if (!isset($chart_data[$status][$month])) {
            $chart_data[$status][$month] = 0;
        }
    }
    ksort($chart_data[$status]);
}

// Convert buckets to readable labels
$readable_months = [];
foreach ($months as $month) {
    $readable_months[] = $bucket_to_readable($month);
}

// ==================== SERVICES DATA (LIST + STATUS COUNTS) ====================
// Fetch all service request statuses
$service_statuses_sql = "SELECT status_id, status_name FROM services_request_statuses ORDER BY status_id";
$service_statuses_result = $conn->query($service_statuses_sql);
$service_statuses = [];
while ($row = $service_statuses_result->fetch_assoc()) {
    $service_statuses[(int)$row['status_id']] = $row['status_name'];
}

// Fetch total counts per service request status
$service_req_stats_sql = "SELECT srs.status_name, COUNT(*) AS count
FROM services_requests sr
JOIN services_request_statuses srs ON sr.status_id = srs.status_id
GROUP BY srs.status_id
ORDER BY srs.status_id";
$service_req_stats_result = $conn->query($service_req_stats_sql);
$service_request_stats = [];
$total_service_requests = 0;
while ($row = $service_req_stats_result->fetch_assoc()) {
    $service_request_stats[$row['status_name']] = (int)$row['count'];
    $total_service_requests += (int)$row['count'];
}

// Ensure all service statuses are represented (even with 0 count)
foreach ($service_statuses as $status_name) {
    if (!isset($service_request_stats[$status_name])) {
        $service_request_stats[$status_name] = 0;
    }
}

// ==================== SERVICE REQUEST TRENDS ====================
switch ($selected_granularity) {
    case 'day':
        $svc_bucket_expr = "DATE(sr.requested_at)";
        $svc_bucket_alias = 'bucket';
        $svc_window_clause = "sr.requested_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $svc_x_axis_label = 'Day';
        $svc_period_desc = 'Past 30 days';
        $svc_bucket_to_readable = function ($bucket) {
            return date('M d, Y', strtotime($bucket));
        };
        break;
    case 'week':
        $svc_bucket_expr = "CONCAT(DATE_FORMAT(sr.requested_at, '%x'), '-W', DATE_FORMAT(sr.requested_at, '%v'))";
        $svc_bucket_alias = 'bucket';
        $svc_window_clause = "sr.requested_at >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)";
        $svc_x_axis_label = 'Week';
        $svc_period_desc = 'Past 12 weeks';
        $svc_bucket_to_readable = function ($bucket) {
            return $bucket;
        };
        break;
    case 'year':
        $svc_bucket_expr = "YEAR(sr.requested_at)";
        $svc_bucket_alias = 'bucket';
        $svc_window_clause = "sr.requested_at >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)";
        $svc_x_axis_label = 'Year';
        $svc_period_desc = 'Past 5 years';
        $svc_bucket_to_readable = function ($bucket) {
            return (string)$bucket;
        };
        break;
    case 'month':
    default:
        $svc_bucket_expr = "DATE_FORMAT(sr.requested_at, '%Y-%m')";
        $svc_bucket_alias = 'bucket';
        $svc_window_clause = "sr.requested_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";
        $svc_x_axis_label = 'Month';
        $svc_period_desc = 'Past 12 months';
        $svc_bucket_to_readable = function ($bucket) {
            return date('M Y', strtotime($bucket . '-01'));
        };
        break;
}

$svc_chart_sql = "SELECT 
    $svc_bucket_expr AS $svc_bucket_alias,
    srs.status_name AS status_name,
    COUNT(*) AS count
FROM services_requests sr
JOIN services_request_statuses srs ON sr.status_id = srs.status_id
WHERE $svc_window_clause
GROUP BY $svc_bucket_expr, srs.status_id
ORDER BY $svc_bucket_alias ASC, srs.status_id";

$svc_chart_result = $conn->query($svc_chart_sql);
$svc_chart_data = [];
$svc_months = [];
if ($svc_chart_result) {
    while ($row = $svc_chart_result->fetch_assoc()) {
        $month = $row['bucket'];
        $statusName = $row['status_name'];
        $count = (int)$row['count'];

        if (!in_array($month, $svc_months)) {
            $svc_months[] = $month;
        }

        if (!isset($svc_chart_data[$statusName])) {
            $svc_chart_data[$statusName] = [];
        }

        $svc_chart_data[$statusName][$month] = $count;
    }
}

// Fill missing months with 0 for each service status
foreach ($svc_chart_data as $statusName => $data) {
    foreach ($svc_months as $month) {
        if (!isset($svc_chart_data[$statusName][$month])) {
            $svc_chart_data[$statusName][$month] = 0;
        }
    }
    ksort($svc_chart_data[$statusName]);
}

// Convert buckets to readable format for service chart
$svc_readable_months = [];
foreach ($svc_months as $month) {
    $svc_readable_months[] = $svc_bucket_to_readable($month);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        .filters-container {
            background: var(--color-card);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--color-border);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-weight: 500;
            color: var(--color-text);
            font-size: 14px;
        }

        .filter-group select {
            padding: 10px 12px;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            font-size: 14px;
            background: var(--color-card);
            color: var(--color-text);
            min-width: 200px;
        }

        .filter-group select:focus {
            outline: none;
            border-color: #18a558;
            box-shadow: 0 0 0 3px rgba(24, 165, 88, 0.15);
        }

        .apply-filters-btn {
            background: var(--color-primary);
            color: var(--color-white);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            margin-top: 20px;
        }

        .apply-filters-btn:hover {
            background: #1b8f3e;
        }

        .stats-header {
            margin-bottom: 20px;
        }

        .stats-header h2 {
            color: #1f2937;
            margin: 0 0 8px 0;
        }

        .stats-header p {
            color: #6b7280;
            margin: 0;
        }

        .kpi-card__trend--neutral {
            color: #6b7280;
        }

        .kpi-card__icon--orange {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .kpi-card__icon--gray {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }

        @media (max-width: 768px) {
            .filters-container {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                width: 100%;
            }

            .filter-group select {
                min-width: 100%;
            }
        }
    </style>
</head>

<body>
    <!-- Mobile Navbar -->
    <?php
    include "includes/mobile_navbar.php";
    ?>

    <!-- Layout Container -->
    <div class="layout">
        <!-- Sidebar -->
        <?php
        include "includes/sidebar.php";
        ?>

        <!-- Main Content -->
        <main class="main-content">


            <!-- Header -->
            <header class="header">
                <div class="header__left">
                    <h1>Dashboard Overview</h1>
                </div>
            </header>

            <section class="section active" id="main_content_section">
                <!-- Filters Section -->
                <div class="filters-container" style="display: flex; align-items: end; gap: 16px; flex-wrap: nowrap; width: 100%; justify-content: space-between;">
                    <form method="GET" action="" style="display: flex; gap: 16px; align-items: end; flex-wrap: nowrap; width: 100%; flex: 1 1 auto;">
                        <div class="filter-group">
                            <label for="cycle_id">Admission Cycle:</label>
                            <select name="cycle_id" id="cycle_id">
                                <option value="">All Academic Year</option>
                                <?php foreach ($cycles as $cycle): ?>
                                    <option value="<?php echo $cycle['id']; ?>"
                                        <?php echo ($selected_cycle_id == $cycle['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cycle['cycle_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="granularity">Aggregate By:</label>
                            <select name="granularity" id="granularity">
                                <option value="day" <?php echo $selected_granularity === 'day' ? 'selected' : ''; ?>>Day</option>
                                <option value="week" <?php echo $selected_granularity === 'week' ? 'selected' : ''; ?>>Week</option>
                                <option value="month" <?php echo $selected_granularity === 'month' ? 'selected' : ''; ?>>Month</option>
                                <option value="year" <?php echo $selected_granularity === 'year' ? 'selected' : ''; ?>>Year</option>
                            </select>
                        </div>

                        <div class="filter-group" style="margin: 0;">
                            <label for="reportScope" class="filter-label">Report Content</label>
                            <select name="reportScope" id="reportScope" class="filter-input" style="min-width: 220px;">
                                <option value="both" selected>Applications + Services</option>
                                <option value="applications">Applications only</option>
                                <option value="services">Services only</option>
                            </select>
                        </div>

                        <button type="submit" class="apply-filters-btn" style="display:none;">Apply Filters</button>
                    </form>
                    <button type="button" id="downloadPdfBtn" class="apply-filters-btn" style="margin-top: 0; flex: 0 0 auto; white-space: nowrap;">Download Report (PDF)</button>
                </div>

                <!-- Statistics Header -->
                <div class="stats-header">
                    <h2>Application Statistics</h2>
                    <p>Showing data for: <?php echo htmlspecialchars($selected_cycle_name); ?></p>
                </div>

                <!-- KPI Cards -->
                <div class="kpi-grid">
                    <!-- Total Applications Card -->
                    <div class="kpi-card">
                        <div class="kpi-card__header">
                            <div>
                                <div class="kpi-card__label">Total Applications</div>
                            </div>
                            <div class="kpi-card__icon kpi-card__icon--blue">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 28px; height: 28px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                        </div>
                        <div class="kpi-card__value"><?php echo number_format($total_applications); ?></div>
                        <div class="kpi-card__trend kpi-card__trend--neutral">
                            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            Current filter results
                        </div>
                    </div>

                    <?php
                    foreach ($statuses as $status):
                        $count = isset($application_stats[$status]) ? $application_stats[$status] : 0;
                        $percentage = $total_applications > 0 ? round(($count / $total_applications) * 100, 1) : 0;
                    ?>
                        <div class="kpi-card">
                            <div class="kpi-card__header">
                                <div>
                                    <div class="kpi-card__label"><?php echo htmlspecialchars($status); ?></div>
                                </div>
                            </div>
                            <div class="kpi-card__value"><?php echo number_format($count); ?></div>
                            <div class="kpi-card__trend kpi-card__trend--neutral">
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                <?php echo $percentage; ?>% of total
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Chart Container -->
                <div class="chart-container">
                    <div class="chart-container__header">
                        <h2 class="chart-container__title">Application Trends</h2>
                        <p class="chart-container__subtitle"><?php echo htmlspecialchars(ucfirst($selected_granularity)); ?> application statistics for <?php echo htmlspecialchars($selected_cycle_name); ?> - <?php echo htmlspecialchars($period_desc); ?></p>
                    </div>
                    <div class="chart-container__canvas">
                        <?php if (empty($chart_data) || empty($months)): ?>
                            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 300px; color: #6b7280; text-align: center;">
                                <svg style="width: 64px; height: 64px; margin-bottom: 16px; opacity: 0.5;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">No Data Available</h3>
                                <p style="font-size: 14px; margin-bottom: 4px;">No application submissions found for <?php echo htmlspecialchars($selected_cycle_name); ?></p>
                                <p style="font-size: 12px; opacity: 0.7;">Try selecting a different admission cycle or check if there are any submitted applications.</p>
                            </div>
                        <?php else: ?>
                            <canvas id="applicationChart"></canvas>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Services Requests Statistics -->
                <div class="stats-header" style="margin-top: 24px;">
                    <h2>Service Request Statistics</h2>
                    <p>Aggregated counts across all services</p>
                </div>

                <div class="kpi-grid">
                    <!-- Total Service Requests -->
                    <div class="kpi-card">
                        <div class="kpi-card__header">
                            <div>
                                <div class="kpi-card__label">Total Service Requests</div>
                            </div>
                            <div class="kpi-card__icon kpi-card__icon--blue">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 28px; height: 28px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zm-8 8a4 4 0 00-4 4v1h16v-1a4 4 0 00-4-4H8z" />
                                </svg>
                            </div>
                        </div>
                        <div class="kpi-card__value"><?php echo number_format($total_service_requests); ?></div>
                        <div class="kpi-card__trend kpi-card__trend--neutral">All statuses</div>
                    </div>

                    <?php foreach ($service_request_stats as $svc_status => $svc_count): ?>
                        <div class="kpi-card">
                            <div class="kpi-card__header">
                                <div>
                                    <div class="kpi-card__label"><?php echo htmlspecialchars($svc_status); ?></div>
                                </div>
                            </div>
                            <div class="kpi-card__value"><?php echo number_format($svc_count); ?></div>
                            <div class="kpi-card__trend kpi-card__trend--neutral">
                                <?php echo ($total_service_requests > 0) ? round(($svc_count / $total_service_requests) * 100, 1) : 0; ?>% of service requests
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Service Request Trends Chart -->
                <div class="chart-container" style="margin-top: 24px;">
                    <div class="chart-container__header">
                        <h2 class="chart-container__title">Service Request Trends</h2>
                        <p class="chart-container__subtitle"><?php echo htmlspecialchars(ucfirst($selected_granularity)); ?> service request statistics - <?php echo htmlspecialchars($svc_period_desc); ?></p>
                    </div>
                    <div class="chart-container__canvas">
                        <?php if (empty($svc_chart_data) || empty($svc_months)): ?>
                            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 300px; color: #6b7280; text-align: center;">
                                <svg style="width: 64px; height: 64px; margin-bottom: 16px; opacity: 0.5;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h4l3-3 4 8 3-4h4" />
                                </svg>
                                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">No Data Available</h3>
                                <p style="font-size: 14px; margin-bottom: 4px;">No service requests found for the selected period.</p>
                                <p style="font-size: 12px; opacity: 0.7;">Try checking later or ensure the services feature has requests.</p>
                            </div>
                        <?php else: ?>
                            <canvas id="serviceChart"></canvas>
                        <?php endif; ?>
                    </div>
                </div>

            </section>
        </main>
    </div>

    <script src="dahsboard.js"></script>
    <script>
        // Add AJAX functionality for dynamic filtering
        document.addEventListener('DOMContentLoaded', function() {
            const cycleSelect = document.getElementById('cycle_id');
            const granularitySelect = document.getElementById('granularity');

            // Auto-submit form when filters change
            function handleFilterChange() {
                const form = document.querySelector('.filters-container form');
                const formData = new FormData(form);
                const params = new URLSearchParams(formData);

                // Update URL without page reload
                const newUrl = window.location.pathname + '?' + params.toString();
                window.history.pushState({}, '', newUrl);

                // Reload the page to show new data
                window.location.reload();
            }

            cycleSelect.addEventListener('change', handleFilterChange);
            if (granularitySelect) {
                granularitySelect.addEventListener('change', handleFilterChange);
            }

            // Remove the submit button since we're auto-submitting
            const submitBtn = document.querySelector('.apply-filters-btn');
            if (submitBtn) {
                submitBtn.style.display = 'none';
            }

            // PDF Generation
            const downloadPdfBtn = document.getElementById('downloadPdfBtn');
            const reportScopeEl = document.getElementById('reportScope');
            const selectedGranularity = '<?php echo addslashes($selected_granularity); ?>';
            const selectedCycleName = '<?php echo addslashes($selected_cycle_name); ?>';
            const periodDesc = '<?php echo addslashes($period_desc); ?>';
            const svcPeriodDesc = '<?php echo addslashes($svc_period_desc); ?>';
            const appStats = <?php echo json_encode($application_stats); ?>;
            const totalApplications = <?php echo (int)$total_applications; ?>;
            const svcStats = <?php echo json_encode($service_request_stats); ?>;
            const totalServiceRequests = <?php echo (int)$total_service_requests; ?>;

            function generatePdfReport() {
                const {
                    jsPDF
                } = window.jspdf;
                if (!jsPDF) return;
                const doc = new jsPDF({
                    orientation: 'portrait',
                    unit: 'pt',
                    format: 'a4'
                });
                const pageWidth = doc.internal.pageSize.getWidth();
                const pageHeight = doc.internal.pageSize.getHeight();
                const margin = 36; // 0.5in
                let cursorY = margin;
                const scope = reportScopeEl ? reportScopeEl.value : 'both';
                const scopeLabel = scope === 'both' ? 'Applications + Services' : (scope === 'applications' ? 'Applications' : 'Services');

                // Header
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(16);
                doc.text('SSO Dashboard Report', margin, cursorY);
                cursorY += 22;
                doc.setFontSize(12);
                doc.setFont('helvetica', 'normal');
                doc.text(`Cycle: ${selectedCycleName || 'All Academic Year'}`, margin, cursorY);
                cursorY += 16;
                doc.text(`Aggregate By: ${selectedGranularity}`, margin, cursorY);
                cursorY += 16;
                doc.text(`Application Period: ${periodDesc}`, margin, cursorY);
                cursorY += 16;
                doc.text(`Service Period: ${svcPeriodDesc}`, margin, cursorY);
                cursorY += 22;
                doc.text(`Sections: ${scopeLabel}`, margin, cursorY);
                cursorY += 18;

                // Application stats and chart (if scope includes applications)
                if (scope !== 'services') {
                    doc.setFont('helvetica', 'bold');
                    doc.text('Application Statistics', margin, cursorY);
                    cursorY += 18;
                    doc.setFont('helvetica', 'normal');
                    doc.text(`Total Applications: ${totalApplications}`, margin, cursorY);
                    cursorY += 16;
                    Object.keys(appStats).forEach((status) => {
                        doc.text(`${status}: ${appStats[status]}`, margin, cursorY);
                        cursorY += 14;
                    });
                    cursorY += 10;

                    // Application chart image
                    const appCanvas = document.getElementById('applicationChart');
                    if (appCanvas) {
                        try {
                            const imgData = appCanvas.toDataURL('image/png', 1.0);
                            const imgWidth = pageWidth - margin * 2;
                            const ratio = appCanvas.height / appCanvas.width;
                            const imgHeight = imgWidth * ratio;
                            if (cursorY + imgHeight > pageHeight - margin) {
                                doc.addPage();
                                cursorY = margin;
                            }
                            doc.addImage(imgData, 'PNG', margin, cursorY, imgWidth, imgHeight);
                            cursorY += imgHeight + 18;
                        } catch (e) {}
                    }
                }

                // Service stats and chart (if scope includes services)
                if (scope !== 'applications') {
                    doc.setFont('helvetica', 'bold');
                    doc.text('Service Request Statistics', margin, cursorY);
                    cursorY += 18;
                    doc.setFont('helvetica', 'normal');
                    doc.text(`Total Service Requests: ${totalServiceRequests}`, margin, cursorY);
                    cursorY += 16;
                    Object.keys(svcStats).forEach((status) => {
                        doc.text(`${status}: ${svcStats[status]}`, margin, cursorY);
                        cursorY += 14;
                    });
                    cursorY += 10;

                    // Service chart image
                    const svcCanvas = document.getElementById('serviceChart');
                    if (svcCanvas) {
                        try {
                            const imgData2 = svcCanvas.toDataURL('image/png', 1.0);
                            const imgWidth2 = pageWidth - margin * 2;
                            const ratio2 = svcCanvas.height / svcCanvas.width;
                            const imgHeight2 = imgWidth2 * ratio2;
                            if (cursorY + imgHeight2 > pageHeight - margin) {
                                doc.addPage();
                                cursorY = margin;
                            }
                            doc.addImage(imgData2, 'PNG', margin, cursorY, imgWidth2, imgHeight2);
                            cursorY += imgHeight2 + 18;
                        } catch (e) {}
                    }
                }

                // Footer
                doc.setFontSize(10);
                doc.text(`Generated: ${new Date().toLocaleString()}`, margin, pageHeight - margin);

                doc.save(`SSO-Dashboard-Report-${scope}-${selectedGranularity}.pdf`);
            }

            if (downloadPdfBtn) {
                downloadPdfBtn.addEventListener('click', generatePdfReport);
            }

            // Initialize Application Trends Chart only if there's data
            const chartCanvas = document.getElementById('applicationChart');

            if (chartCanvas) {
                const ctx = chartCanvas.getContext('2d');

                // Prepare chart data from PHP
                const chartLabels = <?php echo json_encode($readable_months); ?>;
                const chartBucketKeys = <?php echo json_encode($months); ?>;
                const chartData = <?php echo json_encode($chart_data); ?>;
                const xAxisLabel = '<?php echo addslashes($x_axis_label); ?>';

                // Only create chart if we have data
                if (chartLabels.length > 0 && Object.keys(chartData).length > 0) {

                    // Define colors for different statuses
                    const statusColors = {
                        'Pending': {
                            backgroundColor: 'rgba(251, 191, 36, 0.2)',
                            borderColor: 'rgba(251, 191, 36, 1)'
                        },
                        'In Review': {
                            backgroundColor: 'rgba(59, 130, 246, 0.2)',
                            borderColor: 'rgba(59, 130, 246, 1)'
                        },
                        'Waitlisted': {
                            backgroundColor: 'rgba(245, 158, 11, 0.2)',
                            borderColor: 'rgba(245, 158, 11, 1)'
                        },
                        'Accepted': {
                            backgroundColor: 'rgba(34, 197, 94, 0.2)',
                            borderColor: 'rgba(34, 197, 94, 1)'
                        },
                        'Rejected': {
                            backgroundColor: 'rgba(239, 68, 68, 0.2)',
                            borderColor: 'rgba(239, 68, 68, 1)'
                        }
                    };

                    // Prepare datasets for Chart.js
                    const datasets = [];
                    Object.keys(chartData).forEach(status => {
                        // Create data array that matches the chart labels order
                        const data = [];
                        chartBucketKeys.forEach((bucketKey) => {
                            data.push(chartData[status][bucketKey] || 0);
                        });

                        const colors = statusColors[status] || {
                            backgroundColor: 'rgba(107, 114, 128, 0.2)',
                            borderColor: 'rgba(107, 114, 128, 1)'
                        };

                        datasets.push({
                            label: status,
                            data: data,
                            backgroundColor: colors.backgroundColor,
                            borderColor: colors.borderColor,
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        });
                    });

                    // Get selected cycle name for chart title
                    const selectedCycleName = '<?php echo addslashes($selected_cycle_name); ?>';
                    const chartTitle = selectedCycleName === 'All Academic Year' ?
                        'Application Trends Over Time (All Academic Year)' :
                        'Application Trends Over Time - ' + selectedCycleName;

                    // Create the chart
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: chartLabels,
                            datasets: datasets
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: chartTitle,
                                    font: {
                                        size: 16,
                                        weight: 'bold'
                                    }
                                },
                                legend: {
                                    display: true,
                                    position: 'top'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    },
                                    title: {
                                        display: true,
                                        text: 'Number of Applications'
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: xAxisLabel
                                    }
                                }
                            },
                            interaction: {
                                intersect: false,
                                mode: 'index'
                            }
                        }
                    });
                } // End of data check
            } // End of canvas check

            // Initialize Service Request Trends Chart only if there's data
            const svcChartCanvas = document.getElementById('serviceChart');

            if (svcChartCanvas) {
                const sctx = svcChartCanvas.getContext('2d');

                const svcChartLabels = <?php echo json_encode($svc_readable_months); ?>;
                const svcBucketKeys = <?php echo json_encode($svc_months); ?>;
                const svcChartData = <?php echo json_encode($svc_chart_data); ?>;
                const svcXAxisLabel = '<?php echo addslashes($svc_x_axis_label); ?>';

                if (svcChartLabels.length > 0 && Object.keys(svcChartData).length > 0) {
                    const serviceStatusColors = {
                        'Pending': {
                            backgroundColor: 'rgba(251, 191, 36, 0.2)',
                            borderColor: 'rgba(251, 191, 36, 1)'
                        },
                        'In Progress': {
                            backgroundColor: 'rgba(59, 130, 246, 0.2)',
                            borderColor: 'rgba(59, 130, 246, 1)'
                        },
                        'Completed': {
                            backgroundColor: 'rgba(34, 197, 94, 0.2)',
                            borderColor: 'rgba(34, 197, 94, 1)'
                        },
                        'Rejected': {
                            backgroundColor: 'rgba(239, 68, 68, 0.2)',
                            borderColor: 'rgba(239, 68, 68, 1)'
                        },
                        'Needs Resubmission': {
                            backgroundColor: 'rgba(245, 158, 11, 0.2)',
                            borderColor: 'rgba(245, 158, 11, 1)'
                        }
                    };

                    const svcDatasets = [];
                    Object.keys(svcChartData).forEach(statusName => {
                        const data = [];
                        svcBucketKeys.forEach((bucketKey) => {
                            data.push(svcChartData[statusName][bucketKey] || 0);
                        });

                        const colors = serviceStatusColors[statusName] || {
                            backgroundColor: 'rgba(107, 114, 128, 0.2)',
                            borderColor: 'rgba(107, 114, 128, 1)'
                        };
                        svcDatasets.push({
                            label: statusName,
                            data: data,
                            backgroundColor: colors.backgroundColor,
                            borderColor: colors.borderColor,
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        });
                    });

                    new Chart(sctx, {
                        type: 'line',
                        data: {
                            labels: svcChartLabels,
                            datasets: svcDatasets
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Service Request Trends',
                                    font: {
                                        size: 16,
                                        weight: 'bold'
                                    }
                                },
                                legend: {
                                    display: true,
                                    position: 'top'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    },
                                    title: {
                                        display: true,
                                        text: 'Number of Requests'
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: svcXAxisLabel
                                    }
                                }
                            },
                            interaction: {
                                intersect: false,
                                mode: 'index'
                            }
                        }
                    });
                }
            }
        });
    </script>
</body>

</html>