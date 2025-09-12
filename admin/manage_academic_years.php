<?php
include '../db.php';
include 'admin_navbar.php';

// Handle Add
if (isset($_POST['add'])) {
    $start_year = (int)$_POST['start_year'];
    $end_year = (int)$_POST['end_year'];
    $description = trim($_POST['description']);

    if ($start_year && $end_year) {
        $stmt = $conn->prepare("INSERT INTO academic_years (start_year, end_year, description) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $start_year, $end_year, $description);
        $stmt->execute();
        header("Location: manage_academic_years.php");
        exit();
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM academic_years WHERE academic_year_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: manage_academic_years.php");
    exit();
}

// Handle Update
if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $start_year = (int)$_POST['start_year'];
    $end_year = (int)$_POST['end_year'];
    $description = trim($_POST['description']);

    $stmt = $conn->prepare("UPDATE academic_years SET start_year = ?, end_year = ?, description = ? WHERE academic_year_id = ?");
    $stmt->bind_param("iisi", $start_year, $end_year, $description, $id);
    $stmt->execute();
    header("Location: manage_academic_years.php");
    exit();
}

// Fetch all academic years
$results = $conn->query("SELECT * FROM academic_years ORDER BY academic_year_id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Manage Academic Years</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen p-8">

<h1 class="text-3xl font-bold text-yellow-400 mb-6">Manage Academic Years</h1>

<!-- Add Button -->
<button id="openAddModal" class="bg-yellow-500 hover:bg-yellow-600 px-4 py-2 rounded text-black font-semibold mb-6">Add New Academic Year</button>

<!-- Academic Years Table -->
<div class="overflow-x-auto">
    <table class="min-w-full bg-gray-800 rounded-lg">
        <thead class="bg-yellow-500 text-black">
            <tr>
                <th class="px-6 py-3 text-left">ID</th>
                <th class="px-6 py-3 text-left">Start Year</th>
                <th class="px-6 py-3 text-left">End Year</th>
                <th class="px-6 py-3 text-left">Description</th>
                <th class="px-6 py-3 text-left">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $results->fetch_assoc()): ?>
            <tr class="border-b border-gray-700">
                <td class="px-6 py-4"><?= $row['academic_year_id'] ?></td>
                <td class="px-6 py-4"><?= htmlspecialchars($row['start_year']) ?></td>
                <td class="px-6 py-4"><?= htmlspecialchars($row['end_year']) ?></td>
                <td class="px-6 py-4"><?= htmlspecialchars($row['description']) ?></td>
                <td class="px-6 py-4 space-x-2">
                    <button class="editBtn text-blue-400 hover:underline" data-id="<?= $row['academic_year_id'] ?>" data-start="<?= $row['start_year'] ?>" data-end="<?= $row['end_year'] ?>" data-desc="<?= htmlspecialchars($row['description']) ?>">Edit</button>
                    <a href="?delete=<?= $row['academic_year_id'] ?>" onclick="return confirm('Delete this academic year?')" class="text-red-400 hover:underline">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Add/Edit Modal -->
<div id="academicYearModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-gray-800 p-6 rounded-lg w-full max-w-md">
        <h2 id="modalTitle" class="text-2xl font-bold text-yellow-400 mb-4">Add Academic Year</h2>
        <form id="modalForm" method="POST" class="space-y-4">
            <input type="hidden" name="id" id="modalId">
            <div class="flex gap-2">
                <input type="number" name="start_year" id="modalStart" placeholder="Start Year" min="1900" max="2100" required
                       class="w-1/2 px-4 py-2 rounded bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400" />
                <input type="number" name="end_year" id="modalEnd" placeholder="End Year" min="1900" max="2100" required
                       class="w-1/2 px-4 py-2 rounded bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400" />
            </div>
            <input type="text" name="description" id="modalDesc" placeholder="Description (optional)"
                   class="w-full px-4 py-2 rounded bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400" />
            <div class="flex justify-end gap-2">
                <button type="button" id="closeModal" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded">Cancel</button>
                <button type="submit" name="add" id="modalAddBtn" class="bg-yellow-500 hover:bg-yellow-600 px-4 py-2 rounded text-black font-semibold">Add</button>
                <button type="submit" name="update" id="modalUpdateBtn" class="bg-blue-500 hover:bg-blue-600 px-4 py-2 rounded text-white font-semibold hidden">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    const modal = $('#academicYearModal');

    // Open Add Modal
    $('#openAddModal').click(function() {
        $('#modalTitle').text('Add Academic Year');
        $('#modalForm')[0].reset();
        $('#modalId').val('');
        $('#modalAddBtn').show();
        $('#modalUpdateBtn').hide();
        modal.fadeIn();
    });

    // Open Edit Modal
    $('.editBtn').click(function() {
        const id = $(this).data('id');
        $('#modalId').val(id);
        $('#modalStart').val($(this).data('start'));
        $('#modalEnd').val($(this).data('end'));
        $('#modalDesc').val($(this).data('desc'));
        $('#modalTitle').text('Edit Academic Year');
        $('#modalAddBtn').hide();
        $('#modalUpdateBtn').show();
        modal.fadeIn();
    });

    // Close modal
    $('#closeModal').click(function() {
        modal.fadeOut();
    });
});
</script>

<!-- Back Button -->
<div class="mt-6">
    <a href="dashboard.php" class="text-yellow-400 hover:text-white">&larr; Back to Dashboard</a>
</div>

</body>
</html>
