<?php
require_once __DIR__ . '/../core/init.php';

if (!is_superadmin()) {
    header('Location: index.php');
    exit();
}

$settings = new Settings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = sanitize_input($_POST['company_name']);
    $settings->update_setting('company_name', $company_name);
    
    // Handle file upload for company logo
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] == 0) {
        $target_dir = __DIR__ . "/../assets/img/";
        $target_file = $target_dir . basename($_FILES["company_logo"]["name"]);
        move_uploaded_file($_FILES["company_logo"]["tmp_name"], $target_file);
        $settings->update_setting('company_logo', basename($_FILES["company_logo"]["name"]));
    }

    header('Location: settings.php?success=1');
    exit();
}

$all_settings = $settings->get_all_settings();
$page_title = 'Settings';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">System Settings</h1>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Settings updated successfully!</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form action="settings.php" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="company_name" class="form-label">Company Name</label>
                <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo htmlspecialchars($all_settings['company_name'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label for="company_logo" class="form-label">Company Logo</label>
                <input type="file" class="form-control" id="company_logo" name="company_logo">
                <?php if (!empty($all_settings['company_logo'])): ?>
                    <img src="../assets/img/<?php echo htmlspecialchars($all_settings['company_logo']); ?>" alt="Company Logo" class="img-thumbnail mt-2" style="max-height: 100px;">
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
</div>

<?php
include __DIR__ . '/../templates/footer.php';
?>
