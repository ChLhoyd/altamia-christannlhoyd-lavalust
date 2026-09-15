<!DOCTYPE html>
<html>

<head>

    <title>Product Management</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f2f2;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: white;
            padding: 25px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        h1 {
            margin: 0;
            color: #333;
            font-size: 25px;
        }

        .buttons {
            display: flex;
            gap: 8px;
        }

        .btn {
            display: inline-block;
            padding: 9px 14px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }

        .add {
            background: #333;
            color: white;
        }

        .add:hover {
            background: #222;
        }

        .logout {
            background: #ddd;
            color: #333;
        }

        .logout:hover {
            background: #ccc;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #333;
            color: white;
            padding: 11px;
            text-align: left;
            font-size: 14px;
        }

        td {
            padding: 11px;
            border-bottom: 1px solid #ddd;
            color: #444;
            font-size: 14px;
        }

        tr:hover {
            background: #f7f7f7;
        }

        .action {
            display: flex;
            gap: 6px;
        }

        .action a {
            padding: 7px 11px;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            font-size: 13px;
        }

        .edit {
            background: #555;
        }

        .edit:hover {
            background: #333;
        }

        .delete {
            background: #c0392b;
        }

        .delete:hover {
            background: #a93226;
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