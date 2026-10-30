const form = document.getElementById("productForm");
const formTitle = document.getElementById("formTitle");
const saveBtn = document.getElementById("saveBtn");
const cancelBtn = document.getElementById("cancelBtn");
const messageBox = document.getElementById("message");
const tableBody = document.getElementById("productTable");
const recordId = document.getElementById("recordId");
const productId = document.getElementById("productId");
const productName = document.getElementById("productName");
const category = document.getElementById("category");
const price = document.getElementById("price");
const quantity = document.getElementById("quantity");
const description = document.getElementById("description");

let messageTimer = null;

function showMessage(text, type) {
  messageBox.textContent = text;
  messageBox.className = "msg " + type;
  window.scrollTo({ top: 0, behavior: "smooth" });
  clearTimeout(messageTimer);
  messageTimer = setTimeout(() => { messageBox.className = "msg hidden"; }, 4000);
}

async function api(url, options = {}) {
  const response = await fetch(url, {
    headers: { "Content-Type": "application/json" },
    ...options
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.message || "Request failed.");
  return data;
}

function validateForm() {
  if (recordId.value === "" && !/^[A-Za-z0-9-]{1,20}$/.test(productId.value.trim()))
    return "Product ID must be 1-20 letters, numbers or hyphens.";
  if (productName.value.trim().length < 2)
    return "Product name must be at least 2 characters.";
  if (category.value.trim().length < 2)
    return "Category must be at least 2 characters.";
  if (price.value === "" || Number(price.value) < 0)
    return "Price must be 0 or more.";
  if (quantity.value === "" || !Number.isInteger(Number(quantity.value)) || Number(quantity.value) < 0)
    return "Quantity must be a whole number, 0 or more.";
  return "";
}

function resetForm() {
  form.reset();
  recordId.value = "";
  productId.readOnly = false;
  formTitle.textContent = "Add New Product";
  saveBtn.textContent = "Add Product";
  cancelBtn.classList.add("hidden");
}

function editProduct(item) {
  recordId.value = item.id;
  productId.value = item.product_id;
  productId.readOnly = true;
  productName.value = item.product_name;
  category.value = item.category;
  price.value = item.price;
  quantity.value = item.quantity;
  description.value = item.description || "";
  formTitle.textContent = "Update Product";
  saveBtn.textContent = "Update Product";
  cancelBtn.classList.remove("hidden");
  window.scrollTo({ top: 0, behavior: "smooth" });
}

async function deleteProduct(item) {
  if (!confirm("Delete \"" + item.product_name + "\" permanently?")) return;
  try {
    const result = await api("/api/products/" + item.id, { method: "DELETE" });
    showMessage(result.message, "success");
    loadProducts();
  } catch (err) {
    showMessage(err.message, "error");
  }
}

function makeButton(text, className, handler) {
  const btn = document.createElement("button");
  btn.textContent = text;
  btn.className = className;
  btn.addEventListener("click", handler);
  return btn;
}

function updateStats(products) {
  const stock = products.reduce((sum, p) => sum + Number(p.quantity), 0);
  const value = products.reduce((sum, p) => sum + Number(p.price) * Number(p.quantity), 0);
  document.getElementById("statProducts").textContent = products.length;
  document.getElementById("statStock").textContent = stock;
  document.getElementById("statValue").textContent = "\u20B9" + value.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function renderProducts(products) {
  updateStats(products);
  tableBody.replaceChildren();
  if (products.length === 0) {
    const row = tableBody.insertRow();
    const cell = row.insertCell();
    cell.colSpan = 7;
    cell.className = "empty";
    cell.textContent = "No products found. Add your first product above.";
    return;
  }
  products.forEach(item => {
    const row = tableBody.insertRow();
    [
      item.product_id,
      item.product_name,
      item.category,
      "\u20B9" + Number(item.price).toFixed(2),
      item.quantity,
      item.description || ""
    ].forEach(value => { row.insertCell().textContent = value; });
    const actions = row.insertCell();
    actions.append(
      makeButton("Edit", "sm", () => editProduct(item)),
      makeButton("Delete", "sm del", () => deleteProduct(item))
    );
  });
}

async function loadProducts() {
  try {
    renderProducts(await api("/api/products"));
  } catch (err) {
    showMessage(err.message, "error");
  }
}

form.addEventListener("submit", async event => {
  event.preventDefault();
  const problem = validateForm();
  if (problem) return showMessage(problem, "error");

  const isUpdate = recordId.value !== "";
  const body = {
    product_id: productId.value,
    product_name: productName.value,
    category: category.value,
    price: price.value,
    quantity: quantity.value,
    description: description.value
  };

  try {
    const result = await api(
      isUpdate ? "/api/products/" + recordId.value : "/api/products",
      { method: isUpdate ? "PUT" : "POST", body: JSON.stringify(body) }
    );
    showMessage(result.message, "success");
    resetForm();
    loadProducts();
  } catch (err) {
    showMessage(err.message, "error");
  }
});

cancelBtn.addEventListener("click", resetForm);
loadProducts();