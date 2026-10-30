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
        if ($action === 'create') {
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

        } elseif ($action === 'update') {
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

        } elseif ($action === 'delete') {
            $st = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
            $st->execute([clean($_POST['product_id'] ?? '')]);
            flash($st->rowCount() ? 'success' : 'error',
                  $st->rowCount() ? 'Product deleted successfully.' : 'Product not found.');
            redirect();
        }
    } catch (PDOException $ex) {
        $errors[] = "A database error occurred. Please try again.";
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
$totalProducts = count($products);
$totalQty = array_sum(array_column($products, 'quantity'));
$totalValue = 0;
foreach ($products as $p) { $totalValue += $p['price'] * $p['quantity']; }
$f = $isEdit ? $editing : $old;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TechMart Product Management</title>
<style>
  :root{--primary:#2563eb;--primary-d:#1d4ed8;--bg:#f1f5f9;--card:#fff;--text:#1e293b;--muted:#64748b;--border:#e2e8f0}
  *{box-sizing:border-box}
  body{margin:0;font-family:'Segoe UI',system-ui,Arial,sans-serif;background:var(--bg);color:var(--text)}
  header{background:linear-gradient(135deg,#1e3a8a,#2563eb);color:#fff;padding:22px 0;box-shadow:0 2px 8px rgba(0,0,0,.15)}
  header .wrap{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px}
  header h1{margin:0;font-size:24px} header p{margin:0;opacity:.85;font-size:14px}
  .wrap{max-width:1100px;margin:0 auto;padding:0 16px}
  main{padding:24px 0 40px}
  .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:22px}
  .stat{background:var(--card);border-radius:12px;padding:16px 18px;box-shadow:0 1px 4px rgba(0,0,0,.08);border-left:5px solid var(--primary)}
  .stat span{display:block;color:var(--muted);font-size:13px} .stat strong{font-size:24px}
  .card{background:var(--card);border-radius:12px;padding:22px;margin-bottom:24px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
  .card h2{margin:0 0 16px;font-size:19px}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px 18px}
  .full{grid-column:1/-1}
  label{display:block;margin-bottom:5px;font-weight:600;font-size:14px}
  input,textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font:inherit;background:#f8fafc;transition:.15s}
  input:focus,textarea:focus{outline:none;border-color:var(--primary);background:#fff;box-shadow:0 0 0 3px rgba(37,99,235,.15)}
  input[readonly]{background:#e2e8f0;cursor:not-allowed}
  .btn,button{display:inline-block;padding:9px 16px;border:0;border-radius:8px;background:var(--primary);color:#fff;font:inherit;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;transition:.15s}
  .btn:hover,button:hover{background:var(--primary-d)}
  .del{background:#dc2626} .del:hover{background:#b91c1c}
  .gray{background:#64748b} .gray:hover{background:#475569}
  .sm{padding:6px 12px;font-size:13px}
  .actions{margin-top:18px}
  .tbl-wrap{overflow-x:auto}
  table{width:100%;border-collapse:collapse;font-size:14px}
  th{background:#f1f5f9;text-align:left;padding:12px;font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted)}
  td{padding:12px;border-top:1px solid var(--border);vertical-align:middle}
  tbody tr:hover{background:#f8fafc}
  .tag{background:#dbeafe;color:#1e40af;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600}
  .badge{padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700}
  .ok{background:#dcfce7;color:#166534} .low{background:#fef9c3;color:#854d0e} .out{background:#fee2e2;color:#991b1b}
  .msg{padding:12px 16px;border-radius:8px;margin-bottom:18px;font-weight:500}
  .success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
  .error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
  .msg ul{margin:0;padding-left:18px}
  form.inline{display:inline}
  .empty{text-align:center;color:var(--muted);padding:30px}
  @media(max-width:640px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<header><div class="wrap">
  <h1>&#128230; TechMart Electronics</h1>
  <p>Product Management System</p>
</div></header>
<main class="wrap">

<div class="stats">
  <div class="stat"><span>Total Products</span><strong><?= $totalProducts ?></strong></div>
  <div class="stat"><span>Total Stock (units)</span><strong><?= (int)$totalQty ?></strong></div>
  <div class="stat"><span>Inventory Value</span><strong>&#8377;<?= number_format($totalValue, 2) ?></strong></div>
</div>

<?php if ($flash): ?><div class="msg <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
<?php if ($errors): ?>
  <div class="msg error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card">
  <h2><?= $isEdit ? 'Update Product' : 'Add New Product' ?></h2>
  <form method="post" action="index.php">
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">

    <div class="grid">
      <div><label>Product ID</label>
      <input type="text" name="product_id" maxlength="20" required placeholder="e.g. TM-101" value="<?= e($f['product_id'] ?? '') ?>" <?= $isEdit ? 'readonly' : '' ?>></div>
      <div><label>Product Name</label>
      <input type="text" name="product_name" maxlength="100" required placeholder="e.g. Wireless Mouse" value="<?= e($f['product_name'] ?? '') ?>"></div>
      <div><label>Category</label>
      <input type="text" name="category" maxlength="50" required placeholder="e.g. Accessories" value="<?= e($f['category'] ?? '') ?>"></div>
      <div><label>Price (&#8377;)</label>
      <input type="number" name="price" step="0.01" min="0" required placeholder="0.00" value="<?= e($f['price'] ?? '') ?>"></div>
      <div><label>Quantity</label>
      <input type="number" name="quantity" min="0" step="1" required placeholder="0" value="<?= e($f['quantity'] ?? '') ?>"></div>
      <div class="full"><label>Description</label>
      <textarea name="description" rows="3" maxlength="500" placeholder="Short product description"><?= e($f['description'] ?? '') ?></textarea></div>
    </div>

    <p class="actions"><button type="submit"><?= $isEdit ? 'Update' : 'Add Product' ?></button>
       <?php if ($isEdit): ?><a class="btn gray" href="index.php">Cancel</a><?php endif; ?></p>
  </form>
</div>

<div class="card">
<h2>All Products</h2>
<div class="tbl-wrap"><table>
  <tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Qty</th><th>Description</th><th>Actions</th></tr>
  <?php if (!$products): ?>
    <tr><td colspan="7" class="empty">No products found. Add your first product above.</td></tr>
  <?php endif; ?>
  <?php foreach ($products as $p): ?>
  <tr>
    <td><?= e($p['product_id']) ?></td>
    <td><?= e($p['product_name']) ?></td>
    <td><span class="tag"><?= e($p['category']) ?></span></td>
    <td>&#8377;<?= number_format((float)$p['price'], 2) ?></td>
    <td><?php $q=(int)$p['quantity']; ?><span class="badge <?= $q==0 ? 'out' : ($q<10 ? 'low' : 'ok') ?>"><?= $q ?></span></td>
    <td><?= e($p['description']) ?></td>
    <td>
      <a class="btn sm" href="index.php?edit=<?= urlencode($p['product_id']) ?>">Edit</a>
            <form class="inline" method="post" action="index.php"
            onsubmit="return confirm('Delete this product permanently?');">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="product_id" value="<?= e($p['product_id']) ?>">
        <button type="submit" class="del sm">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table></div></div>
</main>
</body>
</html>