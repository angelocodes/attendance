<?php
include '../db.php';
include 'admin_navbar.php';

// Admin ID (replace with session)
$admin_id = 1;

// Settings keys
$setting_keys = ['site_name', 'contact_email', 'contact_phone', 'timezone', 'theme_color'];

// Default values
$defaults = [
    'site_name' => 'My University Attendance System',
    'contact_email' => 'admin@university.edu',
    'contact_phone' => '+256700000000',
    'timezone' => 'Africa/Kampala',
    'theme_color' => '#16a34a',
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($setting_keys as $key) {
        $value = $_POST[$key] ?? '';
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
    }

    // Widgets
    $widgets = ['system_notifications', 'student_overview', 'academic_calendar'];
    foreach ($widgets as $widget) {
        $value = isset($_POST['widget_' . $widget]) ? 'hidden' : 'visible';
        $stmt = $conn->prepare("INSERT INTO user_preferences (user_id, widget, setting_value) VALUES (?, ?, ?) 
                                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->bind_param('iss', $admin_id, $widget, $value);
        $stmt->execute();
    }

    $success_msg = "Settings saved successfully.";
}

// Fetch current settings
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
foreach ($setting_keys as $key) {
    if (!isset($settings[$key])) $settings[$key] = $defaults[$key];
}

// Fetch widgets
$notification_preferences = [];
$result = $conn->query("SELECT widget, setting_value FROM user_preferences WHERE user_id = $admin_id");
while ($row = $result->fetch_assoc()) {
    $notification_preferences[$row['widget']] = $row['setting_value'];
}

// Timezones
$timezones = DateTimeZone::listIdentifiers();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen p-6">
<div class="max-w-4xl mx-auto">
    <h1 class="text-4xl font-bold text-yellow-400 mb-8 text-center">Admin Settings</h1>

    <?php if (!empty($success_msg)): ?>
        <div class="bg-green-600 text-white p-3 rounded mb-6 text-center"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="flex border-b border-gray-700 mb-6">
        <button class="tab-button px-6 py-2 font-semibold text-yellow-400 border-b-2 border-yellow-400 focus:outline-none" data-tab="general">General</button>
        <button class="tab-button px-6 py-2 font-semibold text-gray-400 hover:text-yellow-400 focus:outline-none" data-tab="widgets">Widgets</button>
    </div>

    <form method="POST" class="space-y-6">
        <!-- General Tab -->
        <div id="general" class="tab-content space-y-6">
            <div class="bg-gray-800 p-6 rounded shadow-md space-y-4">
                <div>
                    <label class="block mb-1 font-semibold">Site Name</label>
                    <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name']) ?>" required
                           class="w-full p-3 rounded text-black focus:ring-2 focus:ring-yellow-400">
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Contact Email</label>
                    <input type="email" name="contact_email" value="<?= htmlspecialchars($settings['contact_email']) ?>" required
                           class="w-full p-3 rounded text-black focus:ring-2 focus:ring-yellow-400">
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Contact Phone</label>
                    <input type="text" name="contact_phone" value="<?= htmlspecialchars($settings['contact_phone']) ?>" required
                           class="w-full p-3 rounded text-black focus:ring-2 focus:ring-yellow-400">
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Timezone</label>
                    <select name="timezone" required class="w-full p-3 rounded text-black focus:ring-2 focus:ring-yellow-400">
                        <?php foreach ($timezones as $tz): ?>
                            <option value="<?= htmlspecialchars($tz) ?>" <?= ($settings['timezone'] === $tz) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tz) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Theme Color</label>
                    <input type="color" name="theme_color" value="<?= htmlspecialchars($settings['theme_color']) ?>"
                           class="w-20 h-12 p-0 border-0 rounded cursor-pointer">
                </div>
            </div>
        </div>

        <!-- Widgets Tab -->
        <div id="widgets" class="tab-content hidden space-y-4">
            <div class="bg-gray-800 p-6 rounded shadow-md space-y-4">
                <h2 class="text-xl font-semibold text-yellow-400 mb-2">Widget Preferences</h2>
                <?php
                $widgets = [
                    'system_notifications' => 'System Notifications',
                    'student_overview' => 'Student Overview',
                    'academic_calendar' => 'Academic Calendar'
                ];
                foreach ($widgets as $key => $label): ?>
                    <div class="flex items-center space-x-2">
                        <input type="checkbox" id="widget_<?= $key ?>" name="widget_<?= $key ?>" value="hidden"
                               class="w-5 h-5 accent-yellow-400"
                            <?= isset($notification_preferences[$key]) && $notification_preferences[$key] === 'hidden' ? 'checked' : '' ?>>
                        <label for="widget_<?= $key ?>" class="font-medium"><?= $label ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="text-center">
            <button type="submit" class="bg-yellow-400 text-black px-6 py-2 rounded font-semibold hover:bg-yellow-500">Save Settings</button>
        </div>
    </form>
</div>

<script>
    // Tab switching
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            tabButtons.forEach(b => b.classList.replace('text-yellow-400','text-gray-400'));
            btn.classList.replace('text-gray-400','text-yellow-400');

            tabContents.forEach(tc => tc.classList.add('hidden'));
            document.getElementById(btn.dataset.tab).classList.remove('hidden');
        });
    });
</script>
</body>
</html>
