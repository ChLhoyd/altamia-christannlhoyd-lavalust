<!DOCTYPE html>
<html>
<head>
    <title>Update User</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #111111;
            padding: 40px;
            margin: 0;
        }

        .form-container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(110, 95, 95, 0.2);
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #222;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }

        input {
            width: 100%;
            padding: 12px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 7px;
            font-size: 15px;
            outline: none;
            transition: 0.2s;
        }

        input:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
        }

        .button-container {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .update-btn,
        .back-btn {
            flex: 1;
            padding: 12px;
            border-radius: 7px;
            text-align: center;
            font-weight: bold;
            font-size: 15px;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }

        .update-btn {
            background: #16a34a;
            color: white;
        }

        .update-btn:hover {
            background: #15803d;
        }

        .back-btn {
            background: #e5e7eb;
            color: #333;
        }

        .back-btn:hover {
            background: #d1d5db;
        }
    </style>
</head>

<body>

    <div class="form-container">

        <h1>Update User</h1>

        <form method="POST" action="<?= site_url('users/edit/' . $user['id']); ?>">

            <div class="form-group">
                <label>First Name</label>
                <input
                    type="text"
                    name="firstname"
                    value="<?= $user['firstname']; ?>"
                    required>
            </div>

            <div class="form-group">
                <label>Last Name</label>
                <input
                    type="text"
                    name="lastname"
                    value="<?= $user['lastname']; ?>"
                    required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input
                    type="email"
                    name="email"
                    value="<?= $user['email']; ?>"
                    required>
            </div>

            <div class="form-group">
                <label>Username</label>
                <input
                    type="text"
                    name="username"
                    value="<?= $user['username']; ?>"
                    required>
            </div>

            <div class="button-container">

                <button type="submit" class="update-btn">
                    Update User
                </button>

                <a href="<?= site_url('/'); ?>" class="back-btn">
                    Back
                </a>

            </div>

        </form>

    </div>

</body>
</html>