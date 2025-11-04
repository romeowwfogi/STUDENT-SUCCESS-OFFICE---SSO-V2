<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
require_once 'connection/db_connect.php';
require_once 'function/decrypt.php';

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: applicant_management.php");
    exit;
}

// Get form data
$file_type = isset($_POST['file_type']) ? $_POST['file_type'] : '';
$selected_columns = isset($_POST['columns']) ? $_POST['columns'] : [];
$cycle_id = isset($_POST['cycle_id']) ? (int)$_POST['cycle_id'] : null;

// Validate inputs
if (empty($file_type) || empty($selected_columns)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Please select file type and at least one column.'];
    header("Location: applicant_management.php");
    exit;
}

// Define available columns
$available_columns = [
    'id' => 'ID',
    'name' => 'Name',
    'email' => 'Email',
    'type' => 'Application Type',
    'status' => 'Status',
    'submitted_date' => 'Submitted Date',
    'cycle' => 'Admission Cycle'
];

// Fetch dynamic form fields for the selected cycle
$dynamic_fields = [];
if ($cycle_id) {
    $df_sql = "SELECT DISTINCT ff.name, ff.label, ff.input_type
               FROM form_fields ff
               JOIN form_steps fs ON ff.step_id = fs.id
               JOIN applicant_types at ON fs.applicant_type_id = at.id
               WHERE at.admission_cycle_id = ? AND fs.is_archived = 0 AND ff.is_archived = 0
               ORDER BY fs.step_order, ff.field_order";
    if ($df_stmt = $conn->prepare($df_sql)) {
        $df_stmt->bind_param("i", $cycle_id);
        $df_stmt->execute();
        $df_res = $df_stmt->get_result();
        while ($f = $df_res->fetch_assoc()) {
            $dynamic_fields[] = ['name' => $f['name'], 'label' => $f['label'], 'input_type' => $f['input_type']];
            $available_columns[$f['name']] = $f['label'];
        }
        $df_stmt->close();
    }
}

// Validate selected columns
$valid_columns = array_intersect($selected_columns, array_keys($available_columns));
if (empty($valid_columns)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid columns selected.'];
    header("Location: applicant_management.php");
    exit;
}

// Fetch applicant data
$sql = "SELECT
            s.id AS submission_id,
            s.submitted_at,
            s.status,
            at.name AS applicant_type,
            ac.id AS cycle_id,
            ac.cycle_name,
            u.email AS user_email,
            d_fname.field_value AS first_name,
            d_lname.field_value AS last_name
        FROM
            submissions s
        LEFT JOIN
            applicant_types at ON s.applicant_type_id = at.id
        LEFT JOIN
            admission_cycles ac ON at.admission_cycle_id = ac.id
        LEFT JOIN
            users u ON s.user_id = u.id
        LEFT JOIN
            submission_data d_fname ON (s.id = d_fname.submission_id AND d_fname.field_name = 'first_name')
        LEFT JOIN
            submission_data d_lname ON (s.id = d_lname.submission_id AND d_lname.field_name = 'last_name')
        WHERE
            (ac.is_archived = 0 OR ac.is_archived IS NULL)";

// Add cycle filter if cycle_id is provided
if ($cycle_id) {
    $sql .= " AND ac.id = " . $cycle_id;
}

$sql .= " ORDER BY s.submitted_at DESC";

$result = $conn->query($sql);
if (!$result) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error fetching applicant data: ' . $conn->error];
    header("Location: applicant_management.php");
    exit;
}

$applicants = [];
while ($row = $result->fetch_assoc()) {
    $applicants[] = $row;
}

if (empty($applicants)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'No applicant data found to export.'];
    header("Location: applicant_management.php");
    exit;
}

// Fetch submission_data values for dynamic fields across all listed applicants
$submission_field_values = [];
if (!empty($applicants) && !empty($dynamic_fields)) {
    $submission_ids = array_column($applicants, 'submission_id');
    $field_names = array_column($dynamic_fields, 'name');

    // Build safe IN lists
    $submission_ids_esc = implode(',', array_map('intval', $submission_ids));
    $field_names_esc = implode(',', array_map(function($n) use ($conn){ return "'".$conn->real_escape_string($n)."'"; }, $field_names));

    // Fetch text/select field values from submission_data
    $sd_sql = "SELECT submission_id, field_name, field_value
               FROM submission_data
               WHERE submission_id IN ($submission_ids_esc) AND field_name IN ($field_names_esc)";
    if ($sd_res = $conn->query($sd_sql)) {
        while ($row_sd = $sd_res->fetch_assoc()) {
            $sid = (int)$row_sd['submission_id'];
            $fname = $row_sd['field_name'];
            $submission_field_values[$sid][$fname] = $row_sd['field_value'];
        }
    }

    // Fetch file field values from submission_files
    $sf_sql = "SELECT submission_id, field_name, original_filename, file_path
               FROM submission_files
               WHERE submission_id IN ($submission_ids_esc) AND field_name IN ($field_names_esc)";
    if ($sf_res = $conn->query($sf_sql)) {
        while ($row_sf = $sf_res->fetch_assoc()) {
            $sid = (int)$row_sf['submission_id'];
            $fname = $row_sf['field_name'];
            // Store file info as filename for export
            $submission_field_values[$sid][$fname] = $row_sf['original_filename'];
        }
    }
}

// Prepare data for export
$export_data = [];
$headers = [];

// Build headers based on selected columns
foreach ($valid_columns as $column) {
    $headers[] = $available_columns[$column];
}

// Build data rows
foreach ($applicants as $applicant) {
    $row = [];
    foreach ($valid_columns as $column) {
        switch ($column) {
            case 'id':
                $row[] = $applicant['submission_id'];
                break;
            case 'name':
                $full_name = trim(($applicant['first_name'] ?? '') . ' ' . ($applicant['last_name'] ?? ''));
                $row[] = !empty($full_name) ? $full_name : 'N/A';
                break;
            case 'email':
                $email = $applicant['user_email'] ?? null;
                if ($email) {
                    $dec = decryptData($email);
                    $row[] = ($dec !== false && !empty($dec)) ? $dec : $email;
                } else {
                    $row[] = 'N/A';
                }
                break;
            case 'type':
                $row[] = $applicant['applicant_type'] ?? 'N/A';
                break;
            case 'status':
                $row[] = $applicant['status'] ?? 'N/A';
                break;
            case 'submitted':
            case 'submitted_date':
                $row[] = $applicant['submitted_at'] ? date('Y-m-d H:i:s', strtotime($applicant['submitted_at'])) : 'N/A';
                break;
            case 'cycle':
                $row[] = $applicant['cycle_name'] ?? 'N/A';
                break;
            default:
                // Check if this is a dynamic form field
                $field_value = $submission_field_values[$applicant['submission_id']][$column] ?? '';
                $row[] = !empty($field_value) ? $field_value : 'N/A';
                break;
        }
    }
    $export_data[] = $row;
}

// Generate filename
$timestamp = date('Y-m-d_H-i-s');
$cycle_suffix = $cycle_id ? "_cycle_{$cycle_id}" : "";
$filename = "applicants_export{$cycle_suffix}_{$timestamp}";

// Export based on file type
switch ($file_type) {
    case 'excel':
        exportToExcel($headers, $export_data, $filename);
        break;
    case 'pdf':
        exportToPDF($headers, $export_data, $filename);
        break;
    case 'docs':
        exportToDocs($headers, $export_data, $filename);
        break;
    default:
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid file type selected.'];
        header("Location: applicant_management.php");
        exit;
}

$conn->close();

// Export functions
function exportToExcel($headers, $data, $filename) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<table border="1">';
    echo '<tr>';
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr>';
    
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars($cell) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    exit;
}

function exportToPDF($headers, $data, $filename) {
    // Generate actual PDF content
    $pdfContent = generateSimplePDF($headers, $data, $filename);
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    header('Cache-Control: max-age=0');
    
    echo $pdfContent;
    exit;
}

function generateSimplePDF($headers, $data, $filename) {
    // Simple PDF generation - create a basic PDF structure with table
    $pdf = "%PDF-1.4\n";
    
    // PDF objects
    $objects = [];
    $objectCount = 0;
    
    // Object 1: Catalog
    $objectCount++;
    $objects[$objectCount] = $objectCount . " 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n";
    
    // Object 2: Pages
    $objectCount++;
    $objects[$objectCount] = $objectCount . " 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n";
    
    // Object 3: Page
    $objectCount++;
    $objects[$objectCount] = $objectCount . " 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n/Contents 4 0 R\n/Resources <<\n/Font <<\n/F1 5 0 R\n/F2 6 0 R\n>>\n>>\n>>\nendobj\n";
    
    // Calculate table dimensions
    $pageWidth = 612;
    $pageHeight = 792;
    $margin = 50;
    $tableWidth = $pageWidth - (2 * $margin);
    $columnCount = count($headers);
    $columnWidth = $tableWidth / $columnCount;
    $rowHeight = 25;
    $headerHeight = 30;
    
    // Build content stream
    $content = "BT\n";
    $content .= "/F2 16 Tf\n";
    $content .= ($margin) . " " . ($pageHeight - 80) . " Td\n";
    $content .= "(Applicants Export Report) Tj\n";
    $content .= "ET\n";
    
    $content .= "BT\n";
    $content .= "/F1 10 Tf\n";
    $content .= ($margin) . " " . ($pageHeight - 110) . " Td\n";
    $content .= "(Generated on: " . date('Y-m-d H:i:s') . ") Tj\n";
    $content .= "ET\n";
    
    // Table headers
    $yPos = $pageHeight - 150;
    
    // Draw header row background
    $content .= "0.9 0.9 0.9 rg\n";
    $content .= ($margin) . " " . ($yPos - $headerHeight) . " " . $tableWidth . " " . $headerHeight . " re\n";
    $content .= "f\n";
    
    // Draw header borders
    $content .= "0 0 0 RG\n";
    $content .= "0.5 w\n";
    for ($i = 0; $i <= $columnCount; $i++) {
        $x = $margin + ($i * $columnWidth);
        $content .= ($x) . " " . ($yPos - $headerHeight) . " m\n";
        $content .= ($x) . " " . $yPos . " l\n";
        $content .= "S\n";
    }
    
    // Top and bottom borders for header
    $content .= ($margin) . " " . $yPos . " m\n";
    $content .= ($margin + $tableWidth) . " " . $yPos . " l\n";
    $content .= "S\n";
    $content .= ($margin) . " " . ($yPos - $headerHeight) . " m\n";
    $content .= ($margin + $tableWidth) . " " . ($yPos - $headerHeight) . " l\n";
    $content .= "S\n";
    
    // Header text
    for ($i = 0; $i < count($headers); $i++) {
        $content .= "BT\n";
        $content .= "/F2 10 Tf\n";
        $content .= "0 0 0 rg\n";
        $x = $margin + ($i * $columnWidth) + 5;
        $y = $yPos - 20;
        $content .= ($x) . " " . $y . " Td\n";
        $content .= "(" . str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $headers[$i]) . ") Tj\n";
        $content .= "ET\n";
    }
    
    // Data rows
    $yPos -= $headerHeight;
    $maxRows = min(30, count($data)); // Limit to 30 rows for simplicity
    
    for ($rowIndex = 0; $rowIndex < $maxRows; $rowIndex++) {
        $row = $data[$rowIndex];
        $yPos -= $rowHeight;
        
        // Draw row borders
        $content .= "0 0 0 RG\n";
        $content .= "0.5 w\n";
        for ($i = 0; $i <= $columnCount; $i++) {
            $x = $margin + ($i * $columnWidth);
            $content .= ($x) . " " . ($yPos - $rowHeight) . " m\n";
            $content .= ($x) . " " . $yPos . " l\n";
            $content .= "S\n";
        }
        
        // Top and bottom borders for row
        $content .= ($margin) . " " . $yPos . " m\n";
        $content .= ($margin + $tableWidth) . " " . $yPos . " l\n";
        $content .= "S\n";
        $content .= ($margin) . " " . ($yPos - $rowHeight) . " m\n";
        $content .= ($margin + $tableWidth) . " " . ($yPos - $rowHeight) . " l\n";
        $content .= "S\n";
        
        // Row data
        for ($i = 0; $i < count($row) && $i < $columnCount; $i++) {
            $content .= "BT\n";
            $content .= "/F1 9 Tf\n";
            $content .= "0 0 0 rg\n";
            $x = $margin + ($i * $columnWidth) + 5;
            $y = $yPos - 15;
            $content .= ($x) . " " . $y . " Td\n";
            $cellValue = str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], (string)$row[$i]);
            $content .= "(" . substr($cellValue, 0, 30) . ") Tj\n"; // Increased text length limit
            $content .= "ET\n";
        }
    }
    
    // Object 4: Content stream
    $objectCount++;
    $contentLength = strlen($content);
    $objects[$objectCount] = $objectCount . " 0 obj\n<<\n/Length " . $contentLength . "\n>>\nstream\n" . $content . "\nendstream\nendobj\n";
    
    // Object 5: Font (Helvetica)
    $objectCount++;
    $objects[$objectCount] = $objectCount . " 0 obj\n<<\n/Type /Font\n/Subtype /Type1\n/BaseFont /Helvetica\n>>\nendobj\n";
    
    // Object 6: Font (Helvetica-Bold)
    $objectCount++;
    $objects[$objectCount] = $objectCount . " 0 obj\n<<\n/Type /Font\n/Subtype /Type1\n/BaseFont /Helvetica-Bold\n>>\nendobj\n";
    
    // Build PDF
    $pdf .= implode("", $objects);
    
    // Cross-reference table
    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n";
    $pdf .= "0 " . ($objectCount + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    
    $offset = 9; // Start after "%PDF-1.4\n"
    for ($i = 1; $i <= $objectCount; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
        $offset += strlen($objects[$i]);
    }
    
    // Trailer
    $pdf .= "trailer\n";
    $pdf .= "<<\n";
    $pdf .= "/Size " . ($objectCount + 1) . "\n";
    $pdf .= "/Root 1 0 R\n";
    $pdf .= ">>\n";
    $pdf .= "startxref\n";
    $pdf .= $xrefOffset . "\n";
    $pdf .= "%%EOF\n";
    
    return $pdf;
}

function exportToDocs($headers, $data, $filename) {
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '.doc"');
    header('Cache-Control: max-age=0');
    
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Applicants Export</title>';
    echo '</head>';
    echo '<body>';
    echo '<h1>Applicants Export Report</h1>';
    echo '<p>Generated on: ' . date('Y-m-d H:i:s') . '</p>';
    echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
    echo '<tr>';
    foreach ($headers as $header) {
        echo '<th style="background-color: #f2f2f2; padding: 8px;">' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr>';
    
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td style="padding: 8px;">' . htmlspecialchars($cell) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}
?>