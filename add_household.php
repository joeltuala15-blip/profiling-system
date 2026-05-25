<?php 
include('db.php');
include_once('toast_helpers.php');
session_start();

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'Secretary') {
    header("Location: login.php");
    exit();
}

$today = date('Y-m-d');
$page_toasts = [];
$error_toast = app_toast_from_error_code($_GET['error'] ?? '');
if ($error_toast) {
    $page_toasts[] = $error_toast;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Household</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --accent-blue: #2563eb;
            --text-gray: #64748b;
            --card-bg: #ffffff;
            --border-color: #cbd5e1;
        }
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            display: flex;
            height: 100vh;
            background: #f1f5f9;
            overflow: hidden;
        }
        .main-container {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            background: #f1f5f9;
        }
        .panel {
            background: var(--card-bg);
            padding: 32px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
            max-width: 960px;
            box-sizing: border-box;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
        }

        .choice-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 8px;
        }

        .choice-chip {
            position: relative;
            display: block;
            cursor: pointer;
            min-width: 0;
        }

        .choice-chip input {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
            pointer-events: none;
        }

        .choice-chip span {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: #ffffff;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.25;
            text-align: center;
            box-sizing: border-box;
        }

        .choice-chip:hover span {
            border-color: var(--accent-blue);
            background: #f8fafc;
        }

        .choice-chip input:focus-visible + span {
            outline: 2px solid rgba(37, 99, 235, 0.25);
            outline-offset: 2px;
        }

        .choice-chip input:checked + span {
            border-color: var(--accent-blue);
            background: rgba(130, 78, 57, 0.1);
            color: var(--accent-blue);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-sizing: border-box;
            font-family: inherit;
            font-size: 14px;
            background: #ffffff;
            color: #0f172a;
        }

        .form-group .choice-chip input {
            width: 1px;
            height: 1px;
            padding: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent-blue);
        }

        .form-group input::placeholder {
            color: #94a3b8;
        }

        .footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            border: none;
            text-decoration: none;
            cursor: pointer;
            font-family: inherit;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-cancel {
            background: #f1f5f9;
            color: #1e293b;
        }

        .btn-save {
            background: var(--accent-blue);
            color: white;
        }

        .btn-save:disabled {
            opacity: 0.75;
            cursor: wait;
        }

        .btn-save i {
            margin-right: 8px;
        }

        body.dark-mode {
            background: #0f172a;
        }

        body.dark-mode .main-container {
            background: #0f172a;
        }

        body.dark-mode .panel {
            background: #1e293b;
            border-color: #334155;
        }

        body.dark-mode .form-group label {
            color: #cbd5e1;
        }

        body.dark-mode .form-group input,
        body.dark-mode .form-group select {
            background: #0f172a;
            border-color: #334155;
            color: white;
        }

        body.dark-mode .choice-chip span {
            background: #0f172a;
            border-color: #334155;
            color: #e2e8f0;
        }

        body.dark-mode .choice-chip:hover span {
            border-color: #64748b;
            background: #172033;
        }

        body.dark-mode .choice-chip input:checked + span {
            background: rgba(130, 78, 57, 0.28);
            border-color: var(--accent-blue);
            color: #f8fafc;
        }

        body.dark-mode .form-group .choice-chip input {
            background: transparent;
            border-color: transparent;
        }

        body.dark-mode .form-group input::placeholder {
            color: #475569;
        }

        body.dark-mode .footer {
            border-top-color: #334155;
        }

        body.dark-mode .btn-cancel {
            background: #334155;
            color: white;
        }

        body.dark-mode .btn-cancel:hover {
            background: #475569;
        }

        @media (max-width: 768px) {
            body {
                height: auto;
                overflow: auto;
            }
            .main-container {
                height: auto;
                overflow: visible;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .panel {
                padding: 20px;
                border-radius: 16px;
            }
        }
    </style>
</head>
<body>
<script>
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
    }
</script>

<?php include_once('left_navbar.php'); ?>
<?php render_app_toasts($page_toasts); ?>

<div class="main-container">
    <header class="top-header">
        <div>
            <h2>Add New Household</h2>
            <p>Step 1: Fill in the household information</p>
        </div>
    </header>

    <div class="content-body">
        <div class="panel">
            <form id="add-household-form" action="save_household_session.php" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Household No. *</label>
                        <input type="text" name="hh_no" required>
                    </div>
                    <div class="form-group">
                        <label>Date of Survey</label>
                        <input type="date" name="survey_date" max="<?php echo $today; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Complete Address *</label>
                        <input type="text" name="address" placeholder="e.g., Block 1, Lot 5" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Purok *</label>
                        <select name="purok" required>
                            <option value="" disabled selected>Select Purok</option>
                            <option value="Maya">Maya</option>
                            <option value="Tikling">Tikling</option>
                            <option value="Perico">Perico</option>
                            <option value="Salampati">Salampati</option>
                            <option value="Punay">Punay</option>
                            <option value="Tamsi">Tamsi</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>House *</label>
                        <select name="house" required>
                            <option value="" disabled selected>Select house ownership</option>
                            <option value="Owned">Owned</option>
                            <option value="Rented">Rented</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Electricity *</label>
                        <select name="electricity" required>
                            <option value="" disabled selected>Select electricity</option>
                            <option value="Yes">Yes</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Type of House *</label>
                        <select name="house_type" required>
                            <option value="" disabled selected>Select type of house</option>
                            <option value="Concrete">Concrete</option>
                            <option value="Semi-concrete">Semi-concrete</option>
                            <option value="Light Materials">Light Materials</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Toilet *</label>
                        <select name="toilet" required>
                            <option value="" disabled selected>Select toilet type</option>
                            <option value="Flushed">Flushed</option>
                            <option value="Non-flushed">Non-flushed</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Vulnerability *</label>
                        <select name="vulnerability" required>
                            <option value="" disabled selected>Select vulnerability</option>
                            <option value="Flood">Flood</option>
                            <option value="Storm Surge">Storm Surge</option>
                            <option value="Fire">Fire</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Water Source *</label>
                        <select name="water" required>
                            <option value="" disabled selected>Select water source</option>
                            <option value="Metro Dumaguete City Water District">Metro Dumaguete City Water District</option>
                            <option value="Deep Well">Deep Well</option>
                        </select>
                    </div>
                </div>
                <div class="footer">
                    <a href="residents.php" class="btn btn-cancel">Cancel</a>
                    <button type="submit" class="btn btn-save">Next</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('add-household-form');
        if (!form) return;

        form.addEventListener('submit', function() {
            if (!form.checkValidity()) return;

            const submitButton = form.querySelector('button[type="submit"]');
            if (window.showAppToast) {
                window.showAppToast({
                    type: 'info',
                    title: 'Saving Household',
                    message: 'Household details are being saved. Please wait.'
                });
            }

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>Saving...';
            }
        });
    });
</script>
<?php render_form_draft_script('#add-household-form', 'add-household'); ?>
</body>
</html>
