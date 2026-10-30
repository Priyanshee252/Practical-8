const express = require("express");
const mysql = require("mysql2/promise");
const path = require("path");

const app = express();
app.use(express.json({ limit: "10kb" }));
app.use(express.static(path.join(__dirname, "public")));

const pool = mysql.createPool({
  host: "localhost",
  user: "root",
  password: "",
  database: "techmart_db",
  connectionLimit: 10
});

function clean(value) {
  return String(value ?? "")
    .replace(/<[^>]*>/g, "")
    .replace(/[\u0000-\u001F\u007F]/g, " ")
    .trim();
}

function checkProduct(body, requireId) {
  const errors = [];
  const p = {
    product_id: clean(body.product_id),
    product_name: clean(body.product_name),
    category: clean(body.category),
    price: Number(body.price),
    quantity: Number(body.quantity),
    description: clean(body.description)
  };

  if (requireId && !/^[A-Za-z0-9-]{1,20}$/.test(p.product_id))
    errors.push("Product ID must be 1-20 letters, numbers or hyphens.");
  if (p.product_name.length < 2 || p.product_name.length > 100)
    errors.push("Product name must be 2-100 characters.");
  if (p.category.length < 2 || p.category.length > 50)
    errors.push("Category must be 2-50 characters.");
  if (!Number.isFinite(p.price) || p.price < 0 || p.price > 99999999.99)
    errors.push("Price must be a number of 0 or more.");
  if (!Number.isInteger(p.quantity) || p.quantity < 0 || p.quantity > 1000000)
    errors.push("Quantity must be a whole number of 0 or more.");
  if (p.description.length > 500)
    errors.push("Description must be under 500 characters.");

  return { errors, p };
}

function validId(value) {
  const id = Number(value);
  return Number.isInteger(id) && id > 0 ? id : null;
}

app.get("/api/products", async (req, res) => {
  try {
    const [rows] = await pool.execute("SELECT * FROM products ORDER BY id DESC");
    res.json(rows);
  } catch (err) {
    res.status(500).json({ message: "Could not load products." });
  }
});

app.post("/api/products", async (req, res) => {
  const { errors, p } = checkProduct(req.body, true);
  if (errors.length) return res.status(400).json({ message: errors.join(" ") });
  try {
    await pool.execute(
      "INSERT INTO products (product_id, product_name, category, price, quantity, description) VALUES (?, ?, ?, ?, ?, ?)",
      [p.product_id, p.product_name, p.category, p.price, p.quantity, p.description]
    );
    res.status(201).json({ message: "Product added successfully." });
  } catch (err) {
    if (err.code === "ER_DUP_ENTRY")
      return res.status(409).json({ message: "This Product ID already exists." });
    res.status(500).json({ message: "Could not add the product." });
  }
});

app.put("/api/products/:id", async (req, res) => {
  const id = validId(req.params.id);
  if (!id) return res.status(400).json({ message: "Invalid record." });
  const { errors, p } = checkProduct(req.body, false);
  if (errors.length) return res.status(400).json({ message: errors.join(" ") });
  try {
    const [result] = await pool.execute(
      "UPDATE products SET product_name = ?, category = ?, price = ?, quantity = ?, description = ? WHERE id = ?",
      [p.product_name, p.category, p.price, p.quantity, p.description, id]
    );
    if (result.affectedRows === 0)
      return res.status(404).json({ message: "Product not found." });
    res.json({ message: "Product updated successfully." });
  } catch (err) {
    res.status(500).json({ message: "Could not update the product." });
  }
});

app.delete("/api/products/:id", async (req, res) => {
  const id = validId(req.params.id);
  if (!id) return res.status(400).json({ message: "Invalid record." });
  try {
    const [result] = await pool.execute("DELETE FROM products WHERE id = ?", [id]);
    if (result.affectedRows === 0)
      return res.status(404).json({ message: "Product not found." });
    res.json({ message: "Product deleted successfully." });
  } catch (err) {
    res.status(500).json({ message: "Could not delete the product." });
  }
});

pool.query("SELECT 1")
  .then(() => {
    console.log("Connected to MySQL (techmart_db).");
    app.listen(3000, () => console.log("Server running at http://localhost:3000"));
  })
  .catch(() => {
    console.error("Cannot connect to MySQL. Start MySQL in XAMPP and import schema.sql first.");
    process.exit(1);
  });