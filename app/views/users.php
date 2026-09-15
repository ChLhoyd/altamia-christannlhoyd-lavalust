<!DOCTYPE html>
<html>
<head>
    <title>User Directory</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #111111;
            padding: 40px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(110, 95, 95, 0.1);
        }

        h1 {
            text-align: center;
            margin-bottom: 25px;
        }

        /* Create User Button */
        .create-btn {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 11px 18px;
            border-radius: 7px;
            text-decoration: none;
            font-weight: bold;
            margin-bottom: 20px;
            transition: 0.2s;
        }

        .create-btn:hover {
            background: #1d4ed8;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px;
            border-bottom: 1px solid #31473A;
            text-align: left;
        }

        th {
            background: #333;
            color: white;
        }

        tr:hover {
            background: #f1f1f1;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .update-btn,
        .delete-btn {
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
            transition: 0.2s;
        }

        .update-btn {
            background: #16a34a;
        }

        .update-btn:hover {
            background: #15803d;
        }

        .delete-btn {
            background: #dc2626;
        }

        .delete-btn:hover {
            background: #b91c1c;
        }
    </style>
</head>

<body>

    <div class="container">

        <h1>Users</h1>

        <!-- Create User -->
        <a href="<?= site_url('users/create'); ?>" class="create-btn">
            + Create User
        </a>

        <table>

            <tr>
                <th>ID</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Username</th>
                <th>Actions</th>
            </tr>

            <?php foreach ($users as $user): ?>

                <tr>

                    <td><?= $user['id']; ?></td>

                    <td><?= $user['firstname']; ?></td>

                    <td><?= $user['lastname']; ?></td>

                    <td><?= $user['email']; ?></td>

                    <td><?= $user['username']; ?></td>

                    <td>
                        <div class="action-buttons">

                            <!-- Update -->
                            <a
                                href="<?= site_url('users/edit/' . $user['id']); ?>"
                                class="update-btn">
                                Update
                            </a>

                            <!-- Delete -->
                            <a
                                href="<?= site_url('users/delete/' . $user['id']); ?>"
                                class="delete-btn"
                                onclick="return confirm('Are you sure you want to delete this user?');">
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