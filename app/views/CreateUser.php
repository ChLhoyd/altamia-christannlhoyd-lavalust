<!DOCTYPE html>
<html>
<head>
    <title>Create User</title>

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
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .button-container {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .create-btn,
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

        .create-btn {
            background: #2563eb;
            color: white;
        }

        .create-btn:hover {
            background: #1d4ed8;
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

        <h1>Create User</h1>

        <form method="POST" action="<?= site_url('users/create'); ?>">

            <div class="form-group">
                <label>First Name</label>
                <input
                    type="text"
                    name="firstname"
                    placeholder="Enter first name"
                    required>
            </div>

            <div class="form-group">
                <label>Last Name</label>
                <input
                    type="text"
                    name="lastname"
                    placeholder="Enter last name"
                    required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input
                    type="email"
                    name="email"
                    placeholder="Enter email address"
                    required>
            </div>

            <div class="form-group">
                <label>Username</label>
                <input
                    type="text"
                    name="username"
                    placeholder="Enter username"
                    required>
            </div>

            <div class="button-container">

                <button type="submit" class="create-btn">
                    Create User
                </button>

                <a href="<?= site_url('/'); ?>" class="back-btn">
                    Back
                </a>

            </div>

        </form>

    </div>

</body>
</html>