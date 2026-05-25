<?php
include('db.php');
include_once('toast_helpers.php');
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Secretary') {
    header("Location: login.php");
    exit();
}

$household_no = $_GET['household_no'] ?? '';
$safe_hh_no = mysqli_real_escape_string($conn, $household_no);

$query = "SELECT * FROM households WHERE household_no = '$safe_hh_no' LIMIT 1";
$result = mysqli_query($conn, $query);
$household = $result ? mysqli_fetch_assoc($result) : null;

if (!$household) {
    header("Location: residents.php?error=household_not_found");
    exit();
}

$errors = [];
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_household_no = trim($_POST['household_no'] ?? '');
    $survey_date = trim($_POST['survey_date'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $purok = trim($_POST['purok'] ?? '');
    $house = trim($_POST['house'] ?? '');
    $electricity = trim($_POST['electricity'] ?? '');
    $house_type = trim($_POST['house_type'] ?? '');
    $toilet = trim($_POST['toilet'] ?? '');
    $vulnerability = trim($_POST['vulnerability'] ?? '');
    $water = trim($_POST['water'] ?? '');

    if ($new_household_no === '') $errors[] = 'Household number is required.';
    if ($survey_date === '') $errors[] = 'Survey date is required.';
    if ($survey_date !== '' && strtotime($survey_date) > strtotime($today)) {
        $errors[] = 'Survey date cannot be in the future.';
    }
    if ($address === '') $errors[] = 'Address is required.';
    if ($purok === '') $errors[] = 'Purok is required.';

    if ($new_household_no !== $household['household_no']) {
        $check_stmt = $conn->prepare("SELECT id FROM households WHERE household_no = ? LIMIT 1");
        if ($check_stmt) {
            $check_stmt->bind_param('s', $new_household_no);
            $check_stmt->execute();
            $check_stmt->store_result();
            if ($check_stmt->num_rows > 0) {
                $errors[] = 'That household number is already used.';
            }
            $check_stmt->close();
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();

        try {
            $household_id = (int)$household['id'];
            $old_household_no = $household['household_no'];

            $stmt = $conn->prepare(
                "UPDATE households
                 SET household_no = ?, survey_date = ?, address = ?, purok = ?, house = ?,
                     electricity = ?, house_type = ?, toilet = ?, vulnerability = ?, water = ?
                 WHERE id = ?"
            );

            if (!$stmt) {
                throw new Exception($conn->error);
            }

            $stmt->bind_param(
                'ssssssssssi',
                $new_household_no,
                $survey_date,
                $address,
                $purok,
                $house,
                $electricity,
                $house_type,
                $toilet,
                $vulnerability,
                $water,
                $household_id
            );
            $stmt->execute();
            $stmt->close();

            if ($new_household_no !== $old_household_no) {
                $member_stmt = $conn->prepare("UPDATE residents SET household_no = ? WHERE household_no = ?");
                if (!$member_stmt) {
                    throw new Exception($conn->error);
                }
                $member_stmt->bind_param('ss', $new_household_no, $old_household_no);
                $member_stmt->execute();
                $member_stmt->close();
            }

            $action_desc = mysqli_real_escape_string($conn, "Updated household #$new_household_no");
            mysqli_query($conn, "INSERT INTO logs (action) VALUES ('$action_desc')");

            $conn->commit();
            header("Location: household_members.php?household_no=" . urlencode($new_household_no) . "&success=household_updated");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Failed to update household. Please try again.';
        }
    }
}

$page_toasts = [];
if (!empty($errors)) {
    $page_toasts[] = app_toast_from_message(implode("\n", $errors));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Household</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --accent-blue: #2563eb;
            --text-gray: #64748b;
            --hero-bg: #1e293b;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
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
            border-radius: 24px;
            box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.05);
            border: 1px solid var(--border-color);
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
            border: 1px solid #cbd5e1;
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
            border: 1px solid #cbd5e1;
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
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-group input::placeholder {
            color: #94a3b8;
        }

        .error-box {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
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
            box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.3);
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
            <h2 style="margin:0;">Edit Household</h2>
            <p style="margin:0; color: var(--text-gray);">Update household profile information</p>
        </div>
    </header>

    <div class="content-body">
        <div class="panel">
            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form id="edit-household-form" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Household No.</label>
                        <input type="text" name="household_no" value="<?php echo htmlspecialchars($_POST['household_no'] ?? $household['household_no']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Date of Survey</label>
                        <input type="date" name="survey_date" max="<?php echo $today; ?>" value="<?php echo htmlspecialchars($_POST['survey_date'] ?? $household['survey_date']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Complete Address</label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars($_POST['address'] ?? $household['address']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Purok</label>
                        <?php $purok_value = $_POST['purok'] ?? $household['purok']; ?>
                        <select name="purok" required>
                            <?php foreach (['Maya', 'Tikling', 'Perico', 'Salampati', 'Punay', 'Tamsi'] as $option): ?>
                                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $purok_value === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>House</label>
                        <?php $house_value = $_POST['house'] ?? $household['house']; ?>
                        <select name="house" required>
                            <option value="" disabled <?php echo $house_value === '' ? 'selected' : ''; ?>>Select house ownership</option>
                            <?php foreach (['Owned', 'Rented'] as $option): ?>
                                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $house_value === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Electricity</label>
                        <?php $electricity_value = $_POST['electricity'] ?? $household['electricity']; ?>
                        <select name="electricity" required>
                            <option value="" disabled <?php echo $electricity_value === '' ? 'selected' : ''; ?>>Select electricity</option>
                            <?php foreach (['Yes', 'No'] as $option): ?>
                                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $electricity_value === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Type of House</label>
                        <?php $house_type_value = $_POST['house_type'] ?? $household['house_type']; ?>
                        <select name="house_type" required>
                            <option value="" disabled <?php echo $house_type_value === '' ? 'selected' : ''; ?>>Select type of house</option>
                            <?php foreach (['Concrete', 'Semi-concrete', 'Light Materials'] as $option): ?>
                                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $house_type_value === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Toilet</label>
                        <?php $toilet_value = $_POST['toilet'] ?? $household['toilet']; ?>
                        <select name="toilet" required>
                            <option value="" disabled <?php echo $toilet_value === '' ? 'selected' : ''; ?>>Select toilet type</option>
                            <?php foreach (['Flushed', 'Non-flushed', 'No'] as $option): ?>
                                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $toilet_value === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Vulnerability</label>
                        <?php $vulnerability_value = $_POST['vulnerability'] ?? $household['vulnerability']; ?>
                        <select name="vulnerability" required>
                            <option value="" disabled <?php echo $vulnerability_value === '' ? 'selected' : ''; ?>>Select vulnerability</option>
                            <?php foreach (['Flood', 'Storm Surge', 'Fire'] as $option): ?>
                                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $vulnerability_value === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Water Source</label>
                        <?php $water_value = $_POST['water'] ?? $household['water']; ?>
                        <select name="water" required>
                            <option value="" disabled <?php echo $water_value === '' ? 'selected' : ''; ?>>Select water source</option>
                            <?php foreach (['Metro Dumaguete City Water District', 'Deep Well'] as $option): ?>
                                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $water_value === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="footer">
                    <a href="household_members.php?household_no=<?php echo urlencode($household['household_no']); ?>" class="btn btn-cancel">Cancel</a>
                    <button type="submit" class="btn btn-save">Save Household</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('edit-household-form');
        if (!form) return;

        form.addEventListener('submit', function() {
            if (!form.checkValidity()) return;

            const submitButton = form.querySelector('button[type="submit"]');
            if (window.showAppToast) {
                window.showAppToast({
                    type: 'info',
                    title: 'Updating Household',
                    message: 'Changes are being saved. Please wait.'
                });
            }

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>Saving...';
            }
        });
    });
</script>
<?php render_form_draft_script('#edit-household-form', 'edit-household-' . $household['id']); ?>
</body>
</html>
