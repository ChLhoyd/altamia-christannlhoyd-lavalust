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
        </style>
    </head>

    <body>

        <div class="container">

            <h1>Users</h1>

            <table>
                <tr>
                <th>ID</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Username</th>
                </tr>

                <?php foreach ($users as $user): ?>
                    <tr>
                    <td><?= $user['id']; ?></td>
                    <td><?= $user['firstname']; ?></td>
                    <td><?= $user['lastname']; ?></td>
                    <td><?= $user['email']; ?></td>
                    <td><?= $user['username']; ?></td>
                    </tr>
                <?php endforeach; ?>

            </table>

        </div>

    </body>
 </html>