<!DOCTYPE html>
<html>

<head>

    <title>Add Product</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #111111;
            padding: 40px;
        }

        .container {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
        }

        h1 {
            text-align: center;
        }

        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input,
        textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 7px;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .buttons {
            margin-top: 25px;
            display: flex;
            gap: 10px;
        }

        button,
        a {
            padding: 11px 18px;
            border-radius: 7px;
            border: none;
            text-decoration: none;
            font-weight: bold;
        }

        button {
            background: #2563eb;
            color: white;
            cursor: pointer;
        }

        a {
            background: #6b7280;
            color: white;
        }

    </style>

</head>

<body>

<div class="container">

    <h1>Add Product</h1>

    <form
        action="<?= site_url('products/create'); ?>"
        method="POST"
    >

        <label>Product Name</label>

        <input
            type="text"
            name="product_name"
            required
        >

        <label>Description</label>

        <textarea
            name="description"
            required
        ></textarea>

        <label>Price</label>

        <input
            type="number"
            name="price"
            step="0.01"
            min="0"
            required
        >

        <label>Quantity</label>

        <input
            type="number"
            name="quantity"
            min="0"
            required
        >

        <div class="buttons">

            <button type="submit">
                Add Product
            </button>

            <a href="<?= site_url('products'); ?>">
                Back
            </a>

        </div>

    </form>

</div>

</body>

</html>