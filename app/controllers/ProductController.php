<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->call->database();
        $this->call->model('ProductModel');
        $this->call->library('auth');
    }

    private function check_auth()
    {
        if (!$this->auth->is_logged_in()) {
            redirect('auth/login');
            exit;
        }
    }

    public function index()
    {
        $this->check_auth();

        $products = $this->ProductModel->all();

        $this->call->view('products/index', [
            'products' => $products
        ]);
    }

    public function create()
    {
        $this->check_auth();

        if ($this->request->method() === 'post') {

            $data = [
                'product_name' => $this->request->post('product_name'),
                'description'  => $this->request->post('description'),
                'price'        => $this->request->post('price'),
                'quantity'      => $this->request->post('quantity')
            ];

            $this->ProductModel->insert($data);

            redirect('products');
            exit;
        }

        $this->call->view('products/create');
    }

    public function edit($id)
    {
        $this->check_auth();

        $product = $this->ProductModel->find($id);

        if (!$product) {
            echo "Product not found.";
            exit;
        }

        if ($this->request->method() === 'post') {

            $data = [
                'product_name' => $this->request->post('product_name'),
                'description'  => $this->request->post('description'),
                'price'        => $this->request->post('price'),
                'quantity'      => $this->request->post('quantity')
            ];

            $this->ProductModel->update($id, $data);

            redirect('products');
            exit;
        }

        $this->call->view('products/edit', [
            'product' => $product
        ]);
    }

    public function delete($id)
    {
        $this->check_auth();

        $this->ProductModel->delete($id);

        redirect('products');
        exit;
    }
}