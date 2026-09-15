<!DOCTYPE html>
<html>

<head>

    <title>Edit Product</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f2f2;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 550px;
            margin: 30px auto;
            background: white;
            padding: 25px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 6px;
            color: #333;
            font-weight: bold;
            font-size: 14px;
        }

        input,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-family: Arial, sans-serif;
            font-size: 14px;
            box-sizing: border-box;
        }

        textarea {
            height: 100px;
            resize: vertical;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #777;
        }

        .buttons {
            margin-top: 25px;
            display: flex;
            gap: 8px;
        }

        button,
        a {
            padding: 10px 16px;
            border-radius: 5px;
            border: none;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }

        button {
            background: #333;
            color: white;
            cursor: pointer;
        }

        button:hover {
            background: #222;
        }

        a {
            background: #ddd;
            color: #333;
        }

        a:hover {
            background: #ccc;
        }
    </style>

</head>

<body>

    <div class="container">

        <h1>Edit Product</h1>

        <form
            action="<?= site_url('products/edit/' . $product['id']); ?>"
            method="POST"
        >

            <label>Product Name</label>

            <input
                type="text"
                name="product_name"
                value="<?= htmlspecialchars($product['product_name']); ?>"
                required
            >

            <label>Description</label>

            <textarea
                name="description"
                required
            ><?= htmlspecialchars($product['description']); ?></textarea>

            <label>Price</label>

            <input
                type="number"
                name="price"
                step="0.01"
                min="0"
                value="<?= htmlspecialchars($product['price']); ?>"
                required
            >

            <label>Quantity</label>

            <input
                type="number"
                name="quantity"
                min="0"
                value="<?= htmlspecialchars($product['quantity']); ?>"
                required
            >

            <div class="buttons">

                <button type="submit">
                    Update Product
                </button>

                <a href="<?= site_url('products'); ?>">
                    Back
                </a>

            </div>

        </form>

    </div>

</body>

</html>