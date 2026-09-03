<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support - Medicine Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit();
    }
    include 'includes/nav.php';
    ?>
    <button class="btn btn-primary d-lg-none" id="toggle-sidebar"><i class="bi bi-list"></i></button>
    <div class="main-content">
        <h2>Help & Support</h2>
        <div class="card p-3">
            <h5><i class="bi bi-question-circle"></i> Frequently Asked Questions</h5>
            <input type="text" class="form-control mb-3" id="faq-search" placeholder="Search FAQs..." aria-label="Search FAQs">
            <div class="accordion" id="faq-accordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            How do I add a new medicine?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faq-accordion">
                        <div class="accordion-body">
                            Navigate to the Medicine Management page, click "Add Medicine," fill in the form with details like name, barcode, quantity, type, and expiry date, then confirm to save.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            How do I generate a report?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faq-accordion">
                        <div class="accordion-body">
                            Go to the Reports page, select the report type (e.g., inventory or transactions), apply date filters if needed, and click "Preview" or download as PDF, Excel, or CSV.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            How do I enable voice mode in the chatbot?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faq-accordion">
                        <div class="accordion-body">
                            On the Chatbot page, click the "Voice" button to toggle voice mode and use your microphone to input queries about medicine stock or purpose.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                            How do I change system settings?
                        </button>
                    </h2>
                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faq-accordion">
                        <div class="accordion-body">
                            Go to the Settings page, update the low stock threshold, notification frequency, or backup frequency, and confirm to save. Changes apply system-wide.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                            How do I manage users?
                        </button>
                    </h2>
                    <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faq-accordion">
                        <div class="accordion-body">
                            Admins can access the Users page to add, edit, or delete users. Ensure you have admin privileges to perform these actions.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/phar_help.js"></script>
</body>
</html>