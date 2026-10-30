<?php
session_start();

$host = 'localhost'; $db = 'techmart_db'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Database connection failed. Please check configuration.");
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function clean($s) { return trim(strip_tags((string)$s)); }
function flash($type, $msg) { $_SESSION['flash'] = [$type, $msg]; }
function redirect() { header("Location: index.php"); exit; }

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

function validate(array $in, bool $isNew): array {
    $d = [
        'product_id'   => clean($in['product_id'] ?? ''),
        'product_name' => clean($in['product_name'] ?? ''),
        'category'     => clean($in['category'] ?? ''),
        'price'        => clean($in['price'] ?? ''),
        'quantity'     => clean($in['quantity'] ?? ''),
        'description'  => clean($in['description'] ?? ''),
    ];
    $err = [];
    if ($isNew && !preg_match('/^[A-Za-z0-9\-]{1,20}$/', $d['product_id']))
        $err[] = "Product ID must be 1-20 letters, digits or hyphens.";
    if ($d['product_name'] === '' || mb_strlen($d['product_name']) > 100)
        $err[] = "Product name is required (max 100 characters).";
    if ($d['category'] === '' || mb_strlen($d['category']) > 50)
        $err[] = "Category is required (max 50 characters).";
    if (!is_numeric($d['price']) || $d['price'] < 0 || $d['price'] > 99999999.99)
        $err[] = "Price must be a non-negative number.";
    if (filter_var($d['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false)
        $err[] = "Quantity must be a whole number (0 or more).";
    if (mb_strlen($d['description']) > 500)
        $err[] = "Description must be under 500 characters.";
    return [$d, $err];
}

$errors = []; $old = []; $editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        flash('error', 'Invalid request token.'); redirect();
    }
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {                       // Task 4
            [$d, $errors] = validate($_POST, true);
            if (!$errors) {
                $chk = $pdo->prepare("SELECT 1 FROM products WHERE product_id = ?");
                $chk->execute([$d['product_id']]);
                if ($chk->fetch()) $errors[] = "Product ID already exists.";
            }
            if (!$errors) {
                $pdo->prepare("INSERT INTO products (product_id, product_name, category, price, quantity, description)
                               VALUES (?,?,?,?,?,?)")
                    ->execute([$d['product_id'], $d['product_name'], $d['category'],
                               $d['price'], (int)$d['quantity'], $d['description']]);
                flash('success', 'Product added successfully.'); redirect();
            }
            $old = $d;

        } elseif ($action === 'update') {                 // Task 6
            [$d, $errors] = validate($_POST, false);
            $d['product_id'] = clean($_POST['product_id'] ?? '');
            if (!$errors) {
                $st = $pdo->prepare("UPDATE products SET product_name=?, category=?, price=?, quantity=?, description=?
                                     WHERE product_id=?");
                $st->execute([$d['product_name'], $d['category'], $d['price'],
                              (int)$d['quantity'], $d['description'], $d['product_id']]);
                flash('success', 'Product updated successfully.'); redirect();
            }
            $editing = $d;

        } elseif ($action === 'delete') {                 // Task 7
            $st = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
            $st->execute([clean($_POST['product_id'] ?? '')]);
            flash($st->rowCount() ? 'success' : 'error',
                  $st->rowCount() ? 'Product deleted successfully.' : 'Product not found.');
            redirect();
        }
    } catch (PDOException $ex) {
        $errors[] = "A database error occurred. Please try again.";   // no raw error leaked
    }
}

if (isset($_GET['edit']) && !$editing) {
    $st = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
    $st->execute([clean($_GET['edit'])]);
    $editing = $st->fetch() ?: null;
    if (!$editing) flash('error', 'Product not found.');
}

$products = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll();

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$isEdit = $editing !== null;
$f = $isEdit ? $editing : $old;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TechMart Product Management</title>
<style>
  body{font-family:Arial,sans-serif;max-width:960px;margin:20px auto;padding:0 12px;color:#222}
  h1{color:#1a4d8f} .box{border:1px solid #ccc;border-radius:6px;padding:16px;margin-bottom:24px}
  label{display:block;margin-top:10px;font-weight:bold}
  input,textarea{width:100%;padding:7px;box-sizing:border-box}
  button,.btn{padding:7px 14px;border:0;border-radius:4px;background:#1a4d8f;color:#fff;cursor:pointer;text-decoration:none;font-size:14px}
  .del{background:#c0392b} .gray{background:#777}
  table{width:100%;border-collapse:collapse} th,td{border:1px solid #ccc;padding:8px;text-align:left}
  th{background:#eef2f7} .msg{padding:10px;border-radius:4px;margin-bottom:14px}
  .success{background:#d4edda;color:#155724} .error{background:#f8d7da;color:#721c24}
  form.inline{display:inline}
</style>
</head>
<body>
<h1>TechMart Electronics – Product Management</h1>

<?php if ($flash): ?><div class="msg <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
<?php if ($errors): ?>
  <div class="msg error"><ul style="margin:0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="box">
  <h2><?= $isEdit ? 'Update Product' : 'Add New Product' ?></h2>
  <form method="post" action="index.php">
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">

    <label>Product ID</label>
    <input type="text" name="product_id" maxlength="20" required value="<?= e($f['product_id'] ?? '') ?>" <?= $isEdit ? 'readonly' : '' ?>>
    <label>Product Name</label>
    <input type="text" name="product_name" maxlength="100" required value="<?= e($f['product_name'] ?? '') ?>">
    <label>Category</label>
    <input type="text" name="category" maxlength="50" required value="<?= e($f['category'] ?? '') ?>">
    <label>Price</label>
    <input type="number" name="price" step="0.01" min="0" required value="<?= e($f['price'] ?? '') ?>">
    <label>Quantity</label>
    <input type="number" name="quantity" min="0" step="1" required value="<?= e($f['quantity'] ?? '') ?>">
    <label>Description</label>
    <textarea name="description" rows="3" maxlength="500"><?= e($f['description'] ?? '') ?></textarea>

    <p><button type="submit"><?= $isEdit ? 'Update' : 'Add Product' ?></button>
       <?php if ($isEdit): ?><a class="btn gray" href="index.php">Cancel</a><?php endif; ?></p>
  </form>
</div>

<h2>All Products</h2>
<table>
  <tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Qty</th><th>Description</th><th>Actions</th></tr>
  <?php if (!$products): ?>
    <tr><td colspan="7">No products found.</td></tr>
  <?php endif; ?>
  <?php foreach ($products as $p): ?>
  <tr>
    <td><?= e($p['product_id']) ?></td>
    <td><?= e($p['product_name']) ?></td>
    <td><?= e($p['category']) ?></td>
    <td><?= number_format((float)$p['price'], 2) ?></td>
    <td><?= (int)$p['quantity'] ?></td>
    <td><?= e($p['description']) ?></td>
    <td>
      <a class="btn" href="index.php?edit=<?= urlencode($p['product_id']) ?>">Edit</a>
      <form class="inline" method="post" action="index.php"
            onsubmit="return confirm('Delete this product permanently?');">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="product_id" value="<?= e($p['product_id']) ?>">
        <button type="submit" class="del">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
</body>
</html>