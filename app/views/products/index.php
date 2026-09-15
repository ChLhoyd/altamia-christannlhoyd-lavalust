<!DOCTYPE html>
<html>

<head>

    <title>Product Management</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #111111;
            padding: 40px;
            margin: 0;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        h1 {
            margin: 0;
        }

        .buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 7px;
            text-decoration: none;
            color: white;
            font-weight: bold;
        }

        .add {
            background: #2563eb;
        }

        .add:hover {
            background: #1d4ed8;
        }

        .logout {
            background: #6b7280;
        }

        .logout:hover {
            background: #4b5563;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #333333;
            color: white;
            padding: 14px;
            text-align: left;
        }

        td {
            padding: 13px;
            border-bottom: 1px solid #ddd;
        }

        tr:hover {
            background: #f3f4f6;
        }

        .edit {
            background: #16a34a;
        }

        .edit:hover {
            background: #15803d;
        }

        .delete {
            background: #dc2626;
        }

        .delete:hover {
            background: #b91c1c;
        }

        .action {
            display: flex;
            gap: 7px;
        }

        .action a {
            padding: 7px 12px;
            border-radius: 6px;
            text-decoration: none;
            color: white;
            font-size: 13px;
            font-weight: bold;
        }

    </style>

</head>

<body>

<div class="container">

    <div class="header">

        <h1>Product Management</h1>

        <div class="buttons">

            <a
                href="<?= site_url('products/create'); ?>"
                class="btn add"
            >
                + Add Product
            </a>

            <a
                href="<?= site_url('auth/logout'); ?>"
                class="btn logout"
            >
                Logout
            </a>

        </div>

    </div>

    <table>

        <tr>

            <th>ID</th>
            <th>Product Name</th>
            <th>Description</th>
            <th>Price</th>
            <th>Quantity</th>
            <th>Created At</th>
            <th>Actions</th>

        </tr>

        <?php foreach ($products as $product): ?>

        <tr>

            <td>
                <?= htmlspecialchars($product['id']); ?>
            </td>

            <td>
                <?= htmlspecialchars($product['product_name']); ?>
            </td>

            <td>
                <?= htmlspecialchars($product['description']); ?>
            </td>

            <td>
                ₱<?= number_format($product['price'], 2); ?>
            </td>

            <td>
                <?= htmlspecialchars($product['quantity']); ?>
            </td>

            <td>
                <?= htmlspecialchars($product['created_at']); ?>
            </td>

            <td>

                <div class="action">

                    <a
                        href="<?= site_url('products/edit/' . $product['id']); ?>"
                        class="edit"
                    >
                        Edit
                    </a>

                    <a
                        href="<?= site_url('products/delete/' . $product['id']); ?>"
                        class="delete"
                        onclick="return confirm('Are you sure you want to delete this product?');"
                    >
                        Delete
                    </a>

                </div>

            </td>

        </tr>

        <?php endforeach; ?>

    </table>

</div>

</body>

</html>