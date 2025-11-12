<?php
// db_connect.php
$conn = new mysqli("195.35.61.9", "u337253893_PLPasigSSO", "PLPasigSSO2025", "u337253893_PLPasigSSO");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

date_default_timezone_set('Asia/Manila');

//START - API LIST
$stmt = $conn->prepare("SELECT * FROM api_list");
$stmt->execute();

$result = $stmt->get_result();

$UPDATE_GENERAL_IMAGES = 'UPDATE_GENERAL_IMAGES';
$UPDATE_GENERAL_IMAGES_API = null;

$SSO_EMAIL_SEND = 'SSO_EMAIL_SEND';
$SSO_EMAIL_SEND_API = null;

$EXAM_PERMIT_GENERATOR = 'EXAM_PERMIT_GENERATOR';
$EXAM_PERMIT_GENERATOR_API = null;

while ($row = $result->fetch_assoc()) {
    if ($row['name'] === $UPDATE_GENERAL_IMAGES) {
        $UPDATE_GENERAL_IMAGES_API = $row['api_url'];
    }

    if ($row['name'] === $SSO_EMAIL_SEND) {
        $SSO_EMAIL_SEND_API = $row['api_url'];
    }

    // Correctly match the generator API by name key
    if ($row['name'] === $EXAM_PERMIT_GENERATOR) {
        $EXAM_PERMIT_GENERATOR_API = $row['api_url'];
    }

    // stop if found
    if (
        $UPDATE_GENERAL_IMAGES_API &&
        $SSO_EMAIL_SEND_API &&
        $EXAM_PERMIT_GENERATOR_API
    ) {
        break;
    }
}
//END - API LIST


//START - EMAIL TEMPLATE
$stmt = $conn->prepare("SELECT * FROM email_template WHERE is_active = 1");
$stmt->execute();

$result = $stmt->get_result();

$ADMISSION_UPDATE = 'Admission Update';
$ADMISSION_UPDATE_TITLE = null;
$ADMISSION_UPDATE_SUBJECT = null;
$ADMISSION_UPDATE_TEMPLATE = "";

$EXAM_SCHEDULE = 'Exam Schedule';
$EXAM_SCHEDULE_TITLE = null;
$EXAM_SCHEDULE_SUBJECT = null;
$EXAM_SCHEDULE_TEMPLATE = "";

$EXAM_PERMIT = 'Exam Permit';
$EXAM_PERMIT_TITLE = null;
$EXAM_PERMIT_SUBJECT = null;
$EXAM_PERMIT_TEMPLATE = "";

$TITLE_SERVICE_REQUEST = 'Student Support Services - Service Request';
$SUBJECT_SERVICE_REQUEST = null;
$HTML_CODE_SERVICE_REQUEST = null;

while ($row = $result->fetch_assoc()) {
    if ($row['title'] === $ADMISSION_UPDATE) {
        $ADMISSION_UPDATE_TITLE = $row['title'];
        $ADMISSION_UPDATE_SUBJECT = $row['subject'];
        $ADMISSION_UPDATE_TEMPLATE = $row['html_code'];
    }

    if ($row['title'] === $EXAM_SCHEDULE) {
        $EXAM_SCHEDULE_TITLE = $row['title'];
        $EXAM_SCHEDULE_SUBJECT = $row['subject'];
        $EXAM_SCHEDULE_TEMPLATE = $row['html_code'];
    }

    if ($row['title'] === $EXAM_PERMIT) {
        $EXAM_PERMIT_TITLE = $row['title'];
        $EXAM_PERMIT_SUBJECT = $row['subject'];
        $EXAM_PERMIT_TEMPLATE = $row['html_code'];
    }

    if ($row['title'] === $TITLE_SERVICE_REQUEST) {
        $TITLE_SERVICE_REQUEST = $row['title'];
        $SUBJECT_SERVICE_REQUEST = $row['subject'];
        $HTML_CODE_SERVICE_REQUEST = $row['html_code'];
    }

    // stop if found
    if (
        $ADMISSION_UPDATE_TITLE &&
        $ADMISSION_UPDATE_SUBJECT &&
        $ADMISSION_UPDATE_TEMPLATE &&
        $EXAM_SCHEDULE_TITLE &&
        $EXAM_SCHEDULE_SUBJECT &&
        $EXAM_SCHEDULE_TEMPLATE &&
        $EXAM_PERMIT_TITLE &&
        $EXAM_PERMIT_SUBJECT &&
        $EXAM_PERMIT_TEMPLATE &&
        $TITLE_SERVICE_REQUEST &&
        $SUBJECT_SERVICE_REQUEST &&
        $HTML_CODE_SERVICE_REQUEST
    ) {
        break;
    }
}
//END - EMAIL TEMPLATE