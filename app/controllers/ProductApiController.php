<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductApiController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->call->database();
        $this->call->model('ProductModel');
        $this->call->library('api');
    }

    // LOGIN
    public function login()
    {
        $this->api->require_method('POST');

        $data = $this->api->body();

        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if ($username === '' || $password === '') {
            $this->api->respond([
                'message' => 'Username and password are required.'
            ], 400);

            return;
        }

        $user = $this->db
            ->table('users')
            ->where('username', $username)
            ->get();

        if (!$user || !password_verify($password, $user['password'])) {
            $this->api->respond([
                'message' => 'Invalid username or password.'
            ], 401);

            return;
        }

        $tokens = $this->api->issue_tokens([
            'id'       => $user['id'],
            'username' => $user['username'],
            'role'     => $user['role']
        ]);

        $this->api->respond($tokens);
    }


    // GET ALL PRODUCTS
    public function index()
    {
        $this->api->require_method('GET');

        $this->api->require_jwt();

        $products = $this->ProductModel->all();

        $this->api->respond([
            'products' => $products
        ]);
    }


    // GET ONE PRODUCT
    public function show($id)
    {
        $this->api->require_method('GET');

        $this->api->require_jwt();

        $product = $this->ProductModel->find($id);

        if (!$product) {
            $this->api->respond([
                'message' => 'Product not found.'
            ], 404);

            return;
        }

        $this->api->respond([
            'product' => $product
        ]);
    }


    // ADD PRODUCT
    public function store()
    {
        $this->api->require_method('POST');

        $this->api->require_jwt();

        $data = $this->api->body();

        $productName = $data['product_name'] ?? '';
        $description = $data['description'] ?? '';
        $price = $data['price'] ?? 0;
        $quantity = $data['quantity'] ?? 0;

        if ($productName === '') {
            $this->api->respond([
                'message' => 'Product name is required.'
            ], 400);

            return;
        }

        $productData = [
            'product_name' => $productName,
            'description'  => $description,
            'price'        => $price,
            'quantity'     => $quantity
        ];

        $id = $this->ProductModel->insert($productData);

        $product = $this->ProductModel->find($id);

        $this->api->respond([
            'message' => 'Product added successfully.',
            'product' => $product
        ], 201);
    }


    // UPDATE PRODUCT
    public function update($id)
    {
        $this->api->require_jwt();

        $product = $this->ProductModel->find($id);

        if (!$product) {
            $this->api->respond([
                'message' => 'Product not found.'
            ], 404);

            return;
        }

        $data = $this->api->body();

        $productData = [
            'product_name' => $data['product_name'] ?? $product['product_name'],
            'description'  => $data['description'] ?? $product['description'],
            'price'        => $data['price'] ?? $product['price'],
            'quantity'     => $data['quantity'] ?? $product['quantity']
        ];

        $this->ProductModel->update($id, $productData);

        $updatedProduct = $this->ProductModel->find($id);

        $this->api->respond([
            'message' => 'Product updated successfully.',
            'product' => $updatedProduct
        ]);
    }


    // DELETE PRODUCT
    public function delete($id)
    {
        $this->api->require_method('DELETE');

        $this->api->require_jwt();

        $product = $this->ProductModel->find($id);

        if (!$product) {
            $this->api->respond([
                'message' => 'Product not found.'
            ], 404);

            return;
        }

        $this->ProductModel->delete($id);

        $this->api->respond([
            'message' => 'Product deleted successfully.'
        ]);
    }


    // LOGOUT
    public function logout()
    {
        $this->api->require_method('POST');

        $this->api->require_jwt();

        $data = $this->api->body();

        $refreshToken = $data['refresh_token'] ?? '';

        if ($refreshToken !== '') {
            $this->api->revoke_refresh_token($refreshToken);
        }

        $this->api->respond([
            'message' => 'Logged out successfully.'
        ]);
    }


    // REFRESH TOKEN
    public function refresh()
    {
        $this->api->require_method('POST');

        $data = $this->api->body();

        $refreshToken = $data['refresh_token'] ?? '';

        if ($refreshToken === '') {
            $this->api->respond([
                'message' => 'Refresh token is required.'
            ], 400);

            return;
        }

        $this->api->refresh_access_token($refreshToken);
    }
}