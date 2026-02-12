<?php
session_start();
require "../../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}

// fetch categories
$stmt = $pdo->query("SELECT * FROM doctor_categories ORDER BY name ASC");
$categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Doctor Categories</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 p-8">

<h1 class="text-2xl font-bold mb-6">Doctor Categories</h1>

<div class="bg-white rounded-lg shadow p-6 mb-6">
  <form method="POST" action="save_category.php" class="flex gap-4">
    <input
      type="text"
      name="name"
      placeholder="Category name"
      required
      class="flex-1 border rounded px-4 py-2"
    >
    <button class="bg-blue-600 text-white px-6 py-2 rounded">
      Add
    </button>
  </form>
</div>

<div class="bg-white rounded-lg shadow">
  <table class="w-full text-left">
    <thead class="bg-gray-100">
      <tr>
        <th class="p-3">#</th>
        <th class="p-3">Category</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($categories as $i => $cat): ?>
      <tr class="border-t">
        <td class="p-3"><?= $i + 1 ?></td>
        <td class="p-3"><?= htmlspecialchars($cat['name']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

</body>
</html>
