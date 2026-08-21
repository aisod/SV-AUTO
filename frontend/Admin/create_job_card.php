<?php
/**
 * Legacy forwarder — form posts used to miss the JobCard/ folder because of <base href>.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/JobCard/create_job_card.php';
    exit;
}

header('Location: JobCard/add_job_card.php?error=' . rawurlencode('Invalid request method'));
exit;
