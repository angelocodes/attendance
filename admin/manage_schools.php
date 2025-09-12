<?php
include '../db.php'; // your DB connection
include 'admin_navbar.php'; 
// Add School
if (isset($_POST['add'])) {
    $school_name = trim($_POST['school_name']);
    $school_code = trim($_POST['school_code']);
    $description = trim($_POST['description']);

    if ($school_name && $school_code) {
        // Check for unique school_code
        $stmt_check = $conn->prepare("SELECT school_id FROM schools WHERE school_code = ?");
        $stmt_check->bind_param("s", $school_code);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO schools (school_name, school_code, description) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $school_name, $school_code, $description);
            $stmt->execute();
            header("Location: manage_schools.php");
            exit();
        } else {
            $error = "School code already exists.";
        }
    } else {
        $error = "School name and code are required.";
    }
}

// Delete School
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM schools WHERE school_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: manage_schools.php");
    exit();
}

// Update School
if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $school_name = trim($_POST['school_name']);
    $school_code = trim($_POST['school_code']);
    $description = trim($_POST['description']);

    if ($school_name && $school_code) {
        // Check if school_code is unique except for this id
        $stmt_check = $conn->prepare("SELECT school_id FROM schools WHERE school_code = ? AND school_id != ?");
        $stmt_check->bind_param("si", $school_code, $id);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows === 0) {
            $stmt = $conn->prepare("UPDATE schools SET school_name = ?, school_code = ?, description = ? WHERE school_id = ?");
            $stmt->bind_param("sssi", $school_name, $school_code, $description, $id);
            $stmt->execute();
            header("Location: manage_schools.php");
            exit();
        } else {
            $error = "School code already exists for another school.";
        }
    } else {
        $error = "School name and code are required.";
    }
}

// Fetch all schools
$result = $conn->query("SELECT * FROM schools ORDER BY school_id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Manage Schools</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white p-8 min-h-screen">

<h1 class="text-3xl font-bold mb-6 text-yellow-400">Manage Schools</h1>

<?php if (!empty($error)): ?>
    <div class="bg-red-600 p-3 rounded mb-6 max-w-xl"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Add School Form -->
<form method="POST" class="mb-8 max-w-xl space-y-4 bg-gray-800 p-6 rounded-lg">
    <h2 class="text-xl font-semibold mb-4">Add New School</h2>
    <input type="text" name="school_name" placeholder="School Name" required
        class="w-full p-2 rounded bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400" />
    <input type="text" name="school_code" placeholder="School Code" required
        class="w-full p-2 rounded bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400" />
    <textarea name="description" placeholder="Description (optional)"
        class="w-full p-2 rounded bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400" rows="3"></textarea>
    <button name="add" class="bg-yellow-500 hover:bg-yellow-600 text-black font-semibold px-4 py-2 rounded">Add School</button>
</form>

<!-- Schools Table -->
<div class="overflow-x-auto max-w-full">
    <table class="min-w-full bg-gray-800 rounded-lg">
        <thead class="bg-yellow-500 text-black">
            <tr>
                <th class="px-4 py-2">ID</th>
                <th class="px-4 py-2">School Name</th>
                <th class="px-4 py-2">School Code</th>
                <th class="px-4 py-2">Description</th>
                <th class="px-4 py-2">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($school = $result->fetch_assoc()): ?>
            <tr class="border-b border-gray-700">
            <?php if (isset($_GET['edit']) && $_GET['edit'] == $school['school_id']): ?>
                <form method="POST" class="grid grid-cols-5 gap-2 items-center bg-gray-700 p-2 rounded">
                    <input type="hidden" name="id" value="<?= $school['school_id'] ?>" />
                    <td><?= $school['school_id'] ?></td>
                    <td><input type="text" name="school_name" value="<?= htmlspecialchars($school['school_name']) ?>" required
                        class="p-1 rounded bg-gray-800 text-white w-full" /></td>
                    <td><input type="text" name="school_code" value="<?= htmlspecialchars($school['school_code']) ?>" required
                        class="p-1 rounded bg-gray-800 text-white w-full" /></td>
                    <td><textarea name="description" class="p-1 rounded bg-gray-800 text-white w-full" rows="2"><?= htmlspecialchars($school['description']) ?></textarea></td>
                    <td class="space-x-2">
                        <button name="update" class="bg-yellow-500 hover:bg-yellow-600 text-black px-3 py-1 rounded mr-1">Save</button>
                        <a href="manage_schools.php" class="text-yellow-400 hover:underline">Cancel</a>
                    </td>
                </form>
            <?php else: ?>
                <td><?= $school['school_id'] ?></td>
                <td><?= htmlspecialchars($school['school_name']) ?></td>
                <td><?= htmlspecialchars($school['school_code']) ?></td>
                <td><?= htmlspecialchars($school['description']) ?></td>
                <td class="space-x-2">
                    <a href="?edit=<?= $school['school_id'] ?>" class="text-yellow-400 hover:underline">Edit</a>
                    <a href="?delete=<?= $school['school_id'] ?>" onclick="return confirm('Delete this school?')" class="text-red-500 hover:underline">Delete</a>
                </td>
            <?php endif; ?>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

</body>
</html>
