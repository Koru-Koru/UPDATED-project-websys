
<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$categories = ['Hot', 'Iced', 'Snacks and Bites'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['product_id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        try {
            $stmt = $conn->prepare('DELETE FROM products WHERE product_id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();

            set_flash('Product deleted.');
        } catch (mysqli_sql_exception $e) {
            set_flash('Unable to delete this product.', 'danger');
        }

        header('Location: products.php');
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $category = $_POST['category'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
    $image = trim($_POST['image_url'] ?? '');
    $available = isset($_POST['is_available']) ? 1 : 0;

    if (
        $name === '' ||
        $description === '' ||
        !in_array($category, $categories, true) ||
        $price === false ||
        $price === null ||
        $price < 0 ||
        ($image !== '' && !filter_var($image, FILTER_VALIDATE_URL))
    ) {
        set_flash('Please enter valid product details.', 'danger');
        header('Location: products.php');
        exit;
    }

    try {
        if ($action === 'edit' && $id > 0) {
            $stmt = $conn->prepare(
                'UPDATE products
                 SET name = ?, category = ?, description = ?, price = ?,
                     image_url = ?, is_available = ?
                 WHERE product_id = ?'
            );

            $stmt->bind_param(
                'sssdsii',
                $name,
                $category,
                $description,
                $price,
                $image,
                $available,
                $id
            );
        } elseif ($action === 'add') {
            $stmt = $conn->prepare(
                'INSERT INTO products
                 (name, category, description, price, image_url, is_available)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            $stmt->bind_param(
                'sssdsi',
                $name,
                $category,
                $description,
                $price,
                $image,
                $available
            );
        } else {
            set_flash('Invalid product action.', 'danger');
            header('Location: products.php');
            exit;
        }

        $stmt->execute();
        set_flash($action === 'edit' ? 'Product updated.' : 'Product added.');
    } catch (mysqli_sql_exception $e) {
        set_flash('Unable to save the product. Please check the details.', 'danger');
    }

    header('Location: products.php');
    exit;
}

$products = $conn->query(
    'SELECT * FROM products ORDER BY category, name'
)->fetch_all(MYSQLI_ASSOC);

$editProduct = null;

if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];

    if ($id > 0) {
        $stmt = $conn->prepare(
            'SELECT * FROM products WHERE product_id = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $editProduct = $stmt->get_result()->fetch_assoc();
    }
}

$formAction = $editProduct ? 'edit' : 'add';
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Product Maintenance | Philippines' Best Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/phbestcoffee.css">
</head>

<body class="admin-page">
    <nav class="navbar navbar-dark admin-nav">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
                <img src="../Bestcoffee.jpg"
                     alt="Philippines' Best Coffee logo"
                     style="width:38px;height:38px;object-fit:contain;border-radius:50%;background:#fff">
                <span>Philippines' Best Coffee Admin</span>
            </a>
            <a href="logout.php" class="btn btn-sm btn-outline-light">Log out</a>
        </div>
    </nav>

    <main class="container py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h1 class="h2 mb-1">Product Maintenance</h1>
                <p class="text-secondary mb-0">Add, update, and manage the coffee menu here pogi si sir.</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-coffee">Back to Dashboard</a>
        </div>

        <?php show_flash(); ?>

        <div class="card admin-card shadow-sm mb-4">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">
                    <?= $editProduct ? 'Edit Product' : 'Add Product' ?>
                </h2>

                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="<?= h($formAction) ?>">
                    <input type="hidden" name="product_id" value="<?= (int) ($editProduct['product_id'] ?? 0) ?>">

                    <div class="col-md-6">
                        <label for="name" class="form-label">Product Name</label>
                        <input id="name" class="form-control" name="name" maxlength="120" required
                               value="<?= h($editProduct['name'] ?? '') ?>">
                    </div>

                    <div class="col-md-3">
                        <label for="category" class="form-label">Category</label>
                        <select id="category" class="form-select" name="category" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= h($category) ?>"
                                    <?= ($editProduct['category'] ?? 'Hot') === $category ? 'selected' : '' ?>>
                                    <?= h($category) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="price" class="form-label">Price (₱)</label>
                        <input id="price" class="form-control" type="number" name="price"
                               min="0" step="0.01" required
                               value="<?= h($editProduct['price'] ?? '') ?>">
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" class="form-control" name="description"
                                  rows="3" required><?= h($editProduct['description'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="image_url" class="form-label">Image URL</label>
                        <input id="image_url" class="form-control" type="url" name="image_url"
                               placeholder="https://example.com/image.jpg"
                               value="<?= h($editProduct['image_url'] ?? '') ?>">
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_available"
                                   id="available"
                                   <?= !$editProduct || (int) $editProduct['is_available'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="available">
                                Available for sale
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-coffee">
                            <?= $editProduct ? 'Save Changes' : 'Add Product' ?>
                        </button>

                        <?php if ($editProduct): ?>
                            <a href="products.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card admin-card shadow-sm">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Product Records</h2>

                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $index => $product): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= h($product['name']) ?></td>
                                    <td><?= h($product['category']) ?></td>
                                    <td>₱<?= number_format((float) $product['price'], 2) ?></td>
                                    <td>
                                        <span class="badge text-bg-<?= $product['is_available'] ? 'success' : 'secondary' ?>">
                                            <?= $product['is_available'] ? 'Available' : 'Unavailable' ?>
                                        </span>
                                    </td>
                                    <td class="text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="products.php?edit=<?= (int) $product['product_id'] ?>">
                                            Edit
                                        </a>

                                        <form method="post" class="d-inline"
                                              onsubmit="return confirm('Delete this product?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="product_id"
                                                   value="<?= (int) $product['product_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$products): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-secondary">
                                        No products found. Add a product using the form above.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
